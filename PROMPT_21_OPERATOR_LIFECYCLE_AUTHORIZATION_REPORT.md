# Prompt 21 Completion Report

## SuperBie — Lapor Pak Wali

**Scope:** Operator authorization & Dinas/Unit lifecycle enforcement (D-2 / F-18-09).

**Frozen decision enforced:** D-2 —
> One Operator represents **exactly one** Dinas/Unit; the **Super Admin** assigns/changes that Dinas/Unit through the existing Super Admin User Management surface.

**Principle applied:** *Enforce D-2 exactly as decided — no more, no less.*

---

## 1. Status

```
PASS
```

D-2 is enforced server-side, every mutation path was audited, the Prompt 15 complaint-scoping algorithm is untouched, the Prompt 16 last-Super-Admin invariant is intact, and no business rule was invented. No open decision gate was required by this prompt (see §8 for the D-2 *scope guard* — items that are explicitly **not** implied by D-2).

---

## 2. D-2 Enforcement Summary

| Rule | Result | Evidence |
|---|---|---|
| One Operator → one Dinas/Unit | **PASS** | Single column `users.dinas_unit_id`; model guard keeps at most one; `OperatorLifecycleAuthorizationTest::test_model_keeps_exactly_one_unit_for_an_operator` |
| Assignment controlled by Super Admin | **PASS** | Only `super-admin.users.*` (middleware `role:super_admin` + Form Request `authorize()`) accepts `dinas_unit_id`; all other roles → 403 |
| Operator self-assignment blocked | **PASS** | `test_operator_cannot_self_assign_via_any_available_endpoint` (no operator-accessible mutation path; profile route is masyarakat-only) |
| Operator cross-user assignment blocked | **PASS** | `test_operator_cannot_assign_any_unit_through_super_admin_surface` (Operator A cannot modify Operator B → 403) |
| Existing complaint scoping preserved | **PASS** | `test_active_operator_without_unit_is_denied_the_complaint_surface` + full Prompt 15 suite still green |

---

## 3. Mutation Inventory (actual repository routes)

Every path that can create/change a user's role or Dinas/Unit was located by searching `dinas_unit_id`, `role`, and `operator` across the whole `app/` tree.

| Operation | Endpoint | Actor | Authorization | Result |
|---|---|---|---|---|
| Create user (role + unit) | `POST /super-admin/users` (`super-admin.users.store`) | Super Admin | middleware `role:super_admin` + `StoreUserRequest::authorize()` + model guard | unit **required iff** `role=operator`; otherwise forced NULL |
| Change role / unit | `PUT /super-admin/users/{user}` (`super-admin.users.update`) | Super Admin | middleware `role:super_admin` + `UpdateUserRequest::authorize()` + model guard | same; assignment change audited |
| Activate / deactivate | `PATCH /super-admin/users/{user}/active` (`super-admin.users.toggle`) | Super Admin | middleware `role:super_admin` | **does not touch** `dinas_unit_id` (preserved) |
| Role → Operator | via `users.store` / `users.update` | Super Admin | `Rule::requiredIf(role=operator)` on `dinas_unit_id` | valid unit mandatory |
| Operator → other role | via `users.update` | Super Admin | controller sets unit NULL + model guard | unit cleared (no stale assignment) |
| Dinas/Unit master CRUD | `/super-admin/dinas*` | Super Admin | `role:super_admin` + `SaveDinasUnitRequest` | master data only; never touches `users` |
| Category ↔ Unit mapping | `PUT /super-admin/kategori/{category}/mapping` | Super Admin | `role:super_admin` + `SyncCategoryMappingRequest` | pivot only; never touches `users` |
| Complaint assignment (`assigned_to`) | `PATCH /operator/laporan/{complaint}/tugaskan` | Operator / Super Admin | `role:operator,super_admin` + `authorizeComplaintAccess` | **different concept** — does NOT set a user's `dinas_unit_id` |
| Complaint destination (`complaints.dinas_unit_id`) | `PATCH /operator/laporan/{complaint}/tujuan` | Operator / Super Admin | `role:operator,super_admin` + `authorizeComplaintAccess` | **different concept** — complaint routing, not a user's unit |
| Citizen self-profile | `PATCH /laporan/profil` | Masyarakat | `role:masyarakat` | validates `name/phone/address/password` only; `role`/`dinas_unit_id` **ignored** |

**No bypass path exists.** The only endpoints that write a *user's* `dinas_unit_id` are the two Super-Admin User-Management routes. Complaint endpoints write a *complaint's* `dinas_unit_id` (a distinct column, Prompt 5C) and `assigned_to` — they never modify an operator's account scope.

---

## 4. What was implemented

### 4.1 Model-layer invariant (the only source change)

`app/Models/User.php` — a `saving` guard added to `booted()` (mirroring the existing NIK-immutability guard and the `DinasUnit` "enforced at the model layer" precedent):

```php
static::saving(function (User $user) {
    // D-2 / Prompt 15 invariant (schema.md §4.1):
    //   "Only meaningful for `operator`; forced NULL for every other role."
    //   One Operator → exactly one Dinas/Unit (`users.dinas_unit_id`).
    if ($user->role !== 'operator') {
        $user->dinas_unit_id = null;
    }
});
```

**Why a model guard rather than only request validation:**

- §21 requires that *no bypass path* exists. The "non-Operator ⇒ NULL" rule was previously enforced only at the two Form Requests (duplicated in `store` + `update`). A model-layer guard makes the invariant hold for **every** persistence path — hand-crafted payloads, a seeder, and any future endpoint — not just the dashboard (§29 "no UI-only security").
- §22 (single source of truth) — the rule now lives in one authoritative place rather than being copied per controller.
- It does **not** invent a rule: it only restates the already-frozen invariant from `schema.md` §4.1 and Prompt 15.
- It does **not** force a unit onto an Operator: an Operator without a unit remains a valid, denied-scope state (BDR-3, §14).

The existing Form Requests were **left unchanged** — they are correct and remain the first line of validation. The guard is defence-in-depth, not a replacement.

### 4.2 Explicitly NOT implemented (D-2 does not imply these — §3, §34)

| Not implemented | Why |
|---|---|
| Restricting an Operator to specific categories | Not implied by D-2 → would be a new business rule |
| Restricting the complaint `assign` target to the Operator's own unit | Prompt 15 §N-1 deliberately left this; still no approved rule |
| "One Dinas/Unit = one Operator" | Not stated → `DECISION REQUIRED` if ever needed |
| Forcing a unit **before** an Operator may be active | Not stated; an active operator-without-unit is valid-but-denied (§14) |
| Clearing the unit on **deactivation** | Not stated; assignment preserved for audit/reactivation (§15) |
| Restricting Dinas/Unit to `is_active` when assigning | Not a business rule for user assignment (status is a master-data concept) |
| New locking for concurrent reassignment | Single column → last-write-wins is inherently valid (§25); no invalid state possible |

---

## 5. Security Test Results

```
Before:  528 tests / 2313 assertions
After:   546 tests / 2384 assertions

Failed:  0
Skipped: 0
```

**Delta: +18 tests / +71 assertions** — one new file, `tests/Feature/OperatorLifecycleAuthorizationTest.php` (no existing test was modified, weakened, or deleted).

Coverage of the §27 matrix:

| # | Requirement | Test |
|---|---|---|
| 1 | Super Admin assigns Operator → Unit | `test_super_admin_can_assign_unit_when_creating_an_operator` |
| 2 | Super Admin changes an Operator's unit | `test_super_admin_can_change_an_operators_unit` |
| 3 | Operator cannot self-assign | `test_operator_cannot_self_assign_via_any_available_endpoint` |
| 4 | Operator cannot assign another Operator | `test_operator_cannot_assign_any_unit_through_super_admin_surface` |
| 5 | Admin cannot use the Super-Admin assignment surface | `test_admin_cannot_use_the_super_admin_assignment_surface` |
| 6 | Masyarakat cannot use the endpoint | `test_masyarakat_cannot_use_the_assignment_surface` |
| 7 | Arbitrary/nonexistent unit rejected | `test_assigning_a_nonexistent_unit_is_rejected` |
| 8 | non-Operator → Operator requires valid unit | `test_promoting_a_non_operator_to_operator_requires_a_valid_unit` |
| 9 | Operator → non-Operator follows invariant | `test_demoting_an_operator_clears_the_unit` |
| 10 | Active Operator without unit → no complaint surface | `test_active_operator_without_unit_is_denied_the_complaint_surface` |
| 11 | Operator A cannot modify Operator B (IDOR) | `test_operator_cannot_assign_any_unit_through_super_admin_surface` |
| 12 | Operator A cannot change its own unit | `test_operator_cannot_self_assign_via_any_available_endpoint` |
| 13 | Unauthorized role mutation rejected | (5) + `test_last_active_super_admin_cannot_be_demoted_even_with_unit_fields` |
| — | Model invariant / no bypass path | `test_model_forces_null_unit_for_every_non_operator_role`, `test_model_keeps_exactly_one_unit_for_an_operator`, `test_model_does_not_force_a_unit_onto_an_operator`, `test_model_clears_unit_when_role_changes_away_from_operator` |
| — | Deactivation preserves assignment (§15) | `test_deactivation_preserves_the_unit_assignment` |
| — | Assignment audited with actor/target (§23) | `test_assignment_change_is_audited_with_actor_and_target` |
| — | Password integrity unchanged | `test_reassignment_does_not_change_the_password` |

---

## 6. Regression Results

| Prompt | Area | Result |
|---|---|---|
| Prompt 15 | Operator Dinas/Unit scoping (hybrid visibility, null-unit denial, cross-unit 404, Super Admin global) | **PASS** |
| Prompt 16 | Last-active Super-Admin invariant (transaction + `FOR UPDATE`) | **PASS** |
| Prompt 17 | Complaint submission 5 / 10 min per user, 429, daily limit 5/day | **PASS** |
| Prompt 19 | `auth.session` password-change invalidation; `Asia/Makassar` timezone; secure cookies; `.env.example` | **PASS** |
| Prompt 20 | Login HTTP 429 with native `Retry-After`; policy unchanged (D-5) | **PASS** |

Full suite: `php artisan test` → **546 passed / 0 failed / 0 skipped**. No Prompt 15–20 security test was altered. The model guard did not regress any existing test — confirming it restates (does not change) current behaviour.

Additional invariants re-checked:

- Complaint status enum / transitions untouched;
- daily limit 5/day and submission rate limit 5/10 min untouched;
- role names untouched; D-1 timezone untouched;
- `admin` retains **monitoring-only** (no assignment authority added or removed);
- Super Admin remains global for complaints (unchanged).

---

## 7. Build / Routes / Database

### Build
```
npm run build: PASS   (exit code 0)
```
Only the **pre-existing, non-blocking** `fontaine` font-fallback warning is emitted (also present in Prompts 19–20). No asset/config change was made by this prompt.

### Routes
```
Before: 61
After:  61
```
No route was added, removed, or renamed. D-2 needed **no** new endpoint — the existing Super Admin User Management surface already is the authoritative assignment flow (§9). No email-verification route was added.

### Database safety
```
Migration created:   0
Migration executed:  0
Schema changed:      none
Data changed:        none
Migration status:    2025_01_01_100008_add_dinas_unit_id_to_users_table ... Pending (unchanged)
```
No `migrate`, `migrate:fresh`, `migrate:refresh`, `db:wipe`, `db:seed`, `DROP`, `TRUNCATE`, `DELETE`, or `ALTER` was run. No duplicate migration was created and no existing migration was modified (§31). The `users.dinas_unit_id` column already exists (authored in Prompt 15); Prompt 21 only adds a model-layer rule over it.

---

## 8. Decision Gates (D-2 scope guard — §34)

No new business rule was required to enforce D-2. The following are **explicitly not implied** by D-2 and were therefore **not** implemented. They remain `DECISION REQUIRED` **only if** a future requirement asks for them:

| Question | Status |
|---|---|
| May one Dinas/Unit have many Operators? | **Not restricted** — D-2 says nothing; currently many operators may share a unit (valid) |
| May an Operator hold multiple units? | **Impossible by schema** (single `users.dinas_unit_id`) — consistent with D-2 |
| Must an Operator be assigned a unit before activation? | **Not enforced** — active-without-unit is valid-but-denied (§14) |
| Does deactivation clear the unit? | **No** — assignment preserved (§15) |
| May `admin` perform assignment? | **No** — D-2 names Super Admin; the existing Admin surface has zero management routes, so nothing was removed (§11) |
| Restrict `assign`/`updateDestination`/`updateCategory` targets (F-18-09 residual)? | **Still `DECISION REQUIRED`** — out of D-2 scope; deliberately unchanged (§3, §20) |

---

## 9. Audit Log

Assignment changes are already recorded by the existing Prompt 9 audit architecture (no new framework created, §23):

- `user.created` — includes `dinas_unit_id`;
- `user.dinas_unit_changed` — records `old_dinas_unit_id` / `new_dinas_unit_id`;
- `user.role_changed` — records `old_role` / `new_role`.

Verified by `test_assignment_change_is_audited_with_actor_and_target` (actor = Super Admin, target = the user, previous/new unit, timestamp). The audit surface remains **read-only**.

---

## 10. Files Changed

| File | Change |
|---|---|
| `app/Models/User.php` | Added a `saving` guard in `booted()`: non-`operator` roles always persist `dinas_unit_id = NULL` (D-2 / Prompt 15 invariant, model-layer, no bypass path). |
| `tests/Feature/OperatorLifecycleAuthorizationTest.php` | **New** — 18 tests covering the full §27 matrix + model invariant + audit + deactivation preservation. |
| `rules.md` | Added the FINAL D-2 Operator ↔ Dinas/Unit assignment rule. |
| `schema.md` | Clarified `users.dinas_unit_id` (model-layer enforcement, Super-Admin-only assignment, deactivation preservation) and the relationship note. |
| `architecture.md` | Role-model section documents the D-2 assignment rule and its enforcement layers. |
| `prd.md` | Role table / feature list / F-002B document the D-2 assignment rule. |
| `PROMPT_21_OPERATOR_LIFECYCLE_AUTHORIZATION_REPORT.md` | **New** — this report. |

---

## 11. Success Criteria (§42)

- [x] D-2 enforced server-side (middleware + Form Request + model guard);
- [x] one Operator has one Dinas/Unit;
- [x] Super Admin is the authoritative assignment actor;
- [x] Operator cannot self-assign;
- [x] Operator cannot modify another Operator's assignment;
- [x] unauthorized actor (admin/masyarakat/guest) rejected;
- [x] valid unit required when an account becomes an Operator (existing invariant);
- [x] non-Operator never keeps a stale Operator assignment (model-layer);
- [x] Prompt 15 complaint scoping intact;
- [x] Prompt 16 last-Super-Admin invariant intact;
- [x] assignment audit trail available via the existing audit architecture;
- [x] all mutation paths audited;
- [x] no bypass endpoint;
- [x] no new migration;
- [x] no destructive DB operation;
- [x] no new package;
- [x] test suite PASS (546 / 0 failed / 0 skipped);
- [x] build PASS;
- [x] documentation consistent;
- [x] no additional business rule invented.

---

## 12. Final Verdict

```
NOT PRODUCTION READY
```

Prompt 21 closes D-2 / F-18-09: the Operator ↔ Dinas/Unit lifecycle is now enforced server-side with a single authoritative assignment actor (Super Admin) and a model-layer invariant that leaves no bypass path. Production readiness is still **not** reached, because:

- **Prompt 23** — CI/CD, backup, monitoring, scheduler, RPO/RTO/alerting are outstanding;
- **Open decision gates** — registration abuse policy, forgot-password abuse policy, reset-endpoint enumeration (F-20-01) from Prompt 20; F-18-09 residual complaint-mutation targets;
- **Earlier open items** — dependency CVE scan, deferred trusted-proxy (D-6), D-3 dependency-removal decision, and lower-priority findings (F-18-30, F-18-37, F-18-39);
- **Operational** — rotate the local-dev DB credential exposed in git history (Prompt 19).

> Super Admin owns Operator-to-Dinas assignment; the Operator does not own or self-modify that assignment — enforced exactly as decided, no more, no less.
