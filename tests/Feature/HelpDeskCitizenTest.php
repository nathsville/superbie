<?php

namespace Tests\Feature;

use App\Enums\HelpDeskCategory;
use App\Enums\HelpTicketStatus;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\ComplaintRating;
use App\Models\DinasUnit;
use App\Models\HelpTicket;
use App\Models\HelpTicketMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesLocationFixtures;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT TAHAP 2 — Help Desk / Pusat Bantuan (citizen side).
 *
 * Covers: creation, owner-scoped listing/detail/messaging (IDOR), sender
 * forgery, validation (empty/whitespace/over-length), the related-complaint
 * ownership rule (no existence oracle), the reply/reopen status rules, XSS
 * escaping, and the guarantee that a ticket never mutates a complaint.
 */
class HelpDeskCitizenTest extends TestCase
{
    use RefreshDatabase;
    use CreatesLocationFixtures;

    private User $owner;
    private User $other;
    private User $superAdmin;
    private User $operator;
    private User $admin;

    private ComplaintCategory $category;
    private DinasUnit $unit;
    private int $kecamatanId;
    private int $kelurahanId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner      = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
        $this->other      = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->admin      = User::factory()->create(['role' => 'admin', 'is_active' => true]);

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
            'reference_code'       => 'HD-TEST-' . str_pad((string) static::$seq, 4, '0', STR_PAD_LEFT),
            'user_id'              => $owner->id,
            'subject'              => 'Kendala login akun ' . static::$seq,
            'category'             => HelpDeskCategory::AccountLogin,
            'description'          => 'Saya tidak dapat login ke akun saya sejak pagi.',
            'related_complaint_id' => null,
            'status'               => HelpTicketStatus::Open,
        ], $overrides));

        HelpTicketMessage::create([
            'help_ticket_id' => $ticket->id,
            'user_id'        => $owner->id,
            'body'           => $ticket->description,
        ]);

        return $ticket;
    }

    private function makeComplaint(User $owner, array $overrides = []): Complaint
    {
        static::$seq++;

        return Complaint::create(array_merge([
            'reference_code' => 'LPW-HD-' . str_pad((string) static::$seq, 4, '0', STR_PAD_LEFT),
            'reporter_id'    => $owner->id,
            'category_id'    => $this->category->id,
            'dinas_unit_id'  => $this->unit->id,
            'kecamatan_id'   => $this->kecamatanId,
            'kelurahan_id'   => $this->kelurahanId,
            'title'          => 'Laporan uji help desk ' . static::$seq,
            'description'    => 'Deskripsi laporan untuk konteks tiket bantuan.',
            'status'         => \App\Enums\ComplaintStatus::InProgress,
            'submitted_at'   => now(),
        ], $overrides));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'subject'     => 'Tidak bisa mengirim laporan',
            'category'    => HelpDeskCategory::ComplaintSubmission->value,
            'description' => 'Setiap kali menekan tombol kirim, halaman memuat ulang tanpa hasil.',
        ], $overrides);
    }

    // =========================================================================
    // CREATION
    // =========================================================================

    public function test_citizen_can_create_ticket_when_logged_in(): void
    {
        $response = $this->actingAs($this->owner)->post(route('citizen.help.store'), $this->validPayload());

        $ticket = HelpTicket::first();
        $this->assertNotNull($ticket);
        $response->assertRedirect(route('citizen.help.show', $ticket));

        $this->assertSame($this->owner->id, $ticket->user_id);
        $this->assertSame(HelpTicketStatus::Open, $ticket->status);
        $this->assertSame(HelpDeskCategory::ComplaintSubmission, $ticket->category);
        $this->assertStringStartsWith('HD-', $ticket->reference_code);

        // The opening description is mirrored as the first conversation entry.
        $this->assertSame(1, $ticket->messages()->count());
        $this->assertSame($this->owner->id, $ticket->messages()->first()->user_id);
    }

    public function test_guest_cannot_create_ticket(): void
    {
        $this->post(route('citizen.help.store'), $this->validPayload())
            ->assertRedirect(route('login'));

        $this->assertSame(0, HelpTicket::count());
    }

    public function test_ticket_creation_is_audited_without_recording_contents(): void
    {
        $this->actingAs($this->owner)->post(route('citizen.help.store'), $this->validPayload());

        $log = AuditLog::where('action', 'help_ticket.created')->first();
        $this->assertNotNull($log);
        $this->assertSame('help_ticket', $log->subject_type);
        $this->assertSame($this->owner->id, $log->actor_id);

        // Metadata must NOT contain the subject/description text.
        $metadata = $log->metadata;
        $this->assertArrayHasKey('reference_code', $metadata);
        $this->assertArrayHasKey('category', $metadata);
        $this->assertArrayNotHasKey('description', $metadata);
        $this->assertStringNotContainsString('Tidak bisa mengirim laporan', json_encode($metadata));
    }

    // =========================================================================
    // OWNER-SCOPED ACCESS (IDOR)
    // =========================================================================

    public function test_citizen_can_see_own_ticket_list(): void
    {
        $own = $this->makeTicket($this->owner);
        $this->makeTicket($this->other);

        $response = $this->actingAs($this->owner)->get(route('citizen.help.index'));
        $response->assertOk();
        $response->assertSee($own->reference_code);
        $response->assertDontSee('HD-TEST-0002');
    }

    public function test_citizen_cannot_view_another_users_ticket(): void
    {
        $foreign = $this->makeTicket($this->other);

        $this->actingAs($this->owner)
            ->get(route('citizen.help.show', $foreign))
            ->assertNotFound();
    }

    public function test_citizen_cannot_read_another_users_ticket_messages(): void
    {
        $foreign = $this->makeTicket($this->other, ['description' => 'RAHASIA-MILIK-ORANG-LAIN']);

        $this->actingAs($this->owner)
            ->get(route('citizen.help.show', $foreign))
            ->assertNotFound();
    }

    public function test_citizen_cannot_post_message_to_another_users_ticket(): void
    {
        $foreign = $this->makeTicket($this->other);
        $before = HelpTicketMessage::count();

        $this->actingAs($this->owner)
            ->post(route('citizen.help.reply', $foreign), ['body' => 'Pesan menyusup'])
            ->assertNotFound();

        $this->assertSame($before, HelpTicketMessage::count());
    }

    // =========================================================================
    // SENDER / OWNER FORGERY
    // =========================================================================

    public function test_owner_and_sender_cannot_be_forged_on_create(): void
    {
        $this->actingAs($this->owner)->post(route('citizen.help.store'), $this->validPayload([
            // Malicious extra fields — must be ignored.
            'user_id' => $this->other->id,
            'status'  => HelpTicketStatus::Closed->value,
        ]));

        $ticket = HelpTicket::first();
        $this->assertSame($this->owner->id, $ticket->user_id, 'owner must come from the session');
        $this->assertSame(HelpTicketStatus::Open, $ticket->status, 'status must not come from the request');
    }

    public function test_sender_cannot_be_forged_on_reply(): void
    {
        $ticket = $this->makeTicket($this->owner);

        $this->actingAs($this->owner)->post(route('citizen.help.reply', $ticket), [
            'body'    => 'Pesan lanjutan dari masyarakat.',
            'user_id' => $this->superAdmin->id,
            'sender_id' => $this->superAdmin->id,
        ]);

        $last = HelpTicketMessage::where('help_ticket_id', $ticket->id)->latest('id')->first();
        $this->assertSame($this->owner->id, $last->user_id, 'sender must be the authenticated user');
    }

    // =========================================================================
    // VALIDATION
    // =========================================================================

    public function test_ticket_requires_valid_category(): void
    {
        $this->actingAs($this->owner)
            ->post(route('citizen.help.store'), $this->validPayload(['category' => 'not_a_real_category']))
            ->assertSessionHasErrors('category');

        $this->assertSame(0, HelpTicket::count());
    }

    public function test_ticket_subject_and_description_bounds(): void
    {
        $this->actingAs($this->owner)
            ->post(route('citizen.help.store'), $this->validPayload(['subject' => 'abc']))
            ->assertSessionHasErrors('subject');

        $this->actingAs($this->owner)
            ->post(route('citizen.help.store'), $this->validPayload(['description' => str_repeat('x', 5001)]))
            ->assertSessionHasErrors('description');
    }

    public function test_reply_rejects_empty_and_whitespace_only_body(): void
    {
        $ticket = $this->makeTicket($this->owner);

        $this->actingAs($this->owner)
            ->post(route('citizen.help.reply', $ticket), ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->actingAs($this->owner)
            ->post(route('citizen.help.reply', $ticket), ['body' => '     '])
            ->assertSessionHasErrors('body');

        // Only the opening message exists.
        $this->assertSame(1, HelpTicketMessage::where('help_ticket_id', $ticket->id)->count());
    }

    public function test_reply_rejects_over_length_body(): void
    {
        $ticket = $this->makeTicket($this->owner);

        $this->actingAs($this->owner)
            ->post(route('citizen.help.reply', $ticket), ['body' => str_repeat('y', 5001)])
            ->assertSessionHasErrors('body');
    }

    // =========================================================================
    // RELATED COMPLAINT OWNERSHIP
    // =========================================================================

    public function test_citizen_can_link_own_complaint(): void
    {
        $complaint = $this->makeComplaint($this->owner);

        $this->actingAs($this->owner)->post(route('citizen.help.store'), $this->validPayload([
            'related_complaint_id' => $complaint->id,
        ]));

        $this->assertSame($complaint->id, HelpTicket::first()->related_complaint_id);
    }

    public function test_citizen_cannot_link_another_users_complaint(): void
    {
        $foreign = $this->makeComplaint($this->other);

        $this->actingAs($this->owner)
            ->post(route('citizen.help.store'), $this->validPayload([
                'related_complaint_id' => $foreign->id,
            ]))
            ->assertSessionHasErrors('related_complaint_id');

        $this->assertSame(0, HelpTicket::count());
    }

    public function test_related_complaint_error_does_not_leak_existence(): void
    {
        $foreign = $this->makeComplaint($this->other);

        $expected = 'Laporan terkait tidak ditemukan atau bukan milik Anda.';

        // The SAME message must be returned whether the complaint exists but
        // belongs to somebody else, or does not exist at all (no existence oracle).
        $this->actingAs($this->owner)
            ->post(route('citizen.help.store'), $this->validPayload(['related_complaint_id' => $foreign->id]))
            ->assertSessionHasErrors(['related_complaint_id' => $expected]);

        $this->actingAs($this->owner)
            ->post(route('citizen.help.store'), $this->validPayload(['related_complaint_id' => 999999]))
            ->assertSessionHasErrors(['related_complaint_id' => $expected]);
    }

    public function test_ticket_does_not_change_the_related_complaint_status(): void
    {
        $complaint = $this->makeComplaint($this->owner, ['status' => \App\Enums\ComplaintStatus::InProgress]);

        $this->actingAs($this->owner)->post(route('citizen.help.store'), $this->validPayload([
            'related_complaint_id' => $complaint->id,
        ]));

        $ticket = HelpTicket::first();
        $this->actingAs($this->owner)->post(route('citizen.help.reply', $ticket), ['body' => 'Tambahan info.']);

        $this->assertSame(\App\Enums\ComplaintStatus::InProgress, $complaint->fresh()->status);
    }

    // =========================================================================
    // CONVERSATION STATUS RULES
    // =========================================================================

    public function test_citizen_can_reply_while_replyable(): void
    {
        $ticket = $this->makeTicket($this->owner, ['status' => HelpTicketStatus::InProgress]);

        $this->actingAs($this->owner)
            ->post(route('citizen.help.reply', $ticket), ['body' => 'Baik, terima kasih atas arahannya.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, HelpTicketMessage::where('help_ticket_id', $ticket->id)->count());
    }

    public function test_citizen_reply_on_waiting_for_user_returns_to_in_progress(): void
    {
        $ticket = $this->makeTicket($this->owner, ['status' => HelpTicketStatus::WaitingForUser]);

        $this->actingAs($this->owner)->post(route('citizen.help.reply', $ticket), ['body' => 'Berikut informasi tambahannya.']);

        $this->assertSame(HelpTicketStatus::InProgress, $ticket->fresh()->status);
    }

    public function test_resolved_ticket_rejects_plain_reply_but_allows_reopen(): void
    {
        $ticket = $this->makeTicket($this->owner, [
            'status' => HelpTicketStatus::Resolved,
            'resolved_at' => now(),
        ]);

        $this->actingAs($this->owner)
            ->post(route('citizen.help.reply', $ticket), ['body' => 'Masih bermasalah.'])
            ->assertSessionHasErrors('body');

        $this->assertSame(HelpTicketStatus::Resolved, $ticket->fresh()->status);

        // Reopen is allowed and moves resolved → in_progress.
        $this->actingAs($this->owner)
            ->post(route('citizen.help.reopen', $ticket), ['body' => 'Kendala muncul kembali.'])
            ->assertSessionHasNoErrors();

        $fresh = $ticket->fresh();
        $this->assertSame(HelpTicketStatus::InProgress, $fresh->status);
        $this->assertNull($fresh->resolved_at);
    }

    public function test_closed_ticket_is_terminal(): void
    {
        $ticket = $this->makeTicket($this->owner, ['status' => HelpTicketStatus::Closed]);

        $this->actingAs($this->owner)
            ->post(route('citizen.help.reply', $ticket), ['body' => 'Coba lagi.'])
            ->assertSessionHasErrors('body');

        $this->actingAs($this->owner)
            ->post(route('citizen.help.reopen', $ticket), ['body' => 'Buka dong.'])
            ->assertSessionHasErrors('body');

        $this->assertSame(HelpTicketStatus::Closed, $ticket->fresh()->status);
        $this->assertSame(1, HelpTicketMessage::where('help_ticket_id', $ticket->id)->count());
    }

    public function test_reopen_requires_a_message(): void
    {
        $ticket = $this->makeTicket($this->owner, ['status' => HelpTicketStatus::Resolved, 'resolved_at' => now()]);

        $this->actingAs($this->owner)
            ->post(route('citizen.help.reopen', $ticket), ['body' => '   '])
            ->assertSessionHasErrors('body');

        $this->assertSame(HelpTicketStatus::Resolved, $ticket->fresh()->status);
    }

    // =========================================================================
    // XSS / OUTPUT ESCAPING
    // =========================================================================

    public function test_html_like_message_is_rendered_escaped(): void
    {
        $payload = '<script>alert("xss")</script>';
        $ticket = $this->makeTicket($this->owner, ['status' => HelpTicketStatus::InProgress]);

        $this->actingAs($this->owner)->post(route('citizen.help.reply', $ticket), ['body' => $payload]);

        $response = $this->actingAs($this->owner)->get(route('citizen.help.show', $ticket));
        $response->assertOk();
        // Escaped form is present, raw tag is NOT.
        $response->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false);
        $response->assertDontSee('<script>alert("xss")</script>', false);
    }

    public function test_html_like_subject_is_rendered_escaped(): void
    {
        $ticket = $this->makeTicket($this->owner, ['subject' => '<img src=x onerror=alert(1)>']);

        $response = $this->actingAs($this->owner)->get(route('citizen.help.show', $ticket));
        $response->assertOk();
        $response->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    // =========================================================================
    // UI PRESENCE
    // =========================================================================

    public function test_create_form_is_reachable_and_shows_categories(): void
    {
        $response = $this->actingAs($this->owner)->get(route('citizen.help.create'));
        $response->assertOk();
        $response->assertSee('Akun dan Login');
        $response->assertSee('Pengiriman Laporan');
    }

    public function test_nav_exposes_help_centre(): void
    {
        $this->actingAs($this->owner)
            ->get(route('citizen.dashboard'))
            ->assertOk()
            ->assertSee('Pusat Bantuan');
    }
}
