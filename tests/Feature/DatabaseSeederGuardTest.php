<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 28 (audit T-01) — development-seeder safety.
 *
 * DatabaseSeeder provisions development accounts with a publicly known
 * password ("password123"). It must refuse to run in production, so a stray
 * `db:seed` can never create an active Super Admin with that password on a
 * live system — while continuing to work for local development.
 */
class DatabaseSeederGuardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * In production the seeder must throw before writing anything.
     */
    public function test_database_seeder_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        try {
            (new DatabaseSeeder())->run();
            $this->fail('DatabaseSeeder must refuse to run in production.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('menolak berjalan di production', $e->getMessage());
        }

        $this->assertSame(0, User::query()->count(), 'The refusal must happen before any user is written.');
    }

    /**
     * The production refusal must not have broken local development seeding:
     * the same seeder still provisions the documented fixtures.
     */
    public function test_database_seeder_still_runs_outside_production(): void
    {
        (new DatabaseSeeder())->run();

        $superAdmin = User::query()->where('email', 'superadmin@superbie.local')->first();

        $this->assertNotNull($superAdmin, 'The development Super Admin fixture must be created.');
        $this->assertSame('super_admin', $superAdmin->role);
        $this->assertSame(5, User::query()->count(), 'Exactly the five documented development accounts.');

        $this->assertSame(4, Complaint::query()->count(), 'The four sample complaints must be seeded.');
        $this->assertTrue(
            AuditLog::query()->where('action', 'system.seeded')->exists(),
            'The seed audit entry must be written.'
        );
    }
}
