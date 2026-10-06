<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\DinasUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 21 — Operator authorization & Dinas/Unit lifecycle (D-2).
 *
 * FROZEN DECISION D-2:
 *   - One Operator represents EXACTLY ONE Dinas/Unit (`users.dinas_unit_id`).
 *   - Super Admin is the authoritative actor that assigns/changes an Operator's
 *     Dinas/Unit (through the existing Super Admin User Management surface).
 *   - An Operator does NOT self-assign and does NOT modify another Operator.
 *
 * This suite proves the D-2 invariant server-side (never UI-only), audits every
 * mutation path, and pins the Prompt 15 complaint-scoping behaviour it must not
 * break. It does NOT invent any additional rule (no cross-unit assignment
 * restriction, no one-operator-per-unit rule, etc. — see Prompt 21 §3).
 */
class OperatorLifecycleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $admin;
    private User $operatorA;
    private User $operatorB;
    private User $citizen;

    private DinasUnit $unitA;
    private DinasUnit $unitB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->admin      = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->citizen    = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->unitA = DinasUnit::create([
            'name' => 'Dinas Lifecycle A', 'code' => 'LC-A', 'is_active' => true, 'sort_order' => 1,
        ]);
        $this->unitB = DinasUnit::create([
            'name' => 'Dinas Lifecycle B', 'code' => 'LC-B', 'is_active' => true, 'sort_order' => 2,
        ]);

        $this->operatorA = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unitA->id,
        ]);
        $this->operatorB = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unitB->id,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function createOperatorPayload(array $overrides = []): array
    {
        return array_merge([
            'name'                  => 'Operator Baru',
            'email'                 => 'operator.baru@superbie.local',
            'password'              => 'RahasiaKuat123!',
            'password_confirmation' => 'RahasiaKuat123!',
            'role'                  => 'operator',
            'is_active'             => '1',
            'dinas_unit_id'         => $this->unitA->id,
        ], $overrides);
    }

    // =========================================================================
    // ASSIGNMENT — Super Admin is the authoritative actor
    // =========================================================================

    public function test_super_admin_can_assign_unit_when_creating_an_operator(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.users.store'), $this->createOperatorPayload())
            ->assertRedirect(route('super-admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email'         => 'operator.baru@superbie.local',
            'role'          => 'operator',
            'dinas_unit_id' => $this->unitA->id,
        ]);

        // Assignment is audited (Prompt 21 §23).
        $log = AuditLog::where('action', 'user.created')->firstOrFail();
        $this->assertSame($this->unitA->id, (int) $log->metadata['dinas_unit_id']);
    }

    public function test_super_admin_can_change_an_operators_unit(): void
    {
        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.users.update', $this->operatorA), [
                'name'          => $this->operatorA->name,
                'email'         => $this->operatorA->email,
                'role'          => 'operator',
                'is_active'     => '1',
                'dinas_unit_id' => $this->unitB->id,
            ])
            ->assertRedirect(route('super-admin.users.index'));

        $this->assertSame($this->unitB->id, $this->operatorA->fresh()->dinas_unit_id);

        $log = AuditLog::where('action', 'user.dinas_unit_changed')->firstOrFail();
        $this->assertSame($this->unitA->id, (int) $log->metadata['old_dinas_unit_id']);
        $this->assertSame($this->unitB->id, (int) $log->metadata['new_dinas_unit_id']);
    }

    public function test_assigning_a_nonexistent_unit_is_rejected(): void
    {
        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.users.update', $this->operatorA), [
                'name'          => $this->operatorA->name,
                'email'         => $this->operatorA->email,
                'role'          => 'operator',
                'is_active'     => '1',
                'dinas_unit_id' => 999999,
            ])
            ->assertSessionHasErrors('dinas_unit_id');

        // Unchanged.
        $this->assertSame($this->unitA->id, $this->operatorA->fresh()->dinas_unit_id);
    }

    // =========================================================================
    // IDOR / HORIZONTAL PRIVILEGE ESCALATION
    // =========================================================================

    public function test_operator_cannot_assign_any_unit_through_super_admin_surface(): void
    {
        // Operator A tries to move itself to unit B and to reassign operator B.
        $this->actingAs($this->operatorA)
            ->put(route('super-admin.users.update', $this->operatorA), [
                'name' => $this->operatorA->name, 'email' => $this->operatorA->email,
                'role' => 'operator', 'is_active' => '1', 'dinas_unit_id' => $this->unitB->id,
            ])->assertForbidden();

        $this->actingAs($this->operatorA)
            ->put(route('super-admin.users.update', $this->operatorB), [
                'name' => $this->operatorB->name, 'email' => $this->operatorB->email,
                'role' => 'operator', 'is_active' => '1', 'dinas_unit_id' => $this->unitA->id,
            ])->assertForbidden();

        $this->actingAs($this->operatorA)
            ->post(route('super-admin.users.store'), $this->createOperatorPayload(['email' => 'x@y.local']))
            ->assertForbidden();

        // No assignment changed.
        $this->assertSame($this->unitA->id, $this->operatorA->fresh()->dinas_unit_id);
        $this->assertSame($this->unitB->id, $this->operatorB->fresh()->dinas_unit_id);
    }

    public function test_operator_cannot_self_assign_via_any_available_endpoint(): void
    {
        // There is NO operator-accessible user-management or profile endpoint.
        $this->actingAs($this->operatorA)->get(route('super-admin.users.index'))->assertForbidden();
        $this->actingAs($this->operatorA)->get(route('super-admin.users.create'))->assertForbidden();
        $this->actingAs($this->operatorA)->get(route('super-admin.users.edit', $this->operatorA))->assertForbidden();
        $this->actingAs($this->operatorA)->patch(route('super-admin.users.toggle', $this->operatorA))->assertForbidden();

        // The citizen profile route is masyarakat-only (role middleware) → 403.
        $this->actingAs($this->operatorA)->patch(route('citizen.profile.update'), [
            'name' => 'Hacked', 'phone_number' => '081100000999', 'address' => 'x',
            'dinas_unit_id' => $this->unitB->id, 'role' => 'super_admin',
        ])->assertForbidden();

        // Assignment and role are untouched.
        $fresh = $this->operatorA->fresh();
        $this->assertSame($this->unitA->id, $fresh->dinas_unit_id);
        $this->assertSame('operator', $fresh->role);
    }

    public function test_admin_cannot_use_the_super_admin_assignment_surface(): void
    {
        $this->actingAs($this->admin)
            ->put(route('super-admin.users.update', $this->operatorA), [
                'name' => $this->operatorA->name, 'email' => $this->operatorA->email,
                'role' => 'operator', 'is_active' => '1', 'dinas_unit_id' => $this->unitB->id,
            ])->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('super-admin.users.store'), $this->createOperatorPayload(['email' => 'adminmade@y.local']))
            ->assertForbidden();

        $this->assertSame($this->unitA->id, $this->operatorA->fresh()->dinas_unit_id);
        $this->assertDatabaseMissing('users', ['email' => 'adminmade@y.local']);
    }

    public function test_masyarakat_cannot_use_the_assignment_surface(): void
    {
        $this->actingAs($this->citizen)
            ->put(route('super-admin.users.update', $this->operatorA), [
                'name' => 'Hacked', 'email' => 'hacked@y.local',
                'role' => 'super_admin', 'is_active' => '1', 'dinas_unit_id' => $this->unitB->id,
            ])->assertForbidden();

        $fresh = $this->operatorA->fresh();
        $this->assertSame($this->unitA->id, $fresh->dinas_unit_id);
        $this->assertSame('operator', $fresh->role);
    }

    // =========================================================================
    // ROLE TRANSITION
    // =========================================================================

    public function test_promoting_a_non_operator_to_operator_requires_a_valid_unit(): void
    {
        // Missing unit → rejected, role unchanged.
        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.users.update', $this->citizen), [
                'name' => $this->citizen->name, 'email' => $this->citizen->email,
                'role' => 'operator', 'is_active' => '1', 'dinas_unit_id' => '',
            ])
            ->assertSessionHasErrors('dinas_unit_id');

        $this->assertSame('masyarakat', $this->citizen->fresh()->role);

        // Valid unit → promoted with exactly one unit.
        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.users.update', $this->citizen), [
                'name' => $this->citizen->name, 'email' => $this->citizen->email,
                'role' => 'operator', 'is_active' => '1', 'dinas_unit_id' => $this->unitB->id,
            ])
            ->assertRedirect(route('super-admin.users.index'));

        $promoted = $this->citizen->fresh();
        $this->assertSame('operator', $promoted->role);
        $this->assertSame($this->unitB->id, $promoted->dinas_unit_id);
    }

    public function test_demoting_an_operator_clears_the_unit(): void
    {
        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.users.update', $this->operatorA), [
                'name' => $this->operatorA->name, 'email' => $this->operatorA->email,
                'role' => 'admin', 'is_active' => '1', 'dinas_unit_id' => $this->unitA->id,
            ])
            ->assertRedirect(route('super-admin.users.index'));

        $demoted = $this->operatorA->fresh();
        $this->assertSame('admin', $demoted->role);
        $this->assertNull($demoted->dinas_unit_id);
    }

    // =========================================================================
    // OPERATOR WITHOUT UNIT — denied complaint surface (Prompt 15 BDR-3)
    // =========================================================================

    public function test_active_operator_without_unit_is_denied_the_complaint_surface(): void
    {
        $category = ComplaintCategory::create([
            'name' => 'Kategori LC', 'slug' => 'kategori-lc', 'is_active' => true, 'sort_order' => 1,
        ]);
        $category->dinasUnits()->attach($this->unitA->id);

        $complaint = Complaint::create([
            'reference_code' => 'LPW-LC-' . strtoupper(uniqid()),
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $category->id,
            'dinas_unit_id'  => $this->unitA->id,
            'title'          => 'Laporan LC',
            'description'    => 'Deskripsi laporan lifecycle.',
            'status'         => 'submitted',
            'submitted_at'   => now(),
        ]);

        // An operator WITH unit A can see it.
        $this->actingAs($this->operatorA)
            ->get(route('operator.complaint.show', $complaint))->assertOk();

        // Strip the unit (valid state, denied scope — never a global fallback).
        $this->operatorA->forceFill(['dinas_unit_id' => null])->save();

        $this->actingAs($this->operatorA->fresh())
            ->get(route('operator.complaint.index'))
            ->assertOk()
            ->assertDontSee($complaint->reference_code);

        $this->actingAs($this->operatorA->fresh())
            ->get(route('operator.complaint.show', $complaint))
            ->assertNotFound();
    }

    // =========================================================================
    // MODEL-LEVEL INVARIANT — no bypass path (§21/§22)
    // =========================================================================

    public function test_model_forces_null_unit_for_every_non_operator_role(): void
    {
        foreach (['masyarakat', 'admin', 'super_admin'] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);

            // Direct model write bypassing every HTTP layer / Form Request.
            $user->forceFill(['dinas_unit_id' => $this->unitA->id])->save();

            $this->assertNull(
                $user->fresh()->dinas_unit_id,
                "Role '{$role}' must never persist a Dinas/Unit."
            );
        }
    }

    public function test_model_keeps_exactly_one_unit_for_an_operator(): void
    {
        $operator = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unitA->id,
        ]);

        $this->assertSame($this->unitA->id, $operator->fresh()->dinas_unit_id);

        // Reassignment through the model replaces the single value.
        $operator->forceFill(['dinas_unit_id' => $this->unitB->id])->save();
        $this->assertSame($this->unitB->id, $operator->fresh()->dinas_unit_id);
    }

    public function test_model_does_not_force_a_unit_onto_an_operator(): void
    {
        // BDR-3: an operator without a unit is a VALID (denied-scope) state.
        $operator = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => null,
        ]);

        $this->assertNull($operator->fresh()->dinas_unit_id);
        $this->assertSame('operator', $operator->fresh()->role);
    }

    public function test_model_clears_unit_when_role_changes_away_from_operator(): void
    {
        $this->operatorA->forceFill(['role' => 'admin'])->save();

        $fresh = $this->operatorA->fresh();
        $this->assertSame('admin', $fresh->role);
        $this->assertNull($fresh->dinas_unit_id);
    }

    // =========================================================================
    // ACTIVATION / DEACTIVATION MUST NOT TOUCH THE ASSIGNMENT (§15)
    // =========================================================================

    public function test_deactivation_preserves_the_unit_assignment(): void
    {
        $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.users.toggle', $this->operatorA))
            ->assertRedirect(route('super-admin.users.index'));

        $fresh = $this->operatorA->fresh();
        $this->assertFalse($fresh->is_active);
        // Deactivation is NOT a reassignment: the unit is retained for audit /
        // reactivation (Prompt 21 §15 — no rule invented).
        $this->assertSame($this->unitA->id, $fresh->dinas_unit_id);

        // Reactivation also preserves it.
        $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.users.toggle', $this->operatorA))
            ->assertRedirect();

        $this->assertTrue($this->operatorA->fresh()->is_active);
        $this->assertSame($this->unitA->id, $this->operatorA->fresh()->dinas_unit_id);
    }

    // =========================================================================
    // AUDIT — assignment change is attributable (§23)
    // =========================================================================

    public function test_assignment_change_is_audited_with_actor_and_target(): void
    {
        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.users.update', $this->operatorA), [
                'name' => $this->operatorA->name, 'email' => $this->operatorA->email,
                'role' => 'operator', 'is_active' => '1', 'dinas_unit_id' => $this->unitB->id,
            ]);

        $log = AuditLog::where('action', 'user.dinas_unit_changed')
            ->where('subject_type', 'user')
            ->where('subject_id', $this->operatorA->id)
            ->firstOrFail();

        $this->assertSame($this->superAdmin->id, $log->actor_id);
        $this->assertNotNull($log->created_at);
        $this->assertSame($this->unitA->id, (int) $log->metadata['old_dinas_unit_id']);
        $this->assertSame($this->unitB->id, (int) $log->metadata['new_dinas_unit_id']);
    }

    // =========================================================================
    // PASSWORD INTEGRITY (unchanged by this flow)
    // =========================================================================

    public function test_reassignment_does_not_change_the_password(): void
    {
        $original = $this->operatorA->password;

        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.users.update', $this->operatorA), [
                'name' => $this->operatorA->name, 'email' => $this->operatorA->email,
                'role' => 'operator', 'is_active' => '1', 'dinas_unit_id' => $this->unitB->id,
            ]);

        $this->assertSame($original, $this->operatorA->fresh()->password);
    }

    // =========================================================================
    // LAST SUPER ADMIN INVARIANT STILL INTACT (Prompt 16) — no second path
    // =========================================================================

    public function test_last_active_super_admin_cannot_be_demoted_even_with_unit_fields(): void
    {
        // Only one active super admin exists in setUp.
        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.users.update', $this->superAdmin), [
                'name' => $this->superAdmin->name, 'email' => $this->superAdmin->email,
                'role' => 'admin', 'is_active' => '1', 'dinas_unit_id' => $this->unitA->id,
            ])
            ->assertSessionHasErrors('role');

        $fresh = $this->superAdmin->fresh();
        $this->assertSame('super_admin', $fresh->role);
        $this->assertNull($fresh->dinas_unit_id);
    }
}
