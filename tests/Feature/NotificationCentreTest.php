<?php

namespace Tests\Feature;

use App\Enums\ComplaintReviewState;
use App\Enums\ComplaintStatus;
use App\Models\AppNotification;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\DinasUnit;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SUPERBIE — PHASE N4 — In-app notification CENTRE.
 *
 * Read/unread state, owner-scoped authorization (IDOR), mark-one / mark-all /
 * delete, pagination, render-time target resolution (safe fallback), and the
 * server-rendered UI contract (bell badge + notification page).
 *
 * Recipient / milestone emission rules live in NotificationSystemTest.
 */
class NotificationCentreTest extends TestCase
{
    use RefreshDatabase;

    private User $citizen;
    private User $otherCitizen;
    private User $superAdmin;
    private User $operator;
    private User $admin;

    private DinasUnit $unit;
    private ComplaintCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin   = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->admin        = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->citizen      = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
        $this->otherCitizen = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->category = ComplaintCategory::create([
            'name' => 'Infrastruktur', 'slug' => 'infrastruktur', 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->unit = DinasUnit::create([
            'name' => 'Dinas PUPR', 'code' => 'PUPR', 'is_active' => true, 'sort_order' => 1,
        ]);
        $this->unit->categories()->attach($this->category->id);

        $this->operator = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unit->id,
        ]);
    }

    private static int $seq = 0;

    private function makeComplaint(array $overrides = []): Complaint
    {
        static::$seq++;

        return Complaint::create(array_merge([
            'reference_code' => 'LPW-NC-' . str_pad((string) static::$seq, 4, '0', STR_PAD_LEFT),
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $this->category->id,
            'dinas_unit_id'  => $this->unit->id,
            'title'          => 'Laporan uji notifikasi ' . static::$seq,
            'description'    => 'Deskripsi laporan uji notifikasi.',
            'status'         => ComplaintStatus::Submitted,
            'review_state'   => ComplaintReviewState::PendingInitialValidation,
            'submitted_at'   => now(),
        ], $overrides));
    }

    private function makeNotification(User $recipient, Complaint $complaint, array $overrides = []): AppNotification
    {
        static::$seq++;

        $createdAt = $overrides['created_at'] ?? null;
        unset($overrides['created_at']);

        $notification = AppNotification::create(array_merge([
            'recipient_user_id' => $recipient->id,
            'complaint_id'      => $complaint->id,
            'type'              => NotificationService::M1,
            'title'             => 'Laporan Diajukan',
            'body'              => 'Laporan ' . $complaint->reference_code . ' telah diajukan.',
            'source_event_id'   => static::$seq,
        ], $overrides));

        // `created_at` is not mass-assignable; set it explicitly when a test
        // needs deterministic ordering.
        if ($createdAt !== null) {
            $notification->forceFill(['created_at' => $createdAt])->save();
        }

        return $notification;
    }

    // =========================================================================
    // Page + pagination
    // =========================================================================

    public function test_index_lists_only_the_callers_own_notifications_newest_first(): void
    {
        $mine = $this->makeComplaint();
        $theirs = $this->makeComplaint(['reporter_id' => $this->otherCitizen->id]);

        $older = $this->makeNotification($this->citizen, $mine, ['created_at' => now()->subMinutes(5)]);
        $newer = $this->makeNotification($this->citizen, $mine, ['created_at' => now()]);
        $this->makeNotification($this->otherCitizen, $theirs, ['created_at' => now()]);

        $response = $this->actingAs($this->citizen)->get(route('notifications.index'))->assertOk();

        $response->assertViewHas('notifications', function ($paginator) use ($newer, $older) {
            $ids = collect($paginator->items())->pluck('id')->all();

            return $ids === [$newer->id, $older->id];
        });
    }

    public function test_index_is_paginated_twenty_per_page(): void
    {
        $complaint = $this->makeComplaint();
        for ($i = 0; $i < 25; $i++) {
            $this->makeNotification($this->citizen, $complaint);
        }

        $this->actingAs($this->citizen)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertViewHas('notifications', fn ($p) => $p->count() === 20 && $p->total() === 25);

        $this->actingAs($this->citizen)
            ->get(route('notifications.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('notifications', fn ($p) => $p->count() === 5);
    }

    public function test_index_shows_an_honest_empty_state_when_there_are_no_notifications(): void
    {
        $this->actingAs($this->citizen)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Belum ada notifikasi.');
    }

    public function test_admin_reaches_an_empty_notification_page(): void
    {
        // Admin receives no notifications, but the owner-scoped page still
        // renders an empty list (no error, no fabricated data).
        $this->actingAs($this->admin)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Belum ada notifikasi.');
    }

    public function test_index_requires_authentication(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }

    public function test_page_renders_an_open_link_and_a_delete_form_per_row(): void
    {
        $complaint = $this->makeComplaint();
        $notification = $this->makeNotification($this->citizen, $complaint);

        $html = $this->actingAs($this->citizen)->get(route('notifications.index'))->assertOk()->getContent();

        $this->assertStringContainsString(route('notifications.open', $notification->id), $html);
        $this->assertStringContainsString(route('notifications.destroy', $notification->id), $html);
        $this->assertStringContainsString('Hapus notifikasi', $html);
    }

    // =========================================================================
    // Read / unread
    // =========================================================================

    public function test_opening_a_notification_marks_it_read_and_redirects_to_the_citizen_target(): void
    {
        $complaint = $this->makeComplaint();
        $notification = $this->makeNotification($this->citizen, $complaint);

        $this->assertNull($notification->fresh()->read_at);

        $this->actingAs($this->citizen)
            ->get(route('notifications.open', $notification->id))
            ->assertRedirect(route('citizen.complaint.show', $complaint));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_read_marks_a_single_notification_read(): void
    {
        $complaint = $this->makeComplaint();
        $a = $this->makeNotification($this->citizen, $complaint);
        $b = $this->makeNotification($this->citizen, $complaint);

        $this->actingAs($this->citizen)
            ->patch(route('notifications.read', $a->id))
            ->assertRedirect();

        $this->assertNotNull($a->fresh()->read_at);
        $this->assertNull($b->fresh()->read_at);
    }

    public function test_mark_all_read_marks_every_own_notification_and_leaves_others_untouched(): void
    {
        $mine = $this->makeComplaint();
        $theirs = $this->makeComplaint(['reporter_id' => $this->otherCitizen->id]);

        $this->makeNotification($this->citizen, $mine);
        $this->makeNotification($this->citizen, $mine);
        $foreign = $this->makeNotification($this->otherCitizen, $theirs);

        $this->actingAs($this->citizen)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $this->citizen->appNotifications()->whereNull('read_at')->count());
        // Another user's notification is NEVER touched.
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_unread_count_is_owner_scoped(): void
    {
        $mine = $this->makeComplaint();
        $theirs = $this->makeComplaint(['reporter_id' => $this->otherCitizen->id]);

        $this->makeNotification($this->citizen, $mine);
        $this->makeNotification($this->otherCitizen, $theirs);

        $this->assertSame(1, $this->citizen->appNotifications()->whereNull('read_at')->count());
    }

    // =========================================================================
    // Delete (G11) + IDOR
    // =========================================================================

    public function test_user_may_delete_their_own_notification(): void
    {
        $complaint = $this->makeComplaint();
        $notification = $this->makeNotification($this->citizen, $complaint);

        $this->actingAs($this->citizen)
            ->delete(route('notifications.destroy', $notification->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
        // The complaint itself is NEVER deleted.
        $this->assertDatabaseHas('complaints', ['id' => $complaint->id]);
    }

    public function test_cannot_open_another_users_notification(): void
    {
        $complaint = $this->makeComplaint(['reporter_id' => $this->otherCitizen->id]);
        $foreign = $this->makeNotification($this->otherCitizen, $complaint);

        $this->actingAs($this->citizen)
            ->get(route('notifications.open', $foreign->id))
            ->assertNotFound();

        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_cannot_mark_another_users_notification_read(): void
    {
        $complaint = $this->makeComplaint(['reporter_id' => $this->otherCitizen->id]);
        $foreign = $this->makeNotification($this->otherCitizen, $complaint);

        $this->actingAs($this->citizen)
            ->patch(route('notifications.read', $foreign->id))
            ->assertNotFound();

        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_cannot_delete_another_users_notification(): void
    {
        $complaint = $this->makeComplaint(['reporter_id' => $this->otherCitizen->id]);
        $foreign = $this->makeNotification($this->otherCitizen, $complaint);

        $this->actingAs($this->citizen)
            ->delete(route('notifications.destroy', $foreign->id))
            ->assertNotFound();

        $this->assertDatabaseHas('notifications', ['id' => $foreign->id]);
    }

    public function test_unknown_notification_id_is_not_found(): void
    {
        $this->actingAs($this->citizen)
            ->get(route('notifications.open', 999999))
            ->assertNotFound();
    }

    // =========================================================================
    // Render-time target resolution + safe fallback
    // =========================================================================

    public function test_operator_target_respects_the_dinas_scope(): void
    {
        $inScope = $this->makeComplaint([
            'dinas_unit_id' => $this->unit->id,
            'review_state'  => ComplaintReviewState::ValidatedForOperator,
        ]);
        $inScopeNotification = $this->makeNotification($this->operator, $inScope);
        $this->assertSame(
            route('operator.complaint.show', $inScope),
            $inScopeNotification->targetUrl($this->operator)
        );

        // A notification whose complaint is NOT in the Operator's Dinas must
        // fall back to the safe listing — never a cross-unit detail URL.
        $otherUnit = DinasUnit::create(['name' => 'Dinas Lain', 'code' => 'LAIN', 'is_active' => true, 'sort_order' => 2]);
        $outOfScope = $this->makeComplaint([
            'dinas_unit_id' => $otherUnit->id,
            'review_state'  => ComplaintReviewState::ValidatedForOperator,
        ]);
        $outOfScopeNotification = $this->makeNotification($this->operator, $outOfScope);
        $this->assertSame(
            route('operator.complaint.index'),
            $outOfScopeNotification->targetUrl($this->operator)
        );
    }

    public function test_citizen_target_falls_back_to_history_for_a_foreign_complaint(): void
    {
        $foreign = $this->makeComplaint(['reporter_id' => $this->otherCitizen->id]);
        $notification = $this->makeNotification($this->citizen, $foreign);

        $this->assertSame(route('citizen.history'), $notification->targetUrl($this->citizen));
    }

    public function test_super_admin_target_prefers_the_actionable_queue_detail(): void
    {
        // M1 while still awaiting initial validation → validation queue detail.
        $awaiting = $this->makeComplaint([
            'review_state' => ComplaintReviewState::PendingInitialValidation,
        ]);
        $m1 = $this->makeNotification($this->superAdmin, $awaiting, ['type' => NotificationService::M1]);
        $this->assertSame(route('super-admin.validation.show', $awaiting), $m1->targetUrl($this->superAdmin));

        // M5 while pending completion validation → completion queue detail.
        $pendingCompletion = $this->makeComplaint([
            'review_state' => ComplaintReviewState::PendingCompletionValidation,
            'status'       => ComplaintStatus::InProgress,
        ]);
        $m5 = $this->makeNotification($this->superAdmin, $pendingCompletion, ['type' => NotificationService::M5]);
        $this->assertSame(
            route('super-admin.completion-validation.show', $pendingCompletion),
            $m5->targetUrl($this->superAdmin)
        );

        // Otherwise → the global monitoring detail.
        $settled = $this->makeComplaint([
            'review_state' => ComplaintReviewState::ValidatedForOperator,
            'status'       => ComplaintStatus::Resolved,
        ]);
        $m1b = $this->makeNotification($this->superAdmin, $settled, ['type' => NotificationService::M1]);
        $this->assertSame(route('super-admin.complaints.show', $settled), $m1b->targetUrl($this->superAdmin));
    }

    public function test_target_url_is_null_when_the_viewer_is_not_the_recipient(): void
    {
        $complaint = $this->makeComplaint();
        $notification = $this->makeNotification($this->citizen, $complaint);

        // A stale/forged row must never resolve a URL for a non-owner.
        $this->assertNull($notification->targetUrl($this->otherCitizen));
    }

    // =========================================================================
    // Bell UI contract
    // =========================================================================

    public function test_bell_badge_is_hidden_at_zero_and_capped_at_nine_plus(): void
    {
        $empty = $this->actingAs($this->citizen)->get(route('citizen.dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('notifikasi belum dibaca', $empty);

        $complaint = $this->makeComplaint();
        for ($i = 0; $i < 12; $i++) {
            $this->makeNotification($this->citizen, $complaint);
        }

        $html = $this->actingAs($this->citizen)->get(route('citizen.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('9+', $html);
        $this->assertStringContainsString('12 notifikasi belum dibaca', $html);
    }

    public function test_bell_dropdown_links_to_the_notification_centre(): void
    {
        $html = $this->actingAs($this->citizen)->get(route('citizen.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="Notifikasi"', $html);
        $this->assertStringContainsString('notifOpen', $html);
        $this->assertStringContainsString(route('notifications.index'), $html);
    }
}
