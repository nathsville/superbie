<?php

namespace App\Http\Controllers\Staff;

use App\Enums\ComplaintStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\DinasUnit;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Super Admin — "Semua Laporan" (Prompt 12).
 *
 * READ-ONLY monitoring / inspection surface over existing complaints.
 *
 * Guarantees:
 *   - Authorization: route middleware `role:super_admin` (server-side) plus the
 *     global `active` middleware. Hiding navigation is never relied upon.
 *   - No mutation: this controller exposes NO create/update/delete action and no
 *     such routes are registered. The complaint workflow (status, category,
 *     routing, assignment, notes, attachments) is completely untouched.
 *   - Real data only: list, filters, timeline and audit context are backed by
 *     actual DB rows. No fake counts, statuses, officers or statistics.
 *   - Historical stability: the ACTUAL stored `dinas_unit_id` is displayed as-is.
 *     Nothing is re-resolved, re-mapped or re-assigned on read.
 *   - Privacy: audit metadata is passed through the existing Prompt 10
 *     redaction service; no credentials/secrets are ever rendered.
 */
class SuperAdminComplaintController extends Controller
{
    /**
     * Daftar seluruh laporan (server-side search, filter, pagination).
     */
    public function index(Request $request): View
    {
        // Eager-load every relation rendered by the list to avoid N+1 queries.
        $query = Complaint::query()
            ->with(['reporter', 'category', 'dinasUnit', 'assignee']);

        // ── Search (reference_code, title, description) ──────────────────────
        $search = $request->get('q');
        if (is_string($search) && trim($search) !== '') {
            $search = mb_substr(trim($search), 0, 200);
            $query->where(function ($qb) use ($search) {
                $qb->where('reference_code', 'like', "%{$search}%")
                   ->orWhere('title', 'like', "%{$search}%")
                   ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // ── Status filter (allowlist of the 7 official statuses only) ────────
        $status = $request->get('status');
        if (is_string($status) && $status !== '' && ComplaintStatus::tryFrom($status) !== null) {
            $query->where('status', $status);
        }

        // ── Category filter ──────────────────────────────────────────────────
        $categoryId = filter_var($request->get('category'), FILTER_VALIDATE_INT);
        if ($categoryId !== false) {
            $query->where('category_id', $categoryId);
        }

        // ── Dinas/Unit filter (historical stored destination) ────────────────
        $dinasUnitId = filter_var($request->get('dinas_unit'), FILTER_VALIDATE_INT);
        if ($dinasUnitId !== false) {
            $query->where('dinas_unit_id', $dinasUnitId);
        }

        // ── Date range on the real `submitted_at` timestamp ──────────────────
        $from = $this->validDate($request->get('date_from'));
        if ($from !== null) {
            $query->whereDate('submitted_at', '>=', $from);
        }

        $to = $this->validDate($request->get('date_to'));
        if ($to !== null) {
            $query->whereDate('submitted_at', '<=', $to);
        }

        // ── Sort (mirrors the existing admin monitoring pattern) ─────────────
        $sort = $request->get('sort');
        $sort = is_string($sort) ? $sort : 'newest';
        match ($sort) {
            'oldest' => $query->orderBy('submitted_at')->orderBy('id'),
            'status' => $query->orderBy('status')->orderByDesc('submitted_at'),
            default  => $query->orderByDesc('submitted_at')->orderByDesc('id'),
        };

        $complaints = $query->paginate(20)->withQueryString();

        return view('super-admin.complaints.index', [
            'complaints' => $complaints,
            'statuses'   => ComplaintStatus::cases(),
            // Filter options come from ACTUAL master data (including inactive
            // rows, so historical complaints remain filterable).
            'categories' => ComplaintCategory::orderBy('name')->get(['id', 'name']),
            'dinasUnits' => DinasUnit::orderBy('name')->get(['id', 'name']),
            'filters'    => [
                'q'          => $request->get('q'),
                'status'     => $status,
                'category'   => $request->get('category'),
                'dinas_unit' => $request->get('dinas_unit'),
                'date_from'  => $request->get('date_from'),
                'date_to'    => $request->get('date_to'),
                'sort'       => $sort,
            ],
        ]);
    }

    /**
     * Detail laporan (read-only) — rincian, routing historis, assignment,
     * lampiran, timeline status, catatan, dan konteks audit.
     */
    public function show(Complaint $complaint, AuditLogService $audit): View
    {
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

        // Read-only audit context for THIS complaint. Reuses the Prompt 10
        // redaction service (no duplicate redaction implementation).
        $safeAuditLogs = AuditLog::query()
            ->with('actor')
            ->where('subject_type', 'complaint')
            ->where('subject_id', $complaint->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (AuditLog $log) => [
                'log'      => $log,
                'metadata' => $audit->redactMetadata($log->metadata),
            ]);

        return view('super-admin.complaints.show', [
            'complaint'     => $complaint,
            'safeAuditLogs' => $safeAuditLogs,
        ]);
    }

    /**
     * Accept only well-formed Y-m-d dates; anything else is ignored so malformed
     * input can never reach the query builder.
     */
    private function validDate(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return ($date && $date->format('Y-m-d') === $value) ? $value : null;
    }
}
