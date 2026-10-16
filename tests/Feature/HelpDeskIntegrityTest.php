<?php

namespace Tests\Feature;

use App\Enums\HelpDeskCategory;
use App\Enums\HelpTicketStatus;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\DinasUnit;
use App\Models\HelpTicket;
use App\Models\HelpTicketMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesLocationFixtures;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT TAHAP 2 — Help Desk integrity checks.
 *
 * Covers: route middleware wiring, the write rate limiter, FK/relation integrity,
 * and the reference-code format.
 */
class HelpDeskIntegrityTest extends TestCase
{
    use RefreshDatabase;
    use CreatesLocationFixtures;

    private User $citizen;
    private User $superAdmin;
    private ComplaintCategory $category;
    private DinasUnit $unit;
    private int $kecamatanId;
    private int $kelurahanId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->citizen    = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->category = ComplaintCategory::create([
            'name' => 'Infrastruktur Jalan', 'slug' => 'infrastruktur-jalan',
            'is_active' => true, 'sort_order' => 1,
        ]);
        $this->unit = DinasUnit::create([
            'name' => 'Dinas PUPR', 'code' => 'PUPR', 'is_active' => true, 'sort_order' => 1,
        ]);
        $this->unit->categories()->attach($this->category->id);

        [$this->kecamatanId, $this->kelurahanId] = $this->makeLocationPair();
    }

    private function payload(int $n): array
    {
        return [
            'subject'     => 'Tiket uji nomor ' . $n,
            'category'    => HelpDeskCategory::TechnicalOther->value,
            'description' => 'Deskripsi kendala teknis nomor ' . $n . ' yang cukup panjang.',
        ];
    }

    // =========================================================================
    // ROUTE MIDDLEWARE
    // =========================================================================

    public function test_citizen_routes_carry_auth_and_masyarakat_middleware(): void
    {
        foreach (['citizen.help.index', 'citizen.help.store', 'citizen.help.show', 'citizen.help.reply', 'citizen.help.reopen'] as $name) {
            $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();
            $this->assertContains('auth', $middleware, "{$name} must require auth");
            $this->assertContains('role:masyarakat', $middleware, "{$name} must require the masyarakat role");
        }
    }

    public function test_super_admin_routes_carry_auth_and_super_admin_middleware(): void
    {
        foreach (['super-admin.help.index', 'super-admin.help.show', 'super-admin.help.reply', 'super-admin.help.status'] as $name) {
            $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();
            $this->assertContains('auth', $middleware, "{$name} must require auth");
            $this->assertContains('role:super_admin', $middleware, "{$name} must require the super_admin role");
        }
    }

    public function test_write_routes_carry_the_rate_limiter(): void
    {
        foreach (['citizen.help.store', 'citizen.help.reply', 'citizen.help.reopen'] as $name) {
            $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();
            $this->assertContains('throttle:help-desk-write', $middleware, "{$name} must be rate limited");
        }
    }

    // =========================================================================
    // RATE LIMIT
    // =========================================================================

    public function test_write_endpoint_is_rate_limited(): void
    {
        $limit = (int) config('business_rules.help_desk_write_rate_limit');

        for ($i = 1; $i <= $limit; $i++) {
            $this->actingAs($this->citizen)
                ->post(route('citizen.help.store'), $this->payload($i))
                ->assertRedirect();
        }

        // The (limit + 1)-th write is throttled.
        $this->actingAs($this->citizen)
            ->post(route('citizen.help.store'), $this->payload($limit + 1))
            ->assertStatus(429);

        $this->assertSame($limit, HelpTicket::count());
    }

    // =========================================================================
    // RELATIONS / FK INTEGRITY
    // =========================================================================

    public function test_ticket_message_relations_work(): void
    {
        $ticket = HelpTicket::create([
            'reference_code' => 'HD-INT-0001',
            'user_id'        => $this->citizen->id,
            'subject'        => 'Uji relasi',
            'category'       => HelpDeskCategory::AccountLogin,
            'description'    => 'Deskripsi uji relasi tiket.',
            'status'         => HelpTicketStatus::Open,
        ]);

        HelpTicketMessage::create([
            'help_ticket_id' => $ticket->id,
            'user_id'        => $this->citizen->id,
            'body'           => 'Pesan pertama.',
        ]);

        $this->assertCount(1, $ticket->fresh()->messages);
        $this->assertSame($ticket->id, $ticket->messages->first()->ticket->id);
        $this->assertSame($this->citizen->id, $ticket->messages->first()->user->id);
        $this->assertCount(1, $this->citizen->fresh()->helpTickets);
    }

    public function test_related_complaint_relation_and_null_on_delete(): void
    {
        $complaint = Complaint::create([
            'reference_code' => 'LPW-INT-0001',
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $this->category->id,
            'dinas_unit_id'  => $this->unit->id,
            'kecamatan_id'   => $this->kecamatanId,
            'kelurahan_id'   => $this->kelurahanId,
            'title'          => 'Laporan integritas',
            'description'    => 'Deskripsi laporan integritas.',
            'status'         => \App\Enums\ComplaintStatus::InProgress,
            'submitted_at'   => now(),
        ]);

        $ticket = HelpTicket::create([
            'reference_code'       => 'HD-INT-0002',
            'user_id'              => $this->citizen->id,
            'subject'              => 'Uji referensi laporan',
            'category'             => HelpDeskCategory::ComplaintTracking,
            'description'          => 'Deskripsi uji referensi laporan.',
            'related_complaint_id' => $complaint->id,
            'status'               => HelpTicketStatus::Open,
        ]);

        $this->assertSame($complaint->id, $ticket->relatedComplaint->id);

        // Deleting the complaint (retention-style) must NOT delete the ticket;
        // the reference becomes NULL (ON DELETE SET NULL).
        $complaint->delete();

        $fresh = $ticket->fresh();
        $this->assertNotNull($fresh, 'the ticket must survive the complaint purge');
        $this->assertNull($fresh->related_complaint_id);
    }

    public function test_reference_code_format(): void
    {
        $this->actingAs($this->citizen)->post(route('citizen.help.store'), $this->payload(1));

        $ticket = HelpTicket::first();
        $this->assertMatchesRegularExpression('/^HD-\d{8}-[A-Z0-9]{4}$/', $ticket->reference_code);
    }

    public function test_reference_codes_are_unique(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($this->citizen)->post(route('citizen.help.store'), $this->payload($i));
        }

        $this->assertSame(5, HelpTicket::distinct()->count('reference_code'));
    }
}
