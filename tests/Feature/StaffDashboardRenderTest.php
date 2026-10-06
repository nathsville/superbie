<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard: every staff dashboard must render successfully (HTTP 200).
 *
 * This catches dead `route('...')` references inside Blade views — a broken
 * route name makes the whole dashboard throw RouteNotFoundException (500),
 * which authorization-only tests do not detect.
 */
class StaffDashboardRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_dashboard_renders(): void
    {
        $operator = User::factory()->create(['role' => 'operator', 'is_active' => true]);

        $this->actingAs($operator)->get('/operator/dashboard')->assertOk();
    }

    public function test_admin_dashboard_renders(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
    }

    public function test_super_admin_dashboard_renders(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->actingAs($superAdmin)->get('/super-admin/dashboard')->assertOk();
    }

    public function test_citizen_dashboard_renders(): void
    {
        $citizen = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->actingAs($citizen)->get('/laporan/dashboard')->assertOk();
    }

    public function test_database_seeder_runs_without_error(): void
    {
        // Exercises the real seeder fixture graph, including complaint rows whose
        // `assigned_to` must point at a valid seeded staff user.
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'operator@superbie.local']);
        $this->assertTrue(\App\Models\Complaint::query()->exists());
    }
}
