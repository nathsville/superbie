<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\ComplaintNote;
use App\Models\ComplaintStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Retention purge service (FINAL business rule, Prompt 5B.1).
 *
 *   Retention   : 5 years from complaints.submitted_at (app timezone)
 *   Action      : permanent deletion of the complaint and its lifecycle data
 *   Audit logs  : complaint-related rows are deleted; unrelated rows preserved
 *
 * This is a backend maintenance operation — no controller endpoint.
 */
class ComplaintRetentionService
{
    /**
     * Number of complaints processed per batch (technical detail, not a rule).
     */
    public const BATCH_SIZE = 100;

    /**
     * Build the query for complaints whose retention has expired.
     *
     * Reference: complaints.submitted_at ONLY.
     * - submitted_at IS NULL is NEVER expired (excluded explicitly).
     * - No fallback to created_at/updated_at/resolved_at.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Complaint>
     */
    public function expiredQuery()
    {
        $years = (int) config('business_rules.retention.years', 5);
        $cutoff = now()->subYears($years);

        return Complaint::query()
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '<=', $cutoff);
    }

    /**
     * Count complaints eligible for permanent deletion.
     */
    public function expiredCount(): int
    {
        return $this->expiredQuery()->count();
    }

    /**
     * Permanently delete a single complaint and all dependent lifecycle data.
     *
     * Database and filesystem are NOT atomic, so ordering matters:
     *
     *   PHASE 1 (database): collect attachment paths, then inside a DB
     *     transaction delete complaint_attachments, complaint_notes,
     *     complaint_status_histories, complaint-related audit_logs, and the
     *     complaint row — then COMMIT.
     *   PHASE 2 (filesystem): only AFTER a successful commit, delete the
     *     physical attachment files using the collected paths.
     *
     * This guarantees:
     *   - DB rollback  → physical files are NOT deleted.
     *   - DB commit    → physical files are then cleaned up.
     *   - file cleanup error → no fake DB rollback; DB stays committed.
     *
     * FK audit: complaint_attachments / complaint_status_histories /
     * complaint_notes all reference complaints.id with RESTRICT, so children
     * are deleted before the parent. `audit_logs` uses the plain string
     * subject_type 'complaint' (verified against every AuditLog writer in the
     * project; no morph map exists).
     *
     * @return array{deleted:bool, missing_files:int, failed_files:int}
     */
    public function purge(Complaint $complaint): array
    {
        // ── PHASE 1: collect attachment paths (needed after commit) ──────────
        $attachments = ComplaintAttachment::where('complaint_id', $complaint->id)
            ->get(['id', 'disk', 'path']);

        // ── PHASE 2: database transaction (children → parent), then commit ──
        DB::transaction(function () use ($complaint) {
            ComplaintAttachment::where('complaint_id', $complaint->id)->delete();
            ComplaintNote::where('complaint_id', $complaint->id)->delete();
            ComplaintStatusHistory::where('complaint_id', $complaint->id)->delete();

            // Complaint-related audit logs only (subject_type 'complaint').
            // Unrelated audit logs are preserved.
            DB::table('audit_logs')
                ->where('subject_type', 'complaint')
                ->where('subject_id', $complaint->id)
                ->delete();

            $complaint->delete();
        });

        // ── PHASE 3: filesystem cleanup — only after the commit succeeded ────
        $missingFiles = 0;
        $failedFiles = 0;

        foreach ($attachments as $attachment) {
            try {
                $disk = Storage::disk($attachment->disk);
                if ($disk->exists($attachment->path)) {
                    if (! $disk->delete($attachment->path)) {
                        // Deletion reported failure but DB is already committed.
                        $failedFiles++;
                        Log::error('Retention: gagal menghapus berkas lampiran setelah commit.', [
                            'complaint_id'  => $complaint->id,
                            'attachment_id' => $attachment->id,
                        ]);
                    }
                } else {
                    // Already gone — nothing to clean up.
                    $missingFiles++;
                }
            } catch (\Throwable $e) {
                // Never rollback the committed DB deletion over a file error.
                $failedFiles++;
                Log::error('Retention: exception saat menghapus berkas lampiran setelah commit.', [
                    'complaint_id'  => $complaint->id,
                    'attachment_id' => $attachment->id,
                    'error'         => $e->getMessage(),
                ]);
            }
        }

        return [
            'deleted'       => true,
            'missing_files' => $missingFiles,
            'failed_files'  => $failedFiles,
        ];
    }
}
