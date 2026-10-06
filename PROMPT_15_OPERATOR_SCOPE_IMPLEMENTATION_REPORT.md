# Prompt 15 — Implement Operator Dinas/Unit Scoping + IDOR Hardening

**Type:** Implementation (approved business decisions) + regression coverage
**Scope:** Operator complaint scoping by Dinas/Unit (§1–§30) + per-complaint IDOR hardening
**Repository state:** post-Prompt-14
**Status:** **COMPLETE — awaiting review (no production-readiness claim)**
**Code changes made:** YES (source + tests; see §B/§C)
**Verification (re-run this session):**

| Check | Command | Result |
|---|---|---|
| Full test suite | `php artisan test` | **PASS** — 469 tests, 469 passed, 1536 assertions, 0 failed, 0 skipped (≈24.8s) |
| Route list | `php artisan route:list` | **PASS** — 61 routes (unchanged; no route added/removed) |
| Migrations | `php artisan migrate:status` | 12 migration files; `…100008` **Pending** on the dev DB (intentionally not applied to real data — see §D) |
| Build | `npm run build` | **PASS** (pre-existing non-blocking `fontaine` optional-package warning only) |

Baseline before this prompt (Prompt 13/14): 438 tests / 1453 assertions / 61 routes / 11 Ran migrations.

---

## 0. Verdict (read first)

The four Prompt-14 blockers (BDR-1…BDR-4) were resolved by the approved contract:

- **BDR-1 = 1a** — each Operator belongs to **exactly one** Dinas/Unit via `users.dinas_unit_id`.
- **BDR-2 = 2c** — **hybrid** scope (destination unit OR unrouted-and-category-mapped unit).
- **BDR-3** — an Operator **without a unit is DENIED** everything (no global fallback).
- **BDR-4** — **Livewire is NOT used** (the repository is a Blade + Alpine.js monolith).
- **Super Admin = GLOBAL + STATUS MUTATION** (unchanged; already working, no second path added).

The scope rule, the new column, the User-Management binding, the dashboard scoping, and the per-complaint IDOR guard are implemented with **minimal changes**, **no new Policy/Gate**, **no new role/permission**, and **no change to statuses/transitions/audit/history** (except where scope protection requires the guard to run first).

**No hard-stop condition (§33) was triggered.**

---

## A. DISCOVERY (what already existed; what the change had to respect)

| Concept | Location | Semantics |
|---|---|---|
| Candidate Dinas/Unit set | `category_dinas_unit` pivot | Which units *may* handle a category (many-to-many) |
| ACTUAL destination | `complaints.dinas_unit_id` (nullable, `nullOnDelete`) | Stored unit chosen by an Operator; NULL until routed |
| Operator surface | `routes/web.php` L62 `role:operator,super_admin` | Previously **global** — no per-complaint check existed |
| Authorization mechanism | middleware `role:*` + Form Request `authorize()` + inline checks | **No Policy classes, no Gate** |
| Livewire | not installed | §25 is a no-op guard |
| Stale route names | `resources/views/super-admin/dashboard.blade.php` L26/121 | `super-admin.user.create/index` (singular) — fixed to plural |

The decisive gap (recorded in Prompt 13 §459 / Prompt 14 §A.2) was that **no `users`↔unit binding existed**. Prompt 15 authorizes the minimal schema addition to close it.

---

## B. IMPLEMENTATION (source changes)

### B.1 Migration — NEW (never modified an old one)

`database/migrations/2025_01_01_100008_add_dinas_unit_id_to_users_table.php`

- `foreignId('dinas_unit_id')->nullable()->after('role')->constrained('dinas_units')->nullOnDelete()`
- `down()` uses `dropConstrainedForeignId`.
- **Non-destructive:** nullable, `ON DELETE SET NULL` → removing a master Dinas/Unit **never** deletes a user account. Existing operators keep **NULL** (denied) — **no blanket backfill**.

### B.2 Models

| File | Change |
|---|---|
| `app/Models/User.php` | `dinas_unit_id` in `$fillable`; `dinasUnit(): BelongsTo` |
| `app/Models/DinasUnit.php` | `users(): HasMany` |
| `app/Models/Complaint.php` | `scopeVisibleToOperator(User)` + per-instance `isVisibleToOperator(User)` (reuses the scope → single source of truth for the §4 rule) |

The scope (§4, hybrid) is:

```
operator.dinas_unit_id IS NOT NULL
AND ( complaint.dinas_unit_id = operator.dinas_unit_id
      OR ( complaint.dinas_unit_id IS NULL
           AND complaint.category is mapped to operator.dinas_unit_id via category_dinas_unit ) )
```

A null-unit operator gets `whereRaw('1 = 0')` (deny, no fallback).

### B.3 Controller — `app/Http/Controllers/Staff/OperatorComplaintController.php`

- `index`: applies `visibleToOperator` **for operators only**; Super Admin stays global.
- New private `authorizeComplaintAccess(?User, Complaint)`: `null` → 403; **Super Admin bypasses**; otherwise `isVisibleToOperator` false → **404** (not 403, so out-of-scope endpoints don't leak existence).
- Guard added to **every** per-complaint method: `show`, `updateCategory`, `updateDestination`, `assign`, `updateStatus`, `addNote`, `downloadAttachment`.
- `downloadAttachment` signature extended to `(Request, Complaint, ComplaintAttachment)`; the scope guard runs **before** the existing attachment↔complaint IDOR check.

### B.4 Dashboard scoping — `app/Services/DashboardCacheService.php`

- `getOperatorData(User, bool $refresh)`: scoped for operators, global for Super Admin; null-unit operator → honest empty dashboard.
- Cache invalidation: cache store is `database` (no tags), so a **version counter** (`dashboard:operator:version`) is embedded in every operator cache key (`dashboard:operator:{global|unit:{id|none}}:{version}`); one write invalidates all scoped operator caches.
- `clearGlobalDashboardCaches()`/`clearAll()` bump the version. `OperatorDashboardController` passes the user through.
- `app/Observers/UserObserver.php`: `saved`/`deleted` also bump the operator version (a unit/role change alters what the operator dashboard shows).

### B.5 User Management (extends Prompt 9 surface — no new business rule)

| File | Change |
|---|---|
| `StoreUserRequest` / `UpdateUserRequest` | `dinas_unit_id`: `nullable, integer, exists:dinas_units,id` + `Rule::requiredIf(role === 'operator')`; messages; normalization in `prepareForValidation` |
| `SuperAdminUserController` | `create`/`edit` pass `$dinasUnits`; `store`/`update` persist the unit **only for operators** (forced NULL otherwise); audit metadata includes the unit + a new `user.dinas_unit_changed` audit on change |
| `resources/views/super-admin/users/_form.blade.php` | Alpine `x-data` role state + conditional unit selector (`x-show="role === 'operator'" x-cloak`) |
| `resources/css/app.css` | added `[x-cloak] { display: none !important; }` |
| `resources/views/super-admin/dashboard.blade.php` | stale singular route names fixed to `super-admin.users.*` |
| `database/seeders/DatabaseSeeder.php` | seeded operator assigned the PUPR unit (deterministic by `code`), only when currently unassigned |

**Note (no new rule invented):** the **assignment-target list** in `assign()` remains "any active operator" (unchanged). Restricting it to the complaint's unit would be a *new* business rule and was deliberately **not** introduced.

---

## C. TEST RESULTS

### C.1 New regression suite — `tests/Feature/OperatorDinasScopeTest.php` (31 tests / 81 assertions)

Covers §27–§29:

- **Index scoping:** own-unit visible; other-unit hidden; unrouted+category-mapped visible; unrouted mapped to another unit hidden; unmapped-category hidden; null-unit operator sees nothing; Super Admin global.
- **Show scoping:** same-unit OK; unrouted+mapped OK; other-unit **404**; unrouted-other-unit **404**; null-unit **404**; Super Admin OK.
- **IDOR (404) on every per-complaint endpoint** for an out-of-scope operator: `updateCategory`, `updateDestination`, `assign`, `updateStatus`, `addNote`, `downloadAttachment` — each also asserts **no state change** occurred.
- **In-scope controls:** same-unit status update and attachment download succeed; Super Admin can mutate any complaint.
- **Model rule:** `scopeVisibleToOperator` and `isVisibleToOperator` agree; null-unit scope returns 0.
- **Dashboard:** operator totals scoped (2 vs 1 vs global 3); null-unit empty; cache key scoped + version-bumped.
- **User Management:** operator creation **requires** a unit (422); operator with unit OK; non-operator unit forced NULL; promotion requires unit; unit change audited (`user.dinas_unit_changed`); demotion clears the unit.

### C.2 Adapted existing fixtures (assertions **not** weakened)

Operator fixtures in `OperatorComplaintWorkflowTest`, `ComplaintStatusLifecycleTest`, `CategoryMappingAndRoutingTest`, `DinasUnitRoutingTest`, `SuperAdminCategoryManagementTest`, `SuperAdminUserManagementTest`, `DashboardCachingTest`, `DashboardAuthorizationTest` now (a) bind operators to a unit and (b) map the relevant category to that unit, so the existing workflow assertions remain meaningful under the new scope. `CategoryMappingAndRoutingTest`'s two count-based assertions were made **mapping-precise** (assert the specific pair / `contains`) instead of raw counts, because `setUp` now seeds one baseline mapping.

### C.3 Full suite

```
php artisan test  →  PASS  469 tests, 1536 assertions, 0 failed, 0 skipped
```

No test was deleted, skipped, or weakened. No DB reset/seeded/altered beyond the test database (`superbie_testing`).

---

## D. MIGRATION / ENVIRONMENT NOTES

- `php artisan migrate:status` shows `2025_01_01_100008_add_dinas_unit_id_to_users_table` as **Pending** on the **development** database. This is **intentional**: per the frozen "no destructive DB ops on real data" rule, the new migration is authored but **not applied to the dev/real database** in this session. The **test** database (`superbie_testing`) applies all 12 migrations during `RefreshDatabase` — proven by the 469 passing tests (which exercise `users.dinas_unit_id`).
- The migration is **additive and nullable** → safe to run via `php artisan migrate` when the operator authorizes it. Rolling back only drops the column (`dropConstrainedForeignId`), leaving users intact.

---

## E. SECURITY VERIFICATION

| Guarantee | Result | Evidence |
|---|---|---|
| Operator A cannot see/act on Complaint B (other unit) | **VERIFIED** | `OperatorDinasScopeTest` (index hide + `show`/mutations/download → 404) |
| Operator with no unit is denied everything | **VERIFIED** | `test_operator_without_unit_*`, `test_scope_returns_nothing_for_operator_without_unit` |
| Hybrid visibility (unrouted + category-mapped) works | **VERIFIED** | `test_operator_*unrouted*`, model-rule test |
| Super Admin is global + may mutate status | **VERIFIED** | `test_super_admin_index_is_global`, `test_super_admin_can_show_any_unit_complaint`, `test_super_admin_can_mutate_any_complaint_status` |
| Out-of-scope access does not leak existence | **VERIFIED** | Returns **404**, not 403 |
| No second mutation path created for Super Admin | **VERIFIED** | Same `OperatorComplaintController` used; role middleware unchanged |
| No Policy/Gate/permission/role added | **VERIFIED** | Only inline guard + existing middleware/Form Request |
| Statuses/transitions/audit/history untouched | **VERIFIED** | `ComplaintStatus` enum unchanged; guard runs before, but does not alter, the existing write logic |
| Non-operator accounts never carry a unit | **VERIFIED** | `test_non_operator_user_never_keeps_a_unit`, demotion test |

---

## F. UNRESOLVED ITEMS / NOTES

**No hard-stop (§33) triggered.** Recorded, non-blocking:

- **N-1 (scope decision, documented):** `assign()` still targets **any active operator**. Restricting the assignment target to the complaint's unit would be a **new business rule** → intentionally **not** implemented; flagged for a future decision.
- **N-2 (cache design, documented):** operator cache invalidation uses a **version counter** because the `database` cache store has no tag support. Coarse (invalidates all operator caches on any user/complaint write) but correct and cheap. A tag-capable store (e.g. Redis) would allow finer invalidation — a future optimization, not a correctness gap.
- **N-3 (doc):** `schema.md` §4.1 (new column) and §7 (visibility rule) were updated to match the implementation.
- **N-4 (pre-existing, unrelated):** `ComplaintCategoryFactory` can occasionally generate duplicate slugs (faker), which can intermittently fail `SuperAdminComplaintManagementTest::test_pagination_is_server_side`. Not introduced by this prompt; a candidate minimal-determinism fix is available if it recurs.
- **N-5 (build):** `npm run build` emits a pre-existing non-blocking `fontaine` optional-package warning; the build succeeds.

---

## G. FILES CHANGED (this prompt)

**New**
- `database/migrations/2025_01_01_100008_add_dinas_unit_id_to_users_table.php`
- `tests/Feature/OperatorDinasScopeTest.php`

**Modified (source)**
- `app/Models/User.php`, `app/Models/DinasUnit.php`, `app/Models/Complaint.php`
- `app/Http/Controllers/Staff/OperatorComplaintController.php`
- `app/Http/Controllers/Staff/OperatorDashboardController.php`
- `app/Http/Controllers/Staff/SuperAdminUserController.php`
- `app/Services/DashboardCacheService.php`
- `app/Observers/UserObserver.php`
- `app/Http/Requests/SuperAdmin/StoreUserRequest.php`, `UpdateUserRequest.php`
- `resources/views/super-admin/users/_form.blade.php`
- `resources/views/super-admin/dashboard.blade.php`
- `resources/css/app.css`
- `database/seeders/DatabaseSeeder.php`
- `schema.md`

**Modified (tests — fixtures adapted, assertions preserved)**
- `OperatorComplaintWorkflowTest`, `ComplaintStatusLifecycleTest`, `CategoryMappingAndRoutingTest`, `DinasUnitRoutingTest`, `SuperAdminCategoryManagementTest`, `SuperAdminUserManagementTest`, `DashboardCachingTest`, `DashboardAuthorizationTest`

---

## FINAL RULE — compliance

- **No invented** Dinas/Unit list, mapping, operator→unit backfill, SLA, retention, permission, role, or business rule: **compliant** (unknown rules remain `TODO: Define requirement`).
- **Frozen artifacts untouched:** `app/Enums/ComplaintStatus.php` (statuses/transitions), audit/history semantics, the 4-role set (`petugas` **not** reintroduced).
- **No new Policy/Gate, no new permission/role, no new package/architecture.**
- **No old migration modified; one NEW additive migration.**
- **No test weakened/deleted/skipped; no destructive DB op on real data.**
- **This report does not claim "production ready"** — tests passing is a necessary, not sufficient, condition; open items are listed in §F.

**STOP — awaiting review before any further work.**
