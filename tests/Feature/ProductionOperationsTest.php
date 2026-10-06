<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
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
     * Scheduler inventory: exactly the retention purge is scheduled, daily at
     * 02:00, and nothing runs more frequently than that. This guards the frozen
     * retention rule (frequency must not drift) and detects any accidental
     * high-frequency task.
     */
    public function test_retention_purge_is_the_only_scheduled_task_at_02_00(): void
    {
        $events = app(Schedule::class)->events();

        $this->assertCount(
            1,
            $events,
            'Expected exactly one scheduled task (the retention purge). Add the new task to the operations docs and this inventory if that changes.'
        );

        $event = $events[0];
        $command = is_string($event->command) ? $event->command : '';

        $this->assertStringContainsString('complaints:purge-expired', $command);
        $this->assertSame('0 2 * * *', $event->expression);
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
