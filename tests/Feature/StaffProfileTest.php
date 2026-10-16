<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DinasUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * SUPERBIE — Staff self-service profile.
 *
 * Staff accounts (operator / admin / super_admin) have a real account page so
 * the shell's profile link is never a dead link. A staff member may self-edit
 * their OWN non-privileged contact fields (name, phone number), their profile
 * photo, and their own password.
 *
 * Privileged fields — `role`, `dinas_unit_id`, `email` and `nik` — are NEVER
 * editable here: they remain Super-Admin-only via User Management (Prompt 9/21)
 * and are additionally enforced at the model layer (User::booted).
 *
 * Every value on the page is the authenticated user's own stored data; nothing
 * is fabricated.
 */
class StaffProfileTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        $unit = DinasUnit::factory()->create(['name' => 'Dinas Komunikasi dan Informatika']);

        return User::factory()->create([
            'role'          => 'operator',
            'is_active'     => true,
            'name'          => 'Rina Operator',
            'dinas_unit_id' => $unit->id,
        ]);
    }

    // ── Reachability + rendering per staff role ──────────────────────────────

    public function test_staff_roles_can_open_their_profile_page(): void
    {
        foreach (['operator', 'admin', 'super_admin'] as $role) {
            $user = $role === 'operator'
                ? $this->operator()
                : User::factory()->create([
                    'role'      => $role,
                    'is_active' => true,
                    'name'      => ucfirst($role).' Tester',
                ]);

            $html = $this->actingAs($user)
                ->get(route('staff.profile'))
                ->assertOk()
                ->getContent();

            // Real account data only.
            $this->assertStringContainsString($user->name, $html, "name missing for {$role}");
            $this->assertStringContainsString($user->email, $html, "email missing for {$role}");

            // Privileged fields are never rendered as editable inputs.
            $this->assertStringNotContainsString('name="role"', $html, "role must not be editable for {$role}");
            $this->assertStringNotContainsString('name="dinas_unit_id"', $html, "dinas must not be editable for {$role}");
            $this->assertStringNotContainsString('name="email"', $html, "email must not be editable for {$role}");
            $this->assertStringNotContainsString('name="nik"', $html, "nik must not be editable for {$role}");
        }
    }

    public function test_operator_profile_shows_its_dinas_unit(): void
    {
        $operator = $this->operator();

        $html = $this->actingAs($operator)->get(route('staff.profile'))->assertOk()->getContent();

        $this->assertStringContainsString('Dinas Komunikasi dan Informatika', $html);
        $this->assertStringContainsString('Operator', $html);
    }

    public function test_admin_profile_does_not_render_a_dinas_unit_row(): void
    {
        // Dinas/Unit is meaningful for operators only (Prompt 15). Admin must not
        // see a fabricated/empty unit row.
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $html = $this->actingAs($admin)->get(route('staff.profile'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Dinas/Unit', $html);
    }

    // ── Authorization: the page is staff-only, never for citizens ────────────

    public function test_citizen_cannot_open_the_staff_profile_page(): void
    {
        $citizen = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->actingAs($citizen)->get(route('staff.profile'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('staff.profile'))->assertRedirect(route('login'));
    }

    public function test_citizen_cannot_submit_the_staff_profile_update(): void
    {
        $citizen = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->actingAs($citizen)
            ->patch(route('staff.profile.update'), ['name' => 'Hacked'])
            ->assertForbidden();
    }

    // ── Self-service: a staff member may edit their own contact fields ───────

    public function test_staff_member_can_update_own_name_and_phone(): void
    {
        $operator = $this->operator();

        $this->actingAs($operator)
            ->patch(route('staff.profile.update'), [
                'name'         => 'Nama Baru Operator',
                'phone_number' => '081299990001',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $operator->fresh();
        $this->assertSame('Nama Baru Operator', $fresh->name);
        $this->assertSame('081299990001', $fresh->phone_number);

        // The action is audited.
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $operator->id,
            'action'   => 'profile_updated',
        ]);
    }

    public function test_staff_can_update_own_password(): void
    {
        $admin = User::factory()->create([
            'role'     => 'admin',
            'is_active' => true,
            'password' => Hash::make('OldPassword123!'),
        ]);

        $this->actingAs($admin)
            ->patch(route('staff.profile.update'), [
                'name'                  => $admin->name,
                'current_password'      => 'OldPassword123!',
                'password'              => 'BrandNewPassword123!',
                'password_confirmation' => 'BrandNewPassword123!',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('BrandNewPassword123!', $admin->fresh()->password));
    }

    public function test_staff_password_change_requires_correct_current_password(): void
    {
        $admin = User::factory()->create([
            'role'     => 'admin',
            'is_active' => true,
            'password' => Hash::make('OldPassword123!'),
        ]);

        $this->actingAs($admin)
            ->patch(route('staff.profile.update'), [
                'name'                  => $admin->name,
                'current_password'      => 'WrongPassword!',
                'password'              => 'BrandNewPassword123!',
                'password_confirmation' => 'BrandNewPassword123!',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('OldPassword123!', $admin->fresh()->password));
    }

    public function test_staff_can_upload_a_profile_photo(): void
    {
        Storage::fake('public', ['url' => 'http://localhost/storage']);

        $operator = $this->operator();

        $this->actingAs($operator)
            ->patch(route('staff.profile.update'), [
                'name'   => $operator->name,
                'avatar' => UploadedFile::fake()->image('me.jpg', 200, 200),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $path = $operator->fresh()->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_staff_profile_header_renders_the_uploaded_photo(): void
    {
        Storage::fake('public', ['url' => 'http://localhost/storage']);

        $operator = $this->operator();
        $operator->forceFill(['avatar_path' => 'avatars/'.$operator->id.'/me.jpg'])->save();

        $html = $this->actingAs($operator)->get(route('staff.profile'))->assertOk()->getContent();

        // The header card shows the REAL stored photo, not the initial fallback.
        $url = $operator->avatarUrl();
        $this->assertNotNull($url);
        $this->assertStringContainsString('src="'.$url.'"', $html);
    }

    public function test_staff_avatar_validation_error_is_surfaced(): void
    {
        Storage::fake('public', ['url' => 'http://localhost/storage']);

        $operator = $this->operator();

        // A non-image file is rejected, and the redirect back to the profile page
        // renders the error text (previously the failure was silent).
        $this->from(route('staff.profile'))
            ->actingAs($operator)
            ->followingRedirects()
            ->patch(route('staff.profile.update'), [
                'name'   => $operator->name,
                'avatar' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
            ])
            ->assertOk()
            ->assertSee('Berkas foto harus berupa gambar.');
    }

    public function test_staff_profile_auto_opens_edit_mode_on_validation_error(): void
    {
        $operator = $this->operator();

        // A failed submit must re-open edit mode so the errors (rendered inside
        // the edit-only blocks) are actually visible to the user.
        $this->from(route('staff.profile'))
            ->actingAs($operator)
            ->followingRedirects()
            ->patch(route('staff.profile.update'), ['name' => ''])
            ->assertSee('x-data="{ editing: true,', false);
    }

    // ── Privilege escalation is impossible from this surface ─────────────────

    public function test_staff_cannot_self_assign_role_or_dinas_unit(): void
    {
        $operator = $this->operator();
        $original = $operator->dinas_unit_id;

        $otherUnit = DinasUnit::factory()->create();

        // A hand-crafted payload that bypasses the UI entirely.
        $this->actingAs($operator)
            ->patch(route('staff.profile.update'), [
                'name'          => 'Escalation Attempt',
                'role'          => 'super_admin',
                'dinas_unit_id' => $otherUnit->id,
                'email'         => 'attacker@superbie.local',
                'nik'           => '0000000000000000',
                'is_active'     => false,
            ])
            ->assertRedirect();

        $fresh = $operator->fresh();
        $this->assertSame('operator', $fresh->role, 'role must never change from the self-service profile');
        $this->assertSame($original, $fresh->dinas_unit_id, 'dinas_unit_id must never change from the self-service profile');
        $this->assertNotSame('attacker@superbie.local', $fresh->email, 'email must never change from the self-service profile');
        $this->assertNotSame('0000000000000000', $fresh->nik, 'nik must never change from the self-service profile');
        $this->assertTrue($fresh->is_active, 'is_active must never change from the self-service profile');
    }

    public function test_staff_update_route_exists_and_is_a_single_patch(): void
    {
        // Exactly one mutating route lives on the staff profile prefix.
        $mutating = collect(app('router')->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'profil'))
            ->flatMap(fn ($route) => $route->methods())
            ->filter(fn ($method) => in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true))
            ->values();

        $this->assertSame(['PATCH'], $mutating->all(), 'Staff profile must expose exactly one PATCH mutation');
    }
}
