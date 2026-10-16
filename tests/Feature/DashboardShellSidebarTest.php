<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Complaint;
use App\Models\DinasUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 27 (shell) — Interactive sidebar contract.
 *
 * The shared dashboard shell renders a collapsible icon rail that expands on
 * CLICK (never hover) and can be PINNED open. The pinned preference is persisted
 * in localStorage (a UI preference only — never auth/session/HTML data).
 *
 * These tests pin the server-rendered contract so the behaviour cannot silently
 * regress. They do NOT assert browser behaviour (no JS runner exists; adding one
 * would require a new package).
 *
 * IMPORTANT: the localStorage usage lives in the Blade/Alpine shell, NOT in
 * `resources/js/navigation.js` — that module's "no web storage" rule (so the
 * page cache can never hold authenticated HTML) stays enforced by
 * ProgressiveNavigationTest.
 */
class DashboardShellSidebarTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        $unit = DinasUnit::factory()->create();

        return User::factory()->create([
            'role'          => 'operator',
            'is_active'     => true,
            'dinas_unit_id' => $unit->id,
        ]);
    }

    private function userFor(string $role): User
    {
        return $role === 'operator'
            ? $this->operator()
            : User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    // ── Rail controls present on every role's shell ──────────────────────────

    public function test_shell_renders_collapsible_rail_controls_for_every_role(): void
    {
        $shells = [
            'masyarakat'  => 'citizen.dashboard',
            'operator'    => 'operator.dashboard',
            'admin'       => 'admin.dashboard',
            'super_admin' => 'super-admin.dashboard',
        ];

        foreach ($shells as $role => $routeName) {
            $html = $this->actingAs($this->userFor($role))
                ->get(route($routeName))
                ->assertOk()
                ->getContent();

            // Rail + state classes driven by Alpine.
            $this->assertStringContainsString('sb-rail', $html, "rail missing for {$role}");
            $this->assertStringContainsString('sb-rail-expanded', $html, "expand class missing for {$role}");
            $this->assertStringContainsString('sb-rail-pinned', $html, "pin class missing for {$role}");
            $this->assertStringContainsString('sb-main', $html, "main shift class missing for {$role}");

            // Pin toggle with an explicit, accessible control.
            $this->assertStringContainsString('togglePin()', $html, "pin toggle missing for {$role}");
            $this->assertStringContainsString('aria-pressed', $html, "pin aria-pressed missing for {$role}");

            // Click-to-toggle (NOT hover): menu button + rail surface + outside click.
            $this->assertStringContainsString('toggleRail()', $html, "menu toggle missing for {$role}");
            $this->assertStringContainsString('onRailSurfaceClick($event)', $html, "surface click missing for {$role}");
            $this->assertStringContainsString('collapseRail()', $html, "outside click missing for {$role}");

            // Hover must NOT expand the rail any more.
            $this->assertStringNotContainsString('railHover', $html, "hover state must be gone for {$role}");
        }
    }

    // ── Persistence contract ─────────────────────────────────────────────────

    public function test_pin_preference_is_persisted_in_localstorage(): void
    {
        $user = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $html = $this->actingAs($user)->get('/laporan/dashboard')->assertOk()->getContent();

        // Namespaced UI-preference key, read on init and written on toggle.
        $this->assertStringContainsString('sb.sidebar.pinned', $html);
        $this->assertStringContainsString('localStorage.getItem', $html);
        $this->assertStringContainsString('localStorage.setItem', $html);

        // Guarded so a blocked/unavailable storage degrades gracefully.
        $this->assertStringContainsString('try', $html);
    }

    public function test_persistence_stays_out_of_the_navigation_cache_module(): void
    {
        // The sidebar preference must never leak into the navigation cache layer,
        // whose no-web-storage rule protects against caching authenticated HTML.
        // Scan executable code only (strip comments) — mirroring the existing guard.
        $js = file_get_contents(resource_path('js/navigation.js'));
        $code = preg_replace('#/\*.*?\*/#s', '', $js);
        $code = preg_replace('#(^|\s)//[^\n]*#', '$1', $code);

        $this->assertStringNotContainsString('localStorage', $code);
        $this->assertStringNotContainsString('sessionStorage', $code);
    }

    // ── Accessibility + mobile drawer unchanged ──────────────────────────────

    public function test_rail_keeps_labels_in_dom_and_mobile_drawer(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $html = $this->actingAs($user)->get(route('super-admin.dashboard'))->assertOk()->getContent();

        // Labels stay in the DOM (accessible names + HTML assertions).
        $this->assertStringContainsString('rail-label', $html);
        $this->assertStringContainsString('Semua Laporan', $html);

        // Mobile off-canvas drawer + its trigger are untouched.
        $this->assertStringContainsString('Buka menu navigasi', $html);
        $this->assertStringContainsString('sidebarOpen', $html);
    }

    // ── Desktop layout: docked flex sibling (no overlay) ─────────────────────

    public function test_desktop_layout_docks_the_rail_beside_the_content(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $html = $this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->getContent();

        // Main wrapper is a flex row on desktop; the rail is a shrink-0 sticky
        // sibling and the content is a flexible (min-w-0) sibling.
        $this->assertStringContainsString('lg:flex', $html);
        $this->assertStringContainsString('lg:items-start', $html);
        $this->assertStringContainsString('lg:sticky', $html);
        $this->assertStringContainsString('lg:shrink-0', $html);
        $this->assertStringContainsString('min-w-0', $html);

        // The rail must NOT use viewport-padding on the content to fake docking
        // (that was the overlay approach this layout replaced).
        $this->assertStringNotContainsString('lg:pl-[6.25rem]', $html);
        $this->assertStringNotContainsString('lg:pl-[17.5rem]', $html);
    }

    public function test_sidebar_header_exposes_a_single_control(): void
    {
        // The header keeps ONE desktop control (the pin toggle). The rail is
        // expanded by clicking its surface and collapsed by clicking outside.
        $user = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $html = $this->actingAs($user)->get('/laporan/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('onRailSurfaceClick($event)', $html);
        $this->assertStringContainsString('@click="collapseRail()"', $html);
        $this->assertStringContainsString('togglePin()', $html);

        // No stacked hamburger/chevron controls in the header any more.
        $this->assertStringNotContainsString('sb-rail-toggle', $html);
        $this->assertStringNotContainsString('sb-rail-collapse', $html);
    }

    // ── Header layout: brand inside the sidebar, greeting left in the topbar ──

    public function test_brand_lives_inside_the_sidebar_header(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $html = $this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->getContent();

        // Brand + the single desktop control live in the sidebar header.
        $this->assertStringContainsString('sb-rail-top', $html);
        $this->assertStringContainsString('sb-rail-brand', $html);
        $this->assertStringContainsString('sb-rail-controls', $html);
        $this->assertStringContainsString('sb-rail-pin', $html);

        // Exactly one desktop header control — no stacked hamburger/chevron.
        $this->assertStringNotContainsString('sb-rail-toggle', $html);
        $this->assertStringNotContainsString('sb-rail-collapse', $html);

        // Brand wordmark is present (label collapses on the collapsed rail).
        $this->assertStringContainsString('SuperBie', $html);
        $this->assertStringContainsString('Lapor Pak Wali', $html);
    }

    public function test_topbar_has_no_brand_or_desktop_hamburger(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $html = $this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->getContent();

        // The header <header> block must not carry the brand link or a desktop
        // hamburger; those moved into the sidebar. Only the mobile drawer trigger
        // ("Buka menu navigasi") remains, and it is hidden on desktop.
        $header = substr($html, (int) strpos($html, '<header'), (int) strpos($html, '</header>') - (int) strpos($html, '<header'));

        $this->assertStringNotContainsString('sb-rail-brand', $header);
        $this->assertStringNotContainsString('SuperBie', $header);
        $this->assertStringContainsString('Buka menu navigasi', $header);

        // Greeting is the leftmost content element (flex-1, directly after the
        // mobile-only trigger) so it sits right beside the sidebar on desktop.
        $this->assertStringContainsString('id="page-heading"', $header);
    }

    // ── Bento chrome: canvas + floating rail + greeting + pill search ─────────

    public function test_shell_renders_bento_canvas_and_floating_rail(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $html = $this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->getContent();

        // Soft grey/cream canvas behind the whole shell.
        $this->assertStringContainsString('sb-canvas', $html);

        // Rail is a self-contained floating white capsule with a clear margin
        // from the viewport edge (no hairline border on desktop).
        $this->assertStringContainsString('sb-rail-surface', $html);
        $this->assertStringContainsString('lg:ml-4', $html);
    }

    public function test_shell_header_shows_greeting_with_real_account_name(): void
    {
        $user = User::factory()->create([
            'role'      => 'admin',
            'is_active' => true,
            'name'      => 'Budi Santoso',
        ]);

        $html = $this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->getContent();

        // Greeting uses the real first name only (never fabricated), and the
        // page title stays in the DOM for existing HTML assertions.
        $this->assertStringContainsString('Hello, Budi!', $html);
        $this->assertStringContainsString('Dashboard Admin', $html);
    }

    public function test_search_is_a_pill_and_bell_is_an_enabled_notification_control(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $html = $this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->getContent();

        // Pill-shaped search form with a circular black search badge.
        $this->assertStringContainsString('rounded-full', $html);
        $this->assertStringContainsString('bg-[#0F172A]', $html);

        // PHASE N4 — the bell is now an ENABLED, accessible notification control
        // (no longer a disabled placeholder) wired to the notification centre.
        $this->assertStringContainsString('aria-label="Notifikasi"', $html);
        $this->assertStringContainsString('aria-haspopup="true"', $html);
        $this->assertStringContainsString('notifOpen', $html);
        $this->assertStringContainsString('Lihat semua', $html);
        $this->assertStringContainsString(route('notifications.index'), $html);

        // The old disabled placeholder is gone.
        $this->assertStringNotContainsString('Notifikasi belum tersedia', $html);
        $this->assertStringNotContainsString('cursor-not-allowed', $html);
    }

    public function test_bell_hides_the_unread_badge_when_there_are_no_unread_notifications(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $html = $this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->getContent();

        // No fabricated badge: a zero unread count renders NO numeric badge.
        $this->assertStringNotContainsString('notifikasi belum dibaca', $html);
    }

    public function test_bell_shows_an_unread_badge_for_unread_notifications(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $complaint = Complaint::factory()->create();

        AppNotification::create([
            'recipient_user_id' => $user->id,
            'complaint_id'      => $complaint->id,
            'type'              => 'complaint_submitted',
            'title'             => 'Laporan Diajukan',
            'body'              => 'Laporan '.$complaint->reference_code.' telah diajukan.',
            'source_event_id'   => 1,
        ]);

        $html = $this->actingAs($user)->get(route('super-admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('1 notifikasi belum dibaca', $html);
    }

    public function test_admin_dashboard_uses_borderless_bento_cards(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $html = $this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->getContent();

        // Bento grid surfaces are borderless white cards with soft shadows
        // (the flat style requested), not hairline-bordered boxes.
        $this->assertStringContainsString('soft-card', $html);
        $this->assertStringNotContainsString('rounded-3xl border border-[#E2E8F0]', $html);
    }

    // ── Profile link (topbar) + rail account/footer swap ─────────────────────

    public function test_topbar_profile_is_a_real_link_with_hover_feedback(): void
    {        // Every role links to a REAL profile page (citizen → citizen.profile.edit;
        // staff → staff.profile). No dead link, no false affordance.
        $shells = [
            'masyarakat'  => ['citizen.dashboard', 'citizen.profile.edit'],
            'operator'    => ['operator.dashboard', 'staff.profile'],
            'admin'       => ['admin.dashboard', 'staff.profile'],
            'super_admin' => ['super-admin.dashboard', 'staff.profile'],
        ];

        foreach ($shells as $role => [$dashRoute, $profileRoute]) {
            $html = $this->actingAs($this->userFor($role))
                ->get(route($dashRoute))
                ->assertOk()
                ->getContent();

            // Interactive link (not a static <div>) with hover feedback + pointer.
            $this->assertStringContainsString('sb-topbar-profile', $html, "profile link missing for {$role}");
            $this->assertStringContainsString('cursor-pointer', $html, "pointer cursor missing for {$role}");
            $this->assertStringContainsString('href="'.route($profileRoute).'"', $html, "profile target missing for {$role}");
        }
    }

    public function test_rail_footer_orders_logout_above_the_profile_at_the_bottom(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $html = $this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->getContent();

        // The account row sits at the BOTTOM of the rail (inside the footer,
        // below #primary-navigation, which progressive navigation swaps).
        $navPos      = strpos($html, 'id="primary-navigation"');
        $footerPos   = strpos($html, 'sb-rail-footer');
        $logoutPos   = strpos($html, 'sb-rail-logout');
        $profilePos  = strpos($html, 'sb-rail-profile');

        $this->assertNotFalse($navPos, 'primary navigation missing');
        $this->assertNotFalse($footerPos, 'rail footer missing');
        $this->assertNotFalse($logoutPos, 'logout missing');
        $this->assertNotFalse($profilePos, 'rail profile block missing');

        // Order: [navigation] → [footer] → [logout] → [profile].
        $this->assertGreaterThan($navPos, $footerPos, 'footer must sit below the navigation');
        $this->assertGreaterThan($footerPos, $logoutPos, 'logout must sit inside the footer');
        $this->assertGreaterThan($logoutPos, $profilePos, 'profile must sit below logout (last element)');

        // The account block must NOT sit above the navigation any more.
        $this->assertGreaterThan($navPos, $profilePos, 'profile must not sit above the navigation');

        // The old combined user row is gone.
        $this->assertStringNotContainsString('sb-rail-user', $html);

        // Logout is a labelled button, still a real POST form.
        $this->assertStringContainsString('Logout', $html);
        $this->assertStringContainsString('action="'.route('logout').'"', $html);
    }

    public function test_rail_footer_groups_logout_toggle_and_profile(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $html = $this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->getContent();

        // The footer is a single group: action row (Logout + toggle) then profile.
        $this->assertStringContainsString('sb-rail-footer', $html);
        $this->assertStringContainsString('sb-rail-footer-actions', $html);
        $this->assertStringContainsString('sb-rail-logout', $html);
        $this->assertStringContainsString('sb-rail-expand', $html);

        // The expand/collapse chevron reuses the existing toggleRail() handler and
        // is a small round button (16px icon), NOT a dead control.
        $this->assertStringContainsString('toggleRail()', $html);
        $this->assertStringContainsString('sb-rail-expand-icon', $html);

        // The chevron lives inside the footer group (below the navigation).
        $navPos    = strpos($html, 'id="primary-navigation"');
        $expandPos = strpos($html, 'class="sb-rail-expand ui-animated"');
        $this->assertNotFalse($expandPos, 'expand toggle missing');
        $this->assertGreaterThan($navPos, $expandPos, 'toggle must sit in the footer, below the navigation');

        // The chevron must NOT reuse the removed header control class names.
        $this->assertStringNotContainsString('sb-rail-toggle', $html);
        $this->assertStringNotContainsString('sb-rail-collapse', $html);
    }

    // ── Real uploaded photo reflected in the shell (rail + topbar) ───────────

    public function test_shell_avatars_show_the_uploaded_photo_when_present(): void
    {
        Storage::fake('public', ['url' => 'http://localhost/storage']);

        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $user->forceFill(['avatar_path' => 'avatars/'.$user->id.'/me.jpg'])->save();

        $html = $this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->getContent();

        // Both shell avatars render the REAL stored photo (public disk URL)…
        $expected = $user->avatarUrl();
        $this->assertNotNull($expected);
        $this->assertStringContainsString('src="'.$expected.'"', $html);
        $this->assertSame(2, substr_count($html, 'src="'.$expected.'"'), 'photo must appear in BOTH rail and topbar avatars');
    }

    public function test_shell_avatars_fall_back_to_initials_without_a_photo(): void
    {
        $user = User::factory()->create([
            'role'      => 'admin',
            'is_active' => true,
            'name'      => 'Zahra Tanpa Foto',
            'avatar_path' => null,
        ]);

        $html = $this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->getContent();

        // No fabricated image: the initial is shown instead (rail + topbar).
        $this->assertStringNotContainsString('/storage/avatars/', $html);
        $this->assertSame(
            2,
            preg_match_all('/>\s*Z\s*</', $html),
            'the account initial must render in BOTH rail and topbar avatars'
        );
    }

    // ── Avatar URL is host-agnostic + broken images degrade to initials ──────

    public function test_avatar_url_is_root_relative_and_host_agnostic(): void
    {
        // The `public` disk builds an absolute URL from APP_URL, which in this
        // deployment is a LAN/hotspot address the browser cannot reach — the
        // cause of the broken profile photos. The model must instead return a
        // ROOT-RELATIVE URL so it always resolves against the serving origin.
        Storage::fake('public', ['url' => 'http://192.168.137.1:8000/storage']);

        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $user->forceFill(['avatar_path' => 'avatars/'.$user->id.'/me.jpg'])->save();

        $url = $user->fresh()->avatarUrl();

        $this->assertSame('/storage/avatars/'.$user->id.'/me.jpg', $url);
        $this->assertStringStartsWith('/', (string) $url, 'avatar URL must be root-relative');
        $this->assertStringNotContainsString('192.168.137.1', (string) $url, 'no absolute host may leak into the URL');
        $this->assertStringNotContainsString('http', (string) $url, 'avatar URL must not be absolute');
    }

    public function test_avatar_url_is_null_when_no_photo_is_stored(): void
    {
        $user = User::factory()->create([
            'role'        => 'admin',
            'is_active'   => true,
            'avatar_path' => null,
        ]);

        $this->assertNull($user->avatarUrl());
    }

    public function test_shell_avatars_carry_the_broken_image_fallback(): void
    {
        Storage::fake('public', ['url' => 'http://192.168.137.1:8000/storage']);

        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $user->forceFill(['avatar_path' => 'avatars/'.$user->id.'/me.jpg'])->save();

        $html = $this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->getContent();

        // Both shell avatars (rail + topbar) opt into the fallback component and
        // swap to the initial when the <img> errors — never a broken-image icon.
        $this->assertSame(2, substr_count($html, 'x-data="avatarImage"'));
        $this->assertSame(2, substr_count($html, 'x-on:error="fail()"'));
        $this->assertSame(2, substr_count($html, 'x-show="failed"'));
    }

    // ── Topbar stays clean: "Buat Laporan" lives ONLY in the sidebar nav ─────

    public function test_topbar_does_not_duplicate_the_create_complaint_cta(): void
    {
        $user = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        foreach (['citizen.dashboard', 'citizen.history'] as $routeName) {
            $html = $this->actingAs($user)->get(route($routeName))->assertOk()->getContent();

            // The topbar action slot (#page-actions) must NOT carry the blue
            // "Buat Laporan" CTA — that duplicate is intentionally removed so the
            // header stays clean next to the profile.
            $this->assertMatchesRegularExpression(
                '/id="page-actions"[^>]*>\s*<\/div>/',
                $html,
                "#page-actions must be empty on {$routeName}"
            );

            // …while the sidebar navigation still offers "Buat Laporan".
            $this->assertStringContainsString('Buat Laporan', $html, "sidebar CTA missing on {$routeName}");
            $this->assertStringContainsString(
                'href="'.route('citizen.complaint.create').'"',
                $html,
                "sidebar create link missing on {$routeName}"
            );
        }
    }

    // ── Sidebar nav has no "Profil Saya"; profile is reached via the account ──

    public function test_citizen_sidebar_nav_omits_the_profile_item(): void
    {
        $user = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        foreach (['citizen.dashboard', 'citizen.history', 'citizen.profile.edit'] as $routeName) {
            $html = $this->actingAs($user)->get(route($routeName))->assertOk()->getContent();

            // The sidebar navigation (#primary-navigation) must NOT offer a
            // "Profil Saya" item nor link to the profile route — profile access
            // is intentionally routed through the topbar / footer account element.
            $this->assertMatchesRegularExpression(
                '/id="primary-navigation".*?<\/nav>/s',
                $html,
                'primary navigation block must exist'
            );
            preg_match('/id="primary-navigation".*?<\/nav>/s', $html, $m);
            $nav = $m[0];

            $this->assertStringNotContainsString('Profil Saya', $nav, "sidebar must not offer Profil Saya on {$routeName}");
            $this->assertStringNotContainsString(
                route('citizen.profile.edit'),
                $nav,
                "sidebar must not link to the profile route on {$routeName}"
            );
        }
    }

    public function test_profile_is_still_reachable_from_topbar_and_footer(): void
    {
        $user = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $html = $this->actingAs($user)->get(route('citizen.dashboard'))->assertOk()->getContent();

        // Both the topbar account element and the rail footer account element
        // still link to the REAL profile page.
        $this->assertStringContainsString('sb-topbar-profile', $html);
        $this->assertStringContainsString('sb-rail-profile', $html);
        $this->assertSame(
            2,
            substr_count($html, 'href="'.route('citizen.profile.edit').'"'),
            'profile must remain reachable from BOTH the topbar and the sidebar footer'
        );
    }
}
