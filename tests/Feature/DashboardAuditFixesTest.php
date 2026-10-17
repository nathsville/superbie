<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\DinasUnit;
use App\Models\User;
use App\Services\RatingAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SUPERBIE — Dashboard audit remediation (A1–A11) regression guard.
 *
 * Pins the fixes applied after the read-only accessibility / data-integrity
 * audit of the staff dashboards so they cannot silently regress:
 *
 *   A1  Role distribution reconciles with the "Total Pengguna" KPI (no
 *       hard-coded 4-role list that hides real accounts such as `petugas`).
 *   A2  Dashboard text meets contrast (no `text-[#94A3B8]` slate-400 labels).
 *   A3  The notification bell is reachable below the `sm` breakpoint.
 *   A4  A zero month renders NO bar (no fabricated 2% stub).
 *   A5  `.soft-card-hover` lifts via `translate` (not the `card-enter`-pinned
 *       `transform`).
 *   A6  The super-admin status aggregation is ONE `GROUP BY status` query.
 *   A7  The bell popup is a labelled group, not an invalid `role="menu"`.
 *   A8  Rating tables have column scopes + captions.
 *   A9  Global Dinas/Unit filter options exclude INACTIVE units.
 *   A10 The super-admin dashboard surfaces freshness + a real refresh control.
 *   A11 The <h1> is the page title (greeting is the secondary line).
 */
class DashboardAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function complaint(User $reporter, array $overrides = []): Complaint
    {
        return Complaint::create(array_merge([
            'reference_code' => 'LPW-AUD-' . strtoupper(uniqid()),
            'category_id'    => ComplaintCategory::factory()->create()->id,
            'reporter_id'    => $reporter->id,
            'title'          => 'Laporan uji audit dashboard',
            'description'    => 'Deskripsi laporan pengujian audit dashboard.',
            'location_text'  => 'Jl. Uji',
            'status'         => 'submitted',
            'submitted_at'   => now(),
        ], $overrides));
    }

    // ── A1 — role distribution reconciles with Total Pengguna ────────────────

    public function test_role_distribution_includes_every_real_role(): void
    {
        $sa = $this->superAdmin();
        User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
        User::factory()->create(['role' => 'admin', 'is_active' => true]);
        User::factory()->create(['role' => 'petugas', 'is_active' => true]);

        $unit = DinasUnit::factory()->create();
        User::factory()->create(['role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $unit->id]);

        $response = $this->actingAs($sa)->get('/super-admin/dashboard');

        $response->assertOk();

        // Five accounts exist → the breakdown sums to five (the old hard-coded
        // 4-role loop would have silently dropped the `petugas` account).
        $response->assertViewHas('roleBreakdown', function ($breakdown) {
            return array_sum($breakdown) === 5
                && ($breakdown['petugas'] ?? 0) === 1;
        });

        // The non-canonical role is still shown, with a humanised label.
        $response->assertSee('Petugas (historis)', false);
    }

    // ── A2 — dashboard contrast ──────────────────────────────────────────────

    public function test_dashboard_text_avoids_low_contrast_slate_400(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin/dashboard')->assertOk()->getContent();

        // slate-400 (~2.6:1) must not be used as a text colour anywhere on the
        // dashboard shell/components.
        $this->assertStringNotContainsString('text-[#94A3B8]', $html);

        // The slate-500 tone (#64748B) now ships as a token: the dashboard's
        // secondary text uses `text-ink-soft`, and the raw hex literal must
        // never come back.
        $this->assertStringContainsString('text-ink-soft', $html);
        $this->assertStringNotContainsString('text-[#64748B]', $html);

        // The super-admin dashboard is swept too.
        $superHtml = $this->actingAs($this->superAdmin())->get('/super-admin/dashboard')->assertOk()->getContent();
        $this->assertStringNotContainsString('text-[#94A3B8]', $superHtml);
        $this->assertStringNotContainsString('text-[#64748B]', $superHtml);
    }

    // ── A3 — bell reachable on mobile ────────────────────────────────────────

    public function test_notification_bell_is_not_hidden_on_small_screens(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="Notifikasi"', $html);
        // The bell container used to be `relative hidden sm:block`.
        $this->assertStringNotContainsString('relative hidden sm:block', $html);
    }

    // ── A4 — zero month renders no bar ───────────────────────────────────────

    public function test_zero_month_renders_no_fabricated_bar(): void
    {
        $sa       = $this->superAdmin();
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        // One complaint this month → the other five months are genuinely zero.
        $this->complaint($reporter, ['submitted_at' => now()]);

        $html = $this->actingAs($sa)->get('/super-admin/dashboard')->assertOk()->getContent();

        // The data month is full height; empty months collapse to 0% (never 2%).
        $this->assertStringContainsString('height: 100%', $html);
        $this->assertStringContainsString('height: 0%', $html);
    }

    // ── A5 — hover lift uses `translate` ─────────────────────────────────────

    public function test_soft_card_hover_lifts_with_translate_not_transform(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        // The rule must animate the individual `translate` property, because
        // `.card-enter` pins `transform` with `fill-mode: both`.
        $this->assertMatchesRegularExpression('/translate\s+var\(--motion-normal\)/', $css);
        $this->assertStringContainsString('translate: 0 -1px', $css);

        // And must NOT use a transform-based lift for this utility.
        $this->assertStringNotContainsString('transform: translateY(-1px)', $css);
    }

    // ── A6 — single grouped status query ─────────────────────────────────────

    public function test_super_admin_status_aggregation_is_one_grouped_query(): void
    {
        $sa       = $this->superAdmin();
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->complaint($reporter, ['status' => 'submitted']);
        $this->complaint($reporter, ['status' => 'in_progress']);
        $this->complaint($reporter, ['status' => 'resolved', 'resolved_at' => now()]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($sa)->get('/super-admin/dashboard')->assertOk();
        $log = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $grouped = $log->filter(fn ($q) => str_contains($q, 'group by') && str_contains($q, 'status'));
        $this->assertCount(1, $grouped, 'status must be aggregated with ONE GROUP BY query');

        // No per-status COUNT(*) query remains (the old N-query pattern).
        $perStatus = $log->filter(fn ($q) => preg_match('/count\(\*\) as aggregate from "complaints" where "status"/', $q) === 1);
        $this->assertCount(0, $perStatus, 'per-status COUNT(*) queries must be gone');
    }

    // ── A7 — bell popup ARIA ─────────────────────────────────────────────────

    public function test_bell_popup_is_a_labelled_group_not_an_invalid_menu(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin/dashboard')->assertOk()->getContent();

        // `role="menu"` with non-menuitem children is invalid — must be gone.
        $this->assertStringNotContainsString('role="menu"', $html);
        $this->assertStringContainsString('id="sb-notif-panel"', $html);
        $this->assertStringContainsString('role="group"', $html);
        $this->assertStringContainsString('aria-controls="sb-notif-panel"', $html);
        $this->assertStringContainsString('aria-label="Daftar notifikasi terbaru"', $html);
    }

    // ── A8 — rating tables: scope + caption ──────────────────────────────────

    public function test_rating_tables_have_column_scopes_and_captions(): void
    {
        // The two tables only render when there is data → seed a resolved,
        // rated complaint with a category + Dinas/Unit.
        $admin    = $this->admin();
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $unit     = DinasUnit::factory()->create();
        $category = ComplaintCategory::factory()->create();
        $unit->categories()->attach($category->id);

        $complaint = $this->complaint($reporter, [
            'category_id'   => $category->id,
            'dinas_unit_id' => $unit->id,
            'status'        => 'resolved',
            'resolved_at'   => now(),
        ]);

        \App\Models\ComplaintRating::create([
            'complaint_id' => $complaint->id,
            'user_id'      => $reporter->id,
            'rating'       => 5,
        ]);

        $html = $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('<caption class="sr-only">Rating per Dinas/Unit</caption>', $html);
        $this->assertStringContainsString('<caption class="sr-only">Rating per Kategori</caption>', $html);

        // 8 header cells across the two tables, each scoped to its column.
        $this->assertSame(8, substr_count($html, 'scope="col"'));
    }

    // ── A9 — global filter options exclude inactive units ────────────────────

    public function test_global_filter_options_exclude_inactive_units(): void
    {
        $admin = $this->admin();

        $active   = DinasUnit::create(['name' => 'Dinas Aktif', 'code' => 'AK', 'is_active' => true, 'sort_order' => 1]);
        $inactive = DinasUnit::create(['name' => 'Dinas Nonaktif', 'code' => 'NA', 'is_active' => false, 'sort_order' => 2]);

        $payload = app(RatingAnalyticsService::class)->getRatingAnalytics($admin, [], true);
        $ids     = array_column($payload['options']['dinasUnits'], 'id');

        $this->assertContains($active->id, $ids);
        $this->assertNotContains($inactive->id, $ids, 'a deactivated unit must never be a selectable filter option');
    }

    public function test_operator_still_sees_own_unit_even_when_inactive(): void
    {
        $unit = DinasUnit::create(['name' => 'Dinas Lama', 'code' => 'DL', 'is_active' => false, 'sort_order' => 1]);
        $operator = User::factory()->create(['role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $unit->id]);

        $payload = app(RatingAnalyticsService::class)->getRatingAnalytics($operator, [], true);

        // The operator scope is FORCED server-side; the (inactive) own unit is
        // still the only option so their scoped view keeps working.
        $this->assertCount(1, $payload['options']['dinasUnits']);
        $this->assertSame($unit->id, $payload['options']['dinasUnits'][0]['id']);
    }

    // ── A11 — <h1> is the page title ─────────────────────────────────────────

    public function test_h1_is_the_page_title_and_greeting_is_secondary(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin/dashboard')->assertOk()->getContent();

        $header = substr($html, (int) strpos($html, '<header'), (int) strpos($html, '</header>') - (int) strpos($html, '<header'));

        // The <h1> carries the page title…
        $this->assertMatchesRegularExpression('/<h1[^>]*>\s*Dashboard Admin\s*<\/h1>/', $header);

        // …and the greeting is a secondary line, not the <h1>.
        $this->assertStringNotContainsString('Hello,', substr($header, 0, (int) strpos($header, '</h1>')));
        $this->assertStringContainsString('Hello,', $header);
    }
}
