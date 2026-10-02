<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $citizen1;
    private User $citizen2;
    private User $petugas1;
    private User $petugas2;
    private User $operator;
    private User $admin;
    private User $superAdmin;
    private ComplaintCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->citizen1   = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
        $this->citizen2   = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
        $this->petugas1   = User::factory()->create(['role' => 'petugas', 'is_active' => true]);
        $this->petugas2   = User::factory()->create(['role' => 'petugas', 'is_active' => true]);
        $this->operator   = User::factory()->create(['role' => 'operator', 'is_active' => true]);
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

    // ─── 1. Guest Access Tests ────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login_when_accessing_dashboards(): void
    {
        $this->get('/laporan/dashboard')->assertRedirect('/login');
        $this->get('/petugas/dashboard')->assertRedirect('/login');
        $this->get('/operator/dashboard')->assertRedirect('/login');
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->get('/super-admin/dashboard')->assertRedirect('/login');
    }

    // ─── 2. Role-Based Middleware Tests ───────────────────────────────────────

    public function test_citizen_cannot_access_staff_dashboards(): void
    {
        $this->actingAs($this->citizen1)->get('/petugas/dashboard')->assertForbidden();
        $this->actingAs($this->citizen1)->get('/operator/dashboard')->assertForbidden();
        $this->actingAs($this->citizen1)->get('/admin/dashboard')->assertForbidden();
        $this->actingAs($this->citizen1)->get('/super-admin/dashboard')->assertForbidden();
    }

    public function test_petugas_cannot_access_other_role_dashboards(): void
    {
        $this->actingAs($this->petugas1)->get('/laporan/dashboard')->assertForbidden();
        $this->actingAs($this->petugas1)->get('/operator/dashboard')->assertForbidden();
        $this->actingAs($this->petugas1)->get('/admin/dashboard')->assertForbidden();
        $this->actingAs($this->petugas1)->get('/super-admin/dashboard')->assertForbidden();
    }

    public function test_admin_cannot_access_super_admin_dashboard(): void
    {
        $this->actingAs($this->admin)->get('/super-admin/dashboard')->assertForbidden();
    }

    // ─── 3. Inactive User Test ────────────────────────────────────────────────

    public function test_inactive_user_is_logged_out_and_redirected(): void
    {
        $inactive = User::factory()->create(['role' => 'masyarakat', 'is_active' => false]);

        $this->actingAs($inactive)->get('/laporan/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    // ─── 4. Citizen Data Isolation Tests (Critical requirement) ───────────────

    public function test_citizen_only_sees_their_own_complaints_on_dashboard(): void
    {
        // Complaint owned by citizen 1
        $complaint1 = Complaint::create([
            'reference_code' => 'LPW-TEST-001',
            'reporter_id'    => $this->citizen1->id,
            'category_id'    => $this->category->id,
            'title'          => 'Laporan Rahasia Citizen 1',
            'description'    => 'Deskripsi laporan pertama',
            'status'         => 'submitted',
        ]);

        // Complaint owned by citizen 2
        $complaint2 = Complaint::create([
            'reference_code' => 'LPW-TEST-002',
            'reporter_id'    => $this->citizen2->id,
            'category_id'    => $this->category->id,
            'title'          => 'Laporan Rahasia Citizen 2',
            'description'    => 'Deskripsi laporan kedua',
            'status'         => 'submitted',
        ]);

        // Citizen 1 dashboard must contain citizen 1's report, NOT citizen 2's
        $response1 = $this->actingAs($this->citizen1)->get('/laporan/dashboard');
        $response1->assertOk();
        $response1->assertSee('Laporan Rahasia Citizen 1');
        $response1->assertDontSee('Laporan Rahasia Citizen 2');

        // Citizen 2 dashboard must contain citizen 2's report, NOT citizen 1's
        $response2 = $this->actingAs($this->citizen2)->get('/laporan/dashboard');
        $response2->assertOk();
        $response2->assertSee('Laporan Rahasia Citizen 2');
        $response2->assertDontSee('Laporan Rahasia Citizen 1');
    }

    // ─── 5. Petugas Data Isolation Tests ──────────────────────────────────────

    public function test_petugas_only_sees_complaints_assigned_to_them(): void
    {
        // Complaint assigned to Petugas 1
        Complaint::create([
            'reference_code' => 'LPW-PET-001',
            'reporter_id'    => $this->citizen1->id,
            'assigned_to'    => $this->petugas1->id,
            'category_id'    => $this->category->id,
            'title'          => 'Tugas Petugas 1',
            'description'    => 'Deskripsi tugas 1',
            'status'         => 'in_progress',
        ]);

        // Complaint assigned to Petugas 2
        Complaint::create([
            'reference_code' => 'LPW-PET-002',
            'reporter_id'    => $this->citizen1->id,
            'assigned_to'    => $this->petugas2->id,
            'category_id'    => $this->category->id,
            'title'          => 'Tugas Petugas 2',
            'description'    => 'Deskripsi tugas 2',
            'status'         => 'in_progress',
        ]);

        // Petugas 1 dashboard
        $response1 = $this->actingAs($this->petugas1)->get('/petugas/dashboard');
        $response1->assertOk();
        $response1->assertSee('Tugas Petugas 1');
        $response1->assertDontSee('Tugas Petugas 2');

        // Petugas 2 dashboard
        $response2 = $this->actingAs($this->petugas2)->get('/petugas/dashboard');
        $response2->assertOk();
        $response2->assertSee('Tugas Petugas 2');
        $response2->assertDontSee('Tugas Petugas 1');
    }

    // ─── 6. Dashboard Redirect Test ───────────────────────────────────────────

    public function test_authenticated_users_are_redirected_to_their_correct_dashboard(): void
    {
        $this->actingAs($this->citizen1)->get('/dashboard')->assertRedirect('/laporan/dashboard');
        $this->actingAs($this->petugas1)->get('/dashboard')->assertRedirect('/petugas/dashboard');
        $this->actingAs($this->operator)->get('/dashboard')->assertRedirect('/operator/dashboard');
        $this->actingAs($this->admin)->get('/dashboard')->assertRedirect('/admin/dashboard');
        $this->actingAs($this->superAdmin)->get('/dashboard')->assertRedirect('/super-admin/dashboard');
    }
}
