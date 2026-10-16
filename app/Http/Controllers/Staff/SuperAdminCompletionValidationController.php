<?php

namespace App\Http\Controllers\Staff;

use App\Enums\ComplaintReviewState;
use App\Enums\ComplaintStatus;
use App\Enums\NoteVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\ApproveCompletionValidationRequest;
use App\Http\Requests\SuperAdmin\ReturnCompletionValidationRequest;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintNote;
use App\Models\ComplaintStatusHistory;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * PHASE 3 (Bagian 1C) — Super Admin COMPLETION validation.
 *
 * Once an Operator SUBMITS the finished work (Bagian 1B-2), the complaint waits
 * here (`review_state = pending_completion_validation`) for a Super Admin
 * decision. This surface is DELIBERATELY SEPARATE from the PHASE 2
 * initial-validation surface (SuperAdminValidationController): the two gates
 * have different queues, different eligibility, different side effects, and the
 * completion gate additionally PUBLISHES the citizen-facing public response.
 *
 * It provides:
 *   - the completion queue (N6B-1: ONLY `review_state = pending_completion_validation`
 *     AND `status = in_progress`, oldest submitted first —
 *     `completion_submitted_at ASC, id ASC`);
 *   - the validation detail (reporter, routing, the Operator's INTERNAL
 *     completion narrative, status history);
 *   - APPROVE — performs the single EXISTING domain transition
 *     `in_progress → resolved`, publishes the internal completion narrative as
 *     the citizen-facing public response, and records the status history;
 *   - RETURN — sends the completion back to the Operator with a mandatory
 *     reason (stored in `completion_return_reason`; the status stays
 *     `in_progress`; NO public response, NO resolved history). Like APPROVE, it
 *     is only actionable from `status = in_progress` AND `review_state =
 *     pending_completion_validation` (N6D-1) — a stale non-`in_progress` row is
 *     rejected server-side, before any mutation and again on the locked row.
 *
 * Authorization: route middleware `role:super_admin` (server-side) plus the
 * global `active` middleware. No complaint status enum value is added — the
 * review state is a separate, isolated concept. Every action re-asserts
 * eligibility INSIDE a `lockForUpdate` transaction, so double decisions,
 * approve-after-return and return-after-approve can never both succeed.
 */
class SuperAdminCompletionValidationController extends Controller
{
    /**
     * The completion-validation queue: complaints whose completion the Operator
     * has submitted and which await Super Admin validation, OLDEST SUBMITTED
     * FIRST (deterministic: `completion_submitted_at` ASC, then `id` ASC).
     *
     * N6B-1 (FROZEN, Option A): the `pendingCompletionValidation()` scope requires
     * BOTH `review_state = pending_completion_validation` AND `status = in_progress`,
     * so returned / validated / pre-routing / legacy rows are all excluded — and,
     * crucially, a stale `pending_completion_validation` row whose status was
     * changed by a frozen G7 direct resolve (which does NOT rewrite `review_state`)
     * no longer surfaces here as an actionable item.
     */
    public function index(Request $request): View
    {
        // Eager-load every relation rendered by the queue (no N+1).
        $complaints = Complaint::query()
            ->with(['reporter', 'category', 'dinasUnit.operator', 'kecamatan', 'kelurahan'])
            ->pendingCompletionValidation()
            ->paginate(20)
            ->withQueryString();

        return view('super-admin.completion-validation.index', [
            'complaints' => $complaints,
        ]);
    }

    /**
     * Completion-validation detail for a single complaint awaiting validation.
     * Any complaint NOT in the completion queue is a monitoring concern (see
     * "Semua Laporan") — guarded server-side with a 404.
     */
    public function show(Complaint $complaint): View
    {
        if (! $complaint->isPendingCompletionValidation()) {
            abort(404);
        }

        $complaint->load([
            'reporter',
            'category',
            'dinasUnit.operator',
            'attachments',
            'kecamatan',
            'kelurahan',
            'statusHistories.changedBy',
            'internalNotes.author',
            'publicResponses.author',
        ]);

        // The Operator's INTERNAL completion narrative (if any). It is the ONLY
        // source of the citizen-facing public response; when it is missing the
        // Super Admin CANNOT approve (see approve()).
        $completionNarrative = $this->resolveCompletionNarrative($complaint);

        // N6D-2 (FIXED): the completion gate is only ACTIONABLE from the frozen
        // pair `status = in_progress` AND `review_state =
        // pending_completion_validation`. A stale row (e.g. a frozen G7 direct
        // resolve that deliberately leaves `resolved + pending_completion_validation`)
        // remains REACHABLE so the M5 notification deep-link never 404s, but it
        // is rendered READ-ONLY: no Approve/Return action is offered, mirroring
        // the server-side guards on approve()/returnToOperator().
        $actionable = $complaint->status === ComplaintStatus::InProgress;

        return view('super-admin.completion-validation.show', [
            'complaint'           => $complaint,
            'completionNarrative' => $completionNarrative,
            'actionable'          => $actionable,
            'canApprove'          => $actionable && $completionNarrative !== null,
        ]);
    }

    /**
     * APPROVE the completion: the complaint becomes final `resolved`.
     *
     * Frozen behaviour:
     *   - `status` performs the EXISTING domain transition `in_progress →
     *     resolved` (validated via ComplaintStatus::canTransitionTo — never a
     *     raw status write);
     *   - `review_state` → `validated_for_operator`;
     *   - `resolved_at` = server time; `completion_submitted_at` is KEPT
     *     (historical record); `completion_return_reason` is cleared;
     *   - a `complaint_status_histories` row `in_progress → resolved` is written;
     *   - the previously-INTERNAL completion narrative is published as a
     *     citizen-facing `public_response` note (the internal note is NOT
     *     deleted — it stays as internal audit trail);
     *   - a `complaint.completion_validated` audit event is written.
     *
     * If the completion narrative is UNAVAILABLE, the approval is REJECTED —
     * the public response is NEVER fabricated. Everything is atomic.
     */
    public function approve(ApproveCompletionValidationRequest $request, Complaint $complaint): RedirectResponse
    {
        // Read-only pre-check for a friendly error; re-asserted inside the
        // transaction below (atomic guard against concurrent decisions).
        if (! $complaint->isPendingCompletionValidation()) {
            return redirect()
                ->route('super-admin.completion-validation.index')
                ->withErrors(['validation' => 'Laporan ini tidak sedang menunggu validasi penyelesaian.']);
        }

        // A publishable public response is REQUIRED for `resolved`. The only
        // allowed source is the Operator's own internal completion narrative.
        if ($this->resolveCompletionNarrative($complaint) === null) {
            return redirect()
                ->route('super-admin.completion-validation.show', $complaint)
                ->withErrors(['validation' => 'Tidak dapat menyetujui: narasi penyelesaian internal dari Operator tidak tersedia. Penyelesaian tidak dapat divalidasi tanpa tanggapan publik.']);
        }

        DB::transaction(function () use ($request, $complaint) {
            $fresh = Complaint::whereKey($complaint->id)->lockForUpdate()->first();

            if (! $fresh || ! $fresh->isPendingCompletionValidation()) {
                throw ValidationException::withMessages([
                    'validation' => 'Laporan ini tidak lagi menunggu validasi penyelesaian.',
                ]);
            }

            $currentStatus = $fresh->status;

            // The completion gate is only ever entered from `in_progress`, and
            // approval uses the single EXISTING transition `in_progress → resolved`.
            if ($currentStatus !== ComplaintStatus::InProgress
                || ! $currentStatus->canTransitionTo(ComplaintStatus::Resolved)) {
                throw ValidationException::withMessages([
                    'validation' => 'Status laporan saat ini tidak memungkinkan persetujuan penyelesaian.',
                ]);
            }

            // Re-resolve the narrative INSIDE the transaction so approval can
            // never publish a stale/missing note.
            $narrative = $this->resolveCompletionNarrative($fresh);
            if ($narrative === null) {
                throw ValidationException::withMessages([
                    'validation' => 'Narasi penyelesaian internal tidak tersedia; penyelesaian tidak dapat divalidasi.',
                ]);
            }

            $oldReviewState = $fresh->review_state?->value;
            $resolvedOperator = $fresh->resolvedOperator();

            // `completion_submitted_at` is intentionally NOT cleared — it is kept
            // as the historical record of when the Operator submitted.
            // PHASE 6C — Decision 7: `public_updated_at` is a deprecated,
            // write-only legacy column; no new value is written on approval.
            $fresh->update([
                'status'                   => ComplaintStatus::Resolved,
                'review_state'             => ComplaintReviewState::ValidatedForOperator,
                'resolved_at'              => now(),
                'completion_return_reason' => null,
            ]);

            // Status history — the approved `in_progress → resolved` transition.
            ComplaintStatusHistory::create([
                'complaint_id' => $fresh->id,
                'from_status'  => $currentStatus->value,
                'to_status'    => ComplaintStatus::Resolved->value,
                'changed_by'   => $request->user()->id,
                'note'         => 'Validasi penyelesaian disetujui; laporan dinyatakan selesai.',
                'created_at'   => now(),
            ]);

            // Publish the INTERNAL narrative as the citizen-facing public response.
            // The internal note itself is NOT deleted (historical/internal trail).
            $publicNote = ComplaintNote::create([
                'complaint_id' => $fresh->id,
                'author_id'    => $request->user()->id,
                'visibility'   => NoteVisibility::PublicResponse,
                'body'         => $narrative->body,
                'created_at'   => now(),
            ]);

            $audit = AuditLog::create([
                'actor_id'     => $request->user()->id,
                'action'       => 'complaint.completion_validated',
                'subject_type' => 'complaint',
                'subject_id'   => $fresh->id,
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'metadata'     => [
                    'reference_code'          => $fresh->reference_code,
                    'dinas_unit_id'           => $fresh->dinas_unit_id,
                    'operator_id'             => $resolvedOperator?->id,
                    'old_review_state'        => $oldReviewState,
                    'new_review_state'        => ComplaintReviewState::ValidatedForOperator->value,
                    'from_status'             => $currentStatus->value,
                    'to_status'               => ComplaintStatus::Resolved->value,
                    'resolved_at'             => $fresh->resolved_at?->toIso8601String(),
                    'completion_submitted_at' => $fresh->completion_submitted_at?->toIso8601String(),
                    'completion_note_id'      => $narrative->id,
                    'public_response_note_id' => $publicNote->id,
                    'has_public_response'     => true,
                ],
            ]);

            // PHASE N4 — M7: ONE logical milestone to the reporter + the owning
            // Operator. This covers completion validation + the resolved
            // transition + the published public response; there is deliberately
            // NO separate completion/resolved/public-response notification. The
            // acting Super Admin is never notified. Anchored on this single
            // canonical `complaint.completion_validated` audit row.
            app(NotificationService::class)->emit(
                $fresh,
                NotificationService::M7,
                $audit->id,
                $request->user(),
            );
        });

        return redirect()
            ->route('super-admin.completion-validation.index')
            ->with('success', 'Validasi penyelesaian disetujui. Laporan dinyatakan selesai.');
    }

    /**
     * RETURN the completion to the Operator for correction. The reason is
     * MANDATORY (enforced by the Form Request) and stored in
     * `completion_return_reason` — an INTERNAL Operator/staff message, NEVER
     * shown to the reporter. This is NOT a rejection:
     *   - `status` stays `in_progress`; `resolved_at` stays NULL;
     *   - NO `resolved` status history is written; NO public response is created;
     *   - `completion_submitted_at` is cleared (the Operator must resubmit);
     *   - `review_state` → `completion_returned_to_operator`.
     * Category / Dinas / assigned Operator are never touched. Atomic.
     *
     * Eligibility (N6D-1, FIXED): the return is only valid from the ACTIONABLE
     * completion state — `status = in_progress` AND `review_state =
     * pending_completion_validation` — checked before any mutation AND
     * re-asserted against the LOCKED row inside the transaction (mirroring
     * approve()). A stale `pending_completion_validation` row whose status is no
     * longer `in_progress` (e.g. a frozen G7 direct resolve, or a rejected/closed
     * row) is REJECTED: no mutation, no audit, no `completion_return_reason`, no
     * `completion_submitted_at` clearing, no `review_state` change, no M6.
     */
    public function returnToOperator(ReturnCompletionValidationRequest $request, Complaint $complaint): RedirectResponse
    {
        // Read-only pre-check for a friendly error; re-asserted inside the
        // transaction below (atomic guard against concurrent decisions).
        //
        // N6D-1 (FIXED): a return is ONLY valid from the ACTIONABLE completion
        // state — `status = in_progress` AND `review_state =
        // pending_completion_validation`. A stale row (e.g. a frozen G7 direct
        // resolve that deliberately leaves `resolved + pending_completion_validation`)
        // is NOT actionable and must be rejected exactly as Approve already
        // rejects it (queue filtering is NOT the authorization boundary).
        if (! $complaint->isPendingCompletionValidation()
            || $complaint->status !== ComplaintStatus::InProgress) {
            return redirect()
                ->route('super-admin.completion-validation.index')
                ->withErrors(['validation' => 'Laporan ini tidak sedang menunggu validasi penyelesaian.']);
        }

        $validated = $request->validated();
        $reason = trim($validated['reason']);

        DB::transaction(function () use ($request, $complaint, $reason) {
            $fresh = Complaint::whereKey($complaint->id)->lockForUpdate()->first();

            if (! $fresh || ! $fresh->isPendingCompletionValidation()) {
                throw ValidationException::withMessages([
                    'validation' => 'Laporan ini tidak lagi menunggu validasi penyelesaian.',
                ]);
            }

            // N6D-1 (FIXED): the completion gate is only ever actionable from
            // `in_progress`. This mirrors approve()'s authoritative status
            // check against the LOCKED row, so a stale non-`in_progress` row
            // (G7 direct resolve / rejected / closed) can never be returned.
            if ($fresh->status !== ComplaintStatus::InProgress) {
                throw ValidationException::withMessages([
                    'validation' => 'Status laporan saat ini tidak memungkinkan pengembalian penyelesaian.',
                ]);
            }

            $oldReviewState = $fresh->review_state?->value;

            // Status is deliberately NOT touched (a return is not a rejection).
            $fresh->update([
                'review_state'             => ComplaintReviewState::CompletionReturnedToOperator,
                'completion_return_reason' => $reason,
                'completion_submitted_at'  => null,
            ]);

            $audit = AuditLog::create([
                'actor_id'     => $request->user()->id,
                'action'       => 'complaint.completion_returned',
                'subject_type' => 'complaint',
                'subject_id'   => $fresh->id,
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'metadata'     => [
                    'reference_code'  => $fresh->reference_code,
                    'dinas_unit_id'   => $fresh->dinas_unit_id,
                    'old_review_state' => $oldReviewState,
                    'new_review_state' => ComplaintReviewState::CompletionReturnedToOperator->value,
                    'return_reason'   => $reason,
                    'status_unchanged' => $fresh->status->value,
                ],
            ]);

            // PHASE N4 — M6: notify the owning Operator. The INTERNAL return
            // reason is NEVER placed in the notification body/data.
            app(NotificationService::class)->emit(
                $fresh,
                NotificationService::M6,
                $audit->id,
                $request->user(),
            );
        });

        return redirect()
            ->route('super-admin.completion-validation.index')
            ->with('success', 'Pengajuan penyelesaian dikembalikan kepada Operator untuk diperbaiki.');
    }

    /**
     * Locate the Operator's INTERNAL completion narrative for this complaint.
     *
     * The narrative is stored by the Operator as a plain INTERNAL `complaint_notes`
     * row (Bagian 1B-2). Its id is recorded in the `complaint.completion_submitted`
     * audit metadata (`completion_note_id`), so the approval step can locate and
     * publish EXACTLY that note — no new schema, no fabrication, and no reliance
     * on heuristics (author/time) that could pick the wrong note.
     *
     * Returns NULL when the Operator submitted without a narrative (or the note
     * has since vanished), which makes the approval REJECT.
     */
    private function resolveCompletionNarrative(Complaint $complaint): ?ComplaintNote
    {
        $audit = AuditLog::query()
            ->where('subject_type', 'complaint')
            ->where('subject_id', $complaint->id)
            ->where('action', 'complaint.completion_submitted')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($audit === null) {
            return null;
        }

        $metadata = is_array($audit->metadata) ? $audit->metadata : [];
        $noteId = $metadata['completion_note_id'] ?? null;

        if ($noteId === null) {
            return null;
        }

        $note = ComplaintNote::query()
            ->whereKey($noteId)
            ->where('complaint_id', $complaint->id)
            ->where('visibility', NoteVisibility::Internal->value)
            ->first();

        if ($note === null || trim((string) $note->body) === '') {
            return null;
        }

        return $note;
    }
}
