<?php

namespace Tests\Feature;

use App\Enums\ComplaintReviewState;
use App\Enums\ComplaintStatus;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\ComplaintRating;
use App\Models\DinasUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesLocationFixtures;
use Tests\TestCase;

/**
 * SUPERBIE — STAGE 3 — Official Super Admin reply to a citizen's written review.
 *
 * FROZEN behaviour under test:
 *   - Only a SUPER ADMIN may create the reply. Admin, Operator and Masyarakat are
 *     rejected server-side (route middleware + Form Request), even with a direct
 *     HTTP request.
 *   - The reply lives on `complaint_ratings.admin_response` (nullable TEXT) and is
 *     CREATE-ONCE + IMMUTABLE. It never touches `rating` / `review_text`, the
 *     complaint status, the KPI, or the rating analytics.
 *   - A reply is only possible when the rating exists, carries a NON-EMPTY written
 *     review, and has no reply yet.
 *   - The responder identity and the object relationship are resolved server-side
 *     — `rating_id` / `complaint_id` / `user_id` in the request are ignored.
 *   - The reply is rendered through Blade escaping on both surfaces; the reporter
 *     sees it read-only on their OWN complaint only.
 */
class ComplaintReviewResponseTest extends TestCase
{
    use RefreshDatabase;
    use CreatesLocationFixtures;

    private User $citizenOwner;
    private User $citizenOther;
    private User $operator;
    private User $admin;
    private User $superAdmin;

    private ComplaintCategory $category;
    private DinasUnit $unit;

    private int $kecamatanId;
    private int $kelurahanId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->citizenOwner = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
        $this->citizenOther = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
        $this->admin        = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->superAdmin   = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->category = ComplaintCategory::create([
            'name' => 'Infrastruktur Jalan', 'slug' => 'infrastruktur-jalan',
            'is_active' => true, 'sort_order' => 1,
        ]);

        $this->unit = DinasUnit::create([
            'name' => 'Dinas PUPR', 'code' => 'PUPR', 'is_active' => true, 'sort_order' => 1,
        ]);
        $this->unit->categories()->attach($this->category->id);

        $this->operator = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unit->id,
        ]);

        [$this->kecamatanId, $this->kelurahanId] = $this->makeLocationPair();
    }

    private static int $seq = 0;

    private function makeComplaint(array $overrides = [], ?User $owner = null): Complaint
    {
        static::$seq++;

        return Complaint::create(array_merge([
            'reference_code' => 'LPW-RES-' . str_pad((string) static::$seq, 4, '0', STR_PAD_LEFT),
            'reporter_id'    => ($owner ?? $this->citizenOwner)->id,
            'category_id'    => $this->category->id,
            'dinas_unit_id'  => $this->unit->id,
            'kecamatan_id'   => $this->kecamatanId,
            'kelurahan_id'   => $this->kelurahanId,
            'title'          => 'Laporan uji balasan ulasan ' . static::$seq,
            'description'    => 'Deskripsi laporan uji balasan ulasan.',
            'status'         => ComplaintStatus::Resolved,
            'review_state'   => ComplaintReviewState::ValidatedForOperator,
            'resolved_at'    => now(),
            'submitted_at'   => now(),
        ], $overrides));
    }

    private function makeRating(Complaint $complaint, array $overrides = []): ComplaintRating
    {
        return ComplaintRating::create(array_merge([
            'complaint_id' => $complaint->id,
            'user_id'      => $complaint->reporter_id,
            'rating'       => 4,
            'review_text'  => 'Pelayanan cukup baik, tetapi proses tindak lanjut perlu dipercepat.',
        ], $overrides));
    }

    private function url(Complaint $complaint): string
    {
        return route('super-admin.review-response.store', $complaint);
    }

    // =========================================================================
    // SCHEMA / MODEL
    // =========================================================================

    public function test_admin_response_column_is_nullable_text(): void
    {
        $this->assertTrue(Schema::hasTable('complaint_ratings'));

        $column = collect(Schema::getColumns('complaint_ratings'))->firstWhere('name', 'admin_response');

        $this->assertNotNull($column, 'admin_response column must exist');
        $this->assertSame('text', $column['type']);
        $this->assertTrue((bool) $column['nullable'], 'admin_response must be nullable');
    }

    public function test_has_admin_response_helper(): void
    {
        $complaint = $this->makeComplaint();
        $rating = $this->makeRating($complaint);

        $this->assertFalse($rating->hasAdminResponse());

        $rating->update(['admin_response' => 'Terima kasih atas masukan Anda.']);
        $this->assertTrue($rating->fresh()->hasAdminResponse());

        $rating->update(['admin_response' => '   ']);
        $this->assertFalse($rating->fresh()->hasAdminResponse(), 'whitespace-only reply counts as absent');
    }

    // =========================================================================
    // CREATION
    // =========================================================================

    public function test_super_admin_can_create_reply(): void
    {
        $complaint = $this->makeComplaint();
        $rating = $this->makeRating($complaint);

        $this->actingAs($this->superAdmin)
            ->post($this->url($complaint), [
                'admin_response' => 'Terima kasih atas masukan yang diberikan. Kami akan menggunakannya sebagai bahan evaluasi pelayanan.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('super-admin.complaints.show', $complaint));

        $fresh = $rating->fresh();
        $this->assertSame(
            'Terima kasih atas masukan yang diberikan. Kami akan menggunakannya sebagai bahan evaluasi pelayanan.',
            $fresh->admin_response
        );
    }

    public function test_reply_is_trimmed_before_storage(): void
    {
        $complaint = $this->makeComplaint();
        $rating = $this->makeRating($complaint);

        $this->actingAs($this->superAdmin)
            ->post($this->url($complaint), ['admin_response' => '   Balasan dengan spasi   '])
            ->assertSessionHasNoErrors();

        $this->assertSame('Balasan dengan spasi', $rating->fresh()->admin_response);
    }

    public function test_reply_does_not_change_rating_or_review_text(): void
    {
        $complaint = $this->makeComplaint();
        $rating = $this->makeRating($complaint, ['rating' => 3, 'review_text' => 'Ulasan asli pelapor.']);

        $this->actingAs($this->superAdmin)
            ->post($this->url($complaint), ['admin_response' => 'Balasan resmi.']);

        $fresh = $rating->fresh();
        $this->assertSame(3, $fresh->rating, 'rating must not change');
        $this->assertSame('Ulasan asli pelapor.', $fresh->review_text, 'review_text must not change');
        $this->assertSame('Balasan resmi.', $fresh->admin_response);
    }

    public function test_reply_does_not_change_complaint_status(): void
    {
        $complaint = $this->makeComplaint(['status' => ComplaintStatus::Resolved]);
        $this->makeRating($complaint);

        $this->actingAs($this->superAdmin)
            ->post($this->url($complaint), ['admin_response' => 'Balasan resmi.']);

        $this->assertSame(ComplaintStatus::Resolved, $complaint->fresh()->status);
    }

    public function test_reply_audit_records_identifiers_only_not_text(): void
    {
        $complaint = $this->makeComplaint();
        $rating = $this->makeRating($complaint);

        $this->actingAs($this->superAdmin)
            ->post($this->url($complaint), ['admin_response' => 'RAHASIA-BALASAN-TIDAK-BOLEH-MUNCUL']);

        $log = AuditLog::where('action', 'complaint.review_responded')->first();
        $this->assertNotNull($log);
        $this->assertSame('complaint', $log->subject_type);
        $this->assertSame($complaint->id, $log->subject_id);
        $this->assertSame($this->superAdmin->id, $log->actor_id);
        $this->assertSame($rating->id, $log->metadata['rating_id']);
        $this->assertTrue($log->metadata['has_response']);

        // Neither the review nor the reply text is copied into audit metadata.
        $encoded = json_encode($log->metadata);
        $this->assertStringNotContainsString('RAHASIA-BALASAN-TIDAK-BOLEH-MUNCUL', $encoded);
        $this->assertStringNotContainsString('Pelayanan cukup baik', $encoded);
    }

    // =========================================================================
    // GUARDS — no rating, no review, already answered
    // =========================================================================

    public function test_cannot_reply_when_complaint_has_no_rating(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->superAdmin)
            ->post($this->url($complaint), ['admin_response' => 'Balasan resmi.'])
            ->assertSessionHasErrors('admin_response');
    }

    public function test_cannot_reply_when_review_text_is_absent(): void
    {
        $complaint = $this->makeComplaint();
        $this->makeRating($complaint, ['review_text' => null]);

        $this->actingAs($this->superAdmin)
            ->post($this->url($complaint), ['admin_response' => 'Balasan resmi.'])
            ->assertSessionHasErrors('admin_response');

        $this->assertNull($complaint->fresh()->rating->admin_response);
    }

    public function test_cannot_reply_when_review_text_is_whitespace_only(): void
    {
        $complaint = $this->makeComplaint();
        $this->makeRating($complaint, ['review_text' => '   ']);

        $this->actingAs($this->superAdmin)
            ->post($this->url($complaint), ['admin_response' => 'Balasan resmi.'])
            ->assertSessionHasErrors('admin_response');
    }

    public function test_existing_reply_cannot_be_overwritten(): void
    {
        $complaint = $this->makeComplaint();
        $rating = $this->makeRating($complaint, ['admin_response' => 'Balasan pertama.']);

        $this->actingAs($this->superAdmin)
            ->post($this->url($complaint), ['admin_response' => 'Percobaan menimpa.'])
            ->assertSessionHasErrors('admin_response');

        $this->assertSame('Balasan pertama.', $rating->fresh()->admin_response);
    }

    public function test_second_request_does_not_overwrite_the_first_reply(): void
    {
        $complaint = $this->makeComplaint();
        $rating = $this->makeRating($complaint);

        $this->actingAs($this->superAdmin)
            ->post($this->url($complaint), ['admin_response' => 'Balasan pertama.'])
            ->assertSessionHasNoErrors();

        // A second request (even from another Super Admin) must NOT overwrite.
        $second = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($second)
            ->post($this->url($complaint), ['admin_response' => 'Balasan kedua.'])
            ->assertSessionHasErrors('admin_response');

        $this->assertSame('Balasan pertama.', $rating->fresh()->admin_response);
        // Only ONE response audit exists (the second attempt wrote none).
        $this->assertSame(1, AuditLog::where('action', 'complaint.review_responded')->count());
    }

    // =========================================================================
    // VALIDATION
    // =========================================================================

    public function test_empty_reply_is_rejected(): void
    {
        $complaint = $this->makeComplaint();
        $this->makeRating($complaint);

        $this->actingAs($this->superAdmin)
            ->post($this->url($complaint), ['admin_response' => ''])
            ->assertSessionHasErrors('admin_response');
    }

    public function test_whitespace_only_reply_is_rejected(): void
    {
        $complaint = $this->makeComplaint();
        $this->makeRating($complaint);

        $this->actingAs($this->superAdmin)
            ->post($this->url($complaint), ['admin_response' => '     '])
            ->assertSessionHasErrors('admin_response');
    }

    public function test_reply_longer_than_5000_is_rejected(): void
    {
        $complaint = $this->makeComplaint();
        $this->makeRating($complaint);

        $this->actingAs($this->superAdmin)
            ->post($this->url($complaint), ['admin_response' => str_repeat('a', 5001)])
            ->assertSessionHasErrors('admin_response');

        $this->assertNull($complaint->fresh()->rating->admin_response);
    }

    public function test_reply_exactly_5000_is_accepted(): void
    {
        $complaint = $this->makeComplaint();
        $rating = $this->makeRating($complaint);

        $this->actingAs($this->superAdmin)
            ->post($this->url($complaint), ['admin_response' => str_repeat('a', 5000)])
            ->assertSessionHasNoErrors();

        $this->assertSame(5000, mb_strlen($rating->fresh()->admin_response));
    }

    // =========================================================================
    // AUTHORIZATION
    // =========================================================================

    public function test_guest_cannot_create_reply(): void
    {
        $complaint = $this->makeComplaint();
        $this->makeRating($complaint);

        $this->post($this->url($complaint), ['admin_response' => 'Balasan.'])
            ->assertRedirect(route('login'));

        $this->assertNull($complaint->fresh()->rating->admin_response);
    }

    public function test_citizen_cannot_create_reply(): void
    {
        $complaint = $this->makeComplaint();
        $this->makeRating($complaint);

        $this->actingAs($this->citizenOwner)
            ->post($this->url($complaint), ['admin_response' => 'Balasan.'])
            ->assertForbidden();

        $this->assertNull($complaint->fresh()->rating->admin_response);
    }

    public function test_operator_cannot_create_reply(): void
    {
        $complaint = $this->makeComplaint();
        $this->makeRating($complaint);

        $this->actingAs($this->operator)
            ->post($this->url($complaint), ['admin_response' => 'Balasan.'])
            ->assertForbidden();

        $this->assertNull($complaint->fresh()->rating->admin_response);
    }

    public function test_admin_cannot_create_reply(): void
    {
        $complaint = $this->makeComplaint();
        $this->makeRating($complaint);

        $this->actingAs($this->admin)
            ->post($this->url($complaint), ['admin_response' => 'Balasan.'])
            ->assertForbidden();

        $this->assertNull($complaint->fresh()->rating->admin_response);
    }

    public function test_invalid_complaint_id_is_safe(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.review-response.store', 999999), ['admin_response' => 'Balasan.'])
            ->assertNotFound();
    }

    public function test_responder_and_relationship_cannot_be_forged(): void
    {
        $complaint = $this->makeComplaint();
        $rating = $this->makeRating($complaint);

        $otherComplaint = $this->makeComplaint([], $this->citizenOther);
        $otherRating = $this->makeRating($otherComplaint);

        $this->actingAs($this->superAdmin)->post($this->url($complaint), [
            'admin_response' => 'Balasan resmi.',
            // Malicious extras — must be ignored.
            'rating_id'    => $otherRating->id,
            'complaint_id' => $otherComplaint->id,
            'user_id'      => $this->citizenOther->id,
        ]);

        // The reply is written to the ROUTE complaint's rating, not the forged ids.
        $this->assertSame('Balasan resmi.', $rating->fresh()->admin_response);
        $this->assertNull($otherRating->fresh()->admin_response, 'the forged rating must be untouched');
    }

    // =========================================================================
    // VISIBILITY / XSS
    // =========================================================================

    public function test_citizen_owner_sees_official_reply(): void
    {
        $complaint = $this->makeComplaint();
        $this->makeRating($complaint, ['admin_response' => 'BALASAN-RESMI-TERLIHAT']);

        $this->actingAs($this->citizenOwner)
            ->get(route('citizen.complaint.show', $complaint))
            ->assertOk()
            ->assertSee('Balasan Resmi Super Admin')
            ->assertSee('BALASAN-RESMI-TERLIHAT');
    }

    public function test_citizen_cannot_see_another_users_reply_via_id_manipulation(): void
    {
        $foreign = $this->makeComplaint([], $this->citizenOther);
        $this->makeRating($foreign, ['admin_response' => 'BALASAN-MILIK-ORANG-LAIN']);

        $this->actingAs($this->citizenOwner)
            ->get(route('citizen.complaint.show', $foreign))
            ->assertForbidden()
            ->assertDontSee('BALASAN-MILIK-ORANG-LAIN');
    }

    public function test_no_fake_reply_placeholder_when_absent(): void
    {
        $complaint = $this->makeComplaint();
        $this->makeRating($complaint, ['admin_response' => null]);

        $this->actingAs($this->citizenOwner)
            ->get(route('citizen.complaint.show', $complaint))
            ->assertOk()
            ->assertDontSee('Balasan Resmi Super Admin');
    }

    public function test_reply_is_html_escaped_on_citizen_detail(): void
    {
        $payload = '<script>alert("xss")</script>';
        $complaint = $this->makeComplaint();
        $this->makeRating($complaint, ['admin_response' => $payload]);

        $response = $this->actingAs($this->citizenOwner)
            ->get(route('citizen.complaint.show', $complaint));
        $response->assertOk();
        $response->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false);
        $response->assertDontSee('<script>alert("xss")</script>', false);
    }

    public function test_super_admin_sees_form_before_and_readonly_after(): void
    {
        $complaint = $this->makeComplaint();
        $this->makeRating($complaint);

        // Before a reply: the form is offered.
        $before = $this->actingAs($this->superAdmin)->get(route('super-admin.complaints.show', $complaint));
        $before->assertOk();
        $before->assertSee('Kirim Balasan Resmi');
        $before->assertSee($this->url($complaint), false);

        // After a reply: read-only, no form.
        $this->actingAs($this->superAdmin)->post($this->url($complaint), ['admin_response' => 'Balasan resmi tersimpan.']);

        $after = $this->actingAs($this->superAdmin)->get(route('super-admin.complaints.show', $complaint));
        $after->assertOk();
        $after->assertSee('Balasan Resmi Super Admin');
        $after->assertSee('Balasan resmi tersimpan.');
        $after->assertDontSee('Kirim Balasan Resmi');
    }

    public function test_super_admin_does_not_see_form_when_no_review_text(): void
    {
        $complaint = $this->makeComplaint();
        $this->makeRating($complaint, ['review_text' => null]);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.complaints.show', $complaint))
            ->assertOk()
            ->assertDontSee('Kirim Balasan Resmi');
    }

    public function test_admin_and_operator_see_no_reply_control(): void
    {
        $complaint = $this->makeComplaint();
        $this->makeRating($complaint);

        // Admin has no per-complaint detail route at all.
        $this->actingAs($this->admin)
            ->get('/admin/laporan/' . $complaint->id)
            ->assertNotFound();

        // Operator sees the review read-only, with no reply control.
        $this->actingAs($this->operator)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk()
            ->assertDontSee('Kirim Balasan Resmi')
            ->assertDontSee('Balasan Resmi Super Admin');
    }

    // =========================================================================
    // CROSS-FEATURE INTEGRITY
    // =========================================================================

    public function test_rating_analytics_unchanged_by_reply(): void
    {
        $complaint = $this->makeComplaint();
        $this->makeRating($complaint);

        $this->actingAs($this->superAdmin)
            ->post($this->url($complaint), ['admin_response' => 'JANGAN-MUNCUL-DI-ANALITIK']);

        // Aggregate analytics surface never renders free-text content.
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('JANGAN-MUNCUL-DI-ANALITIK');
    }

    public function test_reply_does_not_create_rating_or_alter_lifecycle(): void
    {
        $complaint = $this->makeComplaint(['status' => ComplaintStatus::Resolved]);
        $rating = $this->makeRating($complaint);

        $beforeCount = ComplaintRating::count();

        $this->actingAs($this->superAdmin)
            ->post($this->url($complaint), ['admin_response' => 'Balasan resmi.']);

        $this->assertSame($beforeCount, ComplaintRating::count(), 'no new rating row is created');
        $this->assertSame(1, ComplaintRating::where('complaint_id', $complaint->id)->count());
        $this->assertSame(ComplaintStatus::Resolved, $complaint->fresh()->status);
        $this->assertSame($rating->id, $complaint->fresh()->rating->id);
    }

    public function test_retention_purge_still_removes_reply_with_rating(): void
    {
        $complaint = $this->makeComplaint();
        $this->makeRating($complaint, ['admin_response' => 'Balasan resmi.']);

        // The reply rides on the rating row, which is child data of the complaint.
        $complaint->rating()->delete();

        $this->assertSame(0, ComplaintRating::where('complaint_id', $complaint->id)->count());
    }

    public function test_migration_row_is_recorded(): void
    {
        $this->assertDatabaseHas('migrations', [
            'migration' => '2025_01_11_100021_add_admin_response_to_complaint_ratings_table',
        ]);
    }

    /**
     * Reversibility + ISOLATION proof: run the ACTUAL migration's down() then
     * up() and assert that ONLY `admin_response` disappears / reappears — the
     * pre-existing `review_text` / `rating` columns, the `complaint_ratings`
     * table itself, and the `complaints` table are untouched. The migration is
     * restored to `up` within the same test, so the net schema is unchanged for
     * other tests.
     */
    public function test_migration_rollback_only_reverts_admin_response_column(): void
    {
        $migration = require database_path('migrations/2025_01_11_100021_add_admin_response_to_complaint_ratings_table.php');

        // Sanity: the column + its siblings exist before rollback.
        $this->assertTrue(Schema::hasColumn('complaint_ratings', 'admin_response'));
        $this->assertTrue(Schema::hasColumn('complaint_ratings', 'review_text'));
        $this->assertTrue(Schema::hasColumn('complaint_ratings', 'rating'));

        $migration->down();

        // ONLY the new column is gone.
        $this->assertFalse(Schema::hasColumn('complaint_ratings', 'admin_response'));
        $this->assertTrue(Schema::hasColumn('complaint_ratings', 'review_text'), 'review_text must survive the rollback');
        $this->assertTrue(Schema::hasColumn('complaint_ratings', 'rating'), 'rating must survive the rollback');

        // No other table was dropped/altered by the rollback.
        $this->assertTrue(Schema::hasTable('complaint_ratings'));
        $this->assertTrue(Schema::hasTable('complaints'));
        $this->assertTrue(Schema::hasTable('users'));

        $migration->up();

        $this->assertTrue(Schema::hasColumn('complaint_ratings', 'admin_response'));
        $this->assertTrue(Schema::hasColumn('complaint_ratings', 'review_text'));
    }

    public function test_role_definition_is_unchanged(): void
    {
        // STAGE 3 must not introduce a role. The source of truth is the
        // `UserRole` enum — assert it still has EXACTLY the four fixed roles and
        // that the retired `petugas` role is absent.
        $this->assertSame(
            ['masyarakat', 'operator', 'admin', 'super_admin'],
            \App\Enums\UserRole::values(),
        );
        $this->assertCount(4, \App\Enums\UserRole::cases());
        $this->assertNotContains('petugas', \App\Enums\UserRole::values());
    }
}
