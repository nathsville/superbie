<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 23 — production-operations readiness.
 *
 * These tests cover ONLY operational facts that were not previously verified:
 * the health endpoint, the scheduler inventory, the secret-free CI workflow,
 * and the "no Redis" constraint. They assert existing behaviour — no business
 * rule is invented and no application code is exercised beyond what already
 * ships.
 */
class ProductionOperationsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The framework health endpoint must be reachable and report success.
     * It is the only liveness/readiness probe this application exposes.
     */
    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->get('/up');

        $response->assertStatus(200);
    }

    /**
     * The health endpoint must never leak secrets (APP_KEY, DB password) or a
     * full environment dump. This is the production probe an operator will
     * expose publicly, so it must stay inert.
     */
    public function test_health_endpoint_does_not_leak_environment_secrets(): void
    {
        $response = $this->get('/up');

        $body = $response->getContent();

        $appKey = (string) config('app.key');
        if ($appKey !== '') {
            $this->assertStringNotContainsString($appKey, $body);
        }

        $dbPassword = (string) config('database.connections.mysql.password');
        if ($dbPassword !== '') {
            $this->assertStringNotContainsString($dbPassword, $body);
        }

        // A boot probe must not echo configuration keys.
        $this->assertStringNotContainsString('APP_KEY', $body);
        $this->assertStringNotContainsString('DB_PASSWORD', $body);
    }

    /**
     * Scheduler inventory: the retention purge (daily 02:00) plus the WINDY
     * weather sync (every 3 hours). This guards the frozen retention rule
     * (frequency must not drift) and detects any accidental high-frequency task.
     */
    public function test_scheduled_tasks_are_the_retention_purge_and_weather_sync(): void
    {
        $events = app(Schedule::class)->events();

        $this->assertCount(
            2,
            $events,
            'Expected exactly two scheduled tasks (retention purge + WINDY weather sync). Add the new task to the operations docs and this inventory if that changes.'
        );

        $byCommand = collect($events)->keyBy(
            fn ($event) => is_string($event->command) ? $event->command : ''
        );

        $purge = $byCommand->first(fn ($event, $command) => str_contains($command, 'complaints:purge-expired'));
        $this->assertNotNull($purge, 'The retention purge must remain scheduled.');
        $this->assertSame('0 2 * * *', $purge->expression);

        // Prompt 28 / audit K-01: the scheduler MUST pass --force, otherwise
        // the command refuses to run in production and retention never happens.
        $this->assertStringContainsString(
            '--force',
            (string) $purge->command,
            'The scheduled retention purge must pass --force.'
        );

        $weather = $byCommand->first(fn ($event, $command) => str_contains($command, 'weather:sync'));
        $this->assertNotNull($weather, 'The WINDY weather sync must be scheduled (D-16).');
        $this->assertSame('0 */3 * * *', $weather->expression);
        $this->assertStringContainsString('--force', (string) $weather->command);
    }

    /**
     * Prompt 28 / audit K-01 — production safety contract for the retention
     * purge: without --force it must refuse to run (manual runs opt in), and
     * with --force it must proceed. Exercised with --dry-run so that this test
     * never deletes anything.
     */
    public function test_retention_purge_requires_force_in_production(): void
    {
        $this->app['env'] = 'production';

        $refused = Artisan::call('complaints:purge-expired');

        $this->assertSame(1, $refused, 'A production run without --force must fail.');
        $this->assertStringContainsString('--force', Artisan::output());

        $accepted = Artisan::call('complaints:purge-expired', [
            '--force' => true,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $accepted, 'A production run with --force must proceed.');
        $this->assertStringContainsString('DRY-RUN', Artisan::output());

        $this->app['env'] = 'testing';
    }

    /**
     * The CI workflow must exist, run the test suite and the asset build, and
     * must never reference production secrets or a deploy step. It targets the
     * throwaway test database only.
     */
    public function test_ci_workflow_is_secret_free_and_deploy_free(): void
    {
        $path = base_path('.github/workflows/ci.yml');
        $this->assertFileExists($path);

        $yml = (string) file_get_contents($path);

        // It must actually run the checks.
        $this->assertStringContainsString('php artisan test', $yml);
        $this->assertStringContainsString('npm run build', $yml);

        // It must never pull a repository/CI secret into the build.
        $this->assertStringNotContainsString('${{ secrets.', $yml);

        // It must target the isolated test database, never production.
        $this->assertStringContainsString('superbie_testing', $yml);
    }

    /**
     * The application must not depend on Redis for queue, cache, or session
     * (documented constraint: no Redis on the MVP). Enforced as a config
     * invariant so a future change cannot silently introduce it.
     */
    public function test_application_does_not_use_redis_for_queue_cache_or_session(): void
    {
        $this->assertNotSame('redis', config('queue.default'));
        $this->assertNotSame('redis', config('cache.default'));
        $this->assertNotSame('redis', config('session.driver'));

        // Failed queue jobs must have a real store (database by default).
        $this->assertNotNull(config('queue.failed.driver'));
        $this->assertNotSame('null', config('queue.failed.driver'));
    }
}
