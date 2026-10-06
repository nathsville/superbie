<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Super Admin — Audit & Security surface (Prompt 10).
 *
 * READ-ONLY view over the EXISTING append-only `audit_logs` table.
 *
 * Guarantees:
 *   - Authorization: route middleware `role:super_admin` (server-side).
 *   - No mutation: this controller exposes no create/update/delete action and
 *     no such routes are registered. Audit records are never modified.
 *   - Real data only: list + filters are backed by actual DB rows; no fake
 *     records, counts, timestamps, IPs, or security metrics are generated.
 *   - Secret-safe presentation: metadata is passed through a presentation-layer
 *     redaction so credentials can never be rendered.
 */
class SuperAdminAuditController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'search'    => $request->get('search'),
            'action'    => $request->get('action'),
            'subject'   => $request->get('subject'),
            'actor'     => $request->get('actor'),
            'date_from' => $request->get('date_from'),
            'date_to'   => $request->get('date_to'),
        ];

        $logs   = $this->audit->paginate($filters);
        $options = $this->audit->filterOptions();

        return view('super-admin.audit.index', [
            'logs'          => $logs,
            'filters'       => $filters,
            'actionOptions' => $options['actions'],
            'subjectOptions' => $options['subjects'],
            'actorOptions'  => $options['actors'],
            'totalLogs'     => $logs->total(),
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        $auditLog->load('actor');

        $safeMetadata = $this->audit->redactMetadata($auditLog->metadata);

        return view('super-admin.audit.show', [
            'log'          => $auditLog,
            'safeMetadata' => $safeMetadata,
            'isRedacted'   => $safeMetadata !== ($auditLog->metadata ?? []),
        ]);
    }
}
