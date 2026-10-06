<?php

namespace App\Http\Controllers\Staff;

use App\Enums\ComplaintStatus;
use App\Enums\NoteVisibility;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\ComplaintCategory;
use App\Models\ComplaintNote;
use App\Models\ComplaintStatusHistory;
use App\Models\DinasUnit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
            ->with(['category', 'assignee', 'reporter'])
            ->orderBy('submitted_at', 'desc');

        // Prompt 15 §4 — Operator scope (hybrid). Super Admin remains GLOBAL.
        // An Operator with no Dinas/Unit sees NOTHING (denied, no fallback).
        if ($request->user()->isOperator()) {
            $query->visibleToOperator($request->user());
        }

        // Search
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                  ->orWhere('reference_code', 'like', '%' . $search . '%');
            });
        }

        // Filter by status (allowlist only)
        if ($status = $request->get('status')) {
            $statusEnum = ComplaintStatus::tryFrom($status);
            if ($statusEnum !== null) {
                $query->where('status', $statusEnum->value);
            }
        }

        // Filter by category
        if ($categoryId = $request->get('category_id')) {
            $query->where('category_id', (int) $categoryId);
        }

        // Filter by assignment: mine, unassigned, all
        if ($assignment = $request->get('assignment')) {
            if ($assignment === 'mine') {
                $query->where('assigned_to', $request->user()->id);
            } elseif ($assignment === 'unassigned') {
                $query->whereNull('assigned_to');
            }
        }

        $complaints = $query->paginate(15)->withQueryString();
        $categories  = ComplaintCategory::active()->get();
        $statuses    = ComplaintStatus::cases();

        return view('operator.complaint.index', compact(
            'complaints',
            'categories',
            'statuses',
        ));
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
            'dinasUnit',
            'reporter',
            'assignee',
            'attachments',
            'statusHistories.changedBy',
            'internalNotes.author',
            'publicResponses.author',
        ]);

        $categories  = ComplaintCategory::active()->get();
        $operators   = User::where('role', 'operator')->where('is_active', true)->orderBy('name')->get();
        $statuses    = ComplaintStatus::cases();

        // Valid ACTUAL destinations for this complaint = Dinas/Unit mapped to its
        // category (Prompt 5C). For NEW assignment, only ACTIVE units are offered.
        // The complaint's already-stored destination is always included so an
        // inactive (historical) destination remains visible/selectable-as-current.
        $mappedDinasUnits = $complaint->category
            ? $complaint->category->dinasUnits()
                ->where(function ($q) use ($complaint) {
                    $q->where('dinas_units.is_active', true);
                    if ($complaint->dinas_unit_id) {
                        $q->orWhere('dinas_units.id', $complaint->dinas_unit_id);
                    }
                })
                ->orderBy('dinas_units.sort_order')
                ->orderBy('dinas_units.name')
                ->get()
            : collect();

        // Allowed next statuses from current status
        $allowedTransitions = $complaint->status->allowedTransitions();

        return view('operator.complaint.show', compact(
            'complaint',
            'categories',
            'operators',
            'statuses',
            'allowedTransitions',
            'mappedDinasUnits',
        ));
    }

    // ─── Phase C: Update Category ─────────────────────────────────────────────

    /**
     * Ubah kategori laporan.
     * Hanya kategori aktif yang dapat dipilih.
     */
    public function updateCategory(Request $request, Complaint $complaint): RedirectResponse
    {
        $this->authorizeComplaintAccess($request->user(), $complaint);

        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:complaint_categories,id'],
        ]);

        // Enforce active-category rule
        $category = ComplaintCategory::where('id', $validated['category_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $oldCategoryId = $complaint->category_id;

        DB::transaction(function () use ($request, $complaint, $category, $oldCategoryId) {
            $complaint->update(['category_id' => $category->id]);

            AuditLog::create([
                'actor_id'     => $request->user()->id,
                'action'       => 'complaint.category_updated',
                'subject_type' => 'complaint',
                'subject_id'   => $complaint->id,
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'metadata'     => [
                    'reference_code'   => $complaint->reference_code,
                    'old_category_id'  => $oldCategoryId,
                    'new_category_id'  => $category->id,
                    'new_category_name' => $category->name,
                ],
            ]);
        });

        return redirect()
            ->route('operator.complaint.show', $complaint)
            ->with('success', 'Kategori laporan berhasil diperbarui ke: ' . $category->name);
    }

    // ─── Phase C2: Update Actual Dinas/Unit Destination (Prompt 5C) ───────────

    /**
     * Tetapkan Dinas/Unit tujuan AKTUAL untuk laporan.
     *
     * Server-side rules (Prompt 5C §E/§F):
     *  - Destination MUST be mapped to the complaint's category (many-to-many).
     *  - For a NEW destination, the Dinas/Unit MUST be active.
     *  - The complaint's currently stored destination is always accepted
     *    (so an inactive historical destination is not force-cleared).
     *  - Null clears the destination.
     *
     * Historical stability: stored on the complaint; later mapping changes do
     * not alter existing complaints.
     */
    public function updateDestination(Request $request, Complaint $complaint): RedirectResponse
    {
        $this->authorizeComplaintAccess($request->user(), $complaint);

        $validated = $request->validate([
            'dinas_unit_id' => ['nullable', 'integer'],
        ]);

        $dinasUnitId = $validated['dinas_unit_id'] ?: null;
        $dinasUnitName = null;

        if ($dinasUnitId !== null) {
            $category = $complaint->category;

            if (! $category) {
                return redirect()
                    ->route('operator.complaint.show', $complaint)
                    ->withErrors(['dinas_unit_id' => 'Laporan belum memiliki kategori sehingga tujuan tidak dapat ditentukan.']);
            }

            // Must be mapped to the category.
            $mapped = $category->dinasUnits()->where('dinas_units.id', $dinasUnitId)->exists();
            if (! $mapped) {
                return redirect()
                    ->route('operator.complaint.show', $complaint)
                    ->withErrors(['dinas_unit_id' => 'Dinas/Unit tersebut tidak terhubung dengan kategori laporan ini.']);
            }

            // New destination must be active — unless it is the complaint's
            // already-stored destination (historical inactive unit stays valid).
            $dinasUnit = DinasUnit::find($dinasUnitId);
            if (! $dinasUnit) {
                return redirect()
                    ->route('operator.complaint.show', $complaint)
                    ->withErrors(['dinas_unit_id' => 'Dinas/Unit tidak ditemukan.']);
            }

            $isCurrentDestination = (int) $complaint->dinas_unit_id === (int) $dinasUnitId;
            if (! $dinasUnit->is_active && ! $isCurrentDestination) {
                return redirect()
                    ->route('operator.complaint.show', $complaint)
                    ->withErrors(['dinas_unit_id' => 'Dinas/Unit tidak aktif dan tidak dapat dipilih sebagai tujuan baru.']);
            }

            $dinasUnitName = $dinasUnit->name;
        }

        $oldDinasUnitId = $complaint->dinas_unit_id;

        DB::transaction(function () use ($request, $complaint, $dinasUnitId, $dinasUnitName, $oldDinasUnitId) {
            $complaint->update(['dinas_unit_id' => $dinasUnitId]);

            AuditLog::create([
                'actor_id'     => $request->user()->id,
                'action'       => 'complaint.destination_updated',
                'subject_type' => 'complaint',
                'subject_id'   => $complaint->id,
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'metadata'     => [
                    'reference_code'      => $complaint->reference_code,
                    'old_dinas_unit_id'   => $oldDinasUnitId,
                    'new_dinas_unit_id'   => $dinasUnitId,
                    'new_dinas_unit_name' => $dinasUnitName,
                ],
            ]);
        });

        $message = $dinasUnitId
            ? 'Tujuan Dinas/Unit berhasil ditetapkan: ' . $dinasUnitName
            : 'Tujuan Dinas/Unit berhasil dihapus.';

        return redirect()
            ->route('operator.complaint.show', $complaint)
            ->with('success', $message);
    }

    // ─── Phase D: Assignment ──────────────────────────────────────────────────

    /**
     * Tugaskan laporan ke Operator.
     * Target harus berperan operator dan aktif.
     * Nilai null (null/0) berarti unassign.
     */
    public function assign(Request $request, Complaint $complaint): RedirectResponse
    {
        $this->authorizeComplaintAccess($request->user(), $complaint);

        $validated = $request->validate([
            'assigned_to' => ['nullable', 'integer'],
        ]);

        $assignedTo = $validated['assigned_to'] ?: null;
        $assigneeName = 'Tidak ditugaskan';

        if ($assignedTo !== null) {
            $targetUser = User::where('id', $assignedTo)
                ->where('role', 'operator')
                ->where('is_active', true)
                ->first();

            if (!$targetUser) {
                return redirect()
                    ->route('operator.complaint.show', $complaint)
                    ->withErrors(['assigned_to' => 'Target penugasan tidak valid. Hanya Operator aktif yang dapat ditugaskan.']);
            }

            $assigneeName = $targetUser->name;
        }

        $oldAssignedTo = $complaint->assigned_to;

        DB::transaction(function () use ($request, $complaint, $assignedTo, $assigneeName, $oldAssignedTo) {
            $complaint->update(['assigned_to' => $assignedTo]);

            AuditLog::create([
                'actor_id'     => $request->user()->id,
                'action'       => 'complaint.assigned',
                'subject_type' => 'complaint',
                'subject_id'   => $complaint->id,
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'metadata'     => [
                    'reference_code'  => $complaint->reference_code,
                    'old_assigned_to' => $oldAssignedTo,
                    'new_assigned_to' => $assignedTo,
                    'assignee_name'   => $assigneeName,
                ],
            ]);
        });

        $message = $assignedTo
            ? 'Laporan berhasil ditugaskan ke: ' . $assigneeName
            : 'Penugasan laporan berhasil dihapus.';

        return redirect()
            ->route('operator.complaint.show', $complaint)
            ->with('success', $message);
    }

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

        $currentStatus = $complaint->status;

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

        DB::transaction(function () use ($request, $complaint, $currentStatus, $newStatusEnum, $validated) {
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

            $complaint->update([
                'status'            => $newStatusEnum,
                'public_updated_at' => now(),
                'resolved_at'       => $newStatusEnum === ComplaintStatus::Resolved ? now() : $complaint->resolved_at,
            ]);

            ComplaintStatusHistory::create([
                'complaint_id' => $complaint->id,
                'from_status'  => $currentStatus->value,
                'to_status'    => $newStatusEnum->value,
                'changed_by'   => $request->user()->id,
                'note'         => $historyNote,
                'created_at'   => now(),
            ]);

            // Save public response for rejected and resolved
            if ($newStatusEnum->requiresPublicResponse()) {
                $publicBody = trim($validated['public_response_body']);
                ComplaintNote::create([
                    'complaint_id' => $complaint->id,
                    'author_id'    => $request->user()->id,
                    'visibility'   => NoteVisibility::PublicResponse,
                    'body'         => $publicBody,
                    'created_at'   => now(),
                ]);
            }

            AuditLog::create([
                'actor_id'     => $request->user()->id,
                'action'       => 'complaint.status_updated',
                'subject_type' => 'complaint',
                'subject_id'   => $complaint->id,
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'metadata'     => [
                    'reference_code'   => $complaint->reference_code,
                    'from_status'      => $currentStatus->value,
                    'to_status'        => $newStatusEnum->value,
                    'has_public_response' => $newStatusEnum->requiresPublicResponse(),
                ],
            ]);
        });

        return redirect()
            ->route('operator.complaint.show', $complaint)
            ->with('success', 'Status laporan berhasil diperbarui ke: ' . $newStatusEnum->label());
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

            // Update public_updated_at when a public response is added
            if ($visibilityEnum === NoteVisibility::PublicResponse) {
                $complaint->update(['public_updated_at' => now()]);
            }

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