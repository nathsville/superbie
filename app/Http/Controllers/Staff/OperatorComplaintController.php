<?php

namespace App\Http\Controllers\Staff;

use App\Enums\ComplaintReviewState;
use App\Enums\ComplaintStatus;
use App\Enums\NoteVisibility;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\ComplaintCategory;
use App\Models\ComplaintNote;
use App\Models\ComplaintStatusHistory;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\User;
use App\Services\LocationBoundaryService;
use App\Services\NotificationService;
use App\Support\QueryInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Operator complaint management controller.
 *
 * Every method verifies:
 *  - authenticated user (via route middleware)
 *  - role = operator (via route middleware)
 *  - is_active (via EnsureUserHasRole middleware)
 *
 * Additional per-action authorization is performed inline where needed.
 */
class OperatorComplaintController extends Controller
{
    // ─── Phase B: List & Detail ───────────────────────────────────────────────

    /**
     * Daftar laporan — semua complaint visible ke Operator.
     * Supports search (title/reference_code) and filter by status/category.
     */
    public function index(Request $request): View
    {
        $query = Complaint::query()
            ->with(['category', 'reporter', 'dinasUnit'])
            ->orderBy('submitted_at', 'desc');

        // Prompt 15 §4 — Operator scope (hybrid). Super Admin remains GLOBAL.
        // An Operator with no Dinas/Unit sees NOTHING (denied, no fallback).
        if ($request->user()->isOperator()) {
            $query->visibleToOperator($request->user());
        }

        // Search — a crafted array (`?search[]=x`) is ignored, never cast inline.
        if ($search = QueryInput::trimmed($request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                  ->orWhere('reference_code', 'like', '%' . $search . '%');
            });
        }

        // Filter by status (allowlist only). LC-2B — guard against non-string
        // input (e.g. `?status[]=x`): an array must be IGNORED, never passed to
        // the enum constructor (which would throw and 500).
        $status = $request->get('status');
        if (is_string($status) && $status !== '') {
            $statusEnum = ComplaintStatus::tryFrom($status);
            if ($statusEnum !== null) {
                $query->where('status', $statusEnum->value);
            }
        }

        // Filter by category — a crafted array (`?category_id[]=x`) is ignored.
        if ($categoryId = QueryInput::int($request->get('category_id'))) {
            $query->where('category_id', $categoryId);
        }

        // PHASE 6C — Decision 9: the Operator "assignment" filter
        // (`assignment=mine` / `assignment=unassigned`) was REMOVED. An Operator
        // is scoped by `complaints.dinas_unit_id == users.dinas_unit_id`; the
        // legacy `assigned_to` column is deprecated and never written, so the
        // filter was meaningless (always empty / always everything).

        $complaints = $query->paginate(15)->withQueryString();
        $categories  = $this->categoryFilterOptions($request->user());
        $statuses    = ComplaintStatus::cases();

        // Coerce echoed filter values so a crafted array (`?search[]=x`) cannot
        // reach the view and raise "Array to string conversion".
        $filters = [
            'search'      => QueryInput::trimmed($request->get('search')) ?? '',
            'status'      => QueryInput::string($request->get('status')) ?? '',
            'category_id' => QueryInput::int($request->get('category_id')),
        ];

        return view('operator.complaint.index', compact(
            'complaints',
            'categories',
            'statuses',
            'filters',
        ));
    }

    /**
     * Category filter options for the "Kelola Laporan" surface, SCOPED to the
     * viewer's role / Dinas-Unit:
     *
     *   - Operator WITH a Dinas/Unit → ONLY the ACTIVE categories mapped to that
     *     unit via `category_dinas_unit` (their own authority). Categories owned
     *     by other Dinas/Units are never offered.
     *   - Operator WITHOUT a unit → NONE. This mirrors BDR-3 (a unit-less
     *     Operator is denied scope; there is NO global fallback).
     *   - Super Admin → the GLOBAL active list (unscoped monitoring).
     *
     * The backend filter itself is unchanged: only the OPTIONS offered here are
     * narrowed. A deep link carrying a `category_id` outside the viewer's scope
     * simply matches no visible complaint (the list is already operator-scoped).
     *
     * @return \Illuminate\Support\Collection<int, ComplaintCategory>
     */
    private function categoryFilterOptions(User $user): Collection
    {
        $base = ComplaintCategory::active();

        // Super Admin keeps the complete, global list.
        if ($user->isSuperAdmin()) {
            return $base->get();
        }

        $unitId = $user->dinas_unit_id;

        // Operator with no Dinas/Unit → no options (no global fallback).
        if ($unitId === null) {
            return collect();
        }

        return $base
            ->whereHas('dinasUnits', fn ($q) => $q->where('dinas_units.id', $unitId))
            ->get();
    }

    /**
     * Detail laporan — operator dapat melihat seluruh informasi operasional,
     * termasuk internal notes.
     */
    public function show(Request $request, Complaint $complaint): View
    {
        $this->authorizeComplaintAccess($request->user(), $complaint);

        $complaint->load([
            'category',
            'dinasUnit.operator',
            'reporter',
            'attachments',
            'statusHistories.changedBy',
            'internalNotes.author',
            'publicResponses.author',
            'kecamatan',
            'kelurahan',
            // PHASE 3 — Bagian 2 — the reporter's satisfaction rating + optional
            // written review, shown READ-ONLY to staff.
            'rating',
        ]);

        // PHASE 2R — the category / Dinas/Unit destination / assigned Operator are
        // READ-ONLY for the Operator and are displayed as plain information, so the
        // former $categories / $operators / $mappedDinasUnits selectors are no
        // longer needed. The handling Operator is resolved from the Dinas/Unit.
        $statuses = ComplaintStatus::cases();

        // FINAL location rule — structured Kecamatan/Kelurahan. Loaded once
        // (2 queries) and filtered client-side by the `locationPicker` component.
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

        // OPTIONAL auto-suggestion of Kecamatan/Kelurahan from a map point, same
        // UX convenience as the citizen form. Server re-validates the pair.
        $boundariesForJs = app(LocationBoundaryService::class)->payload();

        // Allowed next statuses from current status
        $allowedTransitions = $complaint->status->allowedTransitions();

        // PHASE 3 — Completion Validation Gate. An Operator can no longer set a
        // complaint to `resolved` directly (enforced server-side in
        // updateStatus()). Hide it from the dropdown for non-Super-Admin viewers
        // as a convenience — the dedicated "Ajukan Penyelesaian" action replaces
        // it. Super Admin keeps full authority here (its approval part is later).
        if (! $request->user()->isSuperAdmin()) {
            $allowedTransitions = array_values(array_filter(
                $allowedTransitions,
                fn (ComplaintStatus $next) => $next !== ComplaintStatus::Resolved,
            ));
        }

        // PHASE N6A — F-1 (UI guard; the server is the real boundary). While an
        // Operator's completion submission awaits Super Admin validation
        // (`review_state = pending_completion_validation`), the Operator must not
        // be OFFERED any status change. The options are removed entirely; the
        // "Ajukan Penyelesaian" / completion status panel is unaffected.
        // `completion_returned_to_operator` is NOT blocked — the normal options
        // come back once the Super Admin returns the submission.
        $statusUpdateBlockedByCompletion = $request->user()->isOperator()
            && $complaint->isPendingCompletionValidation();

        if ($statusUpdateBlockedByCompletion) {
            $allowedTransitions = [];
        }

        // Whether the "Ajukan Penyelesaian" action should be offered. This mirrors
        // the server-side gate exactly (see Complaint::canSubmitCompletion()) AND
        // is limited to Operators — Super Admin validates later, it never submits.
        $canSubmitCompletion = $request->user()->isOperator() && $complaint->canSubmitCompletion();

        return view('operator.complaint.show', compact(
            'complaint',
            'statuses',
            'allowedTransitions',
            'canSubmitCompletion',
            'statusUpdateBlockedByCompletion',
            'kecamatan',
            'kelurahan',
            'kecamatanForJs',
            'kelurahanForJs',
            'boundariesForJs',
        ));
    }

    // ─── PHASE 2R: Operator cannot change category / destination / assignment ──
    //
    // The former `updateCategory()`, `updateDestination()` and `assign()` actions
    // were REMOVED. Operator immutability of routing metadata is enforced by the
    // ABSENCE of any route/controller code path that could write `category_id`,
    // `dinas_unit_id` or `assigned_to` — the strongest server-side guarantee.
    // Category + Dinas/Unit are decided by Super Admin initial validation only.

    // ─── Phase C2: Update Location (Kecamatan/Kelurahan + coordinates) ─────────

    /**
     * Ubah LOKASI terstruktur laporan (Kecamatan + Kelurahan) — FINAL rule:
     * Operator BOLEH mengubah Kecamatan/Kelurahan.
     *
     * NOTE (PHASE 2R): geographic location is DELIBERATELY SEPARATE from the
     * Dinas/Unit DESTINATION. This endpoint never reads or writes
     * `complaints.dinas_unit_id` — the destination is decided by Super Admin
     * initial validation and is read-only for the Operator.
     *
     * Security boundary (server-side): the pair is validated AS A PAIR.
     *  - Both must exist.
     *  - The chosen Kelurahan MUST belong to the chosen Kecamatan.
     * A request that changes only the Kecamatan and keeps the old Kelurahan is
     * REJECTED — the frontend is never trusted.
     *
     * Prompt 4/5: the endpoint ALSO accepts OPTIONAL latitude/longitude so an
     * authorised Operator can place/adjust/clear the report pin. Coordinates are
     * all-or-nothing (a partial pair is rejected) and may be empty/null to
     * explicitly CLEAR the point. `location_text` is NEVER modified here.
     */
    public function updateLocation(Request $request, Complaint $complaint): RedirectResponse
    {
        $this->authorizeComplaintAccess($request->user(), $complaint);

        $validated = $request->validate([
            'kecamatan_id' => ['required', 'integer', 'exists:kecamatan,id'],
            'kelurahan_id' => [
                'required',
                'integer',
                Rule::exists('kelurahan', 'id')->where(function ($query) use ($request) {
                    $query->where('kecamatan_id', $request->input('kecamatan_id'));
                }),
            ],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ], [
            'kecamatan_id.required' => 'Kecamatan wajib dipilih.',
            'kecamatan_id.exists' => 'Kecamatan yang dipilih tidak ditemukan.',
            'kelurahan_id.required' => 'Kelurahan wajib dipilih.',
            'kelurahan_id.exists' => 'Kelurahan yang dipilih tidak sesuai dengan Kecamatan.',
            'latitude.numeric' => 'Latitude tidak valid.',
            'latitude.between' => 'Latitude harus berada di antara -90 dan 90.',
            'longitude.numeric' => 'Longitude tidak valid.',
            'longitude.between' => 'Longitude harus berada di antara -180 dan 180.',
        ]);

        // Coordinate PAIR rule (Prompt 4/5 §E): both-or-neither. A partial pair
        // must be rejected WITHOUT persisting any change (atomic).
        $hasLat = $validated['latitude'] ?? null;
        $hasLng = $validated['longitude'] ?? null;
        if (($hasLat === null) !== ($hasLng === null)) {
            throw ValidationException::withMessages([
                'latitude' => 'Latitude dan longitude harus diisi bersamaan.',
                'longitude' => 'Latitude dan longitude harus diisi bersamaan.',
            ]);
        }

        $kecamatan = Kecamatan::findOrFail($validated['kecamatan_id']);
        $kelurahan = Kelurahan::findOrFail($validated['kelurahan_id']);

        $oldKecamatanId = $complaint->kecamatan_id;
        $oldKelurahanId = $complaint->kelurahan_id;
        $oldLatitude    = $complaint->latitude;
        $oldLongitude   = $complaint->longitude;

        // Coordinates are only touched when the request actually carries them
        // (the edit form always does). This keeps the endpoint safe for callers
        // that only send the administrative pair, while an explicit "clear"
        // (empty inputs → present-but-null) still resets the point.
        $coordinatesProvided = $request->has('latitude') || $request->has('longitude');

        if ($coordinatesProvided) {
            $newLatitude  = $hasLat !== null ? number_format((float) $hasLat, 7, '.', '') : null;
            $newLongitude = $hasLng !== null ? number_format((float) $hasLng, 7, '.', '') : null;
        } else {
            $newLatitude  = $oldLatitude;
            $newLongitude = $oldLongitude;
        }

        DB::transaction(function () use ($request, $complaint, $kecamatan, $kelurahan, $oldKecamatanId, $oldKelurahanId, $oldLatitude, $oldLongitude, $newLatitude, $newLongitude) {
            // Atomic: administrative pair AND coordinates are written together.
            // `location_text` is intentionally NOT touched.
            $complaint->update([
                'kecamatan_id' => $kecamatan->id,
                'kelurahan_id' => $kelurahan->id,
                'latitude'     => $newLatitude,
                'longitude'    => $newLongitude,
            ]);

            AuditLog::create([
                'actor_id'     => $request->user()->id,
                'action'       => 'complaint.location_updated',
                'subject_type' => 'complaint',
                'subject_id'   => $complaint->id,
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'metadata'     => [
                    'reference_code'      => $complaint->reference_code,
                    'old_kecamatan_id'    => $oldKecamatanId,
                    'new_kecamatan_id'    => $kecamatan->id,
                    'new_kecamatan_name'  => $kecamatan->name,
                    'old_kelurahan_id'    => $oldKelurahanId,
                    'new_kelurahan_id'    => $kelurahan->id,
                    'new_kelurahan_name'  => $kelurahan->name,
                    'old_latitude'        => $oldLatitude,
                    'new_latitude'        => $newLatitude,
                    'old_longitude'       => $oldLongitude,
                    'new_longitude'       => $newLongitude,
                ],
            ]);
        });

        return redirect()
            ->route('operator.complaint.show', $complaint)
            ->with('success', 'Lokasi laporan berhasil diperbarui ke: ' . $kelurahan->name . ', ' . $kecamatan->name);
    }

    // ─── PHASE 2R: assignment REMOVED ─────────────────────────────────────────
    //
    // The former `assign()` action was REMOVED. `complaints.assigned_to` is a
    // legacy compatibility column only: it is NEVER written by any Operator code
    // path and is NEVER used as a routing mechanism. The handling Operator is
    // resolved automatically from `complaint.dinas_unit_id` → `DinasUnit::operator()`.
    // Historical values are preserved untouched.

    // ─── Phase G: Update Status ───────────────────────────────────────────────

    /**
     * Perbarui status laporan.
     * Transisi hanya diizinkan sesuai ComplaintStatus::allowedTransitions().
     * Status, history, dan audit log disimpan secara atomik dalam satu transaksi.
     *
     * Official business rules (Prompt 3B):
     *  - rejected: wajib rejection_reason (non-empty) dan public_response_body
     *  - resolved:  wajib public_response_body
     *  - Authority: Operator dan Super Admin
     *  - No reopen: tidak ada transisi balik dari closed
     *
     * PHASE 3 — completion gate: an OPERATOR may no longer set `resolved`
     * directly (see the guard below); Super Admin keeps that authority. Finishing
     * a complaint is done through `submitCompletion()` instead.
     *
     * PHASE 3 — Bagian 3 (Closed / Finalisasi) — FROZEN business decisions:
     *  - D-3.1 Who may close: Operator (own Dinas/Unit) + Super Admin (global).
     *           Admin and Masyarakat CANNOT (enforced by route middleware); there
     *           is NO system/auto close.
     *  - D-3.2 When: MANUAL only. No auto-close, no scheduler, no grace period,
     *           no `closed_at` column.
     *  - D-3.3 Rating is OPTIONAL: a NULL rating never blocks closing.
     *  - D-3.4 `closed` is terminal for STATUS TRANSITION only. It is NOT a
     *           global mutation lock — internal note / public response / location
     *           update remain allowed after closing; category / Dinas-Unit /
     *           assignment / completion / reopen stay blocked as elsewhere.
     *  - D-3.5 Concurrency: the mutation runs inside a transaction with
     *           `lockForUpdate()` and a stale-state re-check (below), so a double
     *           close can never write a duplicate status history or audit event.
     */
    public function updateStatus(Request $request, Complaint $complaint): RedirectResponse
    {
        $this->authorizeComplaintAccess($request->user(), $complaint);

        $validated = $request->validate([
            'status'               => ['required', 'string'],
            'note'                 => ['nullable', 'string', 'max:1000'],
            'rejection_reason'     => ['nullable', 'string', 'max:1000'],
            'public_response_body' => ['nullable', 'string', 'max:5000'],
        ]);

        $newStatusEnum = ComplaintStatus::tryFrom($validated['status']);

        if ($newStatusEnum === null) {
            return redirect()
                ->route('operator.complaint.show', $complaint)
                ->withErrors(['status' => 'Status tidak valid.']);
        }

        // PHASE N6A — F-1 (frozen): while the Operator's completion submission is
        // awaiting Super Admin validation (`review_state = pending_completion_validation`),
        // the OPERATOR may NOT change the status at all — not to
        // `waiting_for_information`, not to `rejected`, not to `closed`, and not any
        // other transition. The complaint is frozen for the Operator until the Super
        // Admin either approves or RETURNS it. A RETURN moves the review state to
        // `completion_returned_to_operator`, which RESTORES the normal "Perbarui
        // Status" options (this guard deliberately does NOT block that state).
        //
        // This is a workflow/authorization guard only — it changes NO transition in
        // the frozen 11-transition matrix and never rewrites `review_state`. It is
        // enforced server-side (the UI merely hides the options); the AUTHORITATIVE
        // re-check is repeated inside the transaction against the locked row below.
        //
        // Super Admin is deliberately NOT affected: G7 keeps Super Admin's global
        // authority over this same endpoint.
        if ($request->user()->isOperator() && $complaint->isPendingCompletionValidation()) {
            return redirect()
                ->route('operator.complaint.show', $complaint)
                ->withErrors(['status' => 'Laporan sedang menunggu validasi penyelesaian oleh Super Admin. Perbarui Status tidak tersedia sampai pengajuan dikembalikan.']);
        }

        // PHASE 3 — Completion Validation Gate (server-side, not UI-only).
        //
        // An OPERATOR may NEVER set a complaint to `resolved` directly. Finishing
        // a complaint must go through the dedicated "Ajukan Penyelesaian" action
        // (`submitCompletion()`), which keeps the status at `in_progress` and moves
        // the review state to `pending_completion_validation` for Super Admin
        // validation. This closes the direct `in_progress → resolved` (and
        // `waiting_for_information → resolved`) path for Operators.
        //
        // Super Admin retains full authority here (its approval workflow is a
        // separate, later part), so only non-Super-Admin actors are blocked.
        if ($newStatusEnum === ComplaintStatus::Resolved && ! $request->user()->isSuperAdmin()) {
            return redirect()
                ->route('operator.complaint.show', $complaint)
                ->withErrors(['status' => 'Penyelesaian laporan harus diajukan melalui tombol "Ajukan Penyelesaian" agar divalidasi terlebih dahulu oleh Super Admin.']);
        }

        $currentStatus = $complaint->status;

        // Fast-fail on the loaded instance for a friendly error. The AUTHORITATIVE
        // check is re-run inside the transaction against the locked row (D-3.5).
        if (!$currentStatus->canTransitionTo($newStatusEnum)) {
            return redirect()
                ->route('operator.complaint.show', $complaint)
                ->withErrors(['status' => 'Transisi status dari "' . $currentStatus->label() . '" ke "' . $newStatusEnum->label() . '" tidak diizinkan.']);
        }

        // Business rule: rejected requires rejection_reason (non-empty)
        if ($newStatusEnum->requiresRejectionReason()) {
            $reason = trim($validated['rejection_reason'] ?? '');
            if ($reason === '') {
                return redirect()
                    ->route('operator.complaint.show', $complaint)
                    ->withErrors(['rejection_reason' => 'Alasan penolakan wajib diisi dan tidak boleh kosong.']);
            }
        }

        // Business rule: rejected and resolved require public_response_body
        if ($newStatusEnum->requiresPublicResponse()) {
            $publicBody = trim($validated['public_response_body'] ?? '');
            if ($publicBody === '') {
                return redirect()
                    ->route('operator.complaint.show', $complaint)
                    ->withErrors(['public_response_body' => 'Respons publik wajib diisi saat mengubah status ke "' . $newStatusEnum->label() . '".']);
            }
        }

        $user = $request->user();

        try {
            DB::transaction(function () use ($request, $complaint, $user, $newStatusEnum, $validated) {
                // PHASE 3 — Bagian 3 (D-3.5): CONCURRENCY GUARD.
                //
                // Never trust the controller-loaded instance. Lock the row and
                // re-derive the CURRENT status from the database. A concurrent
                // request that already moved the complaint (for example a double
                // "resolved → closed") makes the re-check below fail, so a second
                // status history row and a second audit event can never be written.
                $fresh = Complaint::whereKey($complaint->id)->lockForUpdate()->first();

                if (! $fresh) {
                    throw ValidationException::withMessages([
                        'status' => 'Laporan tidak ditemukan.',
                    ]);
                }

                // Re-assert scope/authorization against the freshly locked row.
                $this->authorizeComplaintAccess($user, $fresh);

                // PHASE N6A — F-1: authoritative re-check of the completion gate
                // against the LOCKED row. A concurrent "Ajukan Penyelesaian" that
                // moved `review_state` to `pending_completion_validation` after the
                // pre-transaction check must still block the Operator (D-3.5 style
                // stale-state protection). Super Admin is never blocked here (G7).
                if ($user->isOperator() && $fresh->isPendingCompletionValidation()) {
                    throw ValidationException::withMessages([
                        'status' => 'Laporan sedang menunggu validasi penyelesaian oleh Super Admin. Perbarui Status tidak tersedia sampai pengajuan dikembalikan.',
                    ]);
                }

                $currentStatus = $fresh->status;

                // Stale-state re-check: the transition must STILL be valid against
                // the locked database state (D-3.5). For closing this means the
                // current status must still be `resolved` (or `rejected`, per the
                // frozen matrix) — never a status that has already been closed.
                if (! $currentStatus->canTransitionTo($newStatusEnum)) {
                    throw ValidationException::withMessages([
                        'status' => 'Transisi status dari "' . $currentStatus->label() . '" ke "' . $newStatusEnum->label() . '" tidak diizinkan.',
                    ]);
                }

                // Compose history note: for rejected, prepend rejection reason
                $historyNote = null;
                if ($newStatusEnum->requiresRejectionReason()) {
                    $reason = trim($validated['rejection_reason']);
                    $historyNote = 'Alasan penolakan: ' . $reason;
                    if (!empty(trim($validated['note'] ?? ''))) {
                        $historyNote .= ' — ' . trim($validated['note']);
                    }
                } else {
                    $historyNote = $validated['note'] ?? null;
                }

                // PHASE 6C — Decision 7: `public_updated_at` is a DEPRECATED,
                // write-only legacy column. No new value is written here.
                $fresh->update([
                    'status'            => $newStatusEnum,
                    'resolved_at'       => $newStatusEnum === ComplaintStatus::Resolved ? now() : $fresh->resolved_at,
                ]);

                ComplaintStatusHistory::create([
                    'complaint_id' => $fresh->id,
                    'from_status'  => $currentStatus->value,
                    'to_status'    => $newStatusEnum->value,
                    'changed_by'   => $user->id,
                    'note'         => $historyNote,
                    'created_at'   => now(),
                ]);

                // Save public response for rejected and resolved
                if ($newStatusEnum->requiresPublicResponse()) {
                    $publicBody = trim($validated['public_response_body']);
                    ComplaintNote::create([
                        'complaint_id' => $fresh->id,
                        'author_id'    => $user->id,
                        'visibility'   => NoteVisibility::PublicResponse,
                        'body'         => $publicBody,
                        'created_at'   => now(),
                    ]);
                }

                $audit = AuditLog::create([
                    'actor_id'     => $user->id,
                    'action'       => 'complaint.status_updated',
                    'subject_type' => 'complaint',
                    'subject_id'   => $fresh->id,
                    'ip_address'   => $request->ip(),
                    'user_agent'   => $request->userAgent(),
                    'metadata'     => [
                        'reference_code'   => $fresh->reference_code,
                        'from_status'      => $currentStatus->value,
                        'to_status'        => $newStatusEnum->value,
                        'has_public_response' => $newStatusEnum->requiresPublicResponse(),
                    ],
                ]);

                // PHASE N4 — citizen-visible status milestones. ONE logical
                // notification per business action (this status update), anchored
                // on the canonical `complaint.status_updated` audit row. G7: a
                // direct Super-Admin `in_progress → resolved` emits M7 exactly
                // once here (the ONLY path that can reach `resolved` via this
                // action, since Operators are blocked above).
                $milestone = match ($newStatusEnum) {
                    ComplaintStatus::InProgress            => NotificationService::M8,
                    ComplaintStatus::WaitingForInformation => NotificationService::M9,
                    ComplaintStatus::Rejected              => NotificationService::M10,
                    ComplaintStatus::Closed                => NotificationService::M11,
                    ComplaintStatus::Resolved              => NotificationService::M7,
                    default                                => null,
                };

                if ($milestone !== null) {
                    app(NotificationService::class)->emit($fresh, $milestone, $audit->id, $user);
                }
            });
        } catch (ValidationException $e) {
            // Raised by the in-transaction stale-state re-check (or a vanished
            // row). Convert to the SAME redirect-with-errors convention used by
            // the pre-transaction checks above.
            return redirect()
                ->route('operator.complaint.show', $complaint)
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('operator.complaint.show', $complaint)
            ->with('success', 'Status laporan berhasil diperbarui ke: ' . $newStatusEnum->label());
    }

    // ─── PHASE 3 — Completion Validation Gate: Operator submits completion ─────

    /**
     * Ajukan Penyelesaian — Operator submits the finished work for Super Admin
     * validation. This is NOT completion approval and NOT a status change.
     *
     * Frozen behaviour:
     *   - status STAYS `in_progress` (never `resolved`); `resolved_at` is NOT set;
     *     no `resolved` status history is written; NO citizen-visible public
     *     response is created.
     *   - `review_state` moves to `pending_completion_validation`.
     *   - `completion_submitted_at` is stamped with the SERVER time.
     *   - `completion_return_reason` is cleared (a resubmission supersedes any
     *     previous return reason). `review_return_reason` is left untouched.
     *
     * Authority: OPERATOR ONLY (this endpoint is not Super Admin's path). The
     * complaint must be visible to the Operator (own Dinas/Unit) and be eligible
     * per {@see Complaint::canSubmitCompletion()}. Everything is atomic.
     */
    public function submitCompletion(Request $request, Complaint $complaint): RedirectResponse
    {
        // Server-side role gate: completion submission is an Operator action only.
        // (The route is shared with `super_admin`; Super Admin validates later, it
        // does not submit. Reject everyone else with 403.)
        if (! $request->user()->isOperator()) {
            abort(403, 'Hanya Operator yang dapat mengajukan penyelesaian.');
        }

        $this->authorizeComplaintAccess($request->user(), $complaint);

        $validated = $request->validate([
            // OPTIONAL narrative. It is stored as an INTERNAL note only — it is
            // NEVER published to the reporter here. Publication happens at Super
            // Admin approval (a later part).
            'completion_note' => ['nullable', 'string', 'max:5000'],
        ]);

        // Workflow gate (reads status + review_state). Rejected cases: not
        // `in_progress`, already `pending_completion_validation` (double submit),
        // `pending_initial_validation` / `returned_to_citizen` (not yet ours),
        // `resolved` / `rejected` / `closed` (not submittable).
        if (! $complaint->canSubmitCompletion()) {
            return redirect()
                ->route('operator.complaint.show', $complaint)
                ->withErrors(['completion' => 'Laporan ini tidak dapat diajukan penyelesaiannya pada status saat ini.']);
        }

        $operator = $request->user();
        $narrative = trim($validated['completion_note'] ?? '');

        DB::transaction(function () use ($complaint, $operator, $narrative) {
            // Re-assert the gate INSIDE the transaction (atomic guard against a
            // concurrent double submission). The status + review_state must still
            // satisfy the same rule.
            $fresh = Complaint::whereKey($complaint->id)->lockForUpdate()->first();

            if (! $fresh || ! $fresh->canSubmitCompletion()) {
                throw ValidationException::withMessages([
                    'completion' => 'Laporan ini tidak lagi dapat diajukan penyelesaiannya.',
                ]);
            }

            $oldReviewState = $fresh->review_state?->value;

            // Completion submission NEVER touches `status`, `resolved_at` or
            // `review_return_reason`.
            $fresh->update([
                'review_state'             => ComplaintReviewState::PendingCompletionValidation,
                'completion_submitted_at'  => now(),
                'completion_return_reason' => null,
            ]);

            // OPTIONAL narrative → INTERNAL note (citizen-invisible). Uses the
            // existing notes mechanism; no speculative schema. Its id is recorded
            // in the audit metadata so the Super Admin approval part can locate it.
            $completionNoteId = null;
            if ($narrative !== '') {
                $note = ComplaintNote::create([
                    'complaint_id' => $fresh->id,
                    'author_id'    => $operator->id,
                    'visibility'   => NoteVisibility::Internal,
                    'body'         => $narrative,
                    'created_at'   => now(),
                ]);
                $completionNoteId = $note->id;
            }

            $audit = AuditLog::create([
                'actor_id'     => $operator->id,
                'action'       => 'complaint.completion_submitted',
                'subject_type' => 'complaint',
                'subject_id'   => $fresh->id,
                'ip_address'   => request()->ip(),
                'user_agent'   => request()->userAgent(),
                'metadata'     => [
                    'reference_code'         => $fresh->reference_code,
                    'dinas_unit_id'          => $fresh->dinas_unit_id,
                    'operator_id'            => $operator->id,
                    'old_review_state'       => $oldReviewState,
                    'new_review_state'       => ComplaintReviewState::PendingCompletionValidation->value,
                    'status'                 => $fresh->status->value,
                    'completion_submitted_at' => $fresh->completion_submitted_at?->toIso8601String(),
                    'has_completion_note'    => $completionNoteId !== null,
                    'completion_note_id'     => $completionNoteId,
                ],
            ]);

            // PHASE N4 — M5: notify every ACTIVE Super Admin (never the Operator
            // actor). Anchored on the canonical completion-submitted audit row.
            app(NotificationService::class)->emit(
                $fresh,
                NotificationService::M5,
                $audit->id,
                $operator,
            );
        });

        return redirect()
            ->route('operator.complaint.show', $complaint)
            ->with('success', 'Penyelesaian laporan berhasil diajukan dan menunggu validasi Super Admin.');
    }

    // ─── Phase E: Internal Note / Public Response ─────────────────────────────

    /**
     * Tambahkan catatan ke laporan.
     * visibility = 'internal'       → hanya Operator/Admin/SuperAdmin
     * visibility = 'public_response' → dapat dilihat pelapor
     */
    public function addNote(Request $request, Complaint $complaint): RedirectResponse
    {
        $this->authorizeComplaintAccess($request->user(), $complaint);

        $validated = $request->validate([
            'body'       => ['required', 'string', 'max:5000'],
            'visibility' => ['required', 'in:internal,public_response'],
        ]);

        $visibilityEnum = NoteVisibility::from($validated['visibility']);

        DB::transaction(function () use ($request, $complaint, $validated, $visibilityEnum) {
            ComplaintNote::create([
                'complaint_id' => $complaint->id,
                'author_id'    => $request->user()->id,
                'visibility'   => $visibilityEnum,
                'body'         => $validated['body'],
                'created_at'   => now(),
            ]);

            // PHASE 6C — Decision 7: `public_updated_at` is a DEPRECATED,
            // write-only legacy column (no consumer). New writes are stopped.
            // The public response itself is still stored below/above as a note.

            AuditLog::create([
                'actor_id'     => $request->user()->id,
                'action'       => 'complaint.note_added',
                'subject_type' => 'complaint',
                'subject_id'   => $complaint->id,
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'metadata'     => [
                    'reference_code' => $complaint->reference_code,
                    'visibility'     => $validated['visibility'],
                ],
            ]);
        });

        $label = $visibilityEnum === NoteVisibility::Internal ? 'Catatan internal' : 'Respons publik';

        return redirect()
            ->route('operator.complaint.show', $complaint)
            ->with('success', $label . ' berhasil ditambahkan.');
    }

    // ─── Phase H: Attachment Download ─────────────────────────────────────────

    /**
     * Download lampiran complaint.
     * Operator dapat mengakses lampiran jika berhak melihat complaint tersebut.
     */
    public function downloadAttachment(Request $request, Complaint $complaint, ComplaintAttachment $attachment): Response
    {
        $this->authorizeComplaintAccess($request->user(), $complaint);

        // Ensure attachment belongs to this complaint (prevent IDOR)
        if ($attachment->complaint_id !== $complaint->id) {
            abort(403, 'Akses lampiran ditolak.');
        }

        $disk = Storage::disk($attachment->disk);
        if (!$disk->exists($attachment->path)) {
            abort(404, 'Berkas lampiran tidak ditemukan di server.');
        }

        return $disk->response($attachment->path, $attachment->original_name);
    }

    // ─── Authorization helper (Prompt 15 §4/§11) ──────────────────────────────

    /**
     * Enforce the Operator visibility rule on a SINGLE complaint.
     *
     * Super Admin has GLOBAL access (Prompt 15: Super Admin = global + status
     * mutation) and therefore bypasses the per-unit scope. Operators are limited
     * to complaints visible under the approved hybrid rule; anything else is
     * answered with 404 (not 403) so the endpoint does not leak the existence of
     * complaints outside the Operator's Dinas/Unit.
     */
    private function authorizeComplaintAccess(?User $user, Complaint $complaint): void
    {
        if ($user === null) {
            abort(403, 'Akses ditolak.');
        }

        // Super Admin is global — no per-unit scope.
        if ($user->isSuperAdmin()) {
            return;
        }

        if (! $complaint->isVisibleToOperator($user)) {
            abort(404);
        }
    }
}