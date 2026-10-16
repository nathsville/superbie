<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\DinasUnit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * SUPERBIE — PHASE 1 — Operator ↔ Dinas/Unit ownership is ONE-TO-ONE.
 *
 * FINAL, approved business rule:
 *   - Each Operator belongs to EXACTLY ONE Dinas/Unit (`users.dinas_unit_id`).
 *   - Each Dinas/Unit is owned by AT MOST ONE Operator.
 *
 * The rule is enforced at THREE independent layers (defence in depth):
 *   1. DB  — UNIQUE index `users_dinas_unit_id_unique` (hard guarantee).
 *   2. HTTP — `StoreUserRequest` / `UpdateUserRequest` `unique` rule.
 *   3. Model — `User::booted()` `saving` guard (catchable ValidationException).
 *
 * This suite also pins the invariants Phase 1 MUST NOT break:
 *   - historical complaints keep their stored destination (`complaints.dinas_unit_id`);
 *   - `complaints.assigned_to` is a DIFFERENT concept and is never touched;
 *   - deactivating a Dinas/Unit never auto-clears the relationship or the Operator;
 *   - role integrity: an Operator with a unit can never become a non-operator
 *     while still holding it;
 *   - only Super Admin may manage the relationship (server-side, never UI-only).
 */
class OperatorDinasOwnershipPhase1Test extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $admin;
    private User $operatorA;
    private User $operatorB;
    private User $citizen;

    private DinasUnit $unitA;
    private DinasUnit $unitB;
    private DinasUnit $freeUnit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->admin      = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->citizen    = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->unitA = DinasUnit::create([
            'name' => 'Dinas Phase1 A', 'code' => 'P1-A', 'is_active' => true, 'sort_order' => 1,
        ]);
        $this->unitB = DinasUnit::create([
            'name' => 'Dinas Phase1 B', 'code' => 'P1-B', 'is_active' => true, 'sort_order' => 2,
        ]);
        $this->freeUnit = DinasUnit::create([
            'name' => 'Dinas Phase1 Bebas', 'code' => 'P1-FREE', 'is_active' => true, 'sort_order' => 3,
        ]);

        $this->operatorA = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unitA->id,
        ]);
        $this->operatorB = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unitB->id,
        ]);
    }

    // =========================================================================
    // 1. SCHEMA — the DB-level 1:1 guarantee
    // =========================================================================

    public function test_dinas_unit_id_has_a_unique_index(): void
    {
        $indexes = collect(DB::select('SHOW INDEX FROM users'))
            ->filter(fn ($row) => $row->Column_name === 'dinas_unit_id');

        $this->assertTrue(
            $indexes->contains(fn ($row) => (int) $row->Non_unique === 0),
            'users.dinas_unit_id must carry a UNIQUE index (the 1:1 DB guarantee).'
        );
    }

    public function test_database_rejects_two_operators_on_the_same_unit(): void
    {
        // Bypass the model guard entirely to prove the DB constraint alone.
        $this->expectException(QueryException::class);

        DB::table('users')->insert([
            'name'          => 'Operator Duplikat',
            'email'         => 'operator.duplikat@superbie.local',
            'password'      => bcrypt('RahasiaKuat123!'),
            'role'          => 'operator',
            'is_active'     => true,
            'dinas_unit_id' => $this->unitA->id,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function test_many_unit_less_operators_do_not_collide(): void
    {
        // MySQL treats NULL as distinct → the UNIQUE index does not block this.
        $a = User::factory()->create(['role' => 'operator', 'is_active' => true, 'dinas_unit_id' => null]);
        $b = User::factory()->create(['role' => 'operator', 'is_active' => true, 'dinas_unit_id' => null]);

        $this->assertNull($a->fresh()->dinas_unit_id);
        $this->assertNull($b->fresh()->dinas_unit_id);
    }

    // =========================================================================
    // 2. RELATIONSHIP — hasOne operator + deprecated users() alias
    // =========================================================================

    public function test_dinas_unit_operator_relationship_returns_the_single_owner(): void
    {
        $this->assertSame($this->operatorA->id, $this->unitA->operator->id);
        $this->assertSame($this->operatorB->id, $this->unitB->operator->id);
        $this->assertNull($this->freeUnit->operator);
    }

    public function test_operator_relationship_ignores_non_operator_rows(): void
    {
        // A hand-crafted non-operator row pointing at a still-FREE unit
        // (defensive read: it must never be surfaced as "the operator").
        DB::table('users')->insert([
            'name'          => 'Bukan Operator',
            'email'         => 'bukan.operator@superbie.local',
            'password'      => bcrypt('RahasiaKuat123!'),
            'role'          => 'admin',
            'is_active'     => true,
            'dinas_unit_id' => $this->freeUnit->id,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->assertNull($this->freeUnit->fresh()->operator);
        $this->assertSame($this->operatorA->id, $this->unitA->fresh()->operator->id);
    }

    public function test_operator_for_dinas_helper_resolves_the_owner(): void
    {
        $this->assertSame($this->operatorA->id, User::operatorForDinas($this->unitA->id)->id);
        $this->assertSame($this->operatorB->id, User::operatorForDinas($this->unitB->id)->id);
        $this->assertNull(User::operatorForDinas($this->freeUnit->id));
    }

    public function test_scope_operator_only_returns_operator_accounts(): void
    {
        $operators = User::operator()->pluck('role')->unique()->all();

        $this->assertSame(['operator'], $operators);
        $this->assertTrue(User::operator()->where('role', '!=', 'operator')->doesntExist());
    }

    // =========================================================================
    // 3. ASSIGNMENT — create / reassign / release through the Super Admin UI
    // =========================================================================

    public function test_super_admin_can_assign_a_free_unit_to_a_new_operator(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.users.store'), [
                'name' => 'Operator Fase Satu',
                'email' => 'operator.fase.satu@superbie.local',
                'password' => 'RahasiaKuat123!',
                'password_confirmation' => 'RahasiaKuat123!',
                'role' => 'operator',
                'is_active' => '1',
                'dinas_unit_id' => $this->freeUnit->id,
            ])
            ->assertRedirect(route('super-admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'operator.fase.satu@superbie.local',
            'dinas_unit_id' => $this->freeUnit->id,
        ]);
    }

    public function test_assigning_an_already_owned_unit_is_rejected(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.users.store'), [
                'name' => 'Operator Kedua',
                'email' => 'operator.kedua@superbie.local',
                'password' => 'RahasiaKuat123!',
                'password_confirmation' => 'RahasiaKuat123!',
                'role' => 'operator',
                'is_active' => '1',
                'dinas_unit_id' => $this->unitA->id, // owned by operatorA
            ])
            ->assertSessionHasErrors('dinas_unit_id');

        $this->assertDatabaseMissing('users', ['email' => 'operator.kedua@superbie.local']);
        // The existing owner is untouched.
        $this->assertSame($this->unitA->id, $this->operatorA->fresh()->dinas_unit_id);
    }

    public function test_reassigning_an_operator_requires_a_free_target_unit(): void
    {
        // Target unitB is already owned by operatorB → rejected.
        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.users.update', $this->operatorA), [
                'name' => $this->operatorA->name,
                'email' => $this->operatorA->email,
                'role' => 'operator',
                'is_active' => '1',
                'dinas_unit_id' => $this->unitB->id,
            ])
            ->assertSessionHasErrors('dinas_unit_id');

        $this->assertSame($this->unitA->id, $this->operatorA->fresh()->dinas_unit_id);
        $this->assertSame($this->unitB->id, $this->operatorB->fresh()->dinas_unit_id);
    }

    public function test_super_admin_can_move_an_operator_to_a_free_unit(): void
    {
        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.users.update', $this->operatorA), [
                'name' => $this->operatorA->name,
                'email' => $this->operatorA->email,
                'role' => 'operator',
                'is_active' => '1',
                'dinas_unit_id' => $this->freeUnit->id,
            ])
            ->assertRedirect(route('super-admin.users.index'));

        $this->assertSame($this->freeUnit->id, $this->operatorA->fresh()->dinas_unit_id);
        // The old unit is now free.
        $this->assertNull($this->unitA->fresh()->operator);
    }

    // =========================================================================
    // 4. MODEL-LAYER GUARD — direct writes cannot bypass the rule
    // =========================================================================

    public function test_model_guard_rejects_a_direct_second_operator_on_an_owned_unit(): void
    {
        $this->expectException(ValidationException::class);

        User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unitA->id,
        ]);
    }

    public function test_model_guard_rejects_a_direct_reassignment_onto_an_owned_unit(): void
    {
        $this->expectException(ValidationException::class);

        $this->operatorA->forceFill(['dinas_unit_id' => $this->unitB->id])->save();
    }

    public function test_model_guard_allows_an_idempotent_save(): void
    {
        // Saving the current owner again with the SAME unit must not self-conflict.
        $this->operatorA->forceFill(['name' => 'Nama Baru Operator A'])->save();

        $this->assertSame($this->unitA->id, $this->operatorA->fresh()->dinas_unit_id);
    }

    // =========================================================================
    // 5. ROLE INTEGRITY — a unit-holding account may never stop being an Operator
    // =========================================================================

    public function test_model_clears_the_unit_when_the_role_leaves_operator(): void
    {
        $this->operatorA->forceFill(['role' => 'admin'])->save();

        $fresh = $this->operatorA->fresh();
        $this->assertSame('admin', $fresh->role);
        $this->assertNull($fresh->dinas_unit_id);
    }

    public function test_demoting_an_operator_through_http_clears_the_unit(): void
    {
        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.users.update', $this->operatorA), [
                'name' => $this->operatorA->name,
                'email' => $this->operatorA->email,
                'role' => 'admin',
                'is_active' => '1',
                'dinas_unit_id' => $this->unitA->id, // ignored: role != operator
            ])
            ->assertRedirect(route('super-admin.users.index'));

        $fresh = $this->operatorA->fresh();
        $this->assertSame('admin', $fresh->role);
        $this->assertNull($fresh->dinas_unit_id);
        // The unit is now free for a real Operator.
        $this->assertNull($this->unitA->fresh()->operator);
    }

    // =========================================================================
    // 6. HISTORICAL PRESERVATION — complaints are NEVER rewritten
    // =========================================================================

    public function test_reassignment_does_not_alter_historical_complaints(): void
    {
        $category = ComplaintCategory::create([
            'name' => 'Kategori P1', 'slug' => 'kategori-p1', 'is_active' => true, 'sort_order' => 1,
        ]);

        $complaint = Complaint::create([
            'reference_code' => 'LPW-P1-0001',
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $category->id,
            'dinas_unit_id'  => $this->unitA->id,
            'assigned_to'    => $this->operatorA->id,
            'title'          => 'Laporan Historis P1',
            'description'    => 'Laporan yang harus tetap utuh setelah reassignment.',
            'status'         => 'submitted',
            'submitted_at'   => now(),
        ]);

        // Move operatorA to a different unit.
        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.users.update', $this->operatorA), [
                'name' => $this->operatorA->name,
                'email' => $this->operatorA->email,
                'role' => 'operator',
                'is_active' => '1',
                'dinas_unit_id' => $this->freeUnit->id,
            ])
            ->assertRedirect();

        // The complaint's stored destination AND assignment are untouched.
        $fresh = $complaint->fresh();
        $this->assertSame($this->unitA->id, $fresh->dinas_unit_id);
        $this->assertSame($this->operatorA->id, $fresh->assigned_to);
    }

    public function test_assigned_to_is_independent_of_unit_ownership(): void
    {
        $category = ComplaintCategory::create([
            'name' => 'Kategori P1b', 'slug' => 'kategori-p1b', 'is_active' => true, 'sort_order' => 2,
        ]);

        // operatorB (unit B) is assigned a complaint whose destination is unit A.
        $complaint = Complaint::create([
            'reference_code' => 'LPW-P1-0002',
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $category->id,
            'dinas_unit_id'  => $this->unitA->id,
            'assigned_to'    => $this->operatorB->id,
            'title'          => 'Laporan Silang P1',
            'description'    => 'assigned_to berbeda dari dinas_unit_id.',
            'status'         => 'submitted',
            'submitted_at'   => now(),
        ]);

        $this->assertSame($this->operatorB->id, $complaint->fresh()->assigned_to);
        $this->assertSame($this->unitA->id, $complaint->fresh()->dinas_unit_id);
    }

    // =========================================================================
    // 7. INACTIVE DINAS — must NOT auto-clear the relationship or the Operator
    // =========================================================================

    public function test_deactivating_a_dinas_keeps_the_relationship_and_the_operator(): void
    {
        $this->unitA->update(['is_active' => false]);

        $fresh = $this->operatorA->fresh();
        $this->assertSame('operator', $fresh->role);
        $this->assertSame($this->unitA->id, $fresh->dinas_unit_id);
        $this->assertSame($this->operatorA->id, $this->unitA->fresh()->operator->id);
    }

    // =========================================================================
    // 8. AUTHORIZATION — only Super Admin may manage the relationship
    // =========================================================================

    public function test_non_super_admin_cannot_assign_a_unit(): void
    {
        foreach ([$this->admin, $this->citizen, $this->operatorA] as $actor) {
            $this->actingAs($actor)
                ->post(route('super-admin.users.store'), [
                    'name' => 'Operator Ilegal',
                    'email' => 'operator.ilegal.' . $actor->id . '@superbie.local',
                    'password' => 'RahasiaKuat123!',
                    'password_confirmation' => 'RahasiaKuat123!',
                    'role' => 'operator',
                    'is_active' => '1',
                    'dinas_unit_id' => $this->freeUnit->id,
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('users', ['role' => 'operator', 'dinas_unit_id' => $this->freeUnit->id]);
    }

    public function test_operator_cannot_reassign_itself_to_a_free_unit(): void
    {
        $this->actingAs($this->operatorA)
            ->put(route('super-admin.users.update', $this->operatorA), [
                'name' => $this->operatorA->name,
                'email' => $this->operatorA->email,
                'role' => 'operator',
                'is_active' => '1',
                'dinas_unit_id' => $this->freeUnit->id,
            ])
            ->assertForbidden();

        $this->assertSame($this->unitA->id, $this->operatorA->fresh()->dinas_unit_id);
        $this->assertNull($this->freeUnit->fresh()->operator);
    }

    // =========================================================================
    // 9. AUDIT — every ownership mutation is attributable
    // =========================================================================

    public function test_creation_of_an_operator_emits_the_assignment_event(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.users.store'), [
                'name' => 'Operator Audit',
                'email' => 'operator.audit@superbie.local',
                'password' => 'RahasiaKuat123!',
                'password_confirmation' => 'RahasiaKuat123!',
                'role' => 'operator',
                'is_active' => '1',
                'dinas_unit_id' => $this->freeUnit->id,
            ])
            ->assertRedirect();

        $user = User::where('email', 'operator.audit@superbie.local')->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'operator.dinas_assigned',
            'subject_type' => 'user',
            'subject_id' => $user->id,
            'actor_id' => $this->superAdmin->id,
        ]);
    }

    public function test_reassignment_emits_the_reassigned_event(): void
    {
        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.users.update', $this->operatorA), [
                'name' => $this->operatorA->name,
                'email' => $this->operatorA->email,
                'role' => 'operator',
                'is_active' => '1',
                'dinas_unit_id' => $this->freeUnit->id,
            ])
            ->assertRedirect();

        $log = AuditLog::where('action', 'operator.dinas_reassigned')
            ->where('subject_type', 'user')
            ->where('subject_id', $this->operatorA->id)
            ->firstOrFail();

        $this->assertSame($this->superAdmin->id, $log->actor_id);
        $this->assertSame($this->unitA->id, (int) $log->metadata['old_dinas_unit_id']);
        $this->assertSame($this->freeUnit->id, (int) $log->metadata['new_dinas_unit_id']);
    }

    public function test_releasing_a_unit_emits_the_unassigned_event(): void
    {
        // Demote the operator → the unit is released.
        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.users.update', $this->operatorA), [
                'name' => $this->operatorA->name,
                'email' => $this->operatorA->email,
                'role' => 'admin',
                'is_active' => '1',
                'dinas_unit_id' => '',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'operator.dinas_unassigned',
            'subject_type' => 'user',
            'subject_id' => $this->operatorA->id,
            'actor_id' => $this->superAdmin->id,
        ]);
    }

    public function test_audit_metadata_never_records_credentials(): void
    {
        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.users.update', $this->operatorA), [
                'name' => $this->operatorA->name,
                'email' => $this->operatorA->email,
                'role' => 'operator',
                'is_active' => '1',
                'dinas_unit_id' => $this->freeUnit->id,
            ])
            ->assertRedirect();

        foreach (AuditLog::all() as $log) {
            $payload = json_encode($log->metadata);
            $this->assertStringNotContainsString('RahasiaKuat123!', $payload);
            $this->assertArrayNotHasKey('password', $log->metadata ?? []);
        }
    }
}
