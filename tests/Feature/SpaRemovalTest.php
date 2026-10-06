<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 19 — Scope F: SPA engine removal.
 *
 * F-18-01: resources/js/spa.js was a client-side navigation/caching engine that
 * contradicted the frozen architecture (Blade + Alpine.js, no SPA). It had no
 * valid functional dependency (only the orphaned layouts/dashboard.blade.php
 * referenced its UI), so it was removed.
 *
 * These assertions guard against reintroduction and against broken references.
 */
class SpaRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_spa_engine_source_file_no_longer_exists(): void
    {
        $this->assertFileDoesNotExist(resource_path('js/spa.js'));
    }

    public function test_app_js_does_not_import_or_start_the_spa_engine(): void
    {
        $appJs = file_get_contents(resource_path('js/app.js'));

        $this->assertStringNotContainsString('spa.js', $appJs);
        $this->assertStringNotContainsString('initSPA', $appJs);
        $this->assertStringNotContainsString('SuperBieSPA', $appJs);

        // Alpine.js must remain the interactivity layer.
        $this->assertStringContainsString('alpinejs', $appJs);
    }

    public function test_no_live_source_file_references_the_spa_engine(): void
    {
        $needles = ['SuperBieSPA', 'initSPA', 'X-SPA-Request', 'data-spa-cache-time', 'spa-progress-bar'];

        $roots = [
            resource_path('js'),
            resource_path('css'),
            resource_path('views'),
            base_path('app'),
            base_path('routes'),
            base_path('config'),
        ];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            foreach ($this->iterateFiles($root) as $file) {
                // Only inspect text sources.
                if (! preg_match('/\.(php|js|css|blade\.php)$/', $file)) {
                    continue;
                }

                $contents = file_get_contents($file);

                foreach ($needles as $needle) {
                    $this->assertStringNotContainsString(
                        $needle,
                        $contents,
                        "SPA reference '{$needle}' still present in {$file}"
                    );
                }
            }
        }
    }

    public function test_app_boots_and_public_pages_render_after_spa_removal(): void
    {
        // Guards against a broken build/boot caused by the removal.
        $this->get('/')->assertOk();
    }

    /**
     * @return iterable<string>
     */
    private function iterateFiles(string $dir): iterable
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                yield $file->getPathname();
            }
        }
    }
}
