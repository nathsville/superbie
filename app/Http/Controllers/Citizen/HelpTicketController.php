<?php

namespace App\Http\Controllers\Citizen;

use App\Enums\HelpDeskCategory;
use App\Enums\HelpTicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Citizen\SendHelpTicketMessageRequest;
use App\Http\Requests\Citizen\StoreHelpTicketRequest;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\HelpTicket;
use App\Models\HelpTicketMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * PROMPT TAHAP 2 — Help Desk / Pusat Bantuan (citizen side).
 *
 * Guarantees:
 *   - Authorization: route middleware `role:masyarakat` + the global `active`
 *     middleware, PLUS an explicit owner guard on every per-ticket action. A
 *     citizen can only ever reach their OWN tickets (IDOR closed): every query is
 *     scoped by `user_id`, so a foreign ticket id resolves to 404.
 *   - No complaint mutation: a related complaint is only a CONTEXT reference. The
 *     Help Desk never changes a complaint's status/category/routing/notes.
 *   - Sender identity is ALWAYS the authenticated user — never request input.
 *   - Message bodies are rendered escaped in Blade (stored XSS closed).
 *   - Audit metadata records the action/actor/reference only; NEVER message or
 *     description contents.
 */
class HelpTicketController extends Controller
{
    /** Tickets per page in the citizen list. */
    private const PER_PAGE = 15;

    /**
     * Daftar tiket milik pengguna yang sedang login.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // Owner-scoped by construction (never a client-supplied user id).
        $query = HelpTicket::query()->forUser($user->id);

        // Optional status filter (allowlist).
        $status = $request->get('status');
        if (is_string($status) && HelpTicketStatus::tryFrom($status) !== null) {
            $query->byStatus($status);
        }

        $tickets = $query->with('relatedComplaint')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('citizen.help.index', [
            'tickets'  => $tickets,
            'statuses' => HelpTicketStatus::cases(),
            'filters'  => ['status' => $status],
        ]);
    }

    /**
     * Form tiket baru.
     */
    public function create(Request $request): View
    {
        // The citizen may link ONLY their own complaints — the dropdown is
        // populated from their own rows and the server re-validates ownership.
        $complaints = Complaint::query()
            ->forReporter($request->user()->id)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get(['id', 'reference_code', 'title']);

        return view('citizen.help.create', [
            'categories' => HelpDeskCategory::cases(),
            'complaints' => $complaints,
        ]);
    }

    /**
     * Simpan tiket baru.
     */
    public function store(StoreHelpTicketRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $ticket = DB::transaction(function () use ($request, $user, $validated) {
            $ticket = HelpTicket::create([
                'reference_code'       => $this->generateReferenceCode(),
                'user_id'              => $user->id,
                'subject'              => $validated['subject'],
                'category'             => $validated['category'],
                'description'          => $validated['description'],
                'related_complaint_id' => $validated['related_complaint_id'] ?? null,
                'status'               => HelpTicketStatus::Open,
            ]);

            // The opening description is ALSO the first conversation entry, so the
            // thread is complete without duplicating the text elsewhere.
            HelpTicketMessage::create([
                'help_ticket_id' => $ticket->id,
                'user_id'        => $user->id,
                'body'           => $validated['description'],
            ]);

            AuditLog::create([
                'actor_id'     => $user->id,
                'action'       => 'help_ticket.created',
                'subject_type' => 'help_ticket',
                'subject_id'   => $ticket->id,
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'metadata'     => [
                    'reference_code'       => $ticket->reference_code,
                    'category'             => $ticket->category->value,
                    'status'               => $ticket->status->value,
                    // Boolean ONLY — never the subject/description contents.
                    'has_related_complaint' => $ticket->related_complaint_id !== null,
                ],
            ]);

            return $ticket;
        });

        return redirect()
            ->route('citizen.help.show', $ticket)
            ->with('success', 'Tiket bantuan berhasil dibuat dengan Nomor Tiket: ' . $ticket->reference_code);
    }

    /**
     * Detail tiket + seluruh percakapan (hanya milik pengguna).
     */
    public function show(Request $request, int $ticket): View
    {
        $helpTicket = $this->findOwnedTicket($request, $ticket);

        $helpTicket->load([
            'messages.user',
            // Only the reference fields of the related complaint are needed; the
            // full complaint (with internal notes) is NEVER loaded here.
            'relatedComplaint',
        ]);

        return view('citizen.help.show', [
            'ticket' => $helpTicket,
        ]);
    }

    /**
     * Kirim pesan lanjutan pada tiket milik pengguna.
     *
     * Allowed ONLY while the ticket is open / in_progress / waiting_for_user. A
     * `resolved` ticket must be reopened explicitly, and a `closed` ticket is
     * terminal — neither silently accepts a plain reply.
     */
    public function reply(SendHelpTicketMessageRequest $request, int $ticket): RedirectResponse
    {
        $helpTicket = $this->findOwnedTicket($request, $ticket);

        if (! $helpTicket->allowsCitizenReply()) {
            return redirect()
                ->route('citizen.help.show', $helpTicket)
                ->withErrors(['body' => $this->replyBlockedMessage($helpTicket)]);
        }

        $user = $request->user();
        $body = $request->validated()['body'];

        DB::transaction(function () use ($request, $helpTicket, $user, $body) {
            HelpTicketMessage::create([
                'help_ticket_id' => $helpTicket->id,
                'user_id'        => $user->id,
                'body'           => $body,
            ]);

            // A reply on `waiting_for_user` hands the ticket back to the handler.
            $next = $helpTicket->status->nextOnCitizenReply();
            if ($next !== null && $helpTicket->status->canTransitionTo($next)) {
                $helpTicket->status = $next;
            }
            // Touch updated_at so the list reflects the last activity.
            $helpTicket->save();

            AuditLog::create([
                'actor_id'     => $user->id,
                'action'       => 'help_ticket.message_sent',
                'subject_type' => 'help_ticket',
                'subject_id'   => $helpTicket->id,
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'metadata'     => [
                    'reference_code' => $helpTicket->reference_code,
                    'sender_role'    => $user->role,
                    'status'         => $helpTicket->status->value,
                    // Length only — NEVER the message body.
                    'body_length'    => mb_strlen($body),
                ],
            ]);
        });

        return redirect()
            ->route('citizen.help.show', $helpTicket)
            ->with('success', 'Pesan Anda telah terkirim.');
    }

    /**
     * Buka kembali tiket yang berstatus `resolved` (validated transition).
     *
     * Only `resolved → in_progress` is allowed, and a message is REQUIRED so the
     * reopen is never silent. `closed` tickets cannot be reopened.
     */
    public function reopen(SendHelpTicketMessageRequest $request, int $ticket): RedirectResponse
    {
        $helpTicket = $this->findOwnedTicket($request, $ticket);

        if (! $helpTicket->allowsCitizenReopen()) {
            return redirect()
                ->route('citizen.help.show', $helpTicket)
                ->withErrors(['body' => 'Tiket ini tidak dapat dibuka kembali.']);
        }

        $user = $request->user();
        $body = $request->validated()['body'];

        DB::transaction(function () use ($request, $helpTicket, $user, $body) {
            $from = $helpTicket->status;
            $to   = HelpTicketStatus::InProgress;

            if (! $from->canTransitionTo($to)) {
                // Defensive: the guard above already blocks this.
                return;
            }

            HelpTicketMessage::create([
                'help_ticket_id' => $helpTicket->id,
                'user_id'        => $user->id,
                'body'           => $body,
            ]);

            $helpTicket->status = $to;
            $helpTicket->resolved_at = null; // no longer resolved
            $helpTicket->save();

            AuditLog::create([
                'actor_id'     => $user->id,
                'action'       => 'help_ticket.reopened',
                'subject_type' => 'help_ticket',
                'subject_id'   => $helpTicket->id,
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'metadata'     => [
                    'reference_code' => $helpTicket->reference_code,
                    'from_status'    => $from->value,
                    'to_status'      => $to->value,
                    'body_length'    => mb_strlen($body),
                ],
            ]);
        });

        return redirect()
            ->route('citizen.help.show', $helpTicket)
            ->with('success', 'Tiket berhasil dibuka kembali dan sedang ditangani.');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Resolve a ticket that BELONGS to the authenticated citizen.
     *
     * Owner-scoped query: a ticket owned by another user (or a non-existent id)
     * resolves to 404 — the existence of another user's ticket is never revealed.
     */
    private function findOwnedTicket(Request $request, int $ticketId): HelpTicket
    {
        return HelpTicket::query()
            ->whereKey($ticketId)
            ->forUser($request->user()->id)
            ->firstOrFail();
    }

    private function replyBlockedMessage(HelpTicket $ticket): string
    {
        return $ticket->status === HelpTicketStatus::Resolved
            ? 'Tiket ini sudah selesai. Buka kembali tiket untuk melanjutkan percakapan.'
            : 'Tiket ini sudah ditutup dan tidak dapat menerima pesan baru.';
    }

    /**
     * Server-generated unique reference: HD-YYYYMMDD-XXXX.
     */
    private function generateReferenceCode(): string
    {
        $prefix = 'HD-' . now()->format('Ymd') . '-';

        do {
            $candidate = $prefix . strtoupper(Str::random(4));
        } while (HelpTicket::where('reference_code', $candidate)->exists());

        return $candidate;
    }
}
