# Prompt 14 — Operator Scope Dinas/Unit & Super Admin Complaint Mutation

**Type:** Discovery + implementation attempt
**Scope:** Operator complaint scoping by Dinas/Unit; Super Admin complaint status mutation
**Repository state:** post-Prompt-13 (Prompts 6–13 complete)
**Status:** **HARD STOP — BLOCKED ON BUSINESS DECISION**
**Code changes made:** **NONE** (discovery-only; see §B)
**Baseline (re-verified):** 438 tests / 1453 assertions / 0 failed / 0 skipped; 61 routes; 11 Ran / 0 Pending migrations

---

## 0. Verdict (read first)

Two business decisions were supplied:

1. **Operator scope = Dinas/Unit** (§1.1 of the prompt) — *cannot be implemented* against the existing repository, because **the Operator↔Dinas/Unit relationship does not exist at any layer** (schema, model, seeder, documentation). Implementing it requires **new business decisions** that are not derivable from the repository (binding cardinality, assignment mechanism, and — critically — *which complaint field defines the scope*, which as stated produces a workflow deadlock). Per the prompt's own §4 / §15 / FINAL RULE, this is a **HARD STOP**.
2. **Super Admin = may mutate complaint status** (§1.2 of the prompt) — **already implemented and already tested** through the existing mechanism. No code change is required or appropriate.

Therefore: **no source file was modified.** Two `BUSINESS DECISION REQUIRED` blocks are emitted in §F. This report is the deliverable.

> This mirrors the established project pattern (Prompt 8 declined to invent an SLA; Prompt 13 stopped and emitted decision blocks). The repository is treated as the source of truth; no entity, field, permission, role, or rule was invented.

---

## A. DISCOVERY

### A.1 Struktur Dinas/Unit existing

| Entity | Table | Model | Notes |
|---|---|---|---|
| Dinas/Unit | `dinas_units` | `App\Models\DinasUnit` | `name`, `code` (nullable, unique), `description`, `is_active`, `sort_order` |
| Category ↔ Dinas/Unit mapping | `category_dinas_unit` (pivot) | `ComplaintCategory::dinasUnits()` / `DinasUnit::categories()` | MANY-TO-MANY, `UNIQUE(category_id, dinas_unit_id)`, FK CASCADE (master-data only) |

Evidence:
- `database/migrations/2025_01_01_100006_create_dinas_units_and_pivot_table.php` L23–44.
- `app/Models/DinasUnit.php` L33–41 (`categories()` BelongsToMany), L52–55 (`complaints()` HasMany).
- `app/Models/ComplaintCategory.php` L41–49 (`dinasUnits()` BelongsToMany).
- `schema.md` §4.2.1 / §4.2.2 (L91–117).

### A.2 Relasi Operator → Dinas/Unit

**DOES NOT EXIST.** This is the decisive gap.

Evidence (exhaustive):
1. **`users` schema has no unit column.** `database/migrations/0001_01_01_000000_create_users_table.php` L14–24 creates: `id`, `name`, `email`, `email_verified_at`, `password`, `role`, `is_active`, `remember_token`, `timestamps`. `database/migrations/2025_01_01_100005_add_identity_fields_to_users_table.php` L25–29 adds only `nik`, `phone_number`, `address`. **No `dinas_unit_id` / `unit_id` / `department_id` / `opd_id`.**
2. **`User` model has no Dinas/Unit relationship.** `app/Models/User.php` L97–110 defines only `complaints()` (reporter_id), `assignedComplaints()` (assigned_to), `auditLogs()` (actor_id). No `belongsTo`/`belongsToMany`/`hasOne` to `DinasUnit`.
3. **No user↔unit pivot table exists.** Searched all migrations for `user_dinas_unit` / `dinas_unit_user` / `user_unit` / `operator_unit` → **0 matches**. The only pivot in the schema is `category_dinas_unit`.
4. **Seeder binds no unit to operators.** `database/seeders/DatabaseSeeder.php` L45–53 creates the single operator (`operator@superbie.local`) with `role='operator'` only — no unit association anywhere in the seeder.
5. **Documentation confirms the gap is an open TODO.** `schema.md` §4.1 (`users`, L54–68) lists no unit column. `schema.md` §13 "TODOs before production" L339: *"Confirm official categories, dinas/unit mapping, and **assignment model**."* L345: *"Confirm whether permissions need finer granularity beyond operator/admin/super_admin."*
6. **Prompt 13 already recorded this.** `PROMPT_13_ARCHITECTURE_SCOPE_GAP_AUDIT_REPORT.md` L459: *"there is also **no `dinas_unit_id` on `users`**, so operators are not bound to a unit at the schema level."*

### A.3 Relasi Complaint → Dinas/Unit

Two distinct, non-interchangeable concepts exist (documented as "mapping ≠ historical destination"):

| Concept | Field/Structure | Semantics | Nullability |
|---|---|---|---|
| **Candidate set** | `category_dinas_unit` pivot | Which Dinas/Units *may* handle a category | N/A |
| **ACTUAL destination** | `complaints.dinas_unit_id` | The Dinas/Unit the Operator chose for *this* complaint; stored for historical stability | **NULLABLE — NULL until routed** |

Evidence:
- `database/migrations/2025_01_01_100007_add_dinas_unit_id_to_complaints_table.php` L30–36 (nullable, `nullOnDelete`, index `(dinas_unit_id, status)`).
- `app/Models/Complaint.php` L19 (`fillable`), L65–68 (`dinasUnit()` BelongsTo).
- `schema.md` §4.3 L128: *"ACTUAL Dinas/Unit destination chosen by Operator … Null only when not yet routed."*
- `app/Http/Controllers/Staff/OperatorComplaintController.php` L197–270 (`updateDestination` — operator picks the actual destination from the category-mapped set).

### A.4 Authorization mechanism existing

- **No Policy classes, no `Gate::define`.** `glob app/Policies/**` → 0 files; `grep "Policy|Gate::|authorize(|can(|AuthServiceProvider"` in `app/` returns only doc-comments and Form Request `authorize()` methods.
- **Mechanism = route middleware + Form Request `authorize()`.**
  - `bootstrap/app.php` L15–18 registers aliases `role` → `EnsureUserHasRole`, `active` → `EnsureUserIsActive`.
  - `app/Http/Middleware/EnsureUserHasRole.php` L20–41 — `abort(403)` if role not in the allow-list.
  - `app/Http/Middleware/EnsureUserIsActive.php` — logout on inactive.
- **Operator complaint surface** is gated **only** by `Route::middleware(['auth', 'role:operator,super_admin'])` (`routes/web.php` L62). There is **no per-complaint authorization** on `show` / `updateCategory` / `updateDestination` / `assign` / `updateStatus` / `addNote` (confirmed: `OperatorComplaintController` uses inline `$request->validate()` and never calls an ownership/scope check). `index` L43–45 is a **global** `Complaint::query()`.
- **Citizen isolation** is query-level: `Complaint::scopeForReporter` (`app/Models/Complaint.php` L101–104), applied in citizen controllers.
- **Admin** is monitoring-only: `routes/web.php` L79–84 (no mutation routes); `AdminDashboardController::complaints` L36–83 is a global read query.

### A.5 Livewire — NOT PRESENT (prompt premise does not match repository)

The prompt lists Livewire in the stack and asks to verify "Livewire endpoint scoping" (§6) and `wire:navigate` (§13). **Livewire is not installed and not used:**
- `composer.json` requires only `laravel/framework`, `laravel/tinker` (no `livewire/livewire`).
- `grep "livewire|Livewire|wire:"` across `*.php|*.json|*.blade.php|*.js` → **0 matches**.

There are **no Livewire components**, therefore there is no Livewire endpoint to scope. This is reported as a discrepancy, not "fixed" (inventing Livewire would violate "do not invent").

### A.6 Stale route reference (§13 check)

`resources/views/super-admin/dashboard.blade.php` references the **singular** route names:
- L26–27: `Route::has('super-admin.user.create')` / `route('super-admin.user.create')`
- L121–122: `Route::has('super-admin.user.index')` / `route('super-admin.user.index')`

Canonical names are **plural** (`super-admin.users.create` / `super-admin.users.index`, `routes/web.php` L93–94). **However**, both references are wrapped in `Route::has(...)` guards, so they render the "Belum tersedia pada baseline ini" fallback rather than throwing — the flow is not broken, but the links are inert. This is recorded as a finding (§F, minor). Not fixed here because the primary task is blocked and the prompt forbids unrelated changes.

---

## B. IMPLEMENTATION

**Files changed: NONE.**

| Intended change | Status | Reason |
|---|---|---|
| Server-side Operator Dinas/Unit scoping (list/detail/search/filter/pagination/stats) | **NOT DONE — BLOCKED** | The Operator↔Dinas/Unit relation does not exist (§A.2); implementing requires new business decisions not derivable from the repository (§F, BDR-1/BDR-2). Prompt §4 / §15 / FINAL RULE mandate HARD STOP. |
| Super Admin complaint status mutation | **NOT DONE — NOT NEEDED** | Already implemented via existing mechanism (`routes/web.php` L62 admits `super_admin` to `role:operator,super_admin`; `updateStatus` writes status + history + audit) and already covered by passing tests (§E.2). Adding anything would create a second mutation path, which §1.2 explicitly forbids. |
| IDOR regression tests (Operator A/B) | **NOT DONE — BLOCKED** | There is no scope rule to assert against; a test would have to invent the operator→unit binding. |
| Super Admin regression tests | **ALREADY EXIST** | `test_super_admin_can_update_status`, `test_super_admin_must_follow_transition_matrix`, `test_super_admin_can_access_operator_routes` (see §E.2). |
| Fix stale singular route names | **NOT DONE** | Out of the primary scope and unrelated to the frozen task; recorded as a finding (§F). |

---

## C. AUTHORIZATION MATRIX (actual, from implementation — not aspirational)

| Role | Complaint visibility | Status mutation | Mechanism (actual) |
|---|---|---|---|
| **Masyarakat** | Own reports only (`reporter_id`), DB-level `scopeForReporter` | **No** | middleware `role:masyarakat` + query scope |
| **Operator** | **GLOBAL — all complaints** (no Dinas/Unit scope exists) | **Yes** (transition matrix enforced) | middleware `role:operator,super_admin`; inline validation; no per-complaint check |
| **Admin** | **GLOBAL — all complaints (monitoring/read-only)** | **No** | middleware `role:admin`; no mutation routes |
| **Super Admin** | **GLOBAL** — via `/super-admin/laporan` (read-only controller) **and** via `/operator/*` (full operator surface) | **Yes** (transition matrix enforced) | middleware `role:super_admin` (super-admin group) + `role:operator,super_admin` (operator group) |

**Required target vs. reality:**

| Requirement | Required | Current | Gap |
|---|---|---|---|
| Operator scoped to own Dinas/Unit | Scoped | **Global** | **UNIMPLEMENTABLE without new decisions** |
| Super Admin may mutate status | Yes | **Yes (already)** | None |

Notes:
- The matrix above is **descriptive of the code as it exists**, per the prompt's instruction not to assume rights the repository does not support.
- `admin` is NOT assumed to have operator rights; it is monitoring-only and has no mutation route.
- Super Admin is **global**, consistent with `architecture.md` L155 ("full website management"), `prd.md` L36, and `schema.md` §255–257. No scoping of Super Admin is requested.

---

## D. TEST RESULTS

Baseline was re-verified **before** concluding (no code was changed, so this is the current state):

| Check | Command | Result |
|---|---|---|
| Full test suite | `php artisan test` | **PASS** — 438 tests, 438 passed, 1453 assertions, 0 failed, 0 skipped (43.6s) |
| Route list | `php artisan route:list` | **PASS** — 61 routes, none broken |
| Migrations | `php artisan migrate:status` | **PASS** — 11 Ran / 0 Pending |
| Build | `npm run build` | **PASS** (per Prompt 13; not re-run — no frontend asset touched) |

- **Targeted tests: N/A** — no implementation was made, so no new targeted tests were added.
- **No test was weakened, deleted, or skipped.**
- **No migration was created or run.**

---

## E. SECURITY VERIFICATION

### E.1 Operator A cannot access Complaint B (IDOR) — **NOT VERIFIABLE**

There is no defined notion of "Operator A's unit" or "Complaint B's unit", so the scenario cannot be expressed:
- Operators have no unit binding (§A.2).
- A complaint's only unit field (`dinas_unit_id`) is **NULL until routed** — see the deadlock analysis below.

Therefore the requested guarantee cannot be verified, and asserting it would require inventing the missing binding. **Reported as blocked, not fixed.**

**⚠ Deadlock analysis (decisive).** If scope were naively defined as `complaints.dinas_unit_id ∈ {operator's units}`:
1. A newly submitted complaint has `dinas_unit_id = NULL` (`schema.md` §4.3 L128; migration `…100007` L30–34).
2. `NULL` matches no operator's unit set → the complaint is invisible to **every** operator.
3. No operator can call `updateDestination` to set the destination, because they cannot even open the complaint (`show` would 403/404 under the scope).
4. **The routing workflow can never start.** The complaint is permanently orphaned.

This is not a code bug — it is proof that the business decision, *as literally stated*, is under-specified. The scope predicate must be decided (routed-only vs. category-based vs. hybrid), which is a business decision.

### E.2 Super Admin can mutate complaint status — **VERIFIED (already working)**

| Scenario | Evidence | Result |
|---|---|---|
| Super Admin can open a complaint | `test_super_admin_can_access_operator_routes` — `OperatorComplaintWorkflowTest.php` L721–726 | PASS |
| Super Admin can change status | `test_super_admin_can_update_status` — `ComplaintStatusLifecycleTest.php` L549–556 | PASS |
| Transition rules still enforced | `test_super_admin_must_follow_transition_matrix` — `ComplaintStatusLifecycleTest.php` L558–567 | PASS |
| Audit trail written | `OperatorComplaintController::updateStatus` L431–444 writes `AuditLog` `action='complaint.status_updated'` with `from_status`/`to_status`/`reference_code`, inside the same `DB::transaction` (L391–445) | Confirmed by code + suite |
| Authority per frozen docs | `schema.md` §255–257 ("Operator: YES / Super Admin: YES / Admin: NO / Masyarakat: NO"); `app/Enums/ComplaintStatus.php` L11–15 | Consistent |
| Route admission | `routes/web.php` L62: `role:operator,super_admin` | Confirmed |

**Conclusion:** §1.2 is already satisfied by the existing mechanism (role middleware + existing controller). No new permission, Policy, Gate, or route was — or should be — introduced.

---

## F. UNRESOLVED ITEMS

### 🔴 BLOCKED / REQUIRES DECISION

---

#### BDR-1 — How is an Operator bound to a Dinas/Unit? (schema does not exist)

The decision "Operator scope = Dinas/Unit" presumes an Operator↔Dinas/Unit binding. **No such binding exists** (§A.2). This cannot be inferred from the repository.

Options (illustrative — **no option selected**):

| # | Option | Shape | Trade-off |
|---|---|---|---|
| 1a | One operator → exactly one unit | `users.dinas_unit_id` (nullable FK) | Simplest; no multi-unit operators; needs backfill decision for existing operators |
| 1b | One operator → many units | pivot `dinas_unit_user` | Flexible; more complex; needs a management UI |
| 1c | Scope derives from role only (no binding) | — | Does **not** achieve Dinas/Unit isolation → contradicts the decision |
| 1d | Defer | keep global operator visibility | Contradicts the decision; documents the gap |

Additional sub-decisions: does the binding apply only to `operator`, or also `admin`? Is it required at user creation (changes `StoreUserRequest`/`UpdateUserRequest`/User Management UI from Prompt 9)? How is it assigned — by Super Admin only?

---

#### BDR-2 — Which field defines a complaint's Dinas/Unit scope? (deadlock risk)

Even with a binding, the scope predicate is undecided, and the obvious choice **deadlocks the workflow** (§E.1).

Options (illustrative — **no option selected**):

| # | Scope predicate | Effect |
|---|---|---|
| 2a | `complaints.dinas_unit_id ∈ operator's units` | **DEADLOCK** — unrouted (NULL) complaints invisible to everyone; routing can never begin |
| 2b | Complaint's category is mapped to one of the operator's units (`category_dinas_unit`) | Unrouted complaints visible; but a category may map to several units → several operators see the same complaint; scope is "candidate", not "assigned" |
| 2c | Hybrid: routed → destination unit only; unrouted → candidate units from category | Closest to the intent, but is a **new rule** requiring explicit approval and precise semantics |
| 2d | Scope by `assigned_to` only | Different concept (assignment ≠ unit); ignores the Dinas/Unit decision |

Sub-decisions: what do operators see for `dinas_unit_id = NULL`? Is a complaint ever visible to >1 unit? Does visibility change when an operator re-routes the destination? What happens to a complaint whose destination unit is later deactivated?

---

#### BDR-3 — Operators without a unit (transition/backfill)

The seeded operator (`operator@superbie.local`) and any existing operators have **no** unit. On the day scoping ships: do they see **everything** (isolation bypass) or **nothing** (lockout)? This is a rollout/business decision.

---

#### BDR-4 — Livewire assumption (premise mismatch)

The prompt requires Livewire endpoint scoping, but **Livewire is not installed** (§A.5). Decision needed: confirm the stack (Blade + Alpine.js monolith, as the repository actually is) and drop the Livewire requirement, or approve adding Livewire (which the project's frozen rules currently forbid).

---

#### 🟡 MINOR (recorded, not fixed)

- **M-1 — Stale singular route names.** `resources/views/super-admin/dashboard.blade.php` L26–27 / L121–122 use `super-admin.user.create` / `super-admin.user.index`; canonical = plural. Guarded by `Route::has(...)`, so non-fatal (renders fallback). Candidate fix: update to `super-admin.users.create` / `super-admin.users.index`.
- **M-2 — No per-complaint authorization anywhere on the operator surface** (Prompt 13 P1-1). Query scope (§6) + a Policy/`authorize()` (§7) are both absent. Both depend on BDR-1/BDR-2.

---

## Proposed (NON-BINDING) implementation sketch — for approval only, NOT applied

If the business approves **BDR-1 option 1a** + **BDR-2 option 2c**, the minimal change set would be:

1. **Migration** — add nullable `dinas_unit_id` (FK → `dinas_units`, `ON DELETE SET NULL`) to `users`. *(New migration; currently forbidden until approved.)*
2. **Model** — `User::dinasUnit()` BelongsTo; `Complaint::scopeVisibleToOperator($user)` implementing the chosen predicate; apply it in `OperatorComplaintController::index` and as a defense-in-depth check in `show`/mutations.
3. **User Management** — expose the binding in `StoreUserRequest`/`UpdateUserRequest` + `super-admin/users/*` views (extends Prompt 9 surface).
4. **Tests** — IDOR regression (Operator A ↮ Complaint B) + Super Admin mutation (already present) + statistics scoping.

> This sketch is explicitly **not implemented** and requires the decisions above first. It is included only to satisfy prompt §4 step 3 ("tampilkan migration/model yang diperlukan").

---

## FINAL RULE — compliance

- **Discovery first:** done (§A), repository used as source of truth.
- **No invented** Dinas/Unit list, operator→unit mapping, status, permission, role, transition, threshold, SLA, or workflow: **compliant**.
- **Structure does not support the given decision** and requires new business decisions → **HARD STOP** (§4 / §15 / FINAL RULE).
- **No source, migration, route, view, model, controller, service, request, test, or config file was modified.** No migration was run. No DB was reset/seeded/altered. No package was installed.
- **§1.2 (Super Admin mutation) is already satisfied** by the existing mechanism and covered by passing tests; no second path was created.

**Awaiting user decision on BDR-1, BDR-2 (and BDR-3/BDR-4) before any implementation. No further work will begin unprompted.**
