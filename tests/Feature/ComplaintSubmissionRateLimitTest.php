<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 17 — Complaint submission rate limiting.
 *
 * APPROVED POLICY (security / abuse protection, NOT a business rule):
 *   5 submissions / 10 minutes / authenticated user  → HTTP 429 when exceeded.
 *   Scope key = authenticated user id (never the client IP).
 *   A submission with 0..N attachments counts as ONE submission.
 *   The daily report limit (5 / calendar day) stays SEPARATE and unchanged.
 *
 * Source of truth: config/business_rules.php
 *   (complaint_submission_rate_limit / complaint_submission_rate_window_minutes)
 * Enforced by the named Laravel rate limiter `complaint-submission`, applied as
 * `throttle:` middleware on the single complaint creation route only.
 */
class ComplaintSubmissionRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $citizenA;
    private User $citizenB;
    private ComplaintCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->citizenA = User::factory()->create([
            'role'         => 'masyarakat',
            'is_active'    => true,
            'password'     => Hash::make('password123'),
            'nik'          => '7373000000009001',
            'phone_number' => '081100000901',
        ]);

        $this->citizenB = User::factory()->create([
            'role'         => 'masyarakat',
            'is_active'    => true,
            'password'     => Hash::make('password123'),
            'nik'          => '7373000000009002',
            'phone_number' => '081100000902',
        ]);

        $this->category = ComplaintCategory::create([
            'name'       => 'Infrastruktur Rate Limit',
            'slug'       => 'infrastruktur-rate-limit',
            'dinas_name' => 'Dinas PUPR',
            'is_active'  => true,
            'sort_order' => 1,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->category->id,
            'title'       => 'Laporan uji rate limit pengiriman',
            'description' => 'Deskripsi laporan pengujian rate limit pengiriman warga.',
        ], $overrides);
    }

    /** Submit a complaint over HTTP as the given user. */
    private function submit(User $user, array $overrides = [])
    {
        return $this->actingAs($user)->post(route('citizen.complaint.store'), $this->payload($overrides));
    }

    /** Create a complaint directly (bypasses HTTP, hence the rate limiter). */
    private function makeComplaint(User $reporter): Complaint
    {
        return Complaint::create([
            'reference_code' => 'LPW-RL-' . strtoupper(uniqid()),
            'reporter_id'    => $reporter->id,
            'category_id'    => $this->category->id,
            'title'          => 'Laporan uji harian',
            'description'    => 'Deskripsi laporan uji harian rate limit.',
            'status'         => 'submitted',
            'submitted_at'   => now(),
        ]);
    }

    // =========================================================================
    // Test 1 — before limit: 1st..5th accepted
    // =========================================================================

    public function test_first_five_submissions_within_window_are_accepted(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->submit($this->citizenA, ['title' => "Laporan ke-{$i}"]);
            $response->assertSessionHasNoErrors();
        }

        $this->assertSame(5, Complaint::where('reporter_id', $this->citizenA->id)->count());
    }

    // =========================================================================
    // Test 2 & 9 & 10 — limit reached: 6th → 429, not created
    // =========================================================================

    public function test_sixth_submission_within_window_is_rate_limited(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->submit($this->citizenA)->assertSessionHasNoErrors();
        }

        $response = $this->submit($this->citizenA, ['title' => 'Laporan ke-6 (harus ditolak)']);

        $response->assertStatus(429);
        $this->assertSame(5, Complaint::where('reporter_id', $this->citizenA->id)->count());
        $this->assertDatabaseMissing('complaints', ['title' => 'Laporan ke-6 (harus ditolak)']);
    }

    public function test_rate_limited_response_is_http_429_with_friendly_message(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->submit($this->citizenA);
        }

        $response = $this->submit($this->citizenA);

        $response->assertStatus(429);
        // Friendly Indonesian message; no internal implementation leakage.
        $response->assertSee('batas pengiriman laporan sementara', false);
        $response->assertDontSee('Laravel', false);
        $response->assertDontSee('complaint-submission', false);
        // Retry-After is provided by the framework rate limiter.
        $this->assertNotNull($response->headers->get('Retry-After'));
    }

    // =========================================================================
    // Test 3 — per-user isolation
    // =========================================================================

    public function test_rate_limit_is_isolated_per_authenticated_user(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->submit($this->citizenA);
        }
        $this->submit($this->citizenA)->assertStatus(429);

        // User B is a DIFFERENT person on a DIFFERENT device/session. Since
        // Prompt 19 the AuthenticateSession middleware binds each session to the
        // authenticated user's password hash, so reusing one session across two
        // users is not a realistic simulation. Start a fresh session (new device)
        // before acting as user B.
        $this->flushSession();

        // User B is unaffected by User A's exhausted quota.
        $this->submit($this->citizenB, ['title' => 'Laporan warga B'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Complaint::where('reporter_id', $this->citizenB->id)->count());
    }

    // =========================================================================
    // Test 4 — window expiration (time manipulation, no sleep)
    // =========================================================================

    public function test_limit_resets_after_window_expires(): void
    {
        // Raise the daily business limit so only the rate window is under test.
        AppSetting::set('daily_report_limit', 100);

        for ($i = 1; $i <= 5; $i++) {
            $this->submit($this->citizenA)->assertSessionHasNoErrors();
        }
        $this->submit($this->citizenA)->assertStatus(429);

        // Travel past the 10-minute window (no sleep()).
        $this->travel(11)->minutes();

        $this->submit($this->citizenA, ['title' => 'Laporan setelah jendela berakhir'])
            ->assertSessionHasNoErrors();

        $this->assertSame(6, Complaint::where('reporter_id', $this->citizenA->id)->count());
    }

    // =========================================================================
    // Test 5 — attachments count as ONE submission
    // =========================================================================

    public function test_multiple_attachments_count_as_a_single_submission(): void
    {
        Storage::fake('private');

        $files = fn () => [
            UploadedFile::fake()->create('lampiran-1.jpg', 100),
            UploadedFile::fake()->create('lampiran-2.png', 100),
            UploadedFile::fake()->create('lampiran-3.pdf', 100),
        ];

        for ($i = 1; $i <= 5; $i++) {
            $this->submit($this->citizenA, [
                'title'       => "Laporan ber-lampiran ke-{$i}",
                'attachments' => $files(),
            ])->assertSessionHasNoErrors();
        }

        // If attachments each consumed a hit, the 2nd request would already 429.
        $this->submit($this->citizenA, ['title' => 'Laporan ke-6 ber-lampiran'])
            ->assertStatus(429);

        $this->assertSame(5, Complaint::where('reporter_id', $this->citizenA->id)->count());
    }

    // =========================================================================
    // Test 6 — daily limit remains independent
    // =========================================================================

    public function test_daily_report_limit_remains_independent_and_enforced(): void
    {
        // Exhaust the daily business limit directly (no HTTP → no rate-limit hit).
        for ($i = 1; $i <= 5; $i++) {
            $this->makeComplaint($this->citizenA);
        }

        // The rate limiter allows this single attempt; the DAILY rule rejects it.
        $response = $this->submit($this->citizenA);

        $response->assertSessionHasErrors('daily_limit');
        $this->assertSame(5, Complaint::where('reporter_id', $this->citizenA->id)->count());
    }

    public function test_daily_limit_rejection_is_not_a_rate_limit_response(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->makeComplaint($this->citizenA);
        }

        // Daily-limit rejection keeps its existing 302 + session-error behavior,
        // it is NOT converted into a 429.
        $this->submit($this->citizenA)->assertStatus(302);
    }

    // =========================================================================
    // Test 7 — unauthenticated follows existing authentication behavior
    // =========================================================================

    public function test_guest_submission_follows_existing_authentication_behavior(): void
    {
        $response = $this->post(route('citizen.complaint.store'), $this->payload());

        $response->assertRedirect(route('login'));
        $this->assertSame(0, Complaint::count());
    }

    // =========================================================================
    // Test 8 — invalid request follows existing validation behavior
    // =========================================================================

    public function test_invalid_request_follows_existing_validation_behavior(): void
    {
        $response = $this->actingAs($this->citizenA)->post(route('citizen.complaint.store'), [
            'category_id' => $this->category->id,
            'title'       => '',
            'description' => 'Deskripsi cukup panjang namun judul kosong.',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('title');
        $this->assertSame(0, Complaint::count());
    }

    // =========================================================================
    // Security — repeated attempts, session variation, direct POST, other routes
    // =========================================================================

    public function test_repeated_attempts_after_limit_never_create_complaints(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->submit($this->citizenA);
        }

        for ($i = 0; $i < 3; $i++) {
            $this->submit($this->citizenA)->assertStatus(429);
        }

        $this->assertSame(5, Complaint::where('reporter_id', $this->citizenA->id)->count());
    }

    public function test_same_user_in_fresh_session_is_still_rate_limited(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->submit($this->citizenA);
        }

        // A brand-new session for the SAME account must not reset the limiter:
        // the key is the user id, not the session/browser.
        $this->flushSession();

        $this->actingAs($this->citizenA)
            ->post(route('citizen.complaint.store'), $this->payload())
            ->assertStatus(429);

        $this->assertSame(5, Complaint::where('reporter_id', $this->citizenA->id)->count());
    }

    public function test_rate_limiter_does_not_throttle_non_submission_routes(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->submit($this->citizenA);
        }
        // Submission quota is exhausted...
        $this->submit($this->citizenA)->assertStatus(429);

        // ...but viewing the form and dashboard must still work (no page throttle).
        $this->actingAs($this->citizenA)->get(route('citizen.complaint.create'))->assertOk();
        $this->actingAs($this->citizenA)->get(route('citizen.dashboard'))->assertOk();
        $this->actingAs($this->citizenA)->get(route('citizen.history'))->assertOk();
    }

    public function test_direct_post_without_visiting_form_is_rate_limited(): void
    {
        // No prior GET to the form — a direct POST must still be limited.
        for ($i = 1; $i <= 5; $i++) {
            $this->submit($this->citizenA)->assertSessionHasNoErrors();
        }

        $this->submit($this->citizenA)->assertStatus(429);
        $this->assertSame(5, Complaint::where('reporter_id', $this->citizenA->id)->count());
    }
}
