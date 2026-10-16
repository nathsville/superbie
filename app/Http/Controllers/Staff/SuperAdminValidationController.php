<?php

namespace App\Http\Controllers\Staff;

use App\Enums\ComplaintReviewState;
use App\Enums\ComplaintStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\ApproveInitialValidationRequest;
use App\Http\Requests\SuperAdmin\ReturnInitialValidationRequest;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\DinasUnit;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * PHASE 2 — Super Admin initial validation.
 *
 * Every new citizen complaint MUST pass through this surface before an Operator
 * can handle it. This controller:
 *   - lists the initial-validation queue (oldest waiting first);
 *   - shows the full validation detail (reporter, category, mapped Dinas/Units,
 *     and the resolved Operator for each candidate Dinas/Unit);
 *   - APPROVES validation (category correction + Dinas routing + automatic
 *     Operator resolution), atomically;
 *   - RETURNS the complaint to the reporter for correction, with a mandatory
 *     reason.
 *
 * Authorization: route middleware `role:super_admin` (server-side). No other
 * role may reach these routes. No complaint status enum value is added — the
 * review state is a separate, isolated concept.
 */
class SuperAdminValidationController extends Controller
{
    /**
     * The initial-validation queue: complaints awaiting Super Admin validation,
     * OLDEST WAITING FIRST (deterministic: submitted_at ASC, then id ASC).
     */
    public function index(Request $request): View
    {
        // Eager-load every relation rendered by the queue (no N+1). The Dinas
        // operator is eager-loaded too, so the "operator ready?" hint is cheap.
        $complaints = Complaint::query()
            ->with(['reporter', 'category', 'dinasUnit.operator', 'kecamatan', 'kelurahan'])
            ->pendingInitialValidation()
            ->paginate(20)
            ->withQueryString();

        return view('super-admin.validation.index', [
            'complaints' => $complaints,
        ]);
    }

    /**
     * Validation detail for a single complaint awaiting validation.
     */
    public function show(Complaint $complaint): View
    {
        // Only complaints in the queue are actionable here; anything else is a
        // monitoring concern (see "Semua Laporan"). Guard server-side.
        if (! $complaint->isAwaitingInitialValidation()) {
            abort(404);
        }

        $complaint->load([
            'reporter',
            'category',
            'attachments',
            'kecamatan',
            'kelurahan',
            'statusHistories.changedBy',
        ]);

        // All ACTIVE categories (Super Admin may correct the category), with their
        // ACTIVE mapped Dinas/Units + resolved Operator eager-loaded in ONE pass
        // (no N+1) so the form can update the destination dropdown live.
        $categories = ComplaintCategory::where('is_active', true)
            ->with(['dinasUnits' => function ($query) {
                $query->where('dinas_units.is_active', true)
                    ->with('operator')
                    ->orderBy('dinas_units.sort_order')
                    ->orderBy('dinas_units.name');
            }])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Robustness: if the complaint's current category was DEACTIVATED after
        // submission it is not in the active list, yet the form must still show
        // it as the current (invalid) selection so the Super Admin knows it must
        // be corrected. It is appended with no Dinas options (the final category
        // must be active — enforced in approve()).
        $currentCategory = $complaint->category;
        if ($currentCategory && ! $categories->contains('id', $currentCategory->id)) {
            $currentCategory->setRelation('dinasUnits', collect());
            $categories->prepend($currentCategory);
        }

        // Candidate Dinas/Units for the CURRENT category (server-rendered default).
        $mappedDinasUnits = $complaint->category_id
            ? ($categories->firstWhere('id', $complaint->category_id)?->dinasUnits ?? collect())
            : collect();

        // Per-category candidate Dinas/Units (server-side truth) so the form
        // updates the destination dropdown when the category is corrected.
        $dinasOptionsByCategory = $categories->mapWithKeys(function (ComplaintCategory $category) {
            return [$category->id => $category->dinasUnits->map(fn (DinasUnit $unit) => [
                'id'            => $unit->id,
                'name'          => $unit->name,
                'has_operator'  => $unit->operator !== null,
                'operator_name' => $unit->operator?->name,
            ])->values()->all()];
        });

        return view('super-admin.validation.show', [
            'complaint'              => $complaint,
            'categories'             => $categories,
            'mappedDinasUnits'       => $mappedDinasUnits,
            'dinasOptionsByCategory' => $dinasOptionsByCategory,
        ]);
    }

    /**
     * Approve initial validation: save the FINAL category, route to a valid
     * Dinas/Unit, resolve the Operator AUTOMATICALLY from that Dinas/Unit, move
     * the review state to `validated_for_operator`, and advance the lifecycle
     * status (submitted → in_progress). Fully atomic.
     */
    public function approve(ApproveInitialValidationRequest $request, Complaint $complaint): RedirectResponse
    {
        // The complaint must still be awaiting validation (guards replay/double
        // approval). Reviewed OUTSIDE the transaction is fine (read-only check);
        // the state is re-checked inside the transaction below.
        if (! $complaint->isAwaitingInitialValidation()) {
            return redirect()
                ->route('super-admin.validation.index')
                ->withErrors(['validation' => 'Laporan ini tidak sedang menunggu validasi awal.']);
        }

        $validated = $request->validated();
        $category = ComplaintCategory::find($validated['category_id']);

        if (! $category || ! $category->is_active) {
            throw ValidationException::withMessages([
                'category_id' => 'Kategori final tidak aktif atau tidak ditemukan.',
            ]);
        }

        $dinasUnit = DinasUnit::find($validated['dinas_unit_id']);

        if (! $dinasUnit) {
            throw ValidationException::withMessages([
                'dinas_unit_id' => 'Dinas/Unit yang dipilih tidak ditemukan.',
            ]);
        }

        // The chosen Dinas/Unit MUST be mapped to the FINAL category.
        $mapped = $category->dinasUnits()->where('dinas_units.id', $dinasUnit->id)->exists();
        if (! $mapped) {
            throw ValidationException::withMessages([
                'dinas_unit_id' => 'Dinas/Unit tersebut tidak terhubung dengan kategori final laporan ini.',
            ]);
        }

        // New routing requires an ACTIVE Dinas/Unit.
        if (! $dinasUnit->is_active) {
            throw ValidationException::withMessages([
                'dinas_unit_id' => 'Dinas/Unit tidak aktif dan tidak dapat dipilih sebagai tujuan baru.',
            ]);
        }

        // The Dinas/Unit MUST have an Operator, otherwise the complaint must NOT
        // be routed silently. It stays in a safe validation state.
        $operator = $dinasUnit->operator;
        if (! $operator) {
            throw ValidationException::withMessages([
                'dinas_unit_id' => 'Dinas/Unit ini belum memiliki Operator. Tetapkan Operator terlebih dahulu sebelum memvalidasi.',
            ]);
        }

        $oldCategoryId   = $complaint->category_id;
        $oldDinasUnitId  = $complaint->dinas_unit_id;
        $categoryChanged = (int) $oldCategoryId !== (int) $category->id;
        $dinasChanged    = (int) $oldDinasUnitId !== (int) $dinasUnit->id;

        DB::transaction(function () use ($request, $complaint, $category, $dinasUnit, $oldCategoryId, $oldDinasUnitId, $categoryChanged, $dinasChanged) {
            // Re-assert the review state INSIDE the transaction (atomic guard).
            $fresh = Complaint::whereKey($complaint->id)->lockForUpdate()->first();
            if (! $fresh || ! $fresh->isAwaitingInitialValidation()) {
                throw ValidationException::withMessages([
                    'validation' => 'Laporan ini tidak lagi menunggu validasi awal.',
                ]);
            }

            $currentStatus = $fresh->status;

            // Advance the lifecycle status through the ACTIVE transition matrix:
            // submitted → in_progress. A legacy status (under_review,
            // waiting_for_information, closed) has no outgoing edge, so it is
            // never resurrected into the active flow by a stale review_state.
            $newStatus = $currentStatus;
            if ($currentStatus->canTransitionTo(ComplaintStatus::InProgress)) {
                $newStatus = ComplaintStatus::InProgress;
            }

            $fresh->update([
                'category_id'             => $category->id,
                'dinas_unit_id'           => $dinasUnit->id,
                'review_state'            => ComplaintReviewState::ValidatedForOperator,
                'review_return_reason'    => null,
                'review_state_updated_at' => now(),
                'status'                  => $newStatus,
            ]);

            if ($newStatus !== $currentStatus) {
                \App\Models\ComplaintStatusHistory::create([
                    'complaint_id' => $fresh->id,
                    'from_status'  => $currentStatus->value,
                    'to_status'    => $newStatus->value,
                    'changed_by'   => $request->user()->id,
                    'note'         => 'Validasi awal disetujui; laporan diteruskan ke Dinas/Unit.',
                    'created_at'   => now(),
                ]);
            }

            $baseMetadata = [
                'reference_code'    => $fresh->reference_code,
                'old_category_id'   => $oldCategoryId,
                'new_category_id'   => $category->id,
                'new_category_name' => $category->name,
                'old_dinas_unit_id' => $oldDinasUnitId,
                'new_dinas_unit_id' => $dinasUnit->id,
                'new_dinas_unit_name' => $dinasUnit->name,
                'old_review_state'  => ComplaintReviewState::PendingInitialValidation->value,
                'new_review_state'  => ComplaintReviewState::ValidatedForOperator->value,
                'resolved_operator_id' => $dinasUnit->operator?->id,
                'resolved_operator_name' => $dinasUnit->operator?->name,
            ];

            // Category correction (only when it actually changed).
            if ($categoryChanged) {
                AuditLog::create([
                    'actor_id'     => $request->user()->id,
                    'action'       => 'complaint.category_corrected',
                    'subject_type' => 'complaint',
                    'subject_id'   => $fresh->id,
                    'ip_address'   => $request->ip(),
                    'user_agent'   => $request->userAgent(),
                    'metadata'     => [
                        'reference_code'  => $fresh->reference_code,
                        'old_category_id' => $oldCategoryId,
                        'new_category_id' => $category->id,
                        'new_category_name' => $category->name,
                    ],
                ]);
            }

            // Dinas routing.
            AuditLog::create([
                'actor_id'     => $request->user()->id,
                'action'       => 'complaint.dinas_assigned',
                'subject_type' => 'complaint',
                'subject_id'   => $fresh->id,
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'metadata'     => [
                    'reference_code'    => $fresh->reference_code,
                    'old_dinas_unit_id' => $oldDinasUnitId,
                    'new_dinas_unit_id' => $dinasUnit->id,
                    'new_dinas_unit_name' => $dinasUnit->name,
                    'resolved_operator_id' => $dinasUnit->operator?->id,
                ],
            ]);

            // The approval itself.
            $approvalAudit = AuditLog::create([
                'actor_id'     => $request->user()->id,
                'action'       => 'complaint.initial_validation_approved',
                'subject_type' => 'complaint',
                'subject_id'   => $fresh->id,
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'metadata'     => $baseMetadata,
            ]);

            // PHASE N4 — M2: one logical milestone to the owning Operator + the
            // reporter (approval + routing + category correction are ONE event).
            // Never the acting Super Admin. Anchored on the canonical audit row.
            app(NotificationService::class)->emit(
                $fresh,
                NotificationService::M2,
                $approvalAudit->id,
                $request->user(),
            );
        });

        $message = 'Validasi awal disetujui. Laporan diteruskan ke ' . $dinasUnit->name
            . ' dan Operator ' . ($operator->name ?? '—') . '.';

        if ($categoryChanged) {
            $message = 'Kategori diperbarui dan validasi awal disetujui. ' . $message;
        }

        return redirect()
            ->route('super-admin.validation.index')
            ->with('success', $message);
    }

    /**
     * Return the complaint to the reporter for correction. The reason is stored
     * as a reporter-visible workflow message and audited. The complaint status is
     * NOT changed to rejected — this is a validation return, not a rejection.
     */
    public function returnToCitizen(ReturnInitialValidationRequest $request, Complaint $complaint): RedirectResponse
    {
        if (! $complaint->isAwaitingInitialValidation()) {
            return redirect()
                ->route('super-admin.validation.index')
                ->withErrors(['validation' => 'Laporan ini tidak sedang menunggu validasi awal.']);
        }

        $validated = $request->validated();
        $reason = trim($validated['reason']);

        DB::transaction(function () use ($request, $complaint, $reason) {
            $fresh = Complaint::whereKey($complaint->id)->lockForUpdate()->first();
            if (! $fresh || ! $fresh->isAwaitingInitialValidation()) {
                throw ValidationException::withMessages([
                    'validation' => 'Laporan ini tidak lagi menunggu validasi awal.',
                ]);
            }

            // Status is deliberately NOT touched (a return is not a rejection).
            $fresh->update([
                'review_state'            => ComplaintReviewState::ReturnedToCitizen,
                'review_return_reason'    => $reason,
                'review_state_updated_at' => now(),
            ]);

            $audit = AuditLog::create([
                'actor_id'     => $request->user()->id,
                'action'       => 'complaint.initial_validation_returned',
                'subject_type' => 'complaint',
                'subject_id'   => $fresh->id,
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'metadata'     => [
                    'reference_code' => $fresh->reference_code,
                    'old_review_state' => ComplaintReviewState::PendingInitialValidation->value,
                    'new_review_state' => ComplaintReviewState::ReturnedToCitizen->value,
                    'return_reason'  => $reason,
                    'status_unchanged' => $fresh->status->value,
                ],
            ]);

            // PHASE N4 — M3: notify ONLY the reporter. The internal return reason
            // is NEVER placed in the notification body/data.
            app(NotificationService::class)->emit(
                $fresh,
                NotificationService::M3,
                $audit->id,
                $request->user(),
            );
        });

        return redirect()
            ->route('super-admin.validation.index')
            ->with('success', 'Laporan dikembalikan kepada pelapor untuk diperbaiki.');
    }
}
