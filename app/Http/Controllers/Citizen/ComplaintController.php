<?php

namespace App\Http\Controllers\Citizen;

use App\Enums\ComplaintReviewState;
use App\Enums\ComplaintStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Citizen\StoreComplaintRatingRequest;
use App\Http\Requests\Citizen\StoreComplaintRequest;
use App\Http\Requests\Citizen\UpdateComplaintRequest;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\ComplaintCategory;
use App\Models\ComplaintRating;
use App\Models\ComplaintStatusHistory;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Services\LocationBoundaryService;
use App\Services\NotificationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ComplaintController extends Controller
{
    /**
     * Show form to create a new complaint.
     *
     * The citizen selects a CATEGORY only (never a Dinas/Unit). The mapped
     * Dinas/Unit are shown as read-only info (authoritative `category_dinas_unit`
     * mapping); the legacy `dinas_name` label is only a fallback for display
     * (Prompt 6 §12/§18/§19).
     */
    public function create(): View
    {
        $categories = ComplaintCategory::active()->with('dinasUnits')->get();

        // Explicit, JS-friendly payload (no reliance on Eloquent relation keys).
        $categoriesForJs = $categories->map(fn (ComplaintCategory $cat) => [
            'id'           => $cat->id,
            'name'         => $cat->name,
            'description'  => $cat->description,
            'dinas_name'   => $cat->dinas_name,
            'mapped_dinas' => $cat->dinasUnits
                ->where('is_active', true)
                ->pluck('name')
                ->values()
                ->all(),
        ])->values();

        // FINAL location rule — structured Kecamatan/Kelurahan (REQUIRED). The
        // master data is small, so it is loaded once server-side (2 queries) and
        // filtered client-side by the `locationPicker` Alpine component. Only the
        // fields the frontend needs are sent; no AJAX endpoint is introduced.
        $kecamatan = Kecamatan::query()->orderBy('name')->get(['id', 'name']);
        $kelurahan = Kelurahan::query()->orderBy('name')->get(['id', 'kecamatan_id', 'name']);

        $kecamatanForJs = $kecamatan->map(fn (Kecamatan $k) => [
            'id'   => $k->id,
            'name' => $k->name,
        ])->values();

        $kelurahanForJs = $kelurahan->map(fn (Kelurahan $k) => [
            'id'           => $k->id,
            'kecamatan_id' => $k->kecamatan_id,
            'name'         => $k->name,
        ])->values();

        // OPTIONAL automatic Kecamatan/Kelurahan suggestion: bundled Parepare
        // kelurahan polygons, resolved to the CURRENT master ids. Purely a UX
        // convenience — the pair stays REQUIRED and is validated server-side.
        $boundariesForJs = app(LocationBoundaryService::class)->payload();

        // OPTIONAL reverse-geocoding of the free-text address ("Lokasi / Alamat
        // Detail"). Owner-approved scope/privacy deviation (see config/map.php):
        // the citizen must OPT IN before any coordinate leaves the browser.
        $geocodeForJs = $this->geocodePayload();

        return view('citizen.complaint.create', compact(
            'categories',
            'categoriesForJs',
            'kecamatan',
            'kelurahan',
            'kecamatanForJs',
            'kelurahanForJs',
            'boundariesForJs',
            'geocodeForJs',
        ));
    }

    /**
     * Client config for the OPTIONAL `locationAddress` (reverse-geocode) component.
     *
     * Only non-secret, browser-facing values. `enabled` lets an operator turn the
     * whole feature off via env without touching the views; the citizen's opt-in
     * checkbox remains the real gate on whether a coordinate is ever transmitted.
     *
     * @return array{enabled: bool, url: string, email: string, locale: string, attribution: string}
     */
    private function geocodePayload(): array
    {
        return [
            'enabled'     => (bool) config('map.reverse_geocode.enabled'),
            'url'         => (string) config('map.reverse_geocode.url'),
            'email'       => (string) (config('map.reverse_geocode.email') ?? ''),
            'locale'      => (string) config('map.reverse_geocode.locale'),
            'attribution' => (string) config('map.reverse_geocode.attribution'),
        ];
    }

    /**
     * Store newly created complaint in storage.
     */
    public function store(StoreComplaintRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Audit PROMPT_28 (S-02): attachment files written inside the
        // transaction are compensated when it rolls back, so a failed create
        // never leaves orphaned files in private storage.
        $storedAttachmentPaths = [];

        $createComplaint = function () use ($user, $validated, $request, &$storedAttachmentPaths) {
            // Generate unique reference code: LPW-YYYYMMDD-XXXX
            $datePrefix = 'LPW-' . now()->format('Ymd') . '-';
            $uniqueCode = null;
            do {
                $candidate = $datePrefix . strtoupper(Str::random(4));
            } while (Complaint::where('reference_code', $candidate)->exists());
            $uniqueCode = $candidate;

            // PHASE 6C — Decision 4: `tracking_secret_hash` is DEPRECATED. Public
            // tracking does not exist, the value is never read/verified/rendered,
            // and no new secret is generated. Historical populated values are
            // left untouched.
            //
            // PHASE 6C — Decision 5: `reporter_phone` is DEPRECATED (always NULL;
            // no phone snapshot is taken). New writes are stopped.
            //
            // PHASE 6C — Decision 6: `reporter_email` is a RETAINED snapshot,
            // still written at creation and shown to Super Admin staff.
            $complaint = Complaint::create([
                'reference_code' => $uniqueCode,
                'category_id' => $validated['category_id'],
                'reporter_id' => $user->id,
                'reporter_email' => $user->email,
                'title' => $validated['title'],
                'description' => $validated['description'],
                'location_text' => $validated['location_text'] ?? null,
                'kecamatan_id' => $validated['kecamatan_id'],
                'kelurahan_id' => $validated['kelurahan_id'],
                // OPTIONAL geographic coordinates (Prompt 4/5). Absent unless the
                // citizen explicitly picked a point (GPS or map click).
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'status' => ComplaintStatus::Submitted,
                // PHASE 2 — every new citizen complaint MUST pass Super Admin
                // initial validation before an Operator can handle it. The
                // complaint starts in the initial-validation queue.
                'review_state' => ComplaintReviewState::PendingInitialValidation,
                'review_state_updated_at' => now(),
                'submitted_at' => now(),
            ]);

            // Save attachments in private storage
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    if (!$file->isValid()) {
                        continue;
                    }

                    $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('complaints/' . $complaint->id, $filename, 'private');
                    $storedAttachmentPaths[] = $path;
                    $checksum = hash_file('sha256', $file->getRealPath());

                    ComplaintAttachment::create([
                        'complaint_id' => $complaint->id,
                        'disk' => 'private',
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'size_bytes' => $file->getSize(),
                        'checksum_sha256' => $checksum,
                    ]);
                }
            }

            // Create initial status history entry
            ComplaintStatusHistory::create([
                'complaint_id' => $complaint->id,
                'from_status' => null,
                'to_status' => ComplaintStatus::Submitted->value,
                'changed_by' => $user->id,
                'note' => 'Laporan berhasil dibuat dan diajukan ke sistem.',
            ]);

            // Write audit log
            $audit = AuditLog::create([
                'actor_id' => $user->id,
                'action' => 'complaint_created',
                'subject_type' => 'complaint',
                'subject_id' => $complaint->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'reference_code' => $complaint->reference_code,
                    'category_id' => $complaint->category_id,
                ],
            ]);

            // PHASE N4 — M1: notify every ACTIVE Super Admin (never the actor).
            // Created INSIDE the same transaction, anchored on the canonical
            // audit row id (idempotency key).
            app(NotificationService::class)->emit(
                $complaint,
                NotificationService::M1,
                $audit->id,
                $user,
            );

            return $complaint;
        };

        try {
            $complaint = DB::transaction($createComplaint);
        } catch (\Throwable $e) {
            Storage::disk('private')->delete($storedAttachmentPaths);
            throw $e;
        }

        return redirect()->route('citizen.complaint.show', $complaint)->with(
            'success',
            'Laporan berhasil dikirim dengan Nomor Registrasi: ' . $complaint->reference_code
        );
    }

    /**
     * Display the specified complaint.
     * Citizen can ONLY view their own complaints.
     * Internal staff notes are NEVER exposed.
     */
    public function show(Complaint $complaint): View
    {
        // STRICT Server-side authorization check
        if ($complaint->reporter_id !== auth()->id()) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat laporan ini.');
        }

        // Eager load only citizen-safe relations
        $complaint->load([
            'category',
            'attachments',
            'statusHistories.changedBy',
            'publicResponses.author',
            'kecamatan',
            'kelurahan',
            // PHASE 3 — Bagian 2 — the reporter's own satisfaction rating (if any).
            'rating',
        ]);

        return view('citizen.complaint.show', compact('complaint'));
    }

    // ─── PHASE 2 — Return-to-citizen correction + resubmission ────────────────

    /**
     * Show the correction form for a complaint the Super Admin returned to the
     * reporter. Only the OWNER may edit, and ONLY while the complaint is in the
     * `returned_to_citizen` review state (server-side; never UI-only).
     *
     * The reporter may correct every field that originates from them
     * (title, description, category, location, attachments). The Dinas/Unit,
     * the Operator, the status and the review state are NOT editable here.
     */
    public function edit(Complaint $complaint): View
    {
        $this->authorizeCitizenCorrection($complaint);

        $complaint->load(['category', 'attachments', 'kecamatan', 'kelurahan']);

        // Same read-only category + location master data as the create form.
        $categories = ComplaintCategory::active()->with('dinasUnits')->get();

        $categoriesForJs = $categories->map(fn (ComplaintCategory $cat) => [
            'id'           => $cat->id,
            'name'         => $cat->name,
            'description'  => $cat->description,
            'dinas_name'   => $cat->dinas_name,
            'mapped_dinas' => $cat->dinasUnits
                ->where('is_active', true)
                ->pluck('name')
                ->values()
                ->all(),
        ])->values();

        $kecamatan = Kecamatan::query()->orderBy('name')->get(['id', 'name']);
        $kelurahan = Kelurahan::query()->orderBy('name')->get(['id', 'kecamatan_id', 'name']);

        $kecamatanForJs = $kecamatan->map(fn (Kecamatan $k) => [
            'id'   => $k->id,
            'name' => $k->name,
        ])->values();

        $kelurahanForJs = $kelurahan->map(fn (Kelurahan $k) => [
            'id'           => $k->id,
            'kecamatan_id' => $k->kecamatan_id,
            'name'         => $k->name,
        ])->values();

        $boundariesForJs = app(LocationBoundaryService::class)->payload();

        // OPTIONAL reverse-geocoding of the free-text address — see create().
        $geocodeForJs = $this->geocodePayload();

        return view('citizen.complaint.edit', compact(
            'complaint',
            'categories',
            'categoriesForJs',
            'kecamatan',
            'kelurahan',
            'kecamatanForJs',
            'kelurahanForJs',
            'boundariesForJs',
            'geocodeForJs',
        ));
    }

    /**
     * Apply the reporter's corrections and RESUBMIT the same complaint to the
     * Super Admin (never a new complaint — see PHASE 2 §13).
     *
     * Security boundary (server-side):
     *   - Only the owner may resubmit (IDOR-safe).
     *   - Only allowed while review_state = returned_to_citizen.
     *   - The reporter CANNOT set dinas_unit_id, assigned_to, status or
     *     review_state — those fields are not read from the request.
     *
     * On success the complaint returns to the initial-validation queue
     * (`pending_initial_validation`) and its status stays `submitted` (a
     * resubmission is NOT a rejection, and NOT a status change).
     */
    public function update(UpdateComplaintRequest $request, Complaint $complaint): RedirectResponse
    {
        $this->authorizeCitizenCorrection($complaint);

        $user = $request->user();
        $validated = $request->validated();

        // Audit PROMPT_28 (S-02): rows move inside the transaction; files are
        // handled around the commit — new uploads are compensated if it rolls
        // back, removed files are deleted only after the commit (see below).
        $newAttachmentPaths = [];
        $removedAttachmentFiles = [];

        $applyCorrection = function () use ($request, $complaint, $user, $validated, &$newAttachmentPaths, &$removedAttachmentFiles) {
            $oldCategoryId = $complaint->category_id;

            // Only citizen-owned fields are written. dinas_unit_id / assigned_to /
            // status / review_state are deliberately absent from this update.
            $complaint->update([
                'category_id'   => $validated['category_id'],
                'title'         => $validated['title'],
                'description'   => $validated['description'],
                'location_text' => $validated['location_text'] ?? null,
                'kecamatan_id'  => $validated['kecamatan_id'],
                'kelurahan_id'  => $validated['kelurahan_id'],
                'latitude'      => $validated['latitude'] ?? null,
                'longitude'     => $validated['longitude'] ?? null,
                // Back to the Super Admin queue; status is intentionally untouched.
                'review_state'  => ComplaintReviewState::PendingInitialValidation,
                'review_return_reason' => null,
                'review_state_updated_at' => now(),
            ]);

            // Remove any attachments the reporter explicitly checked for removal
            // (owner-only, complaint-scoped; files + rows both deleted).
            $removeIds = collect($request->input('remove_attachments', []))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->all();

            if ($removeIds !== []) {
                $toRemove = ComplaintAttachment::where('complaint_id', $complaint->id)
                    ->whereIn('id', $removeIds)
                    ->get();

                foreach ($toRemove as $attachment) {
                    // S-02: the row goes now, the file after the commit.
                    $removedAttachmentFiles[] = ['disk' => $attachment->disk, 'path' => $attachment->path];
                    $attachment->delete();
                }
            }

            // Add newly uploaded attachments (same frozen rules; validated above).
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    if (! $file->isValid()) {
                        continue;
                    }

                    $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('complaints/' . $complaint->id, $filename, 'private');
                    $newAttachmentPaths[] = $path;
                    $checksum = hash_file('sha256', $file->getRealPath());

                    ComplaintAttachment::create([
                        'complaint_id'    => $complaint->id,
                        'disk'            => 'private',
                        'path'            => $path,
                        'original_name'   => $file->getClientOriginalName(),
                        'mime_type'       => $file->getMimeType() ?: 'application/octet-stream',
                        'size_bytes'      => $file->getSize(),
                        'checksum_sha256' => $checksum,
                    ]);
                }
            }

            $audit = AuditLog::create([
                'actor_id'     => $user->id,
                'action'       => 'complaint.citizen_resubmitted',
                'subject_type' => 'complaint',
                'subject_id'   => $complaint->id,
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'metadata'     => [
                    'reference_code'  => $complaint->reference_code,
                    'old_category_id' => $oldCategoryId,
                    'new_category_id' => $complaint->category_id,
                    'review_state'    => ComplaintReviewState::PendingInitialValidation->value,
                ],
            ]);

            // PHASE N4 — M4: notify every ACTIVE Super Admin (never the actor).
            app(NotificationService::class)->emit(
                $complaint,
                NotificationService::M4,
                $audit->id,
                $user,
            );
        };

        try {
            DB::transaction($applyCorrection);
        } catch (\Throwable $e) {
            Storage::disk('private')->delete($newAttachmentPaths);
            throw $e;
        }

        // After the commit: delete the files whose rows are gone. A failure
        // here can only leave an unreferenced file — never a row pointing at
        // a missing one.
        foreach ($removedAttachmentFiles as $file) {
            Storage::disk($file['disk'])->delete($file['path']);
        }

        return redirect()
            ->route('citizen.complaint.show', $complaint)
            ->with('success', 'Perbaikan laporan berhasil dikirim ulang dan menunggu validasi ulang dari Super Admin.');
    }

    /**
     * Server-side authorization for citizen correction/resubmission.
     * Owner-only AND only while the complaint is returned to the reporter.
     */
    private function authorizeCitizenCorrection(Complaint $complaint): void
    {
        if ($complaint->reporter_id !== auth()->id()) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengubah laporan ini.');
        }

        if (! $complaint->isReturnedToCitizen()) {
            abort(403, 'Laporan ini tidak sedang dikembalikan untuk diperbaiki.');
        }
    }

    /**
     * Securely download or view an attachment belonging to own complaint.
     */
    public function downloadAttachment(Complaint $complaint, ComplaintAttachment $attachment): Response
    {
        // STRICT Server-side authorization check
        if ($complaint->reporter_id !== auth()->id() || $attachment->complaint_id !== $complaint->id) {
            abort(403, 'Akses berkas ditolak.');
        }

        $disk = Storage::disk($attachment->disk);
        if (!$disk->exists($attachment->path)) {
            abort(404, 'Berkas lampiran tidak ditemukan di server.');
        }

        return $disk->response($attachment->path, $attachment->original_name);
    }

    // ─── PHASE 3 — Bagian 2 — Citizen satisfaction rating (1–5) ───────────────

    /**
     * Submit the reporter's satisfaction rating for their OWN complaint.
     *
     * Frozen business rules:
     *   - OWNER ONLY: `complaints.reporter_id === auth()->id()` (IDOR closed).
     *     A different citizen / Operator / Admin / Super Admin is rejected
     *     server-side (route middleware `role:masyarakat` + this inline guard).
     *   - ELIGIBLE ONLY while `status = resolved` (single source of truth =
     *     ComplaintStatus). `closed` and every other status are NOT ratable.
     *   - ONE rating per complaint, IMMUTABLE. The `complaint_id` UNIQUE index is
     *     the primary defense; a concurrent double submission is caught and
     *     rejected without overwriting the first rating or writing a second audit.
     *   - The rating NEVER mutates `status`, `review_state`, `resolved_at`, the
     *     completion columns, or any public response — it only inserts one row in
     *     `complaint_ratings`.
     *
     * Everything is atomic (single transaction). An audit event
     * `complaint.rating_submitted` is written ONLY after the rating is persisted.
     *
     * PHASE 3 — Bagian 2 (review): an OPTIONAL written review (`review_text`)
     * rides on the SAME immutable row. It is trimmed; a blank value is stored as
     * NULL. The audit records ONLY a `has_review` boolean — NEVER the review
     * text (the text is free-form citizen content and must not be duplicated into
     * audit metadata). Moderation is NOT implemented (prd.md §4 TODO).
     */
    public function storeRating(StoreComplaintRatingRequest $request, Complaint $complaint): RedirectResponse
    {
        // STRICT server-side ownership check (IDOR). The owner column is
        // `reporter_id` — never `user_id` (which does not exist on complaints).
        if ($complaint->reporter_id !== $request->user()->id) {
            abort(403, 'Anda tidak memiliki hak akses untuk menilai laporan ini.');
        }

        // Eligibility: only a `resolved` complaint may be rated. `closed` (and
        // every other status) is rejected. Never inferred from `review_state`.
        if (! $complaint->canBeRated()) {
            return redirect()
                ->route('citizen.complaint.show', $complaint)
                ->withErrors(['rating' => 'Rating kepuasan hanya dapat diberikan pada laporan yang berstatus selesai.']);
        }

        // Read-only duplicate pre-check for a friendly error; the UNIQUE index
        // re-asserts this atomically below (race-safe).
        if ($complaint->rating()->exists()) {
            return redirect()
                ->route('citizen.complaint.show', $complaint)
                ->withErrors(['rating' => 'Anda sudah memberikan rating untuk laporan ini.']);
        }

        $user  = $request->user();
        $score = (int) $request->validated()['rating'];

        // OPTIONAL review. Trimmed server-side; a blank value becomes NULL so a
        // whitespace-only review is indistinguishable from "no review".
        $reviewText = trim((string) ($request->validated()['review_text'] ?? ''));
        $reviewText = $reviewText === '' ? null : $reviewText;
        $hasReview  = $reviewText !== null;

        try {
            DB::transaction(function () use ($request, $complaint, $user, $score, $reviewText, $hasReview) {
                // Re-assert ownership + eligibility INSIDE the transaction.
                $fresh = Complaint::whereKey($complaint->id)->lockForUpdate()->first();

                if (! $fresh || $fresh->reporter_id !== $user->id) {
                    throw ValidationException::withMessages([
                        'rating' => 'Anda tidak memiliki hak akses untuk menilai laporan ini.',
                    ]);
                }

                if (! $fresh->canBeRated()) {
                    throw ValidationException::withMessages([
                        'rating' => 'Rating kepuasan hanya dapat diberikan pada laporan yang berstatus selesai.',
                    ]);
                }

                // Insert the IMMUTABLE rating (+ optional review). The
                // `complaint_id` UNIQUE index is the real guard: if a concurrent
                // request already inserted a rating, this INSERT throws a
                // unique-constraint violation and the whole transaction
                // (including this audit) rolls back.
                ComplaintRating::create([
                    'complaint_id' => $fresh->id,
                    'user_id'      => $user->id,
                    'rating'       => $score,
                    'review_text'  => $reviewText,
                ]);

                $audit = AuditLog::create([
                    'actor_id'     => $user->id,
                    'action'       => 'complaint.rating_submitted',
                    'subject_type' => 'complaint',
                    'subject_id'   => $fresh->id,
                    'ip_address'   => $request->ip(),
                    'user_agent'   => $request->userAgent(),
                    'metadata'     => [
                        'reference_code'   => $fresh->reference_code,
                        'complaint_id'     => $fresh->id,
                        'reporter_id'      => $user->id,
                        'rating'           => $score,
                        'complaint_status' => $fresh->status->value,
                        // Boolean ONLY — the review text is deliberately NOT
                        // copied into audit metadata.
                        'has_review'       => $hasReview,
                    ],
                ]);

                // PHASE N4 — M12: notify the owning Operator + every ACTIVE Super
                // Admin (never the citizen actor, never Admin/Petugas). The body
                // carries NO reporter identity/PII.
                app(NotificationService::class)->emit(
                    $fresh,
                    NotificationService::M12,
                    $audit->id,
                    $user,
                );
            });
        } catch (QueryException $e) {
            // Concurrent double-submit: another request won the UNIQUE race.
            // No rating was written by THIS request and no audit survived the
            // rollback. Respond with a consistent duplicate error.
            if ($this->isUniqueConstraintViolation($e)) {
                return redirect()
                    ->route('citizen.complaint.show', $complaint)
                    ->withErrors(['rating' => 'Anda sudah memberikan rating untuk laporan ini.']);
            }

            throw $e;
        }

        return redirect()
            ->route('citizen.complaint.show', $complaint)
            ->with('success', 'Terima kasih, rating kepuasan Anda telah tersimpan.');
    }

    /**
     * Whether a query exception is a duplicate-key violation on the rating
     * unique index (MySQL SQLSTATE 23000 / errno 1062).
     */
    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;
        $driverCode = $e->errorInfo[1] ?? null;

        return $sqlState === '23000' && (int) $driverCode === 1062;
    }
}
