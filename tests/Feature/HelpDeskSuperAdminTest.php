<?php

namespace Tests\Feature;

use App\Enums\ComplaintStatus;
use App\Enums\HelpDeskCategory;
use App\Enums\HelpTicketStatus;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\DinasUnit;
use App\Models\HelpTicket;
use App\Models\HelpTicketMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesLocationFixtures;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT TAHAP 2 — Help Desk Super Admin panel + access control.
 *
 * Covers: full-ticket visibility, detail/conversation read, official reply,
 * legal/illegal status transitions, sender forgery rejection, Admin/Operator
 * lockout, IDOR-safe filters/pagination, and audit metadata hygiene.
 */
class HelpDeskSuperAdminTest extends TestCase
{
    use RefreshDatabase;
    use CreatesLocationFixtures;

    private User $superAdmin;
    private User $admin;
    private User $operator;
    private User $citizen;

    private ComplaintCategory $category;
    private DinasUnit $unit;
    private int $kecamatanId;
    private int $kelurahanId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->admin      = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->citizen    = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->category = ComplaintCategory::create([
            'name' => 'Infrastruktur Jalan', 'slug' => 'infrastruktur-jalan',
            'is_active' => true, 'sort_order' => 1,
        ]);

        $this->unit = DinasUnit::create([
            'name' => 'Dinas PUPR', 'code' => 'PUPR', 'is_active' => true, 'sort_order' => 1,
        ]);
        $this->unit->categories()->attach($this->category->id);

        $this->operator = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unit->id,
        ]);

        [$this->kecamatanId, $this->kelurahanId] = $this->makeLocationPair();
    }

    private static int $seq = 0;

    private function makeTicket(User $owner, array $overrides = []): HelpTicket
    {
        static::$seq++;

        $ticket = HelpTicket::create(array_merge([
            'reference_code' => 'HD-SA-' . str_pad((string) static::$seq, 4, '0', STR_PAD_LEFT),
            'user_id'        => $owner->id,
            'subject'        => 'Kendala aplikasi ' . static::$seq,
            'category'       => HelpDeskCategory::AppUsage,
            'description'    => 'Aplikasi tidak merespons saat membuka riwayat.',
            'status'         => HelpTicketStatus::Open,
        ], $overrides));

        HelpTicketMessage::create([
            'help_ticket_id' => $ticket->id,
            'user_id'        => $owner->id,
            'body'           => $ticket->description,
        ]);

        return $ticket;
    }

    // =========================================================================
    // VISIBILITY + DETAIL
    // =========================================================================

    public function test_super_admin_sees_all_tickets(): void
    {
        $a = $this->makeTicket($this->citizen);
        $b = $this->makeTicket($this->admin);

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.help.index'));
        $response->assertOk();
        $response->assertSee($a->reference_code);
        $response->assertSee($b->reference_code);
    }

    public function test_super_admin_can_open_detail_and_read_conversation(): void
    {
        $ticket = $this->makeTicket($this->citizen, ['status' => HelpTicketStatus::InProgress]);
        HelpTicketMessage::create([
            'help_ticket_id' => $ticket->id,
            'user_id'        => $this->citizen->id,
            'body'           => 'PESAN-KONVERSASI-UJI',
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.help.show', $ticket));
        $response->assertOk();
        $response->assertSee('PESAN-KONVERSASI-UJI');
        $response->assertSee($ticket->reference_code);
    }

    public function test_detail_404_for_missing_ticket(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.help.show', 999999))
            ->assertNotFound();
    }

    // =========================================================================
    // REPLY
    // =========================================================================

    public function test_super_admin_can_reply_and_open_moves_to_in_progress(): void
    {
        $ticket = $this->makeTicket($this->citizen, ['status' => HelpTicketStatus::Open]);

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.help.reply', $ticket), ['body' => 'Kami sedang menangani kendala Anda.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(HelpTicketStatus::InProgress, $ticket->fresh()->status);
        $last = HelpTicketMessage::where('help_ticket_id', $ticket->id)->latest('id')->first();
        $this->assertSame($this->superAdmin->id, $last->user_id);
    }

    public function test_super_admin_reply_is_audited_without_recording_body(): void
    {
        $ticket = $this->makeTicket($this->citizen, ['status' => HelpTicketStatus::InProgress]);

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.help.reply', $ticket), ['body' => 'RAHASIA-BALASAN-PANJANG']);

        $log = AuditLog::where('action', 'help_ticket.staff_replied')->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('RAHASIA-BALASAN-PANJANG', json_encode($log->metadata));
        $this->assertArrayHasKey('body_length', $log->metadata);
    }

    public function test_super_admin_cannot_reply_on_closed_ticket(): void
    {
        $ticket = $this->makeTicket($this->citizen, ['status' => HelpTicketStatus::Closed]);

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.help.reply', $ticket), ['body' => 'Balasan telat.'])
            ->assertSessionHasErrors('body');

        $this->assertSame(1, HelpTicketMessage::where('help_ticket_id', $ticket->id)->count());
    }

    public function test_super_admin_cannot_forge_sender(): void
    {
        $ticket = $this->makeTicket($this->citizen, ['status' => HelpTicketStatus::InProgress]);

        $this->actingAs($this->superAdmin)->post(route('super-admin.help.reply', $ticket), [
            'body'    => 'Balasan resmi.',
            'user_id' => $this->citizen->id,
        ]);

        $last = HelpTicketMessage::where('help_ticket_id', $ticket->id)->latest('id')->first();
        $this->assertSame($this->superAdmin->id, $last->user_id);
    }

    // =========================================================================
    // STATUS TRANSITIONS
    // =========================================================================

    public function test_super_admin_can_apply_a_legal_transition(): void
    {
        $ticket = $this->makeTicket($this->citizen, ['status' => HelpTicketStatus::InProgress]);

        $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.help.status', $ticket), ['status' => HelpTicketStatus::Resolved->value])
            ->assertSessionHasNoErrors();

        $fresh = $ticket->fresh();
        $this->assertSame(HelpTicketStatus::Resolved, $fresh->status);
        $this->assertNotNull($fresh->resolved_at);
    }

    public function test_illegal_transition_is_rejected(): void
    {
        // closed → anything is illegal.
        $ticket = $this->makeTicket($this->citizen, ['status' => HelpTicketStatus::Closed]);

        $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.help.status', $ticket), ['status' => HelpTicketStatus::Open->value])
            ->assertSessionHasErrors('status');

        $this->assertSame(HelpTicketStatus::Closed, $ticket->fresh()->status);
    }

    public function test_illegal_backward_transition_is_rejected(): void
    {
        // resolved → waiting_for_user is not in the matrix.
        $ticket = $this->makeTicket($this->citizen, ['status' => HelpTicketStatus::Resolved, 'resolved_at' => now()]);

        $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.help.status', $ticket), ['status' => HelpTicketStatus::WaitingForUser->value])
            ->assertSessionHasErrors('status');

        $this->assertSame(HelpTicketStatus::Resolved, $ticket->fresh()->status);
    }

    public function test_unknown_status_value_is_rejected(): void
    {
        $ticket = $this->makeTicket($this->citizen);

        $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.help.status', $ticket), ['status' => 'not_a_status'])
            ->assertSessionHasErrors('status');
    }

    public function test_status_change_is_audited(): void
    {
        $ticket = $this->makeTicket($this->citizen, ['status' => HelpTicketStatus::Open]);

        $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.help.status', $ticket), ['status' => HelpTicketStatus::InProgress->value]);

        $log = AuditLog::where('action', 'help_ticket.status_updated')->first();
        $this->assertNotNull($log);
        $this->assertSame('open', $log->metadata['from_status']);
        $this->assertSame('in_progress', $log->metadata['to_status']);
    }

    // =========================================================================
    // ACCESS CONTROL — Admin / Operator / Citizen lockout
    // =========================================================================

    public function test_admin_and_operator_and_citizen_cannot_access_panel(): void
    {
        $ticket = $this->makeTicket($this->citizen);

        foreach ([$this->admin, $this->operator, $this->citizen] as $user) {
            $this->actingAs($user)->get(route('super-admin.help.index'))->assertForbidden();
            $this->actingAs($user)->get(route('super-admin.help.show', $ticket))->assertForbidden();
            $this->actingAs($user)
                ->post(route('super-admin.help.reply', $ticket), ['body' => 'Nyahak.'])
                ->assertForbidden();
            $this->actingAs($user)
                ->patch(route('super-admin.help.status', $ticket), ['status' => HelpTicketStatus::Resolved->value])
                ->assertForbidden();
        }

        // No message or status change leaked through.
        $this->assertSame(1, HelpTicketMessage::where('help_ticket_id', $ticket->id)->count());
        $this->assertSame(HelpTicketStatus::Open, $ticket->fresh()->status);
    }

    public function test_guest_cannot_access_panel(): void
    {
        $this->get(route('super-admin.help.index'))->assertRedirect(route('login'));
    }

    // =========================================================================
    // FILTERS / SEARCH / PAGINATION (IDOR-safe)
    // =========================================================================

    public function test_status_and_category_filters_are_allowlisted(): void
    {
        $openTicket   = $this->makeTicket($this->citizen, ['status' => HelpTicketStatus::Open, 'category' => HelpDeskCategory::AccountLogin]);
        $closedTicket = $this->makeTicket($this->citizen, ['status' => HelpTicketStatus::Closed, 'category' => HelpDeskCategory::AppUsage]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.help.index', ['status' => 'open', 'category' => 'account_login']));
        $response->assertOk();
        $response->assertSee($openTicket->reference_code);
        $response->assertDontSee($closedTicket->reference_code);

        // A bogus status value is ignored (never reaches the query as a column).
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.help.index', ['status' => 'status; DROP TABLE help_tickets']))
            ->assertOk();
    }

    public function test_search_by_reference_and_subject(): void
    {
        $match   = $this->makeTicket($this->citizen, ['subject' => 'MASALAH-UNIK-LOGIN']);
        $noMatch = $this->makeTicket($this->citizen, ['subject' => 'Kendala lain biasa']);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.help.index', ['q' => 'MASALAH-UNIK-LOGIN']));
        $response->assertOk();
        $response->assertSee($match->reference_code);
        $response->assertDontSee($noMatch->reference_code);
    }

    // =========================================================================
    // RELATED COMPLAINT CONTEXT (no internal notes leak)
    // =========================================================================

    public function test_detail_shows_related_complaint_reference_but_not_internal_notes(): void
    {
        $complaint = Complaint::create([
            'reference_code' => 'LPW-SA-0001',
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $this->category->id,
            'dinas_unit_id'  => $this->unit->id,
            'kecamatan_id'   => $this->kecamatanId,
            'kelurahan_id'   => $this->kelurahanId,
            'title'          => 'Laporan terkait konteks',
            'description'    => 'Deskripsi laporan terkait.',
            'status'         => ComplaintStatus::InProgress,
            'submitted_at'   => now(),
        ]);

        \App\Models\ComplaintNote::create([
            'complaint_id' => $complaint->id,
            'author_id'    => $this->operator->id,
            'visibility'   => 'internal',
            'body'         => 'CATATAN-INTERNAL-RAHASIA',
        ]);

        $ticket = $this->makeTicket($this->citizen, ['related_complaint_id' => $complaint->id]);

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.help.show', $ticket));
        $response->assertOk();
        $response->assertSee('LPW-SA-0001');
        $response->assertDontSee('CATATAN-INTERNAL-RAHASIA');
    }

    // =========================================================================
    // REGRESSION — complaint/rating untouched
    // =========================================================================

    public function test_help_desk_never_touches_complaint_or_rating_tables(): void
    {
        $complaint = Complaint::create([
            'reference_code' => 'LPW-SA-REG-1',
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $this->category->id,
            'dinas_unit_id'  => $this->unit->id,
            'kecamatan_id'   => $this->kecamatanId,
            'kelurahan_id'   => $this->kelurahanId,
            'title'          => 'Laporan regresi',
            'description'    => 'Deskripsi regresi.',
            'status'         => ComplaintStatus::InProgress,
            'submitted_at'   => now(),
        ]);

        $ticket = $this->makeTicket($this->citizen, ['related_complaint_id' => $complaint->id]);

        $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.help.status', $ticket), ['status' => HelpTicketStatus::Resolved->value]);

        $this->assertSame(ComplaintStatus::InProgress, $complaint->fresh()->status);
        $this->assertSame(0, \App\Models\ComplaintRating::count());
    }
}
