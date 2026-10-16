<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreReviewResponseRequest;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintRating;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * STAGE 3 — Official Super Admin reply to a citizen's written review.
 *
 * This is a DELIBERATELY SEPARATE controller from SuperAdminComplaintController,
 * which is documented as a READ-ONLY monitoring surface and exposes no mutation.
 * Isolating the single write action here keeps that read-only contract intact.
 *
 * Guarantees:
 *   - Authorization: route middleware `role:super_admin` (server-side) plus the
 *     Form Request's own `authorize()`. Admin, Operator and Masyarakat can never
 *     reach this endpoint, even with a direct HTTP request.
 *   - The complaint comes from route model binding; the RATING is resolved from
 *     that complaint server-side. `rating_id` / `complaint_id` / `user_id` are
 *     NEVER read from the request, so neither the responder nor the relationship
 *     can be forged.
 *   - A reply may only be written when the rating EXISTS, carries a NON-EMPTY
 *     citizen review, and has NO reply yet.
 *   - CREATE-ONCE and IMMUTABLE: the write is a conditional, row-locked UPDATE
 *     (`WHERE admin_response IS NULL`). A concurrent second request affects zero
 *     rows and is rejected — it can never overwrite the first reply. There is no
 *     edit/delete path.
 *   - The reply NEVER touches `rating`, `review_text`, the complaint status, the
 *     KPI, or the rating analytics. Only `complaint_ratings.admin_response` is
 *     written.
 *   - Audit metadata records identifiers and a `has_response` boolean ONLY — the
 *     review and reply text are NEVER copied into the audit log.
 */
class SuperAdminReviewResponseController extends Controller
{
    /**
     * Store the one official reply for the review attached to this complaint.
     */
    public function store(StoreReviewResponseRequest $request, Complaint $complaint): RedirectResponse
    {
        // Resolve the rating from the COMPLAINT (never from request input). A
        // complaint without a rating has nothing to reply to.
        $rating = $complaint->rating()->first();

        if ($rating === null) {
            return redirect()
                ->route('super-admin.complaints.show', $complaint)
                ->withErrors(['admin_response' => 'Laporan ini belum memiliki rating/ulasan masyarakat untuk dibalas.']);
        }

        // A reply is only meaningful for an EXISTING written review.
        if (! $rating->hasReview()) {
            return redirect()
                ->route('super-admin.complaints.show', $complaint)
                ->withErrors(['admin_response' => 'Tidak ada ulasan tertulis untuk dibalas.']);
        }

        // Create-once: friendly pre-check; the conditional UPDATE below re-asserts
        // this atomically against concurrent requests.
        if ($rating->hasAdminResponse()) {
            return redirect()
                ->route('super-admin.complaints.show', $complaint)
                ->withErrors(['admin_response' => 'Ulasan ini sudah memiliki balasan resmi.']);
        }

        $response = trim($request->validated()['admin_response']);

        DB::transaction(function () use ($request, $complaint, $rating, $response) {
            // Row-locked, conditional UPDATE — the authoritative create-once
            // guard. If another request already stored a reply, zero rows are
            // affected and the whole action (including this audit) is rejected.
            $affected = ComplaintRating::query()
                ->whereKey($rating->id)
                ->where('complaint_id', $complaint->id)
                ->whereNull('admin_response')
                ->update(['admin_response' => $response]);

            if ($affected === 0) {
                throw ValidationException::withMessages([
                    'admin_response' => 'Ulasan ini sudah memiliki balasan resmi.',
                ]);
            }

            AuditLog::create([
                'actor_id'     => $request->user()->id,
                'action'       => 'complaint.review_responded',
                'subject_type' => 'complaint',
                'subject_id'   => $complaint->id,
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'metadata'     => [
                    'reference_code' => $complaint->reference_code,
                    'complaint_id'   => $complaint->id,
                    'rating_id'      => $rating->id,
                    // Boolean ONLY — neither the review nor the reply text is
                    // copied into audit metadata.
                    'has_response'   => true,
                ],
            ]);
        });

        return redirect()
            ->route('super-admin.complaints.show', $complaint)
            ->with('success', 'Balasan resmi telah tersimpan.');
    }
}
