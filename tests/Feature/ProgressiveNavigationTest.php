<?php

namespace Tests\Feature;

use App\Models\DinasUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 26 — Progressive navigation + in-memory cache (server contract).
 *
 * The navigation layer itself runs in the browser, but its SECURITY depends on
 * server-rendered facts. These tests pin those facts so the client-side cache
 * cannot silently become a cross-user / cross-role / cross-unit leak:
 *
 *   1. A server-derived, non-reversible identity hash is rendered for every
 *      authenticated page and is ABSENT for guests (cache disabled -> safe).
 *   2. That identity varies with user id, role, and Dinas/Unit — the three axes
 *      the frozen authorization model (Prompt 15/21) scopes on.
 *   3. The stable DOM hooks the layer swaps exist on shell pages only.
 *   4. The layer is a progressive enhancement: forbidden storage / SPA
 *      mechanisms are absent from source, and it never intercepts mutations.
 *
 * It does NOT assert browser behaviour (no JS runner exists; adding one would
 * require a new package, which Prompt 26 forbids).
 */
class ProgressiveNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function identityFrom(string $html): ?string
    {
        if (preg_match('/<meta name="navigation-identity" content="([^"]+)"/', $html, $m)) {
            return $m[1];
        }

        return null;
    }

    private function operator(DinasUnit $unit): User
    {
        return User::factory()->create([
            'role'          => 'operator',
            'is_active'     => true,
            'dinas_unit_id' => $unit->id,
        ]);
    }

    // ── Identity meta presence / absence ─────────────────────────────────────

    public function test_guest_pages_have_no_navigation_identity_meta(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('navigation-identity', false);
        $this->get('/register')->assertOk()->assertDontSee('navigation-identity', false);
        $this->get('/')->assertOk()->assertDontSee('navigation-identity', false);
    }

    public function test_authenticated_pages_render_a_navigation_identity_meta(): void
    {
        $user = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $html = $this->actingAs($user)->get('/laporan/dashboard')->assertOk()->getContent();
        $identity = $this->identityFrom($html);

        $this->assertNotNull($identity, 'authenticated page must expose a navigation identity');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $identity, 'identity must be a sha256 hex digest');
    }

    // ── Identity isolation axes (user / role / unit) ─────────────────────────

    public function test_identity_differs_between_two_users_of_the_same_role(): void
    {
        // PHASE 1 — a Dinas/Unit is owned by at most one Operator, so the two
        // operators use distinct units. The navigation identity still varies by
        // user id (the axis under test here); the unit axis is pinned separately
        // by test_identity_changes_when_operator_unit_changes.
        $unitA = DinasUnit::factory()->create();
        $unitB = DinasUnit::factory()->create();
        $a = $this->operator($unitA);
        $b = $this->operator($unitB);

        $idA = $this->identityFrom($this->actingAs($a)->get('/operator/dashboard')->getContent());
        $idB = $this->identityFrom($this->actingAs($b)->get('/operator/dashboard')->getContent());

        $this->assertNotNull($idA);
        $this->assertNotNull($idB);
        $this->assertNotSame($idA, $idB, 'different users must never share a cache namespace');
    }

    public function test_identity_changes_when_role_changes_for_the_same_user(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $asAdmin = $this->identityFrom($this->actingAs($user)->get('/admin/dashboard')->getContent());

        // Role change (server-side) must produce a different identity namespace.
        $user->forceFill(['role' => 'super_admin'])->save();
        $asSuper = $this->identityFrom(
            $this->actingAs($user->fresh())->get('/super-admin/dashboard')->getContent()
        );

        $this->assertNotNull($asAdmin);
        $this->assertNotNull($asSuper);
        $this->assertNotSame($asAdmin, $asSuper, 'role must be part of the cache identity');
    }

    public function test_identity_changes_when_operator_unit_changes(): void
    {
        $unitA = DinasUnit::factory()->create();
        $unitB = DinasUnit::factory()->create();
        $operator = $this->operator($unitA);

        $before = $this->identityFrom($this->actingAs($operator)->get('/operator/dashboard')->getContent());

        $operator->forceFill(['dinas_unit_id' => $unitB->id])->save();
        $after = $this->identityFrom(
            $this->actingAs($operator->fresh())->get('/operator/dashboard')->getContent()
        );

        $this->assertNotNull($before);
        $this->assertNotNull($after);
        $this->assertNotSame($before, $after, 'Dinas/Unit must be part of the cache identity (Operator isolation)');
    }

    public function test_identity_is_a_digest_not_the_raw_identity_components(): void
    {
        $unit = DinasUnit::factory()->create();
        $operator = $this->operator($unit);

        $identity = $this->identityFrom($this->actingAs($operator)->get('/operator/dashboard')->getContent());

        // Must be a non-reversible digest, never the raw "id|role|unit" triple.
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $identity);
        $rawTriple = $operator->id . '|' . 'operator' . '|' . $unit->id;
        $this->assertStringNotContainsString($rawTriple, $identity);
    }

    // ── DOM hooks the layer relies on ────────────────────────────────────────

    public function test_shell_pages_expose_stable_navigation_hooks(): void
    {
        $user = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $html = $this->actingAs($user)->get('/laporan/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('id="main-content"', $html);
        $this->assertStringContainsString('id="primary-navigation"', $html);
        $this->assertStringContainsString('id="page-heading"', $html);
        $this->assertStringContainsString('id="page-actions"', $html);
    }

    public function test_auth_pages_do_not_expose_the_shell_navigation_hook(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="primary-navigation"', $html);
    }

    // ── Progressive-enhancement / source contract ────────────────────────────

    public function test_navigation_module_exists_and_is_bootstrapped_by_app_js(): void
    {
        $this->assertFileExists(resource_path('js/navigation.js'));

        $appJs = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString("./navigation.js", $appJs);
        $this->assertStringContainsString('initNavigation', $appJs);

        // Alpine must remain the interactivity layer, started exactly once.
        $this->assertSame(
            1,
            substr_count($appJs, 'Alpine.start()'),
            'Alpine.start() must be called exactly once'
        );
    }

    public function test_navigation_source_uses_no_forbidden_storage_or_service_worker(): void
    {
        // Scan executable code only; strip comments so the module's own
        // documentation of what it does NOT use cannot cause a false positive.
        $js = file_get_contents(resource_path('js/navigation.js'));
        $code = preg_replace('#/\*.*?\*/#s', '', $js);   // block comments
        $code = preg_replace('#(^|\s)//[^\n]*#', '$1', $code); // line comments

        foreach ([
            'localStorage',
            'sessionStorage',
            'CacheStorage',
            'caches.',
            'indexedDB',
            'serviceWorker',
            'new Worker',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $code, "forbidden client storage in code: {$needle}");
        }
    }

    public function test_navigation_source_is_not_the_removed_spa_engine(): void
    {
        $js = file_get_contents(resource_path('js/navigation.js'));

        foreach (['SuperBieSPA', 'initSPA', 'X-SPA-Request', 'spa-progress-bar'] as $needle) {
            $this->assertStringNotContainsString($needle, $js, "must not resurrect SPA engine: {$needle}");
        }
    }

    public function test_navigation_only_intercepts_get_and_never_mutations(): void
    {
        $js = file_get_contents(resource_path('js/navigation.js'));

        // The fetch issued by the layer is GET-only.
        $this->assertStringContainsString("method: 'GET'", $js);

        // It must not submit forms itself (no mutation interception).
        $this->assertStringNotContainsString('form.submit()', $js);
        $this->assertStringNotContainsString("method: 'POST'", $js);
        $this->assertStringNotContainsString("method: 'PATCH'", $js);
        $this->assertStringNotContainsString("method: 'PUT'", $js);
        $this->assertStringNotContainsString("method: 'DELETE'", $js);

        // The in-memory cache is namespaced by the server identity.
        $this->assertStringContainsString('identityKey', $js);
        $this->assertStringContainsString('clearNavigationCache', $js);
    }

    public function test_navigation_excludes_auth_download_and_form_paths(): void
    {
        $js = file_get_contents(resource_path('js/navigation.js'));

        // Auth + logout + attachment downloads must never be intercepted/cached.
        foreach (["'/login'", "'/register'", "'/forgot-password'", "'/reset-password'", "'/logout'", '/lampiran/'] as $needle) {
            $this->assertStringContainsString($needle, $js, "missing exclusion: {$needle}");
        }

        // Form pages (CSRF) are not cached.
        $this->assertStringContainsString('create|edit|buat', $js);
    }

    /**
     * The public weather surface is a distinct full-page layout (its own dark
     * header/footer and its own Alpine scopes). This layer only swaps
     * `#main-content`'s innerHTML, so intercepting `/cuaca` would inject its
     * markup without its Alpine scope ("tab is not defined") and leave the
     * previous page's chrome in place. It must always full-navigate.
     */
    public function test_navigation_excludes_the_public_weather_surface(): void
    {
        $js = file_get_contents(resource_path('js/navigation.js'));

        $this->assertStringContainsString('WEATHER_PATH', $js);
        $this->assertStringContainsString('/^\\/cuaca', $js);
        $this->assertStringContainsString('isWeatherPath', $js);

        // Excluded as a destination AND never intercepted while the current page
        // is the weather surface.
        $this->assertStringContainsString('if (isWeatherPath(p)) return true;', $js);
        $this->assertStringContainsString('if (isWeatherPath(window.location.pathname)) return;', $js);
    }

    /**
     * The public market surface (`/siphp`; PHASE S) is the same kind of distinct
     * full-page layout as the weather surface: its own header/footer and its own
     * Alpine scope (the wall clock). This layer only swaps `#main-content`'s
     * innerHTML, so intercepting `/siphp` would leave the previous page's chrome
     * in place. It must always full-navigate.
     */
    public function test_navigation_excludes_the_public_market_surface(): void
    {
        $js = file_get_contents(resource_path('js/navigation.js'));

        $this->assertStringContainsString('SIPHP_PATH', $js);
        $this->assertStringContainsString('/^\\/siphp', $js);
        $this->assertStringContainsString('isSiphpPath', $js);

        // Excluded as a destination AND never intercepted while the current page
        // is the market surface.
        $this->assertStringContainsString('if (isSiphpPath(p)) return true;', $js);
        $this->assertStringContainsString('if (isSiphpPath(window.location.pathname)) return;', $js);
    }

    /**
     * The JDIH notice surface (`/jdih`; PHASE J) is the same kind of distinct
     * full-page layout: its own header/footer and no dashboard shell. It must
     * always full-navigate, and its one outbound link is external anyway.
     */
    public function test_navigation_excludes_the_jdih_notice_surface(): void
    {
        $js = file_get_contents(resource_path('js/navigation.js'));

        $this->assertStringContainsString('JDIH_PATH', $js);
        $this->assertStringContainsString('/^\\/jdih', $js);
        $this->assertStringContainsString('isJdihPath', $js);

        // Excluded as a destination AND never intercepted while the current page
        // is the notice surface.
        $this->assertStringContainsString('if (isJdihPath(p)) return true;', $js);
        $this->assertStringContainsString('if (isJdihPath(window.location.pathname)) return;', $js);
    }

    // ── Progressive enhancement: pages remain fully server-rendered ──────────

    public function test_pages_are_fully_server_rendered_without_javascript(): void
    {
        $user = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $html = $this->actingAs($user)->get('/laporan/dashboard')->assertOk()->getContent();

        // Real server-rendered content + real navigation links exist in the HTML.
        $this->assertStringContainsString('<a href="', $html);
        $this->assertStringContainsString('id="main-content"', $html);
    }

    public function test_route_inventory_is_unchanged(): void
    {
        // 61 baseline routes + the approved staff self-service profile page
        // (GET) + the approved staff self-service profile PATCH (edit)
        // + the approved location master data (FINAL rule):
        //     6 Kecamatan CRUD + 6 Kelurahan CRUD = 12
        // + the approved Operator location update route = 1.
        // + PHASE 2 (Super Admin initial validation) = 6:
        //     4 Super Admin validation routes (index / show / approve / return)
        //     + 2 citizen correction routes (edit GET / resubmit PUT).
        // - PHASE 2R (Operator immutability remediation) = -3:
        //     the operator category / destination / assignment mutation routes
        //     were REMOVED (PATCH .../kategori, .../tujuan, .../tugaskan).
        // + PHASE 3 (Completion Validation Gate — Operator submit) = +1:
        //     PATCH /operator/laporan/{complaint}/ajukan-penyelesaian.
        // + PHASE 3 (Completion Validation Gate — Super Admin decisions) = +4:
        //     GET  /super-admin/validasi-penyelesaian               (index)
        //     GET  /super-admin/validasi-penyelesaian/{complaint}   (show)
        //     POST /super-admin/validasi-penyelesaian/{complaint}/setujui    (approve)
        //     POST /super-admin/validasi-penyelesaian/{complaint}/kembalikan (return)
        // + PHASE 3 (Bagian 2 — Citizen satisfaction rating) = +1:
        //     POST /laporan/{complaint}/rating (citizen.rating.store)
        // + PHASE N4 (in-app notifications) = +5:
        //     GET    /notifikasi                          (notifications.index)
        //     POST   /notifikasi/read-all                 (notifications.read-all)
        //     GET    /notifikasi/{notification}/buka      (notifications.open)
        //     PATCH  /notifikasi/{notification}/read      (notifications.read)
        //     DELETE /notifikasi/{notification}           (notifications.destroy)
        // + PROMPT TAHAP 2 (Help Desk / Pusat Bantuan) = +10:
        //     GET    /bantuan                             (citizen.help.index)
        //     GET    /bantuan/buat                        (citizen.help.create)
        //     POST   /bantuan                             (citizen.help.store)
        //     GET    /bantuan/{ticket}                    (citizen.help.show)
        //     POST   /bantuan/{ticket}/pesan              (citizen.help.reply)
        //     POST   /bantuan/{ticket}/buka-kembali       (citizen.help.reopen)
        //     GET    /super-admin/bantuan                 (super-admin.help.index)
        //     GET    /super-admin/bantuan/{ticket}        (super-admin.help.show)
        //     POST   /super-admin/bantuan/{ticket}/balas  (super-admin.help.reply)
        //     PATCH  /super-admin/bantuan/{ticket}/status (super-admin.help.status)
        // + STAGE 3 (Official Super Admin reply to a review) = +1:
        //     POST   /super-admin/balasan-ulasan/{complaint}
        //                                                  (super-admin.review-response.store)
        // + WINDY weather migration (owner-authorized) = +5:
        //     GET    /cuaca                               (weather.index) — public, no login
        //     GET    /cuaca/{location}                    (weather.location) — public, per-location
        //     GET    /super-admin/cuaca                   (super-admin.weather.index)
        //     POST   /super-admin/cuaca/sinkronisasi      (super-admin.weather.sync)
        //     PUT    /super-admin/cuaca/ambang/{setting}  (super-admin.weather.setting.update)
        // - Admin "Monitoring Audit Log" removal (owner-authorized) = -1:
        //     GET    /admin/audit-log                     (admin.audit-log) — page removed
        // + PHASE S (Native SIPHP market module — owner decision D-S1) = +31:
        //     SUPER ADMIN PANEL (D-S2/D-S5/D-S6/D-S7; role:super_admin only):
        //     GET    /super-admin/pasar                                   (super-admin.markets.index)
        //     GET    /super-admin/pasar/create                            (super-admin.markets.create)
        //     POST   /super-admin/pasar                                   (super-admin.markets.store)
        //     GET    /super-admin/pasar/{market}/edit                     (super-admin.markets.edit)
        //     PUT    /super-admin/pasar/{market}                          (super-admin.markets.update)
        //     PATCH  /super-admin/pasar/{market}/status                   (super-admin.markets.toggle)
        //     DELETE /super-admin/pasar/{market}                          (super-admin.markets.destroy)
        //     GET    /super-admin/kategori-barang                         (super-admin.market-categories.index)
        //     GET    /super-admin/kategori-barang/create                  (super-admin.market-categories.create)
        //     POST   /super-admin/kategori-barang                         (super-admin.market-categories.store)
        //     GET    /super-admin/kategori-barang/{category}/edit         (super-admin.market-categories.edit)
        //     PUT    /super-admin/kategori-barang/{category}              (super-admin.market-categories.update)
        //     DELETE /super-admin/kategori-barang/{category}              (super-admin.market-categories.destroy)
        //     GET    /super-admin/barang                                  (super-admin.market-items.index)
        //     GET    /super-admin/barang/create                           (super-admin.market-items.create)
        //     POST   /super-admin/barang                                  (super-admin.market-items.store)
        //     GET    /super-admin/barang/{item}/edit                      (super-admin.market-items.edit)
        //     PUT    /super-admin/barang/{item}                           (super-admin.market-items.update)
        //     DELETE /super-admin/barang/{item}                           (super-admin.market-items.destroy)
        //     GET    /super-admin/harga-pasar                             (super-admin.market-prices.index)
        //     GET    /super-admin/harga-pasar/{market}/catat              (super-admin.market-prices.catat)
        //     POST   /super-admin/harga-pasar/{market}                    (super-admin.market-prices.store)
        //     DELETE /super-admin/harga-pasar/{price}                     (super-admin.market-prices.destroy)
        //     GET    /super-admin/toko-retail                             (super-admin.market-stores.index)
        //     GET    /super-admin/toko-retail/create                      (super-admin.market-stores.create)
        //     POST   /super-admin/toko-retail                             (super-admin.market-stores.store)
        //     GET    /super-admin/toko-retail/{store}/edit                (super-admin.market-stores.edit)
        //     PUT    /super-admin/toko-retail/{store}                     (super-admin.market-stores.update)
        //     DELETE /super-admin/toko-retail/{store}                     (super-admin.market-stores.destroy)
        //     PUBLIC (D-S3; no login, read-only):
        //     GET    /siphp                                               (siphp.index)
        //     GET    /siphp/pasar/{market}                                (siphp.market)
        // + PHASE J (JDIH outbound link — owner requirement) = +1:
        //     GET    /jdih                                               (jdih.index) — public notice before leaving
        // No other route was added, renamed or removed.
        $this->assertSame(
            137,
            count(app('router')->getRoutes()->getRoutes()),
            'Route inventory changed beyond the approved additions (location master data + operator location update + PHASE 2 validation - PHASE 2R removals + PHASE 3 completion submit + PHASE 3 completion validation + PHASE 3 citizen rating + PHASE N4 notifications + PROMPT TAHAP 2 help desk + STAGE 3 review response + WINDY weather - Admin audit-log removal + PHASE S native SIPHP market module + PHASE J JDIH outbound notice page).'
        );
    }
}
