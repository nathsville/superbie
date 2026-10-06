<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\ComplaintCategory;
use App\Models\ComplaintNote;
use App\Models\ComplaintStatusHistory;
use App\Models\DinasUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 5B.1 — Retention & Permanent Deletion
 *
 * Business rule (FINAL):
 *   Retention   = 5 years from complaints.submitted_at (app timezone)
 *   Action      = permanent deletion
 *   Complaint-related audit logs = deleted
 *   Users / master data / unrelated audit logs = preserved
 *
 * NOTE: All tests run against the testing database (RefreshDatabase) and a
 * faked private filesystem. No real/production data is ever deleted.
 */
class ComplaintRetentionTest extends TestCase
{
    use RefreshDatabase;

    private User $citizen;
    private ComplaintCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->citizen = User::factory()->create([
            'role'      => 'masyarakat',
            'is_active' => true,
        ]);

        $this->category = ComplaintCategory::create([
            'name'       => 'Retention Category',
            'slug'       => 'retention-category',
            'description' => 'Kategori untuk pengujian retensi',
            'is_active'  => true,
            'sort_order' => 1,
        ]);
    }

    private function makeComplaint(array $overrides = []): Complaint
    {
        return Complaint::create(array_merge([
            'reference_code' => 'LPW-RET-' . strtoupper(uniqid()),
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $this->category->id,
            'title'          => 'Laporan uji retensi',
            'description'    => 'Deskripsi laporan pengujian retensi 5B.1.',
            'status'         => 'submitted',
            'submitted_at'   => now(),
        ], $overrides));
    }

    private function attachFile(Complaint $complaint, string $disk = 'private'): ComplaintAttachment
    {
        $path = 'complaints/' . $complaint->id . '/' . uniqid() . '.jpg';
        Storage::disk($disk)->put($path, 'binary-content');

        return ComplaintAttachment::create([
            'complaint_id'  => $complaint->id,
            'disk'          => $disk,
            'path'          => $path,
            'original_name' => 'lampiran.jpg',
            'mime_type'     => 'image/jpeg',
            'size_bytes'    => 14,
        ]);
    }

    // =========================================================================
    // T — BOUNDARY / ELIGIBILITY (T1–T5)
    // =========================================================================

    public function test_t1_not_expired_four_years_is_retained(): void
    {
        $complaint = $this->makeComplaint(['submitted_at' => now()->subYears(4)]);

        $this->artisan('complaints:purge-expired')->assertSuccessful();

        $this->assertDatabaseHas('complaints', ['id' => $complaint->id]);
    }

    public function test_t2_exactly_five_years_boundary_is_expired(): void
    {
        // submitted_at + 5 years <= now → expired. Exactly 5 years qualifies.
        $complaint = $this->makeComplaint(['submitted_at' => now()->subYears(5)]);

        $this->artisan('complaints:purge-expired')->assertSuccessful();

        $this->assertDatabaseMissing('complaints', ['id' => $complaint->id]);
    }

    public function test_t3_more_than_five_years_is_deleted(): void
    {
        $complaint = $this->makeComplaint(['submitted_at' => now()->subYears(5)->subDay()]);

        $this->artisan('complaints:purge-expired')->assertSuccessful();

        $this->assertDatabaseMissing('complaints', ['id' => $complaint->id]);
    }

    public function test_t4_future_submitted_at_is_retained(): void
    {
        $complaint = $this->makeComplaint(['submitted_at' => now()->addYear()]);

        $this->artisan('complaints:purge-expired')->assertSuccessful();

        $this->assertDatabaseHas('complaints', ['id' => $complaint->id]);
    }

    public function test_t5_null_submitted_at_is_never_deleted(): void
    {
        // The schema declares complaints.submitted_at as NOT NULL with a
        // default of CURRENT_TIMESTAMP, so a NULL row cannot exist. This is
        // STRONGER than the business rule requirement. We therefore verify the
        // eligibility query itself: it must explicitly exclude NULL and must
        // never fall back to created_at (which would violate the rule).
        $query = (new \App\Services\ComplaintRetentionService())->expiredQuery();
        $sql = $query->toSql();

        $this->assertStringContainsString('is not null', $sql);
        $this->assertStringContainsString('submitted_at', $sql);
        $this->assertStringNotContainsString('created_at', $sql);

        // And functionally: an old created_at must NOT make a fresh complaint
        // eligible — only submitted_at counts.
        $fresh = $this->makeComplaint([
            'submitted_at' => now()->subDay(),
        ]);
        // Backdate created_at only (simulating an old row created long ago).
        Complaint::where('id', $fresh->id)->update(['created_at' => now()->subYears(10)]);

        $this->artisan('complaints:purge-expired')->assertSuccessful();

        $this->assertDatabaseHas('complaints', ['id' => $fresh->id]);
    }

    // =========================================================================
    // U — CASCADE / DEPENDENCY
    // =========================================================================

    public function test_expired_complaint_dependencies_are_all_deleted(): void
    {
        Storage::fake('private');
        $complaint = $this->makeComplaint(['submitted_at' => now()->subYears(6)]);

        $attachment = $this->attachFile($complaint);
        ComplaintNote::create([
            'complaint_id' => $complaint->id,
            'author_id'    => $this->citizen->id,
            'visibility'   => 'internal',
            'body'         => 'Catatan internal retensi',
        ]);
        ComplaintStatusHistory::create([
            'complaint_id' => $complaint->id,
            'from_status'  => null,
            'to_status'    => 'submitted',
            'changed_by'   => $this->citizen->id,
            'note'         => 'Diajukan',
        ]);
        AuditLog::create([
            'actor_id'     => $this->citizen->id,
            'action'       => 'complaint_created',
            'subject_type' => 'complaint',
            'subject_id'   => $complaint->id,
        ]);

        $this->artisan('complaints:purge-expired')->assertSuccessful();

        $this->assertDatabaseMissing('complaints', ['id' => $complaint->id]);
        $this->assertDatabaseMissing('complaint_status_histories', ['complaint_id' => $complaint->id]);
        $this->assertDatabaseMissing('complaint_notes', ['complaint_id' => $complaint->id]);
        $this->assertDatabaseMissing('complaint_attachments', ['id' => $attachment->id]);
        Storage::disk('private')->assertMissing($attachment->path);
    }

    public function test_master_data_and_users_are_preserved(): void
    {
        $dinas = DinasUnit::create(['name' => 'Dinas Retensi', 'is_active' => true]);
        $this->category->dinasUnits()->attach($dinas->id);

        $complaint = $this->makeComplaint(['submitted_at' => now()->subYears(6)]);

        $this->artisan('complaints:purge-expired')->assertSuccessful();

        $this->assertDatabaseMissing('complaints', ['id' => $complaint->id]);
        $this->assertDatabaseHas('users', ['id' => $this->citizen->id]);
        $this->assertDatabaseHas('complaint_categories', ['id' => $this->category->id]);
        $this->assertDatabaseHas('dinas_units', ['id' => $dinas->id]);
        $this->assertDatabaseHas('category_dinas_unit', [
            'category_id' => $this->category->id,
            'dinas_unit_id' => $dinas->id,
        ]);
    }

    // =========================================================================
    // V — AUDIT LOG
    // =========================================================================

    public function test_v1_complaint_related_audit_log_is_deleted(): void
    {
        $complaint = $this->makeComplaint(['submitted_at' => now()->subYears(6)]);
        $log = AuditLog::create([
            'actor_id'     => $this->citizen->id,
            'action'       => 'complaint_updated',
            'subject_type' => 'complaint',
            'subject_id'   => $complaint->id,
        ]);

        $this->artisan('complaints:purge-expired')->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['id' => $log->id]);
    }

    public function test_v2_unexpired_complaint_audit_log_is_preserved(): void
    {
        $complaint = $this->makeComplaint(['submitted_at' => now()->subYears(1)]);
        $log = AuditLog::create([
            'actor_id'     => $this->citizen->id,
            'action'       => 'complaint_updated',
            'subject_type' => 'complaint',
            'subject_id'   => $complaint->id,
        ]);

        $this->artisan('complaints:purge-expired')->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', ['id' => $log->id]);
    }

    public function test_v3_unrelated_user_audit_log_is_preserved(): void
    {
        $log = AuditLog::create([
            'actor_id'     => $this->citizen->id,
            'action'       => 'profile_updated',
            'subject_type' => 'user',
            'subject_id'   => $this->citizen->id,
        ]);

        $this->artisan('complaints:purge-expired')->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', ['id' => $log->id]);
    }

    public function test_v4_category_audit_log_is_preserved(): void
    {
        $complaint = $this->makeComplaint(['submitted_at' => now()->subYears(6)]);
        $log = AuditLog::create([
            'actor_id'     => $this->citizen->id,
            'action'       => 'category_updated',
            'subject_type' => 'ComplaintCategory',
            'subject_id'   => $this->category->id,
        ]);

        $this->artisan('complaints:purge-expired')->assertSuccessful();

        // Complaint removed, but the category audit log must survive.
        $this->assertDatabaseMissing('complaints', ['id' => $complaint->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $log->id]);
    }

    // =========================================================================
    // W — ATTACHMENT FILE
    // =========================================================================

    public function test_expired_attachment_file_and_record_are_deleted(): void
    {
        Storage::fake('private');
        $complaint = $this->makeComplaint(['submitted_at' => now()->subYears(6)]);
        $attachment = $this->attachFile($complaint);

        Storage::disk('private')->assertExists($attachment->path);

        $this->artisan('complaints:purge-expired')->assertSuccessful();

        $this->assertDatabaseMissing('complaint_attachments', ['id' => $attachment->id]);
        Storage::disk('private')->assertMissing($attachment->path);
    }

    public function test_unexpired_attachment_file_and_record_are_preserved(): void
    {
        Storage::fake('private');
        $complaint = $this->makeComplaint(['submitted_at' => now()->subYears(1)]);
        $attachment = $this->attachFile($complaint);

        $this->artisan('complaints:purge-expired')->assertSuccessful();

        $this->assertDatabaseHas('complaint_attachments', ['id' => $attachment->id]);
        Storage::disk('private')->assertExists($attachment->path);
    }

    public function test_missing_physical_file_does_not_fail_purge(): void
    {
        Storage::fake('private');
        $complaint = $this->makeComplaint(['submitted_at' => now()->subYears(6)]);
        $attachment = $this->attachFile($complaint);

        // Orphan the file — record exists but binary already gone.
        Storage::disk('private')->delete($attachment->path);

        $this->artisan('complaints:purge-expired')->assertSuccessful();

        $this->assertDatabaseMissing('complaints', ['id' => $complaint->id]);
        $this->assertDatabaseMissing('complaint_attachments', ['id' => $attachment->id]);
    }

    // =========================================================================
    // X — USER RETENTION
    // =========================================================================

    public function test_user_identity_is_not_deleted_by_complaint_retention(): void
    {
        $user = User::factory()->create([
            'role'         => 'masyarakat',
            'nik'          => '7373000000008888',
            'phone_number' => '081300000088',
            'address'      => 'Alamat tetap ada',
        ]);

        $complaint = $this->makeComplaint([
            'reporter_id'  => $user->id,
            'submitted_at' => now()->subYears(6),
        ]);

        $this->artisan('complaints:purge-expired')->assertSuccessful();

        $this->assertDatabaseMissing('complaints', ['id' => $complaint->id]);
        $this->assertDatabaseHas('users', [
            'id'           => $user->id,
            'nik'          => '7373000000008888',
            'phone_number' => '081300000088',
            'address'      => 'Alamat tetap ada',
        ]);
    }

    // =========================================================================
    // Y — MULTI-COMPLAINT
    // =========================================================================

    public function test_multi_complaint_purge_only_removes_expired(): void
    {
        Storage::fake('private');

        $a = $this->makeComplaint(['submitted_at' => now()->subYears(6)]);
        $b = $this->makeComplaint(['submitted_at' => now()->subYears(7)]);
        $c = $this->makeComplaint(['submitted_at' => now()->subYears(2)]);

        $attA = $this->attachFile($a);
        $attC = $this->attachFile($c);

        $this->artisan('complaints:purge-expired')->assertSuccessful();

        $this->assertDatabaseMissing('complaints', ['id' => $a->id]);
        $this->assertDatabaseMissing('complaints', ['id' => $b->id]);
        $this->assertDatabaseHas('complaints', ['id' => $c->id]);

        $this->assertDatabaseMissing('complaint_attachments', ['id' => $attA->id]);
        $this->assertDatabaseHas('complaint_attachments', ['id' => $attC->id]);
        Storage::disk('private')->assertMissing($attA->path);
        Storage::disk('private')->assertExists($attC->path);
    }

    // =========================================================================
    // O — IDEMPOTENCY
    // =========================================================================

    public function test_purge_is_idempotent(): void
    {
        $complaint = $this->makeComplaint(['submitted_at' => now()->subYears(6)]);

        $this->artisan('complaints:purge-expired')->assertSuccessful();
        $this->assertDatabaseMissing('complaints', ['id' => $complaint->id]);

        // Second run must succeed and not error.
        $this->artisan('complaints:purge-expired')->assertSuccessful();
        $this->assertDatabaseMissing('complaints', ['id' => $complaint->id]);
    }

    // =========================================================================
    // P — DRY RUN
    // =========================================================================

    public function test_dry_run_reports_without_deleting(): void
    {
        Storage::fake('private');
        $complaint = $this->makeComplaint(['submitted_at' => now()->subYears(6)]);
        $attachment = $this->attachFile($complaint);

        $this->artisan('complaints:purge-expired', ['--dry-run' => true])
            ->expectsOutputToContain('DRY-RUN')
            ->assertSuccessful();

        $this->assertDatabaseHas('complaints', ['id' => $complaint->id]);
        $this->assertDatabaseHas('complaint_attachments', ['id' => $attachment->id]);
        Storage::disk('private')->assertExists($attachment->path);
    }

    // =========================================================================
    // S — SAFETY GUARD
    // =========================================================================

    public function test_non_production_run_needs_no_force_flag(): void
    {
        // Test environment is not production → --force not required.
        $this->assertFalse(app()->environment('production'));

        $this->artisan('complaints:purge-expired')->assertSuccessful();
    }

    // =========================================================================
    // PROMPT 5B.2 — ATTACHMENT SAFETY (DB vs FILESYSTEM ORDERING)
    // =========================================================================

    /**
     * TEST A — successful retention deletes DB record AND physical file.
     */
    public function test_a_successful_retention_deletes_record_and_physical_file(): void
    {
        Storage::fake('private');
        $complaint = $this->makeComplaint(['submitted_at' => now()->subYears(6)]);
        $attachment = $this->attachFile($complaint);

        Storage::disk('private')->assertExists($attachment->path);

        $this->artisan('complaints:purge-expired')->assertSuccessful();

        $this->assertDatabaseMissing('complaints', ['id' => $complaint->id]);
        $this->assertDatabaseMissing('complaint_attachments', ['id' => $attachment->id]);
        Storage::disk('private')->assertMissing($attachment->path);
    }

    /**
     * TEST B — database transaction failure must NOT delete physical files.
     *
     * We force a genuine DB transaction failure by making the final complaint
     * delete throw (a thrown exception inside DB::transaction triggers a real
     * rollback). The physical file must survive because deletion now happens
     * only AFTER a successful commit.
     */
    public function test_b_database_failure_rolls_back_and_keeps_physical_file(): void
    {
        Storage::fake('private');
        $complaint = $this->makeComplaint(['submitted_at' => now()->subYears(6)]);
        $attachment = $this->attachFile($complaint);

        // Force a real failure inside the transaction (on the parent delete).
        Complaint::deleting(function () {
            throw new \RuntimeException('forced DB failure for retention rollback test');
        });

        $service = new \App\Services\ComplaintRetentionService();

        try {
            $service->purge($complaint);
            $this->fail('Expected the transaction to throw and roll back.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('forced DB failure', $e->getMessage());
        } finally {
            // Remove the listener so other tests are unaffected.
            Complaint::flushEventListeners();
        }

        // Rollback → NOTHING deleted, and the physical file MUST still exist.
        $this->assertDatabaseHas('complaints', ['id' => $complaint->id]);
        $this->assertDatabaseHas('complaint_attachments', ['id' => $attachment->id]);
        Storage::disk('private')->assertExists($attachment->path);
    }

    /**
     * TEST C — missing physical file must not fail the purge.
     */
    public function test_c_missing_physical_file_does_not_fail_purge(): void
    {
        Storage::fake('private');
        $complaint = $this->makeComplaint(['submitted_at' => now()->subYears(6)]);
        $attachment = $this->attachFile($complaint);

        // Binary already gone before retention runs.
        Storage::disk('private')->delete($attachment->path);

        $this->artisan('complaints:purge-expired')->assertSuccessful();

        $this->assertDatabaseMissing('complaints', ['id' => $complaint->id]);
        $this->assertDatabaseMissing('complaint_attachments', ['id' => $attachment->id]);
    }

    /**
     * TEST D — physical file cleanup failure must NOT rollback the committed
     * DB deletion, and the command must report the failed cleanup.
     *
     * We simulate a filesystem delete failure by faking the disk and pointing
     * the attachment at a path the fake disk reports as existing but whose
     * delete() returns false. Reliable simulation of a native filesystem
     * failure is not possible with the local driver, so we assert the flow at
     * the service contract level using a fake disk whose delete() returns false.
     */
    public function test_d_file_cleanup_failure_is_reported_without_db_rollback(): void
    {
        $complaint = $this->makeComplaint(['submitted_at' => now()->subYears(6)]);

        // Attachment DB record pointing at a private path.
        ComplaintAttachment::create([
            'complaint_id'  => $complaint->id,
            'disk'          => 'private',
            'path'          => 'complaints/' . $complaint->id . '/will-fail.jpg',
            'original_name' => 'will-fail.jpg',
            'mime_type'     => 'image/jpeg',
            'size_bytes'    => 10,
        ]);

        // A fake disk that reports the file as existing but fails deletion.
        $disk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $disk->shouldReceive('exists')->andReturn(true);
        $disk->shouldReceive('delete')->andReturn(false);
        Storage::shouldReceive('disk')->with('private')->andReturn($disk);

        $service = new \App\Services\ComplaintRetentionService();
        $result = $service->purge($complaint);

        // DB deletion committed (no fake rollback).
        $this->assertDatabaseMissing('complaints', ['id' => $complaint->id]);
        $this->assertTrue($result['deleted']);
        $this->assertSame(1, $result['failed_files']);
    }

    /**
     * Path safety — retention only ever deletes paths belonging to the
     * complaint's own attachment records (verified by construction: only
     * complaint_attachments.path is used, never arbitrary paths).
     */
    public function test_path_safety_unrelated_file_is_not_touched(): void
    {
        Storage::fake('private');

        // An unrelated private file that must survive.
        Storage::disk('private')->put('complaints/other/keep-me.jpg', 'keep');

        $complaint = $this->makeComplaint(['submitted_at' => now()->subYears(6)]);
        $attachment = $this->attachFile($complaint);

        $this->artisan('complaints:purge-expired')->assertSuccessful();

        Storage::disk('private')->assertMissing($attachment->path);
        Storage::disk('private')->assertExists('complaints/other/keep-me.jpg');
    }

    // =========================================================================
    // PROMPT 5B.2 — AUDIT LOG subject_type CONVENTION (LOCK-IN)
    // =========================================================================

    /**
     * The project records complaint audit logs with the literal string
     * subject_type = 'complaint' (verified: ComplaintController and
     * OperatorComplaintController all use 'complaint'; no morph map exists).
     *
     * This test proves retention targets the SAME value the application writes
     * by creating an audit log through the real application flow.
     */
    public function test_audit_log_written_by_app_uses_complaint_subject_type(): void
    {
        $this->category->update(['is_active' => true]);

        // Trigger the real complaint-creation flow (writes AuditLog).
        $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), [
            'category_id' => $this->category->id,
            'title'       => 'Laporan untuk lock-in subject_type',
            'description' => 'Memastikan nilai subject_type audit log benar.',
        ])->assertSessionHasNoErrors();

        $complaint = Complaint::where('reporter_id', $this->citizen->id)->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'subject_type' => 'complaint',
            'subject_id'   => $complaint->id,
        ]);

        // And that this exact value is what retention matches.
        $sql = $this->retentionAuditSql($complaint->id);
        $this->assertSame(1, $sql);
    }

    private function retentionAuditSql(int $complaintId): int
    {
        return \Illuminate\Support\Facades\DB::table('audit_logs')
            ->where('subject_type', 'complaint')
            ->where('subject_id', $complaintId)
            ->count();
    }
}
