<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\DinasUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 27 — Command Center dashboard analytics (regression guard).
 *
 * These tests pin the NEW, data-derived analytics added by the dashboard
 * redesign so they cannot silently regress into fabricated/leaky behaviour:
 *
 *   1. Aging buckets are computed from real `submitted_at` values.
 *   2. Category / Dinas distribution reflect real DB counts only.
 *   3. The monthly trend is a factual aggregation (inflow/resolution/net gap).
 *   4. Operator analytics stay SCOPED to the operator's Dinas/Unit — no
 *      cross-unit leakage through the new sections.
 *   5. No service-level ("SLA") or fabricated strings leak into the views.
 */
class CommandCenterAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function makeComplaint(User $reporter, array $overrides = []): Complaint
    {
        return Complaint::create(array_merge([
            'reference_code' => 'LPW-CC-' . strtoupper(uniqid()),
            'tracking_secret_hash' => hash('sha256', 'secret'),
            'category_id'    => ComplaintCategory::factory()->create()->id,
            'reporter_id'    => $reporter->id,
            'title'          => 'Laporan uji command center',
            'description'    => 'Deskripsi laporan pengujian command center analytics.',
            'location_text'  => 'Jl. Uji',
            'status'         => 'submitted',
            'submitted_at'   => now(),
        ], $overrides));
    }

    public function test_super_admin_dashboard_exposes_derived_analytics(): void
    {
        $sa       = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->makeComplaint($reporter, ['submitted_at' => now()->subDays(10)]); // >7 hari
        $this->makeComplaint($reporter, ['submitted_at' => now()]);              // 0-1 hari

        $response = $this->actingAs($sa)->get('/super-admin/dashboard');

        $response->assertOk();
        $response->assertViewHas('aging', function ($aging) {
            return isset($aging['buckets']) && $aging['total'] === 2;
        });
        $response->assertViewHas('kategoriTeratas', fn ($k) => is_array($k));
        $response->assertViewHas('dinasDistribution', fn ($d) => is_array($d));
        $response->assertViewHas('trenBulanan', fn ($t) => is_array($t) && count($t) === 6);

        // No fabricated strings / service-level claims anywhere.
        foreach (['SLA', 'Terlambat', 'Drs. M. Sanusi', 'Lowokwaru', 'Klojen', 'Kedungkandang', 'SHA-256'] as $bad) {
            $response->assertDontSee($bad, false);
        }
    }

    public function test_aging_buckets_are_factual(): void
    {
        $sa       = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->makeComplaint($reporter, ['submitted_at' => now()]);          // 0-1
        $this->makeComplaint($reporter, ['submitted_at' => now()->subDays(3)]); // 2-3
        $this->makeComplaint($reporter, ['submitted_at' => now()->subDays(5)]); // 4-7
        $this->makeComplaint($reporter, ['submitted_at' => now()->subDays(30)]); // >7

        $response = $this->actingAs($sa)->get('/super-admin/dashboard');

        $response->assertViewHas('aging', function ($aging) {
            $byLabel = collect($aging['buckets'])->pluck('count', 'label');
            return $byLabel['0–1 hari'] === 1
                && $byLabel['2–3 hari'] === 1
                && $byLabel['4–7 hari'] === 1
                && $byLabel['>7 hari'] === 1
                && $aging['total'] === 4;
        });
    }

    public function test_operator_analytics_are_scoped_to_own_unit(): void
    {
        $unitA = DinasUnit::create(['name' => 'Dinas CC A', 'code' => 'CC-A', 'is_active' => true, 'sort_order' => 1]);
        $unitB = DinasUnit::create(['name' => 'Dinas CC B', 'code' => 'CC-B', 'is_active' => true, 'sort_order' => 2]);

        $operator = User::factory()->create(['role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $unitA->id]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->makeComplaint($reporter, ['dinas_unit_id' => $unitA->id, 'submitted_at' => now()->subDays(9)]);
        $this->makeComplaint($reporter, ['dinas_unit_id' => $unitB->id, 'submitted_at' => now()->subDays(9)]);

        $response = $this->actingAs($operator)->get('/operator/dashboard');

        $response->assertOk();
        $response->assertViewHas('totalLaporan', 1);
        $response->assertViewHas('aging', fn ($aging) => $aging['total'] === 1);
        // The other unit's Dinas must never appear in the scoped distribution.
        $response->assertDontSee('Dinas CC B');
    }

    public function test_operator_without_unit_sees_empty_analytics(): void
    {
        $operator = User::factory()->create(['role' => 'operator', 'is_active' => true, 'dinas_unit_id' => null]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->makeComplaint($reporter, ['submitted_at' => now()]);

        $response = $this->actingAs($operator)->get('/operator/dashboard');

        $response->assertOk();
        $response->assertViewHas('totalLaporan', 0);
        $response->assertViewHas('aging', fn ($aging) => $aging['total'] === 0);
    }

    public function test_admin_dashboard_shows_honest_empty_states_with_no_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        // Honest empty states for the RETAINED cards (the streamlined dashboard
        // no longer renders the Dinas/Unit, Kategori, or Wilayah matrices).
        $response->assertSee('Belum ada data historis laporan.');
        $response->assertSee('Belum ada data status laporan.');
        $response->assertSee('Belum ada data kategori.');
        $response->assertDontSee('SLA', false);
        $response->assertDontSee('Terlambat', false);
    }

    // =========================================================================
    // Decision-oriented trend chart (Masuk vs Selesai vs Backlog)
    // =========================================================================

    public function test_monthly_trend_exposes_factual_cumulative_backlog(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        // Two complaints this month, one resolved this month → backlog grows by 1.
        $this->makeComplaint($reporter, ['submitted_at' => now()]);
        $this->makeComplaint($reporter, ['submitted_at' => now()]);
        $this->makeComplaint($reporter, [
            'submitted_at' => now(),
            'status'       => 'resolved',
            'resolved_at'  => now(),
        ]);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertViewHas('trenBulanan', function ($t) {
            if (! is_array($t) || count($t) !== 6) {
                return false;
            }
            // Every point carries a factual cumulative backlog (running balance).
            foreach ($t as $point) {
                if (! array_key_exists('backlog', $point)) {
                    return false;
                }
            }
            $last = end($t);

            // This month: 3 masuk, 1 selesai → running backlog ends at +2.
            return $last['inflow'] === 3 && $last['resolved'] === 1 && $last['backlog'] === 2;
        });
    }

    public function test_admin_trend_chart_is_decision_oriented(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->makeComplaint($reporter, ['submitted_at' => now()]);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();

        // New title + sub-description (Prompt 3/4 — backlog-first framing).
        $response->assertSee('Tren Laporan & Backlog');
        $response->assertSee('Perbandingan laporan masuk, selesai, dan backlog per bulan.');

        // Clear legend for all three lines — "Backlog", never "Belum Selesai".
        $response->assertSee('Laporan Masuk');
        $response->assertSee('Laporan Selesai');
        $response->assertSee('Backlog');
        $response->assertDontSee('Belum Selesai');

        // Still no SLA / fabricated claims.
        $response->assertDontSee('SLA', false);
        $response->assertDontSee('Terlambat', false);
    }

    public function test_trend_chart_renders_hover_tooltip_bands(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->makeComplaint($reporter, ['submitted_at' => now()]);

        $html = $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->getContent();

        // Hoverable per-month bands + tooltip markup (real Masuk/Selesai/Backlog).
        $this->assertStringContainsString('tl-band', $html);
        $this->assertStringContainsString('tl-tip', $html);
        $this->assertStringContainsString('Backlog', $html);
    }

    // =========================================================================
    // Beban Dinas/Unit × Status (stacked horizontal bar chart)
    // =========================================================================

    public function test_admin_dinas_status_breakdown_is_active_and_sorted_by_load(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $unitA = DinasUnit::create(['name' => 'Dinas Uji A', 'code' => 'UJI-A', 'is_active' => true, 'sort_order' => 1]);
        $unitB = DinasUnit::create(['name' => 'Dinas Uji B', 'code' => 'UJI-B', 'is_active' => true, 'sort_order' => 2]);

        // Unit A: 3 active (1 submitted, 2 in_progress).
        $this->makeComplaint($reporter, ['dinas_unit_id' => $unitA->id, 'status' => 'submitted']);
        $this->makeComplaint($reporter, ['dinas_unit_id' => $unitA->id, 'status' => 'in_progress']);
        $this->makeComplaint($reporter, ['dinas_unit_id' => $unitA->id, 'status' => 'in_progress']);

        // Unit B: 1 legacy (waiting_for_information, NOT active) + 1 resolved
        // (must be excluded from the active breakdown).
        $this->makeComplaint($reporter, ['dinas_unit_id' => $unitB->id, 'status' => 'waiting_for_information']);
        $this->makeComplaint($reporter, [
            'dinas_unit_id' => $unitB->id,
            'status'        => 'resolved',
            'resolved_at'   => now(),
        ]);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertViewHas('dinasStatusBreakdown', function ($rows) use ($unitA) {
            // LC-2C: only ACTIVE work (submitted + in_progress) is charted, so the
            // legacy-only Unit B is not a load row at all.
            if (! is_array($rows) || count($rows) !== 1) {
                return false;
            }

            $first = $rows[0];

            return $first['name'] === $unitA->name
                && $first['total'] === 3
                && ($first['statuses']['submitted'] ?? 0) === 1
                && ($first['statuses']['in_progress'] ?? 0) === 2;
        });
    }

    public function test_admin_dinas_chart_card_is_removed_from_dashboard(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $unit = DinasUnit::create(['name' => 'Dinas Uji Stack', 'code' => 'UJI-STK', 'is_active' => true, 'sort_order' => 1]);
        $this->makeComplaint($reporter, ['dinas_unit_id' => $unit->id, 'status' => 'in_progress']);

        $html = $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->getContent();

        // The "Beban Dinas/Unit Berdasarkan Status" card was removed in the
        // dashboard streamlining pass — its `dinasStatusBreakdown` payload is
        // still computed (asserted above) but no longer surfaced. (The Dinas/Unit
        // name may still legitimately appear in the Rating Analytics filter, so
        // only the chart-specific markup is asserted absent.)
        $this->assertStringNotContainsString('Beban Dinas/Unit Berdasarkan Status', $html);
        $this->assertStringNotContainsString('dsb-seg', $html);

        // Still no SLA / fabricated claims.
        $this->assertStringNotContainsString('SLA', $html);
        $this->assertStringNotContainsString('Terlambat', $html);
    }

    // =========================================================================
    // Distribusi Umur Laporan Aktif (aging buckets) — REMOVED from Admin UI
    // =========================================================================

    public function test_admin_aging_distribution_card_is_removed_from_dashboard(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->makeComplaint($reporter, ['submitted_at' => now()]);              // 0–1 hari
        $this->makeComplaint($reporter, ['submitted_at' => now()->subDays(30)]); // > 7 hari

        $html = $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->getContent();

        // The "Distribusi Umur Laporan Aktif" card was removed in the dashboard
        // streamlining pass — the underlying `aging` payload is still computed
        // (asserted elsewhere) but is no longer surfaced on the Admin dashboard.
        $this->assertStringNotContainsString('Distribusi Umur Laporan Aktif', $html);
        $this->assertStringNotContainsString('aging-attention', $html);

        // Still no SLA / fabricated claims.
        $this->assertStringNotContainsString('SLA', $html);
        $this->assertStringNotContainsString('Terlambat', $html);
    }

    // =========================================================================
    // Kategori dengan Backlog Tertinggi (dual-bar backlog) — REMOVED from Admin UI
    // =========================================================================

    public function test_admin_category_backlog_compares_total_vs_active_and_sorts(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $catA = ComplaintCategory::factory()->create(['name' => 'Kategori Uji A']);
        $catB = ComplaintCategory::factory()->create(['name' => 'Kategori Uji B']);

        // Category A: 1 active, 1 resolved, 1 legacy (closed) → total 3, active 1.
        $this->makeComplaint($reporter, ['category_id' => $catA->id, 'status' => 'in_progress']);
        $this->makeComplaint($reporter, ['category_id' => $catA->id, 'status' => 'resolved', 'resolved_at' => now()]);
        $this->makeComplaint($reporter, ['category_id' => $catA->id, 'status' => 'closed']);

        // Category B: 2 active → total 2, active 2 (should rank first).
        $this->makeComplaint($reporter, ['category_id' => $catB->id, 'status' => 'submitted']);
        $this->makeComplaint($reporter, ['category_id' => $catB->id, 'status' => 'in_progress']);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertViewHas('kategoriBacklog', function ($rows) use ($catA, $catB) {
            if (! is_array($rows) || count($rows) !== 2) {
                return false;
            }

            // Sorted by active backlog descending → B (2) before A (1).
            $first  = $rows[0];
            $second = $rows[1];

            return $first['name'] === $catB->name
                && $first['active'] === 2
                && $first['total'] === 2
                && $first['resolved'] === 0
                && $second['name'] === $catA->name
                && $second['active'] === 1
                && $second['total'] === 3
                && $second['resolved'] === 1
                // LC-2C: the closed row is a LEGACY status, kept DISTINCT from
                // rejections (rejected = 0 here).
                && $second['rejected'] === 0
                && $second['legacy'] === 1;
        });
    }

    public function test_admin_category_backlog_card_is_removed_from_dashboard(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $cat = ComplaintCategory::factory()->create(['name' => 'Kategori Uji Dual']);
        $this->makeComplaint($reporter, ['category_id' => $cat->id, 'status' => 'in_progress']);
        $this->makeComplaint($reporter, ['category_id' => $cat->id, 'status' => 'resolved', 'resolved_at' => now()]);

        $html = $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->getContent();

        // The "Kategori dengan Backlog Tertinggi" card was removed in the
        // dashboard streamlining pass — its `kategoriBacklog` payload is still
        // computed (asserted above) but no longer surfaced.
        $this->assertStringNotContainsString('Kategori dengan Backlog Tertinggi', $html);
        $this->assertStringNotContainsString('kbl-row', $html);
        $this->assertStringNotContainsString('Total masuk:', $html);
        $this->assertStringNotContainsString('Masih aktif:', $html);

        // Still no SLA / fabricated claims.
        $this->assertStringNotContainsString('SLA', $html);
        $this->assertStringNotContainsString('Terlambat', $html);
    }

    // =========================================================================
    // Kategori × Status heatmap (bottleneck) — REMOVED from Admin UI
    // =========================================================================

    public function test_admin_category_status_matrix_counts_per_status(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $catA = ComplaintCategory::factory()->create(['name' => 'Kategori Matrix A']);
        $catB = ComplaintCategory::factory()->create(['name' => 'Kategori Matrix B']);

        // A: 1 submitted + 2 in_progress (active 3) + 1 resolved.
        $this->makeComplaint($reporter, ['category_id' => $catA->id, 'status' => 'submitted']);
        $this->makeComplaint($reporter, ['category_id' => $catA->id, 'status' => 'in_progress']);
        $this->makeComplaint($reporter, ['category_id' => $catA->id, 'status' => 'in_progress']);
        $this->makeComplaint($reporter, ['category_id' => $catA->id, 'status' => 'resolved', 'resolved_at' => now()]);

        // B: 1 submitted (active 1).
        $this->makeComplaint($reporter, ['category_id' => $catB->id, 'status' => 'submitted']);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertViewHas('kategoriMatrix', function ($rows) use ($catA, $catB) {
            if (! is_array($rows) || count($rows) !== 2) {
                return false;
            }

            // Sorted by active backlog descending → A (3) before B (1).
            $first  = $rows[0];
            $second = $rows[1];

            return $first['name'] === $catA->name
                && $first['active'] === 3
                && $first['total'] === 4
                && ($first['counts']['submitted'] ?? 0) === 1
                && ($first['counts']['in_progress'] ?? 0) === 2
                && ($first['counts']['resolved'] ?? 0) === 1
                && $second['name'] === $catB->name
                && $second['active'] === 1
                && ($second['counts']['submitted'] ?? 0) === 1;
        });
    }

    public function test_admin_heatmap_card_is_removed_from_dashboard(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $cat = ComplaintCategory::factory()->create(['name' => 'Infrastruktur Uji']);
        $this->makeComplaint($reporter, ['category_id' => $cat->id, 'status' => 'in_progress']);
        $this->makeComplaint($reporter, ['category_id' => $cat->id, 'status' => 'in_progress']);

        $html = $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->getContent();

        // The "Kategori × Status" heatmap card was removed in the dashboard
        // streamlining pass — its `kategoriMatrix` payload is still computed
        // (asserted above) but no longer surfaced.
        $this->assertStringNotContainsString('Kategori × Status', $html);
        $this->assertStringNotContainsString('hm-cell', $html);
        $this->assertStringNotContainsString('Tinggi (penumpukan)', $html);

        // Still no SLA / fabricated claims.
        $this->assertStringNotContainsString('SLA', $html);
        $this->assertStringNotContainsString('Terlambat', $html);
    }

    // =========================================================================
    // Perlu Segera Ditindaklanjuti — Attention Center (REMOVED from Admin UI)
    // =========================================================================

    public function test_admin_attention_summary_splits_unassigned_age_buckets(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        // No Dinas/Unit: 1 fresh (0–1 hari), 1 mid (2–3 hari), 1 late (>3 hari).
        $this->makeComplaint($reporter, ['dinas_unit_id' => null, 'submitted_at' => now()]);
        $this->makeComplaint($reporter, ['dinas_unit_id' => null, 'submitted_at' => now()->subDays(3)]);
        $this->makeComplaint($reporter, ['dinas_unit_id' => null, 'submitted_at' => now()->subDays(6)]);

        // Routed complaint (has a Dinas/Unit) must NOT be counted.
        $unit = DinasUnit::create(['name' => 'Dinas Bucket Uji', 'code' => 'BCK-1', 'is_active' => true, 'sort_order' => 1]);
        $this->makeComplaint($reporter, ['dinas_unit_id' => $unit->id, 'submitted_at' => now()->subDays(6)]);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertViewHas('attention', function ($attention) {
            if (! is_array($attention) || ($attention['belumDinasUnit'] ?? 0) !== 3) {
                return false;
            }

            $buckets = $attention['belumDinasUnitBuckets'] ?? [];
            $byLabel = collect($buckets)->pluck('count', 'label');

            return $attention['belumDinasUnitTotal'] === 3
                && ($byLabel['0–1 hari'] ?? -1) === 1
                && ($byLabel['2–3 hari'] ?? -1) === 1
                && ($byLabel['>3 hari'] ?? -1) === 1;
        });
    }

    public function test_admin_attention_center_is_removed_from_dashboard(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        // No Dinas/Unit + older than 7 days → both signals would previously fire.
        $this->makeComplaint($reporter, [
            'dinas_unit_id' => null,
            'submitted_at'  => now()->subDays(9),
        ]);

        $html = $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->getContent();

        // The consolidated Attention Center card was removed in the dashboard
        // streamlining pass — the underlying `attention` payload is still
        // computed (asserted elsewhere) but no longer surfaced on the page.
        $this->assertStringNotContainsString('Perlu Segera Ditindaklanjuti', $html);
        $this->assertStringNotContainsString('Buka Monitoring Laporan', $html);
        $this->assertStringNotContainsString('Tidak ada masalah utama yang terdeteksi', $html);

        // Still no SLA / fabricated claims.
        $this->assertStringNotContainsString('SLA', $html);
        $this->assertStringNotContainsString('Terlambat', $html);
    }

    // =========================================================================
    // Bottleneck Status Laporan — funnel + main pile-up flag
    // =========================================================================

    public function test_admin_status_flow_counts_stages_and_flags_bottleneck(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        // in_progress holds the most ACTIVE reports → should be the bottleneck.
        // LC-2C: legacy rows (under_review / waiting_for_information) are seeded
        // too, to prove they are EXCLUDED from the active funnel.
        $this->makeComplaint($reporter, ['status' => 'submitted']);
        $this->makeComplaint($reporter, ['status' => 'under_review']);
        $this->makeComplaint($reporter, ['status' => 'in_progress']);
        $this->makeComplaint($reporter, ['status' => 'in_progress']);
        $this->makeComplaint($reporter, ['status' => 'in_progress']);
        $this->makeComplaint($reporter, ['status' => 'waiting_for_information']);
        $this->makeComplaint($reporter, ['status' => 'resolved', 'resolved_at' => now()]);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertViewHas('statusFlow', function ($flow) {
            if (! is_array($flow) || ! isset($flow['stages'])) {
                return false;
            }

            $byValue = collect($flow['stages'])->keyBy('value');

            return $flow['bottleneck'] === 'in_progress'
                // Active = submitted (1) + in_progress (3) = 4; legacy excluded.
                && $flow['active_total'] === 4
                // Flow = active (4) + resolved (1) = 5.
                && $flow['flow_total'] === 5
                && ($byValue['submitted']['count'] ?? -1) === 1
                && ($byValue['in_progress']['count'] ?? -1) === 3
                && ($byValue['resolved']['count'] ?? -1) === 1
                // Legacy statuses are NOT stages in the active funnel.
                && ! isset($byValue['under_review'])
                && ! isset($byValue['waiting_for_information'])
                // 3 of 4 active = 75.0%
                && ($byValue['in_progress']['share'] ?? -1) === 75.0;
        });
    }

    public function test_admin_bottleneck_status_card_renders_bars_and_flag(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->makeComplaint($reporter, ['status' => 'in_progress']);
        $this->makeComplaint($reporter, ['status' => 'in_progress']);

        $html = $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->getContent();

        // Title + sub-description.
        $this->assertStringContainsString('Bottleneck Status Laporan', $html);
        $this->assertStringContainsString('Pantauan penumpukan volume laporan pada setiap tahapan alur penanganan.', $html);

        // ACTIVE workflow stages rendered as bars (LC-2C: submitted, in_progress)
        // plus "Selesai" for context. Legacy stages are NOT shown.
        $this->assertStringContainsString('Diajukan', $html);
        $this->assertStringContainsString('Sedang Diproses', $html);
        $this->assertStringContainsString('Selesai', $html);
        $this->assertStringNotContainsString('Sedang Ditinjau', $html);
        $this->assertStringNotContainsString('Menunggu Informasi', $html);

        // Main pile-up flag + tooltip share.
        $this->assertStringContainsString('bsl-row', $html);
        $this->assertStringContainsString('Titik Penumpukan Utama', $html);
        $this->assertStringContainsString('dari laporan aktif', $html);

        // Still no SLA / fabricated claims.
        $this->assertStringNotContainsString('SLA', $html);
        $this->assertStringNotContainsString('Terlambat', $html);
    }

    public function test_admin_bottleneck_status_has_no_flag_when_no_active_reports(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        // Only a resolved report → no active pile-up.
        $this->makeComplaint($reporter, ['status' => 'resolved', 'resolved_at' => now()]);

        $html = $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('Bottleneck Status Laporan', $html);
        $this->assertStringNotContainsString('Titik Penumpukan Utama', $html);
    }

    // =========================================================================
    // Persentase Penyelesaian per Kategori — tiering + lagging-first sort
    // =========================================================================

    public function test_admin_category_completion_ratio_tier_and_sort(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $low  = ComplaintCategory::factory()->create(['name' => 'Kategori Lagging']);
        $high = ComplaintCategory::factory()->create(['name' => 'Kategori Bagus']);

        // Lagging: 1 resolved / 4 total = 25% → tier "low".
        $this->makeComplaint($reporter, ['category_id' => $low->id, 'status' => 'resolved', 'resolved_at' => now()]);
        $this->makeComplaint($reporter, ['category_id' => $low->id, 'status' => 'submitted']);
        $this->makeComplaint($reporter, ['category_id' => $low->id, 'status' => 'in_progress']);
        $this->makeComplaint($reporter, ['category_id' => $low->id, 'status' => 'in_progress']);

        // Good: 3 resolved / 4 total = 75% → tier "high".
        $this->makeComplaint($reporter, ['category_id' => $high->id, 'status' => 'resolved', 'resolved_at' => now()]);
        $this->makeComplaint($reporter, ['category_id' => $high->id, 'status' => 'resolved', 'resolved_at' => now()]);
        $this->makeComplaint($reporter, ['category_id' => $high->id, 'status' => 'resolved', 'resolved_at' => now()]);
        $this->makeComplaint($reporter, ['category_id' => $high->id, 'status' => 'submitted']);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertViewHas('kategoriPenyelesaian', function ($rows) use ($low, $high) {
            if (! is_array($rows) || count($rows) !== 2) {
                return false;
            }

            // Sorted by completion ascending → lagging category first.
            $first  = $rows[0];
            $second = $rows[1];

            return $first['name'] === $low->name
                && $first['percent'] === 25.0
                && $first['tier'] === 'low'
                && $first['resolved'] === 1
                && $first['total'] === 4
                && $second['name'] === $high->name
                && $second['percent'] === 75.0
                && $second['tier'] === 'high';
        });
    }

    public function test_admin_category_completion_card_renders_bars_ratios_and_tooltip(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $cat = ComplaintCategory::factory()->create(['name' => 'Saluran Air Uji']);
        $this->makeComplaint($reporter, ['category_id' => $cat->id, 'status' => 'resolved', 'resolved_at' => now()]);
        $this->makeComplaint($reporter, ['category_id' => $cat->id, 'status' => 'submitted']);
        $this->makeComplaint($reporter, ['category_id' => $cat->id, 'status' => 'submitted']);
        $this->makeComplaint($reporter, ['category_id' => $cat->id, 'status' => 'submitted']);

        $html = $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->getContent();

        // Title + sub-description.
        $this->assertStringContainsString('Persentase Penyelesaian per Kategori', $html);
        $this->assertStringContainsString('Rasio perbandingan laporan selesai terhadap total laporan masuk pada masing-masing kategori.', $html);

        // Ratio + percentage + tooltip breakdown.
        $this->assertStringContainsString('cpc-row', $html);
        $this->assertStringContainsString('Saluran Air Uji', $html);
        $this->assertStringContainsString('1/4', $html);
        $this->assertStringContainsString('25%', $html);
        $this->assertStringContainsString('Saluran Air Uji: 1 dari 4 laporan selesai', $html);

        // Still no SLA / fabricated claims.
        $this->assertStringNotContainsString('SLA', $html);
        $this->assertStringNotContainsString('Terlambat', $html);
    }

    // =========================================================================
    // Dinas/Unit dengan Laporan Berumur Panjang — long-age backlog per unit
    // =========================================================================

    public function test_admin_dinas_long_aging_counts_only_over_7_days(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $unitA = DinasUnit::create(['name' => 'Dinas Long A', 'code' => 'LNG-A', 'is_active' => true, 'sort_order' => 1]);
        $unitB = DinasUnit::create(['name' => 'Dinas Long B', 'code' => 'LNG-B', 'is_active' => true, 'sort_order' => 2]);

        // Unit A: 4 long-age ACTIVE reports (>7 hari) → triggers the warning badge.
        $this->makeComplaint($reporter, ['dinas_unit_id' => $unitA->id, 'status' => 'in_progress', 'submitted_at' => now()->subDays(10)]);
        $this->makeComplaint($reporter, ['dinas_unit_id' => $unitA->id, 'status' => 'submitted', 'submitted_at' => now()->subDays(9)]);
        $this->makeComplaint($reporter, ['dinas_unit_id' => $unitA->id, 'status' => 'in_progress', 'submitted_at' => now()->subDays(12)]);
        $this->makeComplaint($reporter, ['dinas_unit_id' => $unitA->id, 'status' => 'submitted', 'submitted_at' => now()->subDays(8)]);
        // Legacy long-age rows must NOT count as active (LC-2C).
        $this->makeComplaint($reporter, ['dinas_unit_id' => $unitA->id, 'status' => 'under_review', 'submitted_at' => now()->subDays(30)]);
        $this->makeComplaint($reporter, ['dinas_unit_id' => $unitA->id, 'status' => 'waiting_for_information', 'submitted_at' => now()->subDays(25)]);
        // Recent active (0–1 hari) must NOT count.
        $this->makeComplaint($reporter, ['dinas_unit_id' => $unitA->id, 'status' => 'in_progress', 'submitted_at' => now()]);

        // Unit B: 1 long-age active + 1 resolved (long age) → resolved must NOT count.
        $this->makeComplaint($reporter, ['dinas_unit_id' => $unitB->id, 'status' => 'submitted', 'submitted_at' => now()->subDays(10)]);
        $this->makeComplaint($reporter, ['dinas_unit_id' => $unitB->id, 'status' => 'resolved', 'resolved_at' => now(), 'submitted_at' => now()->subDays(20)]);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertViewHas('dinasLongAging', function ($rows) use ($unitA, $unitB) {
            if (! is_array($rows) || count($rows) !== 2) {
                return false;
            }

            // Sorted by long-age load descending → unit A (4) before B (1).
            $first  = $rows[0];
            $second = $rows[1];

            return $first['name'] === $unitA->name
                && $first['count'] === 4
                && $first['oldest'] !== null
                && $first['oldest']['age_days'] >= 12
                && $second['name'] === $unitB->name
                && $second['count'] === 1;
        });
    }

    public function test_admin_dinas_long_aging_card_is_removed_from_dashboard(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $unit = DinasUnit::create(['name' => 'Dinas PUPR Uji', 'code' => 'PUPR-UJI', 'is_active' => true, 'sort_order' => 1]);

        for ($i = 0; $i < 4; $i++) {
            $this->makeComplaint($reporter, [
                'dinas_unit_id' => $unit->id,
                'status'        => 'in_progress',
                'submitted_at'  => now()->subDays(10),
            ]);
        }

        $html = $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->getContent();

        // The "Dinas/Unit dengan Laporan Berumur Panjang" card was removed in the
        // dashboard streamlining pass — its `dinasLongAging` payload is still
        // computed (asserted above) but no longer surfaced.
        $this->assertStringNotContainsString('Dinas/Unit dengan Laporan Berumur Panjang', $html);
        $this->assertStringNotContainsString('dla-row', $html);
        $this->assertStringNotContainsString('dla-attention', $html);

        // Still no SLA / fabricated claims.
        $this->assertStringNotContainsString('SLA', $html);
        $this->assertStringNotContainsString('Terlambat', $html);
    }

    public function test_admin_dashboard_renders_with_minimal_single_category_and_unit(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        // Exactly one category, one Dinas/Unit, one fresh active report, no
        // long-age report and no belum-Dinas/Unit report → the dashboard must
        // render without errors and every section must handle the thin data.
        $unit = DinasUnit::create(['name' => 'Dinas Tunggal Uji', 'code' => 'TGL-1', 'is_active' => true, 'sort_order' => 1]);
        $cat  = ComplaintCategory::factory()->create(['name' => 'Kategori Tunggal Uji']);
        $this->makeComplaint($reporter, [
            'category_id'   => $cat->id,
            'dinas_unit_id' => $unit->id,
            'status'        => 'submitted',
            'submitted_at'  => now(),
        ]);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertSee('Dashboard Admin');
        $response->assertSee('Kategori Tunggal Uji');
        $response->assertSee('Dinas Tunggal Uji');
        $response->assertDontSee('SLA', false);
        $response->assertDontSee('Terlambat', false);
    }

    // =========================================================================
    // Tingkat Penyelesaian — compact monthly completion-rate insight
    // =========================================================================

    public function test_admin_completion_insight_computes_monthly_rate_and_growth(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        // Last month: 4 plain + 1 resolved (submitted last month) → inflow 5,
        // resolved 1 → 20%.
        $lastMonth = now()->startOfMonth()->subMonth();
        for ($i = 0; $i < 4; $i++) {
            $this->makeComplaint($reporter, ['submitted_at' => $lastMonth->copy()->addDays(3)]);
        }
        $this->makeComplaint($reporter, [
            'status'       => 'resolved',
            'submitted_at' => $lastMonth->copy()->addDays(3),
            'resolved_at'  => $lastMonth->copy()->addDays(10),
        ]);

        // This month: 3 plain + 2 resolved (submitted this month) → inflow 5,
        // resolved 2 → 40% (up +20 from last month).
        for ($i = 0; $i < 3; $i++) {
            $this->makeComplaint($reporter, ['submitted_at' => now()->startOfMonth()->addDays(2)]);
        }
        for ($i = 0; $i < 2; $i++) {
            $this->makeComplaint($reporter, [
                'status'       => 'resolved',
                'submitted_at' => now()->startOfMonth()->addDay(),
                'resolved_at'  => now()->startOfMonth()->addDays(5),
            ]);
        }

        $html = $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->getContent();

        // Title + sub-description (Prompt 3/4 — compact insight, not a big chart).
        $this->assertStringContainsString('Tingkat Penyelesaian', $html);
        $this->assertStringContainsString('Laporan selesai terhadap laporan masuk, per bulan.', $html);

        // Headline latest rate + growth badge (factual, from real counts).
        $this->assertStringContainsString('40%', $html);
        $this->assertStringContainsString('+20% dari bulan sebelumnya', $html);

        // Still no SLA / fabricated claims.
        $this->assertStringNotContainsString('SLA', $html);
        $this->assertStringNotContainsString('Terlambat', $html);
    }

    public function test_admin_completion_insight_is_honest_when_no_inflow(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $html = $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->getContent();

        // No reports at all → honest empty state, no fabricated 0% figure.
        $this->assertStringContainsString('Tingkat Penyelesaian', $html);
        $this->assertStringNotContainsString('tingkat penyelesaian bulan terakhir', $html);
    }

    // =========================================================================
    // Final layout polish — audit activity feed + donut removal
    // =========================================================================

    public function test_admin_audit_feed_is_removed_from_dashboard(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $operator = User::factory()->create(['role' => 'operator', 'is_active' => true, 'name' => 'Fauzan Kasim']);

        $log = new \App\Models\AuditLog([
            'actor_id'     => $operator->id,
            'action'       => 'complaint_status_updated',
            'subject_type' => 'complaint',
            'subject_id'   => 1,
        ]);
        $log->created_at = now()->subHours(21);
        $log->save();

        $html = $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->getContent();

        // The "Aktivitas Audit Terkini" feed card was removed in the dashboard
        // streamlining pass — the audit data layer is untouched (Super Admin
        // still owns the "Audit & Keamanan" surface), but the Admin dashboard
        // must not render the feed or any dead audit-log link.
        $this->assertStringNotContainsString('Aktivitas Audit Terkini', $html);
        $this->assertStringNotContainsString('Lihat Semua Audit Log', $html);
        $this->assertStringNotContainsString('Fauzan Kasim', $html);

        // Still no SLA / fabricated claims.
        $this->assertStringNotContainsString('SLA', $html);
    }

    public function test_admin_dashboard_drops_completion_donut_and_uses_space(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->makeComplaint($reporter, ['submitted_at' => now()]);

        $html = $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->getContent();

        // The completion donut card is gone (info is covered by the KPI + trend).
        $this->assertStringNotContainsString('Dari total laporan tercatat', $html);
        $this->assertStringNotContainsString('donut', $html);

        // The streamlined dashboard no longer hosts the long-age Dinas/Unit card.
        $this->assertStringNotContainsString('Dinas/Unit dengan Laporan Berumur Panjang', $html);
    }

    // =========================================================================
    // Prompt 2/4 — backend data + query fixes (attention, ordering, N+1, status)
    // =========================================================================

    public function test_admin_attention_counts_only_active_reports_without_dinas_unit(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
        $unit     = DinasUnit::create(['name' => 'Dinas Belum Uji', 'code' => 'BLM-1', 'is_active' => true, 'sort_order' => 1]);

        // Active + NO Dinas/Unit → counted (and lands in the ">3 hari" bucket).
        $this->makeComplaint($reporter, ['dinas_unit_id' => null, 'status' => 'submitted', 'submitted_at' => now()->subDays(6)]);
        // Active + HAS a Dinas/Unit → NOT counted.
        $this->makeComplaint($reporter, ['dinas_unit_id' => $unit->id, 'status' => 'in_progress']);
        // Terminal + NO Dinas/Unit → NOT counted (only ACTIVE reports count).
        $this->makeComplaint($reporter, ['dinas_unit_id' => null, 'status' => 'resolved', 'resolved_at' => now()]);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertViewHas('attention', function ($attention) {
            if (! is_array($attention) || ($attention['belumDinasUnit'] ?? null) !== 1) {
                return false;
            }

            $byLabel = collect($attention['belumDinasUnitBuckets'] ?? [])->pluck('count', 'label');

            return $attention['belumDinasUnitTotal'] === 1
                && ($byLabel['0–1 hari'] ?? -1) === 0
                && ($byLabel['2–3 hari'] ?? -1) === 0
                && ($byLabel['>3 hari'] ?? -1) === 1;
        });
    }

    public function test_belum_dinas_unit_metric_depends_only_on_dinas_unit_not_operator(): void
    {
        // PHASE 6C — Decision 3: the former `belumDitugaskan` (`assigned_to IS
        // NULL`) metric was consolidated into the single `belumDinasUnit`
        // metric. The count must depend ONLY on `dinas_unit_id` and be entirely
        // independent of the deprecated `assigned_to` column.
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $operator = User::factory()->create(['role' => 'operator', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
        $unit     = DinasUnit::create(['name' => 'Dinas Pisah Uji', 'code' => 'PIS-1', 'is_active' => true, 'sort_order' => 1]);

        // Has an OPERATOR (assigned_to set) but NO Dinas/Unit → counted.
        $this->makeComplaint($reporter, ['assigned_to' => $operator->id, 'dinas_unit_id' => null, 'status' => 'submitted']);
        // Has a DINAS/UNIT but NO operator → NOT counted (routing is resolved).
        $this->makeComplaint($reporter, ['assigned_to' => null, 'dinas_unit_id' => $unit->id, 'status' => 'submitted']);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertViewHas('attention', function ($attention) {
            // Only the Dinas/Unit-based count exists; the legacy key is gone.
            return ($attention['belumDinasUnit'] ?? null) === 1
                && ! array_key_exists('belumDitugaskan', $attention);
        });
    }

    public function test_admin_laporan_perhatian_orders_oldest_active_first(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $old = $this->makeComplaint($reporter, ['submitted_at' => now()->subDays(10)]);
        $mid = $this->makeComplaint($reporter, ['submitted_at' => now()->subDays(5)]);
        $new = $this->makeComplaint($reporter, ['submitted_at' => now()->subDay()]);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        // ASC by submitted_at → the longest-active report sits at the top.
        $response->assertViewHas('laporanPerhatian', function ($rows) use ($old, $mid, $new) {
            return $rows->pluck('id')->all() === [$old->id, $mid->id, $new->id];
        });
    }

    public function test_admin_laporan_perhatian_keeps_the_oldest_when_over_limit(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $made = [];
        for ($days = 12; $days >= 1; $days--) {
            $made[$days] = $this->makeComplaint($reporter, ['submitted_at' => now()->subDays($days)]);
        }

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertViewHas('laporanPerhatian', function ($rows) use ($made) {
            // limit(10) → the two newest (1 & 2 days) are dropped, oldest first.
            if ($rows->count() !== 10) {
                return false;
            }

            $ids = $rows->pluck('id')->all();

            return $ids[0] === $made[12]->id
                && $ids[9] === $made[3]->id
                && ! in_array($made[1]->id, $ids, true)
                && ! in_array($made[2]->id, $ids, true);
        });
    }

    public function test_admin_laporan_lists_eager_load_dinas_unit(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
        $unit     = DinasUnit::create(['name' => 'Dinas Eager Uji', 'code' => 'EGR-1', 'is_active' => true, 'sort_order' => 1]);

        // Active → appears in BOTH the "terbaru" and "perhatian" lists.
        $this->makeComplaint($reporter, ['dinas_unit_id' => $unit->id, 'status' => 'in_progress', 'submitted_at' => now()->subDays(3)]);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertViewHas('laporanTerbaru', fn ($rows) => $rows->isNotEmpty() && $rows->every(fn ($c) => $c->relationLoaded('dinasUnit')));
        $response->assertViewHas('laporanPerhatian', fn ($rows) => $rows->isNotEmpty() && $rows->every(fn ($c) => $c->relationLoaded('dinasUnit')));
    }

    public function test_admin_payload_omits_unused_kategori_teratas(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        // Removed from the Admin payload only — the shared method still serves
        // the Operator & Super Admin dashboards.
        $response->assertViewMissing('kategoriTeratas');
    }

    public function test_admin_status_breakdown_matches_real_counts_after_consolidation(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->makeComplaint($reporter, ['status' => 'submitted']);
        $this->makeComplaint($reporter, ['status' => 'under_review']);
        $this->makeComplaint($reporter, ['status' => 'in_progress']);
        $this->makeComplaint($reporter, ['status' => 'in_progress']);
        $this->makeComplaint($reporter, ['status' => 'resolved', 'resolved_at' => now()]);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertViewHas('statusBreakdown', function ($breakdown) {
            return count($breakdown) === 7
                && ($breakdown['submitted']['count'] ?? -1) === 1
                && ($breakdown['under_review']['count'] ?? -1) === 1
                && ($breakdown['in_progress']['count'] ?? -1) === 2
                && ($breakdown['waiting_for_information']['count'] ?? -1) === 0
                && ($breakdown['resolved']['count'] ?? -1) === 1
                && ($breakdown['rejected']['count'] ?? -1) === 0
                && ($breakdown['closed']['count'] ?? -1) === 0;
        });
    }

    // =========================================================================
    // Admin dashboard is MONITORING ONLY — no mutation, no state change
    // =========================================================================

    public function test_admin_dashboard_is_read_only(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $complaint = $this->makeComplaint($reporter, ['status' => 'submitted']);

        // No mutation route is exposed on the admin surface.
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.complaints.show'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.complaints.assign'));

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();

        // Loading the dashboard changes nothing.
        $this->assertDatabaseCount('complaints', 1);
        $this->assertSame('submitted', $complaint->fresh()->status->value);
        $this->assertNull($complaint->fresh()->assigned_to);
    }
}
