<?php

namespace Tests\Feature;

use App\Enums\ComplaintReviewState;
use App\Enums\ComplaintStatus;
use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\ComplaintNote;
use App\Models\DinasUnit;
use App\Models\User;
use App\Services\ComplaintRetentionService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesLocationFixtures;
use Tests\TestCase;

/**
 * SUPERBIE — PHASE N4 — In-app notification SYSTEM.
 *
 * Covers the frozen milestone matrix (M1–M13), recipient rules (Citizen /
 * Operator-via-Dinas / active Super Admin; never Admin/Petugas/actor/inactive),
 * idempotency, privacy, and retention integration.
 *
 * Read/unread, routes, authorization and UI are covered by
 * NotificationCentreTest.
 */
class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;
    use CreatesLocationFixtures;

    private User $superAdmin;
    private User $superAdmin2;
    private User $admin;
    private User $operatorA;
    private User $operatorB;
    private User $citizen;

    private ComplaintCategory $categoryA;
    private ComplaintCategory $categoryB;
    private DinasUnit $unitA;
    private DinasUnit $unitB;

    private int $kecamatanId;
    private int $kelurahanId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin  = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->superAdmin2 = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->admin       = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->citizen     = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->categoryA = ComplaintCategory::create([
            'name' => 'Infrastruktur Jalan', 'slug' => 'infrastruktur-jalan',
            'is_active' => true, 'sort_order' => 1,
        ]);
        $this->categoryB = ComplaintCategory::create([
            'name' => 'Kebersihan', 'slug' => 'kebersihan',
            'is_active' => true, 'sort_order' => 2,
        ]);

        $this->unitA = DinasUnit::create([
            'name' => 'Dinas PUPR', 'code' => 'PUPR', 'is_active' => true, 'sort_order' => 1,
        ]);
        $this->unitB = DinasUnit::create([
            'name' => 'Dinas Lingkungan Hidup', 'code' => 'DLH', 'is_active' => true, 'sort_order' => 2,
        ]);

        $this->unitA->categories()->attach($this->categoryA->id);
        $this->unitB->categories()->attach($this->categoryB->id);

        $this->operatorA = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unitA->id,
        ]);
        $this->operatorB = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unitB->id,
        ]);

        [$this->kecamatanId, $this->kelurahanId] = $this->makeLocationPair();
    }

    private static int $seq = 0;

    private function makeComplaint(array $overrides = []): Complaint
    {
        static::$seq++;

        return Complaint::create(array_merge([
            'reference_code' => 'LPW-N4-' . str_pad((string) static::$seq, 4, '0', STR_PAD_LEFT),
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $this->categoryA->id,
            'dinas_unit_id'  => $this->unitA->id,
            'kecamatan_id'   => $this->kecamatanId,
            'kelurahan_id'   => $this->kelurahanId,
            'title'          => 'Laporan uji notifikasi ' . static::$seq,
            'description'    => 'Deskripsi laporan uji notifikasi in-app.',
            'status'         => ComplaintStatus::Submitted,
            'review_state'   => ComplaintReviewState::PendingInitialValidation,
            'submitted_at'   => now(),
        ], $overrides));
    }

    /** Number of notifications for a recipient (optionally of a given type). */
    private function countFor(User $user, ?string $type = null): int
    {
        $q = AppNotification::where('recipient_user_id', $user->id);
        if ($type !== null) {
            $q->where('type', $type);
        }

        return $q->count();
    }

    // =========================================================================
    // M1 — complaint_submitted
    // =========================================================================

    public function test_m1_citizen_submit_notifies_all_active_super_admins_but_not_the_citizen(): void
    {
        $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), [
            'category_id' => $this->categoryA->id,
            'title'       => 'Jalan rusak parah di depan pasar',
            'description' => 'Jalan rusak parah dan membahayakan pengguna jalan.',
            ...$this->locationPayload(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->countFor($this->superAdmin, NotificationService::M1));
        $this->assertSame(1, $this->countFor($this->superAdmin2, NotificationService::M1));
        // Self-notification is never created for the acting citizen.
        $this->assertSame(0, $this->countFor($this->citizen));
        // Admin never receives any notification.
        $this->assertSame(0, $this->countFor($this->admin));
    }

    public function test_m1_inactive_super_admin_is_not_notified(): void
    {
        $this->superAdmin2->update(['is_active' => false]);

        $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), [
            'category_id' => $this->categoryA->id,
            'title'       => 'Laporan uji super admin nonaktif',
            'description' => 'Deskripsi laporan uji super admin nonaktif.',
            ...$this->locationPayload(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->countFor($this->superAdmin, NotificationService::M1));
        $this->assertSame(0, $this->countFor($this->superAdmin2));
    }

    // =========================================================================
    // M2 — initial_validation_approved
    // =========================================================================

    public function test_m2_approval_notifies_operator_and_citizen_but_not_the_actor(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->superAdmin)->post(route('super-admin.validation.approve', $complaint), [
            'category_id'   => $this->categoryA->id,
            'dinas_unit_id' => $this->unitA->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->countFor($this->operatorA, NotificationService::M2));
        $this->assertSame(1, $this->countFor($this->citizen, NotificationService::M2));
        // The acting Super Admin never receives their own action.
        $this->assertSame(0, $this->countFor($this->superAdmin, NotificationService::M2));
        // The unrelated Operator of another Dinas is never notified.
        $this->assertSame(0, $this->countFor($this->operatorB, NotificationService::M2));
    }

    public function test_m2_never_uses_assigned_to_as_the_operator_recipient(): void
    {
        // A legacy `assigned_to` pointing at an unrelated user must be ignored:
        // the Operator is resolved from the Dinas/Unit only.
        $complaint = $this->makeComplaint(['assigned_to' => $this->admin->id]);

        $this->actingAs($this->superAdmin)->post(route('super-admin.validation.approve', $complaint), [
            'category_id'   => $this->categoryA->id,
            'dinas_unit_id' => $this->unitA->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->countFor($this->operatorA, NotificationService::M2));
        $this->assertSame(0, $this->countFor($this->admin));
    }

    // =========================================================================
    // M3 — initial_validation_returned
    // =========================================================================

    public function test_m3_return_notifies_only_the_citizen_and_leaks_no_internal_reason(): void
    {
        $complaint = $this->makeComplaint();
        $secret = 'ALASAN_INTERNAL_RAHASIA_XYZ';

        $this->actingAs($this->superAdmin)->post(route('super-admin.validation.return', $complaint), [
            'reason' => $secret . ' panjang',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->countFor($this->citizen, NotificationService::M3));
        $this->assertSame(0, $this->countFor($this->operatorA, NotificationService::M3));

        $notification = AppNotification::where('recipient_user_id', $this->citizen->id)
            ->where('type', NotificationService::M3)->firstOrFail();
        $this->assertStringNotContainsString($secret, $notification->body);
        $this->assertStringNotContainsString($secret, json_encode($notification->data));
    }

    // =========================================================================
    // M4 — complaint_resubmitted
    // =========================================================================

    public function test_m4_resubmission_notifies_super_admins(): void
    {
        $complaint = $this->makeComplaint([
            'review_state' => ComplaintReviewState::ReturnedToCitizen,
        ]);

        $this->actingAs($this->citizen)->put(route('citizen.complaint.update', $complaint), [
            'category_id' => $this->categoryA->id,
            'title'       => 'Judul laporan setelah perbaikan',
            'description' => 'Deskripsi laporan setelah diperbaiki oleh pelapor.',
            ...$this->locationPayload(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->countFor($this->superAdmin, NotificationService::M4));
        $this->assertSame(1, $this->countFor($this->superAdmin2, NotificationService::M4));
    }

    // =========================================================================
    // M5 — completion_submitted
    // =========================================================================

    public function test_m5_completion_submission_notifies_super_admins_not_the_operator(): void
    {
        $complaint = $this->makeComplaint([
            'status'       => ComplaintStatus::InProgress,
            'review_state' => ComplaintReviewState::ValidatedForOperator,
        ]);

        $this->actingAs($this->operatorA)->patch(route('operator.complaint.submit-completion', $complaint), [
            'completion_note' => 'Pekerjaan selesai.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->countFor($this->superAdmin, NotificationService::M5));
        $this->assertSame(1, $this->countFor($this->superAdmin2, NotificationService::M5));
        $this->assertSame(0, $this->countFor($this->operatorA));
    }

    // =========================================================================
    // M6 — completion_returned
    // =========================================================================

    public function test_m6_completion_return_notifies_the_operator_and_leaks_no_internal_reason(): void
    {
        $complaint = $this->makePendingCompletion();
        $secret = 'ALASAN_PENGEMBALIAN_INTERNAL_ABC';

        $this->actingAs($this->superAdmin)->post(route('super-admin.completion-validation.return', $complaint), [
            'reason' => $secret . ' alasan panjang',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->countFor($this->operatorA, NotificationService::M6));
        $this->assertSame(0, $this->countFor($this->citizen, NotificationService::M6));

        $notification = AppNotification::where('recipient_user_id', $this->operatorA->id)
            ->where('type', NotificationService::M6)->firstOrFail();
        $this->assertStringNotContainsString($secret, $notification->body);
        $this->assertStringNotContainsString($secret, json_encode($notification->data));
    }

    // =========================================================================
    // M7 — completion_validated / resolved
    // =========================================================================

    public function test_m7_approval_notifies_citizen_and_operator_exactly_once_each(): void
    {
        $complaint = $this->makePendingCompletion();

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.completion-validation.approve', $complaint))
            ->assertSessionHasNoErrors();

        // Exactly ONE logical M7 per recipient — the published public response
        // must NOT create a second (M13) notification.
        $this->assertSame(1, $this->countFor($this->citizen, NotificationService::M7));
        $this->assertSame(1, $this->countFor($this->operatorA, NotificationService::M7));
        $this->assertSame(0, $this->countFor($this->superAdmin, NotificationService::M7));
        $this->assertSame(0, AppNotification::where('type', 'public_response_added')->count());
    }

    public function test_m7_direct_super_admin_resolve_via_update_status_emits_once(): void
    {
        // G7 — a valid Super Admin `in_progress → resolved` through the status
        // action emits M7 exactly once (no duplicate).
        $complaint = $this->makeComplaint([
            'status'       => ComplaintStatus::InProgress,
            'review_state' => ComplaintReviewState::ValidatedForOperator,
        ]);

        $this->actingAs($this->superAdmin)->patch(route('operator.complaint.update-status', $complaint), [
            'status'               => 'resolved',
            'public_response_body' => 'Laporan telah diselesaikan oleh Dinas.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->countFor($this->citizen, NotificationService::M7));
        $this->assertSame(1, $this->countFor($this->operatorA, NotificationService::M7));
    }

    // =========================================================================
    // M8 / M9 / M10 — citizen-visible status transitions
    // =========================================================================

    public function test_m8_submitted_to_in_progress_notifies_citizen(): void
    {
        // LC-2 — the only edge that reaches `in_progress` is submitted → in_progress.
        $complaint = $this->makeComplaint([
            'status'       => ComplaintStatus::Submitted,
            'review_state' => ComplaintReviewState::ValidatedForOperator,
        ]);

        $this->actingAs($this->operatorA)->patch(route('operator.complaint.update-status', $complaint), [
            'status' => 'in_progress',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->countFor($this->citizen, NotificationService::M8));
    }

    public function test_m8_is_not_emitted_as_a_duplicate_on_initial_validation_approval(): void
    {
        // LC-2 — initial-validation approval moves submitted → in_progress and
        // emits M2 ONLY. It must NEVER also emit M8.
        $complaint = $this->makeComplaint([
            'status'       => ComplaintStatus::Submitted,
            'review_state' => ComplaintReviewState::PendingInitialValidation,
        ]);

        $this->actingAs($this->superAdmin)->post(route('super-admin.validation.approve', $complaint), [
            'category_id'   => $this->categoryA->id,
            'dinas_unit_id' => $this->unitA->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->countFor($this->citizen, NotificationService::M2));
        $this->assertSame(0, $this->countFor($this->citizen, NotificationService::M8));
        $this->assertSame(0, $this->countFor($this->operatorA, NotificationService::M8));
        $this->assertSame(
            0,
            AppNotification::where('type', 'status_in_progress')->count(),
            'No status_in_progress (M8) notification may be produced by initial validation.',
        );
    }

    public function test_m9_is_dormant_because_waiting_for_information_is_legacy(): void
    {
        // LC-2 — no active transition reaches `waiting_for_information`, so M9 can
        // never be emitted from the active flow.
        foreach ([ComplaintStatus::Submitted, ComplaintStatus::InProgress] as $from) {
            $complaint = $this->makeComplaint([
                'status'       => $from,
                'review_state' => ComplaintReviewState::ValidatedForOperator,
            ]);

            $this->actingAs($this->operatorA)
                ->patch(route('operator.complaint.update-status', $complaint), [
                    'status' => 'waiting_for_information',
                ])
                ->assertSessionHasErrors('status');
        }

        $this->assertSame(0, $this->countFor($this->citizen, NotificationService::M9));
    }

    public function test_m10_rejection_notifies_citizen(): void
    {
        $complaint = $this->makeComplaint([
            'status'       => ComplaintStatus::InProgress,
            'review_state' => ComplaintReviewState::ValidatedForOperator,
        ]);

        $this->actingAs($this->operatorA)->patch(route('operator.complaint.update-status', $complaint), [
            'status'               => 'rejected',
            'rejection_reason'     => 'Laporan tidak memenuhi syarat.',
            'public_response_body' => 'Laporan ditolak karena tidak memenuhi syarat.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->countFor($this->citizen, NotificationService::M10));
    }

    // =========================================================================
    // M11 — status_closed (DORMANT: manual close retired in LC-2)
    // =========================================================================

    public function test_m11_is_dormant_because_the_manual_close_edge_is_retired(): void
    {
        // LC-2 — `resolved`/`rejected` are final outcomes; the close edge is gone,
        // so M11 can never be emitted from the active flow.
        foreach ([ComplaintStatus::Resolved, ComplaintStatus::Rejected] as $from) {
            $complaint = $this->makeComplaint([
                'status'       => $from,
                'review_state' => ComplaintReviewState::ValidatedForOperator,
            ]);

            $this->actingAs($this->operatorA)
                ->patch(route('operator.complaint.update-status', $complaint), [
                    'status' => 'closed',
                ])
                ->assertSessionHasErrors('status');
        }

        $this->assertSame(0, $this->countFor($this->citizen, NotificationService::M11));
        $this->assertSame(0, $this->countFor($this->operatorA, NotificationService::M11));
    }

    // =========================================================================
    // M12 — rating_submitted
    // =========================================================================

    public function test_m12_rating_notifies_operator_and_super_admins_not_the_citizen_or_admin(): void
    {
        $complaint = $this->makeComplaint([
            'status'       => ComplaintStatus::Resolved,
            'review_state' => ComplaintReviewState::ValidatedForOperator,
        ]);

        $this->actingAs($this->citizen)->post(route('citizen.rating.store', $complaint), [
            'rating' => 5,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->countFor($this->operatorA, NotificationService::M12));
        $this->assertSame(1, $this->countFor($this->superAdmin, NotificationService::M12));
        $this->assertSame(1, $this->countFor($this->superAdmin2, NotificationService::M12));
        $this->assertSame(0, $this->countFor($this->citizen));
        $this->assertSame(0, $this->countFor($this->admin));
    }

    // =========================================================================
    // M13 — standalone public response
    // =========================================================================

    public function test_m13_standalone_public_response_creates_no_notification(): void
    {
        $complaint = $this->makeComplaint([
            'status'       => ComplaintStatus::InProgress,
            'review_state' => ComplaintReviewState::ValidatedForOperator,
        ]);

        $this->actingAs($this->operatorA)->post(route('operator.complaint.add-note', $complaint), [
            'body'       => 'Respons publik tanpa milestone.',
            'visibility' => 'public_response',
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, AppNotification::count());
    }

    // =========================================================================
    // Recipient isolation — Admin / Petugas
    // =========================================================================

    public function test_admin_and_petugas_never_receive_notifications(): void
    {
        $petugas = User::factory()->create(['role' => 'petugas', 'is_active' => true]);

        $complaint = $this->makeComplaint([
            'status'       => ComplaintStatus::Resolved,
            'review_state' => ComplaintReviewState::ValidatedForOperator,
        ]);

        $this->actingAs($this->citizen)->post(route('citizen.rating.store', $complaint), [
            'rating' => 4,
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, $this->countFor($this->admin));
        $this->assertSame(0, $this->countFor($petugas));
    }

    // =========================================================================
    // Inactive recipients
    // =========================================================================

    public function test_inactive_operator_does_not_receive_and_existing_notifications_are_kept(): void
    {
        // First, a notification is created while the Operator is ACTIVE.
        $complaint = $this->makeComplaint();
        $this->actingAs($this->superAdmin)->post(route('super-admin.validation.approve', $complaint), [
            'category_id'   => $this->categoryA->id,
            'dinas_unit_id' => $this->unitA->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->countFor($this->operatorA, NotificationService::M2));

        // Deactivate → the existing notification survives.
        $this->operatorA->update(['is_active' => false]);
        $this->assertSame(1, $this->countFor($this->operatorA, NotificationService::M2));

        // A NEW business action does not create a NEW notification for them.
        $complaint2 = $this->makeComplaint();
        $this->actingAs($this->superAdmin)->post(route('super-admin.validation.approve', $complaint2), [
            'category_id'   => $this->categoryA->id,
            'dinas_unit_id' => $this->unitA->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->countFor($this->operatorA, NotificationService::M2));
    }

    public function test_reactivated_user_can_receive_future_notifications(): void
    {
        $this->superAdmin->update(['is_active' => false]);

        $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), [
            'category_id' => $this->categoryA->id,
            'title'       => 'Laporan pertama saat super admin nonaktif',
            'description' => 'Deskripsi laporan pertama saat nonaktif.',
            ...$this->locationPayload(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, $this->countFor($this->superAdmin, NotificationService::M1));

        $this->superAdmin->update(['is_active' => true]);

        $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), [
            'category_id' => $this->categoryA->id,
            'title'       => 'Laporan kedua setelah super admin aktif',
            'description' => 'Deskripsi laporan kedua setelah aktif kembali.',
            ...$this->locationPayload(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->countFor($this->superAdmin, NotificationService::M1));
    }

    // =========================================================================
    // Dinas without Operator (G8)
    // =========================================================================

    public function test_dinas_without_operator_skips_operator_notification_without_fallback(): void
    {
        // G8 — when the complaint's Dinas/Unit has NO Operator, the Operator
        // notification is SKIPPED (logged) and there is NEVER a fallback to
        // another user. The initial-approval action itself blocks routing to a
        // unit without an Operator, so this condition is exercised through a
        // later milestone (M6 = operator-only audience) at the service boundary.
        $unitNoOperator = DinasUnit::create([
            'name' => 'Dinas Tanpa Operator', 'code' => 'NO-OP', 'is_active' => true, 'sort_order' => 9,
        ]);

        $complaint = $this->makeComplaint(['dinas_unit_id' => $unitNoOperator->id]);

        $audit = AuditLog::create([
            'actor_id'     => $this->superAdmin->id,
            'action'       => 'complaint.completion_returned',
            'subject_type' => 'complaint',
            'subject_id'   => $complaint->id,
        ]);

        app(NotificationService::class)->emit($complaint, NotificationService::M6, $audit->id, $this->superAdmin);

        // No Operator exists → NO notification at all, and NO fallback to any
        // other user (e.g. the other Dinas' Operator).
        $this->assertSame(0, AppNotification::count());
        $this->assertSame(0, $this->countFor($this->operatorB, NotificationService::M6));
    }

    // =========================================================================
    // Idempotency
    // =========================================================================

    public function test_same_source_event_id_is_idempotent(): void
    {
        $complaint = $this->makeComplaint();
        $service = app(NotificationService::class);

        $audit = AuditLog::create([
            'actor_id'     => $this->citizen->id,
            'action'       => 'complaint_created',
            'subject_type' => 'complaint',
            'subject_id'   => $complaint->id,
        ]);

        $service->emit($complaint, NotificationService::M1, $audit->id, $this->citizen);
        $service->emit($complaint, NotificationService::M1, $audit->id, $this->citizen);

        $this->assertSame(1, $this->countFor($this->superAdmin, NotificationService::M1));
        $this->assertSame(1, $this->countFor($this->superAdmin2, NotificationService::M1));
    }

    public function test_two_recipients_produce_two_rows(): void
    {
        $complaint = $this->makeComplaint();
        $audit = AuditLog::create([
            'actor_id'     => $this->superAdmin->id,
            'action'       => 'complaint.initial_validation_approved',
            'subject_type' => 'complaint',
            'subject_id'   => $complaint->id,
        ]);

        app(NotificationService::class)->emit($complaint, NotificationService::M2, $audit->id, $this->superAdmin);

        $this->assertSame(2, AppNotification::where('type', NotificationService::M2)->count());
    }

    public function test_same_recipient_different_business_event_produces_separate_rows(): void
    {
        $complaint = $this->makeComplaint();
        $service = app(NotificationService::class);

        $audit1 = AuditLog::create([
            'actor_id' => $this->operatorA->id, 'action' => 'complaint.status_updated',
            'subject_type' => 'complaint', 'subject_id' => $complaint->id,
        ]);
        $audit2 = AuditLog::create([
            'actor_id' => $this->operatorA->id, 'action' => 'complaint.status_updated',
            'subject_type' => 'complaint', 'subject_id' => $complaint->id,
        ]);

        $service->emit($complaint, NotificationService::M8, $audit1->id, $this->operatorA);
        $service->emit($complaint, NotificationService::M8, $audit2->id, $this->operatorA);

        $this->assertSame(2, $this->countFor($this->citizen, NotificationService::M8));
    }

    public function test_database_unique_constraint_is_the_final_protection(): void
    {
        $complaint = $this->makeComplaint();

        $row = [
            'recipient_user_id' => $this->superAdmin->id,
            'complaint_id'      => $complaint->id,
            'type'              => NotificationService::M1,
            'title'             => 'x',
            'body'              => 'y',
            'source_event_id'   => 999,
            'created_at'        => now(),
            'updated_at'        => now(),
        ];

        DB::table('notifications')->insert($row);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('notifications')->insert($row);
    }

    // =========================================================================
    // Retention
    // =========================================================================

    public function test_complaint_purge_deletes_its_notifications(): void
    {
        $complaint = $this->makeComplaint(['submitted_at' => now()->subYears(6)]);

        AppNotification::create([
            'recipient_user_id' => $this->superAdmin->id,
            'complaint_id'      => $complaint->id,
            'type'              => NotificationService::M1,
            'title'             => 'Laporan Diajukan',
            'body'              => 'Laporan '.$complaint->reference_code.' telah diajukan.',
            'source_event_id'   => 1,
        ]);

        $this->assertDatabaseHas('notifications', ['complaint_id' => $complaint->id]);

        $this->artisan('complaints:purge-expired')->assertSuccessful();

        $this->assertDatabaseMissing('complaints', ['id' => $complaint->id]);
        $this->assertDatabaseMissing('notifications', ['complaint_id' => $complaint->id]);
    }

    public function test_notifications_of_non_expired_complaints_remain(): void
    {
        $complaint = $this->makeComplaint(['submitted_at' => now()->subYear()]);

        $notification = AppNotification::create([
            'recipient_user_id' => $this->superAdmin->id,
            'complaint_id'      => $complaint->id,
            'type'              => NotificationService::M1,
            'title'             => 'Laporan Diajukan',
            'body'              => 'Laporan '.$complaint->reference_code.' telah diajukan.',
            'source_event_id'   => 1,
        ]);

        $this->artisan('complaints:purge-expired')->assertSuccessful();

        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
    }

    public function test_purge_service_returns_cleanly_with_notifications_present(): void
    {
        $complaint = $this->makeComplaint(['submitted_at' => now()->subYears(6)]);

        AppNotification::create([
            'recipient_user_id' => $this->superAdmin->id,
            'complaint_id'      => $complaint->id,
            'type'              => NotificationService::M1,
            'title'             => 'Laporan Diajukan',
            'body'              => 'Laporan '.$complaint->reference_code.' telah diajukan.',
            'source_event_id'   => 1,
        ]);

        $result = app(ComplaintRetentionService::class)->purge($complaint);

        $this->assertTrue($result['deleted']);
        $this->assertDatabaseMissing('notifications', ['complaint_id' => $complaint->id]);
    }

    // =========================================================================
    // Privacy / payload
    // =========================================================================

    public function test_notification_payload_contains_no_internal_or_pii_data(): void
    {
        $complaint = $this->makePendingCompletion();

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.completion-validation.approve', $complaint))
            ->assertSessionHasNoErrors();

        foreach (AppNotification::all() as $n) {
            $haystack = $n->title . ' ' . $n->body . ' ' . json_encode($n->data);
            // No citizen PII, no internal narrative, no secrets.
            $this->assertStringNotContainsString($this->citizen->email, $haystack);
            $this->assertStringNotContainsString('NARASI_INTERNAL_OPERATOR_XYZ', $haystack);
            $this->assertStringNotContainsString('tracking_secret', $haystack);
        }
    }

    // =========================================================================
    // Helper: build a complaint awaiting completion validation
    // =========================================================================

    private function makePendingCompletion(?string $narrative = 'NARASI_INTERNAL_OPERATOR_XYZ'): Complaint
    {
        $complaint = $this->makeComplaint([
            'status'                  => ComplaintStatus::InProgress,
            'review_state'            => ComplaintReviewState::PendingCompletionValidation,
            'completion_submitted_at' => now(),
        ]);

        $noteId = null;
        if ($narrative !== null) {
            $note = ComplaintNote::create([
                'complaint_id' => $complaint->id,
                'author_id'    => $this->operatorA->id,
                'visibility'   => 'internal',
                'body'         => $narrative,
                'created_at'   => now(),
            ]);
            $noteId = $note->id;
        }

        AuditLog::create([
            'actor_id'     => $this->operatorA->id,
            'action'       => 'complaint.completion_submitted',
            'subject_type' => 'complaint',
            'subject_id'   => $complaint->id,
            'metadata'     => [
                'reference_code'      => $complaint->reference_code,
                'completion_note_id'  => $noteId,
            ],
        ]);

        return $complaint;
    }
}
