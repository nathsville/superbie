<?php

namespace Tests\Feature;

use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\DinasUnit;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\User;
use App\Services\DashboardCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * SUPERBIE — LC-2C — DASHBOARD / KPI / CACHE lifecycle alignment.
 *
 * Pins the dashboard layer to the normalized LC-2 lifecycle:
 *   - ACTIVE workflow  = submitted, in_progress
 *   - FINAL outcome    = resolved, rejected
 *   - LEGACY (history) = under_review, waiting_for_information, closed
 *
 * Guarded invariants:
 *   1. "active" KPIs/aggregations EXCLUDE legacy + final outcomes;
 *   2. the success stat is `resolved` (never `closed`);
 *   3. the rejection stat is `rejected` (never merged with legacy);
 *   4. legacy rows stay visible in TOTALS (honest history) but are never counted
 *      as active work and are labelled "historis" where they surface;
 *   5. cross-role scope (citizen = own, operator = own Dinas/Unit, super admin =
 *      global) stays intact under the new lifecycle;
 *   6. dashboard caches stay consistent + invalidate on complaint changes;
 *   7. empty-data dashboards render honestly (no fabricated figures, no errors);
 *   8. the ACTIVE list stays derived from the enum (single source of truth).
 */
class DashboardLc2cLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $superAdmin;
    private User $citizen;
    private User $operator;
    private DinasUnit $unit;
    private ComplaintCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = DinasUnit::create([
            'name' => 'Dinas LC-2C', 'code' => 'LC2C-1', 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->admin      = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->citizen    = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
        $this->operator   = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unit->id,
        ]);

        $this->category = ComplaintCategory::create([
            'name' => 'Infrastruktur LC-2C', 'slug' => 'infrastruktur-lc2c',
            'dinas_name' => 'Dinas PUPR', 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    private static int $seq = 0;

    private function makeComplaint(string $status, array $overrides = []): Complaint
    {
        static::$seq++;

        return Complaint::create(array_merge([
            'reference_code' => 'LPW-LC2C-' . str_pad((string) static::$seq, 4, '0', STR_PAD_LEFT),
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $this->category->id,
            'title'          => 'Laporan LC-2C ' . static::$seq,
            'description'    => 'Deskripsi laporan uji LC-2C.',
            'location_text'  => 'Kec. Uji, Kota Parepare',
            'status'         => $status,
            'submitted_at'   => now(),
        ], $overrides));
    }

    // =========================================================================
    // 1. Enum is the single source of truth for the active list
    // =========================================================================

    public function test_active_status_list_is_derived_from_the_enum(): void
    {
        $expected = array_map(
            fn (ComplaintStatus $s) => $s->value,
            ComplaintStatus::activeCases(),
        );

        $this->assertSame(['submitted', 'in_progress'], $expected);

        // The service constant must never drift from the enum.
        $ref    = new \ReflectionClass(DashboardCacheService::class);
        $active = $ref->getConstant('ACTIVE_STATUS_VALUES');

        $this->assertSame($expected, array_values($active));

        // Legacy helpers classify exactly the three history statuses.
        $this->assertSame(
            ['under_review', 'waiting_for_information', 'closed'],
            array_map(fn (ComplaintStatus $s) => $s->value, ComplaintStatus::legacyCases()),
        );
    }

    // =========================================================================
    // 2. Admin aggregation — active excludes legacy + final outcomes
    // =========================================================================

    public function test_admin_kpis_count_only_active_work_as_active(): void
    {
        // 2 active + 1 legacy + 1 resolved + 1 rejected = 5 total.
        $this->makeComplaint('submitted');
        $this->makeComplaint('in_progress');
        $this->makeComplaint('under_review');           // legacy
        $this->makeComplaint('resolved', ['resolved_at' => now()]);
        $this->makeComplaint('rejected');

        $response = $this->actingAs($this->admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertViewHas('totalLaporan', 5);
        $response->assertViewHas('laporanAktif', 2);   // submitted + in_progress only
        $response->assertViewHas('laporanSelesai', 1); // resolved (NOT closed)
    }

    public function test_admin_status_breakdown_flags_legacy_and_uses_resolved_for_success(): void
    {
        $this->makeComplaint('submitted');
        $this->makeComplaint('under_review');   // legacy
        $this->makeComplaint('closed');         // legacy
        $this->makeComplaint('resolved', ['resolved_at' => now()]);
        $this->makeComplaint('rejected');

        $response = $this->actingAs($this->admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertViewHas('statusBreakdown', function ($breakdown) {
            if (! is_array($breakdown) || count($breakdown) !== 7) {
                return false;
            }

            // Every status is present (honest history) and legacy is flagged.
            return $breakdown['submitted']['count'] === 1
                && ($breakdown['submitted']['legacy'] ?? true) === false
                && ($breakdown['in_progress']['legacy'] ?? true) === false
                && $breakdown['under_review']['count'] === 1
                && ($breakdown['under_review']['legacy'] ?? false) === true
                && ($breakdown['waiting_for_information']['legacy'] ?? false) === true
                && ($breakdown['closed']['legacy'] ?? false) === true
                && ($breakdown['closed']['count'] ?? -1) === 1
                && ($breakdown['resolved']['legacy'] ?? true) === false
                && ($breakdown['resolved']['count'] ?? -1) === 1
                && ($breakdown['rejected']['count'] ?? -1) === 1;
        });
    }

    public function test_admin_status_flow_excludes_legacy_stages(): void
    {
        $this->makeComplaint('submitted');
        $this->makeComplaint('in_progress');
        $this->makeComplaint('under_review');                    // legacy
        $this->makeComplaint('waiting_for_information');         // legacy
        $this->makeComplaint('resolved', ['resolved_at' => now()]);

        $response = $this->actingAs($this->admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertViewHas('statusFlow', function ($flow) {
            $values = array_column($flow['stages'], 'value');

            return $flow['active_total'] === 2          // submitted + in_progress
                && $flow['flow_total'] === 3            // active + resolved
                && ! in_array('under_review', $values, true)
                && ! in_array('waiting_for_information', $values, true)
                && ! in_array('closed', $values, true);
        });
    }

    public function test_admin_category_backlog_keeps_rejected_and_legacy_distinct(): void
    {
        $cat = ComplaintCategory::factory()->create(['name' => 'Kategori LC-2C']);

        $this->makeComplaint('in_progress', ['category_id' => $cat->id]);          // active
        $this->makeComplaint('resolved', ['category_id' => $cat->id, 'resolved_at' => now()]);
        $this->makeComplaint('rejected', ['category_id' => $cat->id]);
        $this->makeComplaint('closed', ['category_id' => $cat->id]);               // legacy

        $response = $this->actingAs($this->admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertViewHas('kategoriBacklog', function ($rows) use ($cat) {
            $row = collect($rows)->firstWhere('name', $cat->name);

            return $row !== null
                && $row['total'] === 4
                && $row['active'] === 1
                && $row['resolved'] === 1
                && $row['rejected'] === 1   // NOT merged with legacy
                && $row['legacy'] === 1     // closed only
                && $row['active'] + $row['resolved'] + $row['rejected'] + $row['legacy'] === $row['total'];
        });
    }

    public function test_admin_dinas_and_aging_breakdowns_count_only_active_work(): void
    {
        $this->makeComplaint('submitted', ['dinas_unit_id' => $this->unit->id, 'submitted_at' => now()->subDays(10)]);
        $this->makeComplaint('under_review', ['dinas_unit_id' => $this->unit->id, 'submitted_at' => now()->subDays(10)]);

        $response = $this->actingAs($this->admin)->get('/admin/dashboard');

        $response->assertOk();

        // The Dinas chart carries only active statuses and only active load.
        $response->assertViewHas('dinasStatusBreakdown', function ($rows) {
            if (count($rows) !== 1) {
                return false;
            }

            $statuses = array_keys($rows[0]['statuses']);

            return $rows[0]['total'] === 1
                && ! in_array('under_review', $statuses, true)
                && ! in_array('waiting_for_information', $statuses, true);
        });

        // Aging buckets (active only) exclude the legacy long-age row.
        $response->assertViewHas('aging', function ($aging) {
            $byLabel = collect($aging['buckets'])->pluck('count', 'label');

            return $aging['total'] === 1 && $byLabel['>7 hari'] === 1;
        });
    }

    // =========================================================================
    // 3. Cross-role scope under the new lifecycle
    // =========================================================================

    public function test_citizen_kpis_split_active_resolved_and_rejected(): void
    {
        // Own reports: 2 active + 1 resolved + 1 rejected + 1 legacy = 5.
        $this->makeComplaint('submitted');
        $this->makeComplaint('in_progress');
        $this->makeComplaint('resolved', ['resolved_at' => now()]);
        $this->makeComplaint('rejected');
        $this->makeComplaint('closed');   // legacy: counts toward total only

        // Another reporter's active report must never leak into this citizen.
        $other = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
        $this->makeComplaint('in_progress', ['reporter_id' => $other->id]);

        $response = $this->actingAs($this->citizen)->get('/laporan/dashboard');

        $response->assertOk();
        $response->assertViewHas('totalLaporan', 5);
        $response->assertViewHas('laporanProses', 2);   // active only, scoped
        $response->assertViewHas('laporanSelesai', 1);  // resolved
        $response->assertViewHas('laporanDitolak', 1);  // rejected
    }

    public function test_operator_kpis_are_scoped_and_active_only(): void
    {
        $otherUnit = DinasUnit::create([
            'name' => 'Dinas LC-2C Lain', 'code' => 'LC2C-2', 'is_active' => true, 'sort_order' => 2,
        ]);

        // Own unit: 2 active + 1 legacy + 1 resolved today.
        $this->makeComplaint('submitted', ['dinas_unit_id' => $this->unit->id]);
        $this->makeComplaint('in_progress', ['dinas_unit_id' => $this->unit->id]);
        $this->makeComplaint('under_review', ['dinas_unit_id' => $this->unit->id]);
        $this->makeComplaint('resolved', ['dinas_unit_id' => $this->unit->id, 'resolved_at' => now()]);

        // Another unit's active report must never leak in.
        $this->makeComplaint('in_progress', ['dinas_unit_id' => $otherUnit->id]);

        $response = $this->actingAs($this->operator)->get('/operator/dashboard');

        $response->assertOk();
        $response->assertViewHas('totalLaporan', 4);          // scoped to own unit
        $response->assertViewHas('perluTindakan', 1);         // submitted only
        $response->assertViewHas('selesaiHariIni', 1);        // resolved today
        $response->assertViewHas('aging', fn ($aging) => $aging['total'] === 2); // active only
        $response->assertDontSee('Dinas LC-2C Lain');
    }

    public function test_super_admin_kpis_use_active_and_resolved_across_the_system(): void
    {
        $this->makeComplaint('submitted');
        $this->makeComplaint('in_progress');
        $this->makeComplaint('waiting_for_information');   // legacy
        $this->makeComplaint('resolved', ['resolved_at' => now()]);
        $this->makeComplaint('closed');                    // legacy

        $response = $this->actingAs($this->superAdmin)->get('/super-admin/dashboard');

        $response->assertOk();
        $response->assertViewHas('totalLaporan', 5);
        $response->assertViewHas('laporanAktif', 2);   // active only (legacy excluded)
        $response->assertViewHas('laporanSelesai', 1); // resolved (NOT closed)
    }

    // =========================================================================
    // 4. Cache consistency + invalidation
    // =========================================================================

    public function test_admin_dashboard_cache_is_consistent_across_requests(): void
    {
        $this->makeComplaint('submitted');
        $this->makeComplaint('under_review');

        Cache::forget('dashboard:admin');

        $first = $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk();
        $this->assertTrue(Cache::has('dashboard:admin'));

        $cached = Cache::get('dashboard:admin');

        // A second request reuses the cached payload unchanged (60s TTL).
        $second = $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk();

        $this->assertSame(60, DashboardCacheService::CACHE_TTL_SECONDS);
        $this->assertSame($cached['laporanAktif'], 1);
        $this->assertSame($first->viewData('laporanAktif'), $second->viewData('laporanAktif'));
        $this->assertSame($first->viewData('totalLaporan'), $second->viewData('totalLaporan'));
    }

    public function test_admin_dashboard_cache_invalidates_when_a_complaint_changes_status(): void
    {
        $complaint = $this->makeComplaint('submitted');

        Cache::forget('dashboard:admin');
        $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk();
        $this->assertTrue(Cache::has('dashboard:admin'));

        // A status change fires the observer → the admin cache is dropped.
        $complaint->update(['status' => 'in_progress']);
        $this->assertFalse(Cache::has('dashboard:admin'));

        // Recomputing reflects the new lifecycle position.
        $this->actingAs($this->admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertViewHas('laporanAktif', 1)
            ->assertViewHas('laporanSelesai', 0);
    }

    public function test_operator_cache_version_bump_invalidates_all_operator_caches(): void
    {
        $service = app(DashboardCacheService::class);

        $this->makeComplaint('submitted', ['dinas_unit_id' => $this->unit->id]);

        // Capture the key AFTER seeding (complaint creation bumps the version).
        $key = $service->operatorCacheKey($this->operator);

        $this->actingAs($this->operator)->get('/operator/dashboard')->assertOk();
        $this->assertTrue(Cache::has($key));

        // Bumping the version changes the key → the old entry is orphaned.
        $service->bumpOperatorCacheVersion();
        $this->assertNotSame($key, $service->operatorCacheKey($this->operator));
    }

    // =========================================================================
    // 5. Empty-data safety
    // =========================================================================

    public function test_all_role_dashboards_render_honest_empty_state_with_no_data(): void
    {
        foreach ([
            [$this->admin, '/admin/dashboard'],
            [$this->superAdmin, '/super-admin/dashboard'],
            [$this->operator, '/operator/dashboard'],
            [$this->citizen, '/laporan/dashboard'],
        ] as [$user, $url]) {
            $this->actingAs($user)->get($url)
                ->assertOk()
                ->assertViewHas('totalLaporan', 0);
        }

        // Admin empty funnel + empty Layer 2B payloads must be honest, not NaN.
        $this->actingAs($this->admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertViewHas('laporanAktif', 0)
            ->assertViewHas('laporanSelesai', 0)
            ->assertViewHas('statusFlow', fn ($flow) => $flow['active_total'] === 0 && $flow['flow_total'] === 0 && $flow['bottleneck'] === null)
            ->assertViewHas('kategoriBacklog', fn ($rows) => $rows === [])
            ->assertViewHas('dinasStatusBreakdown', fn ($rows) => $rows === [])
            ->assertViewHas('geographicLayer2B', fn ($b) => $b['activeLoadByKelurahan'] === []
                && $b['activeRateByKelurahan'] === []);
    }

    // =========================================================================
    // 6. Presentation — legacy is labelled "historis", never an active stage
    // =========================================================================

    public function test_admin_dashboard_wilayah_status_matrix_is_removed(): void
    {
        $kecamatan = Kecamatan::create(['name' => 'Kecamatan LC-2C', 'code' => '93001']);
        $kelurahan = Kelurahan::create(['kecamatan_id' => $kecamatan->id, 'name' => 'Kelurahan LC-2C']);

        $this->makeComplaint('submitted', ['kecamatan_id' => $kecamatan->id, 'kelurahan_id' => $kelurahan->id]);
        $this->makeComplaint('under_review', ['kecamatan_id' => $kecamatan->id, 'kelurahan_id' => $kelurahan->id]);

        $html = $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk()->getContent();

        // The "Wilayah × Status" history matrix (which carried the "historis"
        // legacy labels) was removed in the dashboard streamlining pass — the
        // `statusByKelurahan` payload is still computed but no longer surfaced.
        $this->assertStringNotContainsString('Wilayah × Status', $html);
        $this->assertStringNotContainsString('historis', $html);
    }

    public function test_admin_dashboard_active_charts_show_no_legacy_stage_when_no_history_exists(): void
    {
        // Only active complaints and NO geographic history → the active charts
        // must not surface any legacy stage label at all.
        $this->makeComplaint('submitted');
        $this->makeComplaint('in_progress');

        $html = $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('Bottleneck Status Laporan', $html);
        $this->assertStringContainsString('Sedang Diproses', $html);
        $this->assertStringNotContainsString('Sedang Ditinjau', $html);
        $this->assertStringNotContainsString('Menunggu Informasi', $html);
        $this->assertStringNotContainsString('Ditutup', $html);
    }
}
