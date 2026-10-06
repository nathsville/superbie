<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\DinasUnit;
use App\Models\User;
use App\Services\DashboardCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardCachingTest extends TestCase
{
    use RefreshDatabase;

    private User $citizen;
    private User $operator;
    private User $admin;
    private User $superAdmin;
    private ComplaintCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        // Prompt 15 — Operators are scoped to a Dinas/Unit.
        $unit = DinasUnit::create([
            'name' => 'Dinas Uji Cache', 'code' => 'UJI-CACHE',
            'is_active' => true, 'sort_order' => 1,
        ]);

        $this->citizen    = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
        $this->operator   = User::factory()->create(['role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $unit->id]);
        $this->admin      = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->category = ComplaintCategory::create([
            'name'       => 'Infrastruktur Jalan',
            'slug'       => 'infrastruktur-jalan',
            'dinas_name' => 'Dinas PUPR',
            'is_active'  => true,
            'sort_order' => 1,
        ]);
    }

    public function test_citizen_dashboard_metrics_are_cached_and_invalidated_on_new_complaint(): void
    {
        $cacheKey = "dashboard:citizen:{$this->citizen->id}";
        Cache::forget($cacheKey);
        $this->assertFalse(Cache::has($cacheKey));

        // 1st request populates cache
        $response = $this->actingAs($this->citizen)->get('/laporan/dashboard');
        $response->assertOk();
        $this->assertTrue(Cache::has($cacheKey));

        // Create new complaint for this citizen
        Complaint::create([
            'reference_code' => 'LPW-TEST-001',
            'tracking_secret_hash' => hash('sha256', 'secret'),
            'category_id' => $this->category->id,
            'reporter_id' => $this->citizen->id,
            'title' => 'Test Jalan Rusak',
            'description' => 'Deskripsi jalan rusak panjang',
            'location_text' => 'Jl. Bau Massepe',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        // Observer should invalidate citizen cache
        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_operator_dashboard_metrics_are_cached(): void
    {
        // Prompt 15 — the operator cache key is scoped per Dinas/Unit + version.
        $cacheKey = app(DashboardCacheService::class)->operatorCacheKey($this->operator);
        Cache::forget($cacheKey);

        $response = $this->actingAs($this->operator)->get('/operator/dashboard');
        $response->assertOk();
        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_admin_dashboard_supports_refresh_param(): void
    {
        $cacheKey = 'dashboard:admin';
        Cache::forget($cacheKey);

        $res1 = $this->actingAs($this->admin)->get('/admin/dashboard');
        $res1->assertOk();
        $this->assertTrue(Cache::has($cacheKey));

        // 2nd request: renders directly from cached metrics
        $res2 = $this->actingAs($this->admin)->get('/admin/dashboard');
        $res2->assertOk();
        $res2->assertSee('Dashboard Admin');

        // Calling with ?refresh=1 should refresh cache successfully
        $response = $this->actingAs($this->admin)->get('/admin/dashboard?refresh=1');
        $response->assertOk();
        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_super_admin_dashboard_metrics_are_cached_and_cleared_on_user_create(): void
    {
        $cacheKey = 'dashboard:superadmin';
        Cache::forget($cacheKey);

        $this->actingAs($this->superAdmin)->get('/super-admin/dashboard');
        $this->assertTrue(Cache::has($cacheKey));

        // Create new user -> UserObserver clears superadmin cache
        User::factory()->create(['role' => 'masyarakat']);
        $this->assertFalse(Cache::has($cacheKey));
    }
}
