<?php

namespace Tests\Feature;

use App\Enums\ComplaintReviewState;
use App\Enums\ComplaintStatus;
use App\Enums\UserRole;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\DinasUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesLocationFixtures;
use Tests\TestCase;

/**
 * SUPERBIE — PHASE 6C — Legacy semantics remediation.
 *
 * This suite locks in the 9 FROZEN PHASE 6B decisions and the implementation
 * choices made in PHASE 6C. It asserts the AFTER behaviour only; it never
 * weakens an existing guard.
 *
 * Decisions covered:
 *   1. Historical `petugas` users/complaints are RETAINED as historical (no new
 *      Petugas routes/actors, no reassignment, no deletion).
 *   2. `complaints.assigned_to` is DEPRECATED — never written by any active
 *      workflow, never used for routing/filtering; historical values untouched.
 *   3. The former "Belum Ditugaskan" KPI is REDEFINED to "Belum Ditentukan
 *      Dinas/Unit" and derived from `dinas_unit_id IS NULL`.
 *   4. `complaints.tracking_secret_hash` is DEPRECATED — no longer written.
 *   5. `complaints.reporter_phone` is DEPRECATED — no longer written.
 *   6. `complaints.reporter_email` is a RETAINED snapshot — still written.
 *   7. `complaints.public_updated_at` is DEPRECATED — no longer written.
 *   8. `complaint_categories.dinas_name` is RETAINED as historical.
 *   9. The Operator assignment filter (mine/unassigned) is REMOVED.
 */
class Phase6CLegacySemanticsRemediationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesLocationFixtures;

    private User $superAdmin;
    private User $admin;
    private User $operator;
    private User $citizen;
    private ComplaintCategory $category;
    private DinasUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->admin      = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->citizen    = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->unit = DinasUnit::create([
            'name' => 'Dinas Uji 6C', 'code' => 'UJI-6C', 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->category = ComplaintCategory::create([
            'name' => 'Infrastruktur Jalan', 'slug' => 'infrastruktur-jalan',
            'dinas_name' => 'Dinas PUPR', 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->category->dinasUnits()->attach($this->unit->id);

        $this->operator = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unit->id,
        ]);
    }

    private static int $seq = 0;

    private function makeComplaint(array $overrides = []): Complaint
    {
        static::$seq++;

        return Complaint::create(array_merge([
            'reference_code' => 'LPW-6C-' . str_pad((string) static::$seq, 4, '0', STR_PAD_LEFT),
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $this->category->id,
            'title'          => 'Laporan uji 6C ' . static::$seq,
            'description'    => 'Deskripsi laporan uji PHASE 6C yang cukup panjang.',
            'status'         => ComplaintStatus::Submitted,
            'submitted_at'   => now(),
        ], $overrides));
    }

    // =========================================================================
    // Decision 1 — Historical Petugas retained as historical
    // =========================================================================

    public function test_historical_petugas_user_and_complaint_are_retained(): void
    {
        // A legacy `petugas` row (role string column) with no Dinas/Unit.
        $petugas = User::create([
            'name'      => 'Petugas Historis',
            'email'     => 'petugas.historis@example.test',
            'password'  => bcrypt('password123'),
            'role'      => 'petugas',
            'is_active' => true,
        ]);

        $complaint = $this->makeComplaint(['assigned_to' => $petugas->id]);

        // The historical row and its assignment are preserved untouched.
        $this->assertDatabaseHas('users', ['id' => $petugas->id, 'role' => 'petugas']);
        $this->assertSame($petugas->id, $complaint->fresh()->assigned_to);
        $this->assertNull($petugas->fresh()->dinas_unit_id);
    }

    public function test_petugas_is_not_an_active_role(): void
    {
        // The active role enum MUST NOT contain `petugas` — it is historical only.
        $this->assertNull(UserRole::tryFrom('petugas'));
        $this->assertSame(
            ['masyarakat', 'operator', 'admin', 'super_admin'],
            array_map(fn (UserRole $r) => $r->value, UserRole::cases()),
        );
    }

    public function test_no_petugas_routes_exist(): void
    {
        // No Petugas actor surface was ever (re)introduced.
        $uris = collect(Route::getRoutes())->map(fn ($r) => $r->uri())->all();

        foreach ($uris as $uri) {
            $this->assertStringNotContainsString('petugas', strtolower($uri));
        }
    }

    // =========================================================================
    // Decision 2 — `assigned_to` deprecated (never written by runtime)
    // =========================================================================

    public function test_citizen_store_does_not_set_assigned_to(): void
    {
        $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), [
            'category_id'   => $this->category->id,
            'title'         => 'Jalan berlubang parah di depan pasar',
            'description'   => 'Lubang cukup dalam dan membahayakan pengendara sepeda motor.',
            'location_text' => 'Jl. Bau Massepe',
            ...$this->locationPayload(),
        ])->assertSessionHasNoErrors();

        $complaint = Complaint::where('reporter_id', $this->citizen->id)->firstOrFail();
        $this->assertNull($complaint->assigned_to);
    }

    public function test_initial_validation_approval_does_not_set_assigned_to(): void
    {
        $complaint = $this->makeComplaint([
            'review_state' => ComplaintReviewState::PendingInitialValidation,
        ]);

        $this->actingAs($this->superAdmin)->post(route('super-admin.validation.approve', $complaint), [
            'category_id'   => $this->category->id,
            'dinas_unit_id' => $this->unit->id,
        ])->assertSessionHasNoErrors();

        $fresh = $complaint->fresh();
        $this->assertSame($this->unit->id, $fresh->dinas_unit_id);
        $this->assertNull($fresh->assigned_to);
    }

    public function test_no_operator_assignment_route_exists(): void
    {
        $this->assertFalse(Route::has('operator.complaint.assign'));
        $this->assertFalse(Route::has('operator.complaint.update-category'));
    }

    public function test_deprecated_assignee_relation_is_retained_for_history_only(): void
    {
        // The relation still resolves historical rows but is documented LEGACY.
        $assignee = User::factory()->create(['role' => 'operator', 'is_active' => true]);
        $complaint = $this->makeComplaint(['assigned_to' => $assignee->id]);

        $this->assertSame($assignee->id, $complaint->assignee?->id);
        $this->assertTrue($assignee->assignedComplaints()->whereKey($complaint->id)->exists());
    }

    // =========================================================================
    // Decision 3 — "Belum Ditentukan Dinas/Unit" (dinas_unit_id IS NULL)
    // =========================================================================

    public function test_admin_dashboard_attention_is_dinas_unit_based(): void
    {
        // No Dinas/Unit, active → counted.
        $this->makeComplaint(['dinas_unit_id' => null, 'status' => ComplaintStatus::Submitted]);
        // Has a Dinas/Unit → NOT counted.
        $this->makeComplaint(['dinas_unit_id' => $this->unit->id, 'status' => ComplaintStatus::Submitted]);
        // Terminal + no Dinas/Unit → NOT counted (only active).
        $this->makeComplaint(['dinas_unit_id' => null, 'status' => ComplaintStatus::Resolved, 'resolved_at' => now()]);

        $response = $this->actingAs($this->admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertViewHas('attention', function ($attention) {
            return is_array($attention)
                && ($attention['belumDinasUnit'] ?? null) === 1
                && ! array_key_exists('belumDitugaskan', $attention);
        });
    }

    public function test_admin_complaints_kpi_uses_belum_dinas_unit_key(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/laporan');

        $response->assertOk();
        $kpi = $response->viewData('kpi');
        $this->assertIsArray($kpi);
        $this->assertArrayHasKey('belum_dinas_unit', $kpi);
        $this->assertArrayNotHasKey('belum_ditugaskan', $kpi);
    }

    public function test_admin_dashboard_belum_dinas_unit_wording_card_is_removed(): void
    {
        $this->makeComplaint(['dinas_unit_id' => null, 'status' => ComplaintStatus::Submitted, 'submitted_at' => now()->subDays(9)]);

        $html = $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk()->getContent();

        // The Attention Center card (which surfaced the "Belum Ditentukan
        // Dinas/Unit" wording) was removed in the dashboard streamlining pass.
        // The `attention` payload is still dinas_unit-based (asserted above);
        // only the rendered wording card is gone.
        $this->assertStringNotContainsString('Laporan belum ditentukan Dinas/Unit', $html);
        $this->assertStringNotContainsString('Laporan belum ditugaskan', $html);
    }

    public function test_operator_dashboard_belum_dinas_unit_is_dinas_unit_based(): void
    {
        // Visible to the operator, but already routed → NOT counted.
        $this->makeComplaint([
            'dinas_unit_id' => $this->unit->id,
            'review_state'  => ComplaintReviewState::ValidatedForOperator,
            'status'        => ComplaintStatus::Submitted,
        ]);
        // Visible to the operator via category mapping, but NO Dinas/Unit → counted.
        $this->makeComplaint([
            'dinas_unit_id' => null,
            'review_state'  => ComplaintReviewState::ValidatedForOperator,
            'status'        => ComplaintStatus::Submitted,
        ]);

        $response = $this->actingAs($this->operator)->get('/operator/dashboard');

        $response->assertOk();
        $response->assertViewHas('belumDinasUnit', 1);
    }

    public function test_admin_unassigned_filter_is_dinas_unit_based(): void
    {
        $unrouted = $this->makeComplaint(['dinas_unit_id' => null, 'status' => ComplaintStatus::Submitted]);
        $routed   = $this->makeComplaint(['dinas_unit_id' => $this->unit->id, 'status' => ComplaintStatus::Submitted]);
        $resolved = $this->makeComplaint(['dinas_unit_id' => null, 'status' => ComplaintStatus::Resolved, 'resolved_at' => now()]);

        $response = $this->actingAs($this->admin)->get('/admin/laporan?assigned=unassigned');

        $response->assertOk();
        $response->assertSee($unrouted->reference_code);
        $response->assertDontSee($routed->reference_code);
        $response->assertDontSee($resolved->reference_code);
    }

    // =========================================================================
    // Decision 4 — `tracking_secret_hash` deprecated (no longer written)
    // =========================================================================

    public function test_citizen_store_does_not_write_tracking_secret_hash(): void
    {
        $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), [
            'category_id'   => $this->category->id,
            'title'         => 'Lampu jalan mati di Jl. Mattirotasi',
            'description'   => 'Dua tiang lampu PJU padam sejak beberapa hari yang lalu.',
            'location_text' => 'Jl. Mattirotasi',
            ...$this->locationPayload(),
        ])->assertSessionHasNoErrors();

        $complaint = Complaint::where('reporter_id', $this->citizen->id)->firstOrFail();
        $this->assertNull($complaint->tracking_secret_hash);
    }

    // =========================================================================
    // Decision 5 — `reporter_phone` deprecated (no longer written)
    // =========================================================================

    public function test_citizen_store_does_not_write_reporter_phone(): void
    {
        $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), [
            'category_id'   => $this->category->id,
            'title'         => 'Drainase tersumbat menyebabkan genangan',
            'description'   => 'Hujan deras menyebabkan genangan air masuk ke teras rumah warga.',
            'location_text' => 'Jl. Lasinrang',
            ...$this->locationPayload(),
        ])->assertSessionHasNoErrors();

        $complaint = Complaint::where('reporter_id', $this->citizen->id)->firstOrFail();
        $this->assertNull($complaint->reporter_phone);
    }

    // =========================================================================
    // Decision 6 — `reporter_email` retained snapshot (still written)
    // =========================================================================

    public function test_citizen_store_writes_reporter_email_snapshot(): void
    {
        $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), [
            'category_id'   => $this->category->id,
            'title'         => 'Sampah menumpuk di TPS Pasar Lakessi',
            'description'   => 'Kontainer sampah sudah penuh dan meluap ke badan jalan.',
            'location_text' => 'Pasar Lakessi',
            ...$this->locationPayload(),
        ])->assertSessionHasNoErrors();

        $complaint = Complaint::where('reporter_id', $this->citizen->id)->firstOrFail();
        $this->assertSame($this->citizen->email, $complaint->reporter_email);
    }

    // =========================================================================
    // Decision 7 — `public_updated_at` deprecated (no longer written)
    // =========================================================================

    public function test_operator_status_change_does_not_write_public_updated_at(): void
    {
        $complaint = $this->makeComplaint([
            'dinas_unit_id' => $this->unit->id,
            'review_state'  => ComplaintReviewState::ValidatedForOperator,
            'status'        => ComplaintStatus::Submitted,
        ]);

        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-status', $complaint), [
                'status' => ComplaintStatus::InProgress->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(ComplaintStatus::InProgress, $complaint->fresh()->status);
        $this->assertNull($complaint->fresh()->public_updated_at);
    }

    public function test_operator_note_does_not_write_public_updated_at(): void
    {
        $complaint = $this->makeComplaint([
            'dinas_unit_id' => $this->unit->id,
            'review_state'  => ComplaintReviewState::ValidatedForOperator,
            'status'        => ComplaintStatus::InProgress,
        ]);

        $this->actingAs($this->operator)
            ->post(route('operator.complaint.add-note', $complaint), [
                'body'       => 'Catatan internal pengujian PHASE 6C.',
                'visibility' => 'internal',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($complaint->fresh()->public_updated_at);
    }

    public function test_completion_approval_does_not_write_public_updated_at(): void
    {
        $complaint = $this->makeComplaint([
            'dinas_unit_id'          => $this->unit->id,
            'review_state'           => ComplaintReviewState::PendingCompletionValidation,
            'status'                 => ComplaintStatus::InProgress,
            'completion_submitted_at' => now(),
        ]);

        $note = \App\Models\ComplaintNote::create([
            'complaint_id' => $complaint->id,
            'author_id'    => $this->operator->id,
            'visibility'   => \App\Enums\NoteVisibility::Internal,
            'body'         => 'Narasi penyelesaian pekerjaan lapangan yang telah diselesaikan.',
            'created_at'   => now(),
        ]);

        \App\Models\AuditLog::create([
            'actor_id'     => $this->operator->id,
            'action'       => 'complaint.completion_submitted',
            'subject_type' => 'complaint',
            'subject_id'   => $complaint->id,
            'ip_address'   => '127.0.0.1',
            'user_agent'   => 'test',
            'metadata'     => [
                'reference_code'      => $complaint->reference_code,
                'completion_note_id'  => $note->id,
                'has_completion_note' => true,
            ],
        ]);

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.completion-validation.approve', $complaint))
            ->assertSessionHasNoErrors();

        $fresh = $complaint->fresh();
        $this->assertSame(ComplaintStatus::Resolved, $fresh->status);
        $this->assertNull($fresh->public_updated_at);
    }

    // =========================================================================
    // Decision 8 — `complaint_categories.dinas_name` retained historical
    // =========================================================================

    public function test_complaint_categories_dinas_name_column_is_retained(): void
    {
        $this->assertTrue(
            Schema::hasColumn('complaint_categories', 'dinas_name'),
            'dinas_name must be retained as historical (no column drop).'
        );

        // The legacy value is still persisted and readable.
        $this->assertSame('Dinas PUPR', $this->category->fresh()->dinas_name);
    }

    // =========================================================================
    // Decision 9 — Operator assignment filter REMOVED
    // =========================================================================

    public function test_operator_index_has_no_assignment_filter_ui(): void
    {
        $complaint = $this->makeComplaint([
            'dinas_unit_id' => $this->unit->id,
            'review_state'  => ComplaintReviewState::ValidatedForOperator,
            'status'        => ComplaintStatus::Submitted,
        ]);

        $html = $this->actingAs($this->operator)->get('/operator/laporan')->assertOk()->getContent();

        $this->assertStringNotContainsString('name="assignment"', $html);
        $this->assertStringNotContainsString('Ditugaskan ke Saya', $html);
        $this->assertStringNotContainsString('Belum Ditugaskan', $html);
        // The list itself still renders normally.
        $this->assertStringContainsString($complaint->reference_code, $html);
    }

    public function test_operator_index_ignores_legacy_assignment_query_param(): void
    {
        $complaint = $this->makeComplaint([
            'dinas_unit_id' => $this->unit->id,
            'review_state'  => ComplaintReviewState::ValidatedForOperator,
            'status'        => ComplaintStatus::Submitted,
        ]);

        // A stale bookmarked `?assignment=mine` must not change (or break) the
        // scoped list — the filter no longer exists.
        $html = $this->actingAs($this->operator)
            ->get('/operator/laporan?assignment=mine')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($complaint->reference_code, $html);
    }

    // =========================================================================
    // Cross-cutting — no new routes / no public tracking
    // =========================================================================

    public function test_no_public_tracking_or_assignment_routes_exist(): void
    {
        $uris = collect(Route::getRoutes())->map(fn ($r) => strtolower($r->uri()))->all();

        foreach ($uris as $uri) {
            $this->assertStringNotContainsString('tracking', $uri);
            $this->assertStringNotContainsString('tugaskan', $uri);
            $this->assertStringNotContainsString('assign', $uri);
        }
    }

    public function test_seeder_no_longer_writes_assigned_to(): void
    {
        // The seeder must not populate the deprecated column (no fixture depends
        // on it and no runtime path reads it).
        $seeder = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        $this->assertStringNotContainsString("'assigned_to'", $seeder);
    }
}
