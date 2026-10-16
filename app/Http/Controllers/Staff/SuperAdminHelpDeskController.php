<?php

namespace App\Http\Controllers\Staff;

use App\Enums\HelpDeskCategory;
use App\Enums\HelpTicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\ReplyHelpTicketRequest;
use App\Http\Requests\SuperAdmin\UpdateHelpTicketStatusRequest;
use App\Models\AuditLog;
use App\Models\HelpTicket;
use App\Models\HelpTicketMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * PROMPT TAHAP 2 — Help Desk panel (Super Admin only).
 *
 * Guarantees:
 *   - Authorization: route middleware `role:super_admin` (server-side). Admin and
 *     Operator can never reach these endpoints, regardless of UI.
 *   - Allowlisted filters/sort only — never an arbitrary column name.
 *   - Status changes obey the explicit transition matrix in `HelpTicketStatus`.
 *   - Sender identity is ALWAYS the authenticated user.
 *   - Audit metadata records action/actor/reference only; NEVER message contents.
 *   - Reading a ticket NEVER mutates the linked complaint.
 */
class SuperAdminHelpDeskController extends Controller
{
    private const PER_PAGE = 20;

    /**
     * Daftar seluruh tiket dengan filter + pencarian + paginasi.
     */
    public function index(Request $request): View
    {
        $query = HelpTicket::query()->with(['user', 'relatedComplaint']);

        // ── Search (reference_code, subject) — bounded, parameterised LIKE ────
        $search = $request->get('q');
        if (is_string($search) && trim($search) !== '') {
            $search = mb_substr(trim($search), 0, 200);
            $query->where(function ($qb) use ($search) {
                $qb->where('reference_code', 'like', "%{$search}%")
                   ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        // ── Status filter (enum allowlist) ───────────────────────────────────
        $status = $request->get('status');
        if (is_string($status) && HelpTicketStatus::tryFrom($status) !== null) {
            $query->where('status', $status);
        }

        // ── Category filter (enum allowlist) ─────────────────────────────────
        $category = $request->get('category');
        if (is_string($category) && HelpDeskCategory::tryFrom($category) !== null) {
            $query->where('category', $category);
        }

        // ── Sort (allowlist; never a client column name) ─────────────────────
        $sort = $request->get('sort');
        $sort = is_string($sort) ? $sort : 'newest';
        match ($sort) {
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'status' => $query->orderBy('status')->orderByDesc('updated_at'),
            default  => $query->orderByDesc('updated_at')->orderByDesc('id'),
        };

        $tickets = $query->paginate(self::PER_PAGE)->withQueryString();

        return view('super-admin.help.index', [
            'tickets'    => $tickets,
            'statuses'   => HelpTicketStatus::cases(),
            'categories' => HelpDeskCategory::cases(),
            'filters'    => [
                'q'        => $request->get('q'),
                'status'   => $status,
                'category' => $category,
                'sort'     => $sort,
            ],
        ]);
    }

    /**
     * Detail tiket + percakapan + konteks laporan terkait (read-only).
     */
    public function show(int $ticket): View
    {
        $helpTicket = HelpTicket::query()
            ->with(['user', 'messages.user', 'relatedComplaint.category'])
            ->findOrFail($ticket);

        return view('super-admin.help.show', [
            'ticket' => $helpTicket,
        ]);
    }

    /**
     * Balasan resmi Super Admin.
     */
    public function reply(ReplyHelpTicketRequest $request, int $ticket): RedirectResponse
    {
        $helpTicket = HelpTicket::query()->findOrFail($ticket);

        if (! $helpTicket->allowsStaffReply()) {
            return redirect()
                ->route('super-admin.help.show', $helpTicket)
                ->withErrors(['body' => 'Tiket ini tidak dapat dibalas pada status saat ini.']);
        }

        $user = $request->user();
        $body = $request->validated()['body'];

        DB::transaction(function () use ($request, $helpTicket, $user, $body) {
            HelpTicketMessage::create([
                'help_ticket_id' => $helpTicket->id,
                'user_id'        => $user->id,
                'body'           => $body,
            ]);

            // The first official reply on an `open` ticket begins handling.
            $next = $helpTicket->status->nextOnStaffReply();
            if ($next !== null && $helpTicket->status->canTransitionTo($next)) {
                $helpTicket->status = $next;
            }
            $helpTicket->save();

            AuditLog::create([
                'actor_id'     => $user->id,
                'action'       => 'help_ticket.staff_replied',
                'subject_type' => 'help_ticket',
                'subject_id'   => $helpTicket->id,
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'metadata'     => [
                    'reference_code' => $helpTicket->reference_code,
                    'sender_role'    => $user->role,
                    'status'         => $helpTicket->status->value,
                    'body_length'    => mb_strlen($body),
                ],
            ]);
        });

        return redirect()
            ->route('super-admin.help.show', $helpTicket)
            ->with('success', 'Balasan resmi telah dikirim.');
    }

    /**
     * Ubah status tiket melalui transisi yang VALID.
     */
    public function updateStatus(UpdateHelpTicketStatusRequest $request, int $ticket): RedirectResponse
    {
        $helpTicket = HelpTicket::query()->findOrFail($ticket);
        $target = HelpTicketStatus::from($request->validated()['status']);
        $user = $request->user();

        if ($helpTicket->status === $target) {
            return redirect()
                ->route('super-admin.help.show', $helpTicket)
                ->withErrors(['status' => 'Status tiket sudah ' . $target->label() . '.']);
        }

        if (! $helpTicket->status->canTransitionTo($target)) {
            return redirect()
                ->route('super-admin.help.show', $helpTicket)
                ->withErrors(['status' => 'Perubahan status dari ' . $helpTicket->status->label()
                    . ' ke ' . $target->label() . ' tidak diperbolehkan.']);
        }

        DB::transaction(function () use ($request, $helpTicket, $target, $user) {
            $from = $helpTicket->status;

            $helpTicket->status = $target;
            // `resolved_at` reflects the resolution moment; cleared on reopen.
            $helpTicket->resolved_at = $target === HelpTicketStatus::Resolved ? now() : null;
            $helpTicket->save();

            AuditLog::create([
                'actor_id'     => $user->id,
                'action'       => 'help_ticket.status_updated',
                'subject_type' => 'help_ticket',
                'subject_id'   => $helpTicket->id,
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'metadata'     => [
                    'reference_code' => $helpTicket->reference_code,
                    'from_status'    => $from->value,
                    'to_status'      => $target->value,
                ],
            ]);
        });

        return redirect()
            ->route('super-admin.help.show', $helpTicket)
            ->with('success', 'Status tiket diperbarui menjadi ' . $target->label() . '.');
    }
}
