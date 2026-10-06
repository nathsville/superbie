<?php

namespace Tests\Feature;

use App\Enums\ComplaintStatus;
use App\Models\AppSetting;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 19 — Scope B: authoritative application timezone.
 *
 * FINAL (D-1): the authoritative application timezone is Asia/Makassar
 * (WITA / UTC+8). It is the single source of truth for business-rule calendar
 * boundaries, in particular the daily report limit (5 / calendar day, WITA).
 *
 * The business limit itself is UNCHANGED (5). Only the calendar boundary is
 * asserted to follow WITA rather than UTC.
 */
class TimezoneConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private User $citizen;
    private ComplaintCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->citizen = User::factory()->create([
            'role'      => 'masyarakat',
            'is_active' => true,
            'password'  => Hash::make('password123'),
        ]);

        $this->category = ComplaintCategory::create([
            'name'      => 'Infrastruktur Jalan',
            'slug'      => 'infrastruktur-jalan',
            'is_active' => true,
        ]);
    }

    public function test_application_timezone_is_asia_makassar(): void
    {
        $this->assertSame('Asia/Makassar', config('app.timezone'));
        // Laravel sets PHP's default timezone from config('app.timezone').
        $this->assertSame('Asia/Makassar', date_default_timezone_get());
    }

    public function test_application_timezone_defaults_to_wita_without_env_override(): void
    {
        // The locked decision must be the built-in default (never silently UTC),
        // so a missing APP_TIMEZONE cannot fall back to UTC.
        $this->assertNotSame('UTC', config('app.timezone'));
    }

    /**
     * Boundary invariant: a complaint submitted late in the WITA day and one
     * submitted just after WITA midnight belong to DIFFERENT calendar days,
     * even though they fall in the SAME UTC calendar day.
     *
     * This is exactly the case where a UTC boundary would be wrong.
     */
    public function test_daily_limit_boundary_follows_wita_not_utc(): void
    {
        // Limit = 1 complaint per calendar day (business value unchanged, only
        // the boundary is under test).
        AppSetting::set('daily_report_limit', 1);

        // 2026-03-01 23:30 WITA == 2026-03-01 15:30 UTC (same UTC day)
        $lateWita = Carbon::parse('2026-03-01 23:30:00', 'Asia/Makassar');
        $this->travelTo($lateWita);

        // Sanity: the app's calendar day is 2026-03-01, and it is STILL the same
        // UTC calendar day.
        $this->assertSame('2026-03-01', today()->toDateString());
        $this->assertSame('2026-03-01', Carbon::now('UTC')->toDateString());

        $first = $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), [
            'category_id' => $this->category->id,
            'title'       => 'Laporan malam WITA',
            'description' => 'Dikirim pukul 23:30 WITA.',
        ]);
        $first->assertSessionHasNoErrors();
        $this->assertSame(1, Complaint::where('reporter_id', $this->citizen->id)->count());

        // 2026-03-02 00:30 WITA == 2026-03-01 16:30 UTC (STILL the same UTC day,
        // but a NEW WITA calendar day).
        $earlyNextWita = Carbon::parse('2026-03-02 00:30:00', 'Asia/Makassar');
        $this->travelTo($earlyNextWita);

        $this->assertSame('2026-03-02', today()->toDateString());      // WITA day advanced
        $this->assertSame('2026-03-01', Carbon::now('UTC')->toDateString()); // UTC day did NOT

        // Because the app boundary is WITA, the daily limit has reset: the
        // second submission on the new WITA day is allowed.
        $second = $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), [
            'category_id' => $this->category->id,
            'title'       => 'Laporan dini hari WITA',
            'description' => 'Dikirim pukul 00:30 WITA hari berikutnya.',
        ]);
        $second->assertSessionHasNoErrors();

        $this->assertSame(2, Complaint::where('reporter_id', $this->citizen->id)->count());
    }

    /**
     * The daily limit still blocks a second submission on the SAME WITA day,
     * proving the business rule (5 / calendar day) is preserved.
     */
    public function test_daily_limit_still_enforced_within_same_wita_day(): void
    {
        AppSetting::set('daily_report_limit', 1);

        $this->travelTo(Carbon::parse('2026-03-01 10:00:00', 'Asia/Makassar'));

        Complaint::create([
            'reference_code' => 'LPW-20260301-TZ01',
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $this->category->id,
            'title'          => 'Laporan pagi WITA',
            'description'    => 'Laporan pertama hari WITA.',
            'status'         => ComplaintStatus::Submitted,
            'submitted_at'   => now(),
        ]);

        // Same WITA calendar day, one hour later.
        $this->travelTo(Carbon::parse('2026-03-01 11:00:00', 'Asia/Makassar'));

        $response = $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), [
            'category_id' => $this->category->id,
            'title'       => 'Laporan kedua hari WITA',
            'description' => 'Harus ditolak batas harian.',
        ]);

        $response->assertSessionHasErrors('daily_limit');
        $this->assertSame(1, Complaint::where('reporter_id', $this->citizen->id)->count());
    }
}
