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
        $unit = DinasUnit::factory()->create();
        $a = $this->operator($unit);
        $b = $this->operator($unit);

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
        $this->assertSame(
            61,
            count(app('router')->getRoutes()->getRoutes()),
            'Prompt 26 must not add/remove routes'
        );
    }
}
