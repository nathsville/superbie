# SUPERBIE — PHASE N4 IMPLEMENTATION REPORT

## In-App Notification System ("Lapor Pak Wali")

> **LC-2 CORRECTION NOTE (added by LC-2D, later than this document):** This is a dated implementation report. Its milestone matrix M1–M12 remains the source of truth for recipients/copy, **but** under the LC-2 active lifecycle (`submitted → in_progress → {resolved | rejected}`) the transitions to `waiting_for_information` and `closed` no longer exist, so **M9** (`status_waiting_for_information`) and **M11** (`status_closed`) are **dormant** for new complaints. Their constants, recipient mapping, and emit branches are deliberately **retained** (not removed) for historical compatibility; historical notifications already delivered under M9/M11 remain. See `schema.md` §4.8 / §6 and `architecture.md` §5.5 for the current statement. The historical content below is retained unchanged for traceability.

> Phase: **N4** — in-app notification system (implementation).
> Prereqs frozen: **N2** (business decisions) + **N3** (technical design).
> Hard stop after this report — **no N5**.

---

## 1. Objective and Scope

Implement the in-app notification system defined by the frozen N2/N3 decisions:

- a real `notifications` table + an explicit `NotificationService`;
- emission of all 12 in-app milestones (M1–M12) from the canonical business actions;
- an owner-scoped notification centre (bell + dropdown + `/notifikasi` page);
- full read/unread + delete UX;
- retention integration;
- automated tests;
- documentation sync.

**In scope:** in-app notifications only. **Out of scope:** email/Mailable, queue/Job, broadcasting/websocket/realtime, Laravel's `Notifiable` database channel, external providers (email/SMS/WhatsApp), observers/events/listeners, public tracking.

**Hard constraint respected:** no unrelated refactor; lifecycle/roles/status/`review_state`/Dinas routing/deprecated columns unchanged; no existing migration modified.

---

## 2. Baseline (pre-N4)

| Metric | Baseline | After N4 |
|---|---|---|
| Tests | 1216 | **1269** |
| Assertions | 5838 | **6048** |
| Failures | 0 | **0** |
| Migrations | 21 Ran | **22 Ran** |
| Routes | 85 | **90** |
| `npm run build` | PASS | **PASS** |

Baseline was re-verified before any N4 code was written. Test setup unchanged: **MySQL** (host `127.0.0.1`, port `3307`, DB `superbie_testing`); `phpunit.xml` was **NOT** switched to SQLite.

---

## 3. Deliverables (files)

### New files
| File | Purpose |
|---|---|
| `database/migrations/2025_01_08_100018_create_notifications_table.php` | `notifications` schema + idempotency UNIQUE + 2 indexes |
| `app/Models/AppNotification.php` | model (table `notifications`), casts, scopes, `markAsRead()`, render-time `targetUrl()` |
| `app/Services/NotificationService.php` | milestone constants, audience map, copy templates, `emit()`, recipient resolution, idempotent `deliver()` |
| `app/Http/Controllers/NotificationController.php` | index / open / markRead / markAllRead / destroy (owner-scoped) |
| `resources/views/notifications/index.blade.php` | notification page (paginated, role-based nav, mark-all) |
| `resources/views/components/notification-item.blade.php` | shared row (open link + delete form) |
| `tests/Feature/NotificationSystemTest.php` | 30 tests — emission matrix, recipients, idempotency, retention, privacy |
| `tests/Feature/NotificationCentreTest.php` | 21 tests — read/unread, IDOR, pagination, target resolution, bell UI |

### Modified files
| File | Change |
|---|---|
| `app/Models/User.php` | added `appNotifications()` HasMany (avoids `Notifiable::notifications()` clash) |
| `routes/web.php` | 5 notification routes (`/notifikasi`, `/notifikasi/read-all`, `/notifikasi/{id}/buka`, `/notifikasi/{id}/read`, `/notifikasi/{id}`) |
| `app/Http/Controllers/Citizen/ComplaintController.php` | M1, M4, M12 emit points (inside existing transactions) |
| `app/Http/Controllers/Staff/SuperAdminValidationController.php` | M2, M3 emit points |
| `app/Http/Controllers/Staff/SuperAdminCompletionValidationController.php` | M6, M7 emit points |
| `app/Http/Controllers/Staff/OperatorComplaintController.php` | M5 + M8/M9/M10/M11/M7-via-`updateStatus` emit points |
| `app/Services/ComplaintRetentionService.php` | `purge()` deletes `AppNotification` rows before the complaint |
| `resources/views/components/layouts/dashboard.blade.php` | bell enabled (unread badge, dropdown ≤10, "Lihat semua") + 2 indexed queries |
| `tests/Feature/DashboardShellSidebarTest.php` | bell test rewritten (disabled → enabled) + 2 new bell tests |
| `tests/Feature/ProgressiveNavigationTest.php` | route-inventory guard 85 → 90 (documented) |
| `rules.md`, `architecture.md`, `design.md`, `schema.md`, `prd.md`, `PROGRESS.md`, `README.md`, `SETUP_AND_DOCS.md` | N4 documentation sync |

---

## 4. Database — `notifications` table

Migration `2025_01_08_100018_create_notifications_table` (additive; `down()` drops the table):

| Column | Type | Null | Key / constraint |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | No | PK |
| `recipient_user_id` | BIGINT UNSIGNED | No | FK → `users.id` RESTRICT |
| `complaint_id` | BIGINT UNSIGNED | No | FK → `complaints.id` RESTRICT |
| `type` | VARCHAR(64) | No | part of UNIQUE |
| `title` | VARCHAR(180) | No | — |
| `body` | TEXT | No | — |
| `data` | JSON | Yes | NULL |
| `read_at` | TIMESTAMP | Yes | NULL = unread |
| `source_event_id` | BIGINT UNSIGNED | No | part of UNIQUE |
| `created_at` / `updated_at` | TIMESTAMP | Yes | — |

**Indexes:**
- `UNIQUE(recipient_user_id, complaint_id, type, source_event_id)` → `notifications_idempotency_unique` — the DB-layer idempotency guarantee.
- `INDEX(recipient_user_id, read_at)` → `notifications_recipient_read_idx` — unread count/listing.
- `INDEX(recipient_user_id, created_at, id)` → `notifications_recipient_recent_idx` — newest-first listing/dropdown.

Migration ran successfully: `2025_01_08_100018_create_notifications_table .. [12] Ran`. Total migrations: **22 Ran**.

---

## 5. Milestone Matrix (frozen N3) — as implemented

| # | Constant | Business action | Emitted from | Recipients |
|---|---|---|---|---|
| M1 | `complaint_submitted` | citizen submit | `Citizen\ComplaintController::store` | all active Super Admin |
| M2 | `initial_validation_approved` | SA approve initial | `SuperAdminValidationController::approve` | Operator + reporter |
| M3 | `initial_validation_returned` | SA return initial | `SuperAdminValidationController::return` | reporter |
| M4 | `complaint_resubmitted` | citizen resubmit | `Citizen\ComplaintController::update` | all active Super Admin |
| M5 | `completion_submitted` | Operator submit completion | `OperatorComplaintController::submitCompletion` | all active Super Admin |
| M6 | `completion_returned` | SA return completion | `SuperAdminCompletionValidationController::return` | Operator |
| M7 | `completion_validated` | SA approve completion / SA direct resolve | `SuperAdminCompletionValidationController::approve`, `OperatorComplaintController::updateStatus` | reporter + Operator |
| M8 | `status_in_progress` | → `in_progress` | `OperatorComplaintController::updateStatus` | reporter |
| M9 | `status_waiting_for_information` | → `waiting_for_information` | `OperatorComplaintController::updateStatus` | reporter |
| M10 | `status_rejected` | → `rejected` | `OperatorComplaintController::updateStatus` | reporter |
| M11 | `status_closed` | → `closed` | `OperatorComplaintController::updateStatus` | reporter + Operator |
| M12 | `rating_submitted` | citizen rating | `Citizen\ComplaintController::storeRating` | Operator + all active Super Admin |
| M13 | *(standalone public response)* | Operator add public-response note | `OperatorComplaintController::addNote` | **none** (no notification) |

`location_updated` — **OUT OF SCOPE** (not wired).

**`updateStatus` mapping:** `InProgress→M8`, `WaitingForInformation→M9`, `Rejected→M10`, `Closed→M11`, `Resolved→M7` (G7). `UnderReview` via `updateStatus` is **deliberately NOT mapped** — the canonical M2 lives only in `SuperAdminValidationController::approve`, so mapping `UnderReview` here would double-notify. Documented, not a gap.

---

## 6. Recipient Rules (frozen)

- **Masyarakat:** own-complaint milestones only.
- **Operator:** own-Dinas milestones, resolved **only** via `complaints.dinas_unit_id → DinasUnit::operator()`. `assigned_to` is **never** consulted.
- **Super Admin:** all relevant milestones; every **active** Super Admin receives (single query, no N+1).
- **Admin** and historical **Petugas:** receive **NOTHING**.
- **Self-notification:** never (actor excluded in `deliver()`).
- **Inactive recipient** (`is_active != true`): no **new** notification; existing rows retained; reactivation receives future ones.
- **Dinas without Operator (G8):** Operator notification skipped + `Log::info(...)`; **no fallback**.
- **Idempotency:** UNIQUE `(recipient_user_id, complaint_id, type, source_event_id)` + application pre-check + MySQL 1062 catch.
- **Transaction:** every `emit()` runs **inside** the caller's existing `DB::transaction`, right after the canonical `AuditLog::create(...)`; the audit id is `source_event_id`. A notification failure rolls back the business action.

---

## 7. Notification Centre (read/unread + delete)

- **Routes (5):** `notifications.index` (GET `/notifikasi`), `notifications.read-all` (POST `/notifikasi/read-all`), `notifications.open` (GET `/notifikasi/{notification}/buka`), `notifications.read` (PATCH `/notifikasi/{notification}/read`), `notifications.destroy` (DELETE `/notifikasi/{notification}`). Route param is a **scalar id** (`whereNumber`), never an implicitly-bound model.
- **Owner-scoped (IDOR closed):** every query starts from `auth()->user()->appNotifications()->findOrFail($id)`. A user can never read/mark/delete another user's notification (404).
- **Read state:** `read_at IS NULL` = unread. Opening marks read; mark-one; mark-all; unread count.
- **Listing:** newest first (`created_at DESC, id DESC`), paginated **20/page**.
- **Delete (G11):** user may delete their own notification; the complaint is never touched.
- **Target URL:** resolved at **render time** from `(type, recipient role, complaint_id)`; never stored. Super Admin prefers the actionable queue detail (G5); Operator respects Dinas scope; Citizen only their own; invalid → safe listing fallback (no error, no unauthorized data).
- **Bell:** enabled button (`aria-label="Notifikasi"`, `aria-haspopup="true"`, Alpine `notifOpen`, Escape + click-outside), unread badge **hidden at 0** / **`9+`** for large counts, dropdown ≤10 latest, "Lihat semua" → `notifications.index`. Hidden on mobile (`hidden sm:block`) so the layout is not broken.
- **Privacy:** `data` is minimal (`complaint_id`, `reference_code`, `milestone`, `status`) — no PII, no internal reason/narrative, no secrets, no hardcoded URLs. All text escaped with `{{ }}`.
- **No caching** of notification queries.

---

## 8. Retention Integration

`ComplaintRetentionService::purge()` now deletes `AppNotification` rows **before** the parent complaint (FK RESTRICT), inside the existing DB transaction. The retention policy is **unchanged**: 5 years from `complaints.submitted_at`, command `complaints:purge-expired`, scheduler 02:00.

---

## 9. Tests — New / Changed

### New: `tests/Feature/NotificationSystemTest.php` — 30 tests / 98 assertions
Covers M1–M13 emission, recipients (Citizen/Operator/Super Admin), actor exclusion, Admin/Petugas exclusion, inactive recipients, reactivation, Dinas-without-Operator (no fallback), `assigned_to` never used, idempotency (same `source_event_id`), UNIQUE constraint as final guard, retention purge, and privacy (no PII/internal reason).

### New: `tests/Feature/NotificationCentreTest.php` — 21 tests / 53 assertions
Covers owner-scoped listing + pagination (20/page), empty state, authentication requirement, open→mark-read + redirect, mark-one, mark-all (leaves others untouched), unread count, delete own, IDOR on open/mark/delete (404), unknown id (404), render-time target resolution + safe fallbacks (Citizen/Operator/Super Admin), non-owner target = null, bell badge hidden-at-0 / `9+`, bell dropdown link.

### Changed: `tests/Feature/DashboardShellSidebarTest.php`
- Rewrote `test_search_is_a_pill_and_bell_is_an_enabled_notification_control` (asserts enabled bell, `aria-label="Notifikasi"`, `aria-haspopup="true"`, `notifOpen`, "Lihat semua", `notifications.index`; asserts the disabled placeholder is gone). No test deleted.
- Added `test_bell_hides_the_unread_badge_when_there_are_no_unread_notifications` and `test_bell_shows_an_unread_badge_for_unread_notifications`.

### Changed: `tests/Feature/ProgressiveNavigationTest.php`
- Route-inventory guard updated **85 → 90** with the 5 new routes documented (no test deleted).

---

## 10. Test Results (actual)

| Run | Tests | Assertions | Failures |
|---|---|---|---|
| `NotificationSystemTest` | 30 | 98 | 0 |
| `NotificationCentreTest` | 21 | 53 | 0 |
| Notification suite + shell (`filter`) | 70 | 305 | 0 |
| **Full suite** | **1269** | **6048** | **0** |

Full suite was run **3 consecutive times**: all green (1269 / 6048 / 0). +53 tests vs baseline (30 + 21 + 2 new bell tests).

---

## 11. Verification Commands

| Command | Result |
|---|---|
| `php artisan test` | **1269 passed / 6048 assertions / 0 failures** |
| `php artisan migrate:status` | **22 migrations — all `Ran`** (incl. `2025_01_08_100018_create_notifications_table`) |
| `php artisan route:list` | **90 routes** |
| `npm run build` | **PASS** (vite build, 16 modules transformed) |

---

## 12. Idempotency & Concurrency

- Idempotency anchor = the canonical `audit_logs.id` for the business action (`source_event_id`), stable across retries of the **same** action.
- Two-layer protection: application `exists()` pre-check (fast path) + UNIQUE index (final guarantee).
- Concurrency: MySQL duplicate-key (`SQLSTATE 23000` / errno `1062`) is caught in `deliver()` and swallowed (the logical notification already exists); any other `QueryException` is re-thrown so the business transaction rolls back.
- Different business events (different audit ids) for the same recipient produce **separate** rows (verified).

---

## 13. Security & Privacy

- **IDOR closed:** owner-scoped relation + scalar route id + `findOrFail` on every read/mark/delete. `AppNotification::find($id)` is never used alone.
- **No unauthorized data:** render-time target resolution re-checks the recipient's own access (ownership / Dinas scope); invalid/stale targets fall back to a safe listing.
- **No PII/secret leakage:** `data` is minimal; internal reasons (M3/M6) and internal narratives are never placed in a notification; no hardcoded URLs.
- **XSS:** all output escaped (`{{ }}`).
- **No forbidden mechanisms:** no `Mail::`/`Mailable`, no `ShouldQueue`/`dispatch`, no broadcasting, no `Notifiable` database channel usage, no observers/events/listeners for notifications.

---

## 14. Forbidden-Pattern Audit

| Pattern | Result |
|---|---|
| `Mail::` / `Mailable` in `app/` | none |
| `ShouldQueue` / `dispatch(` / `Jobs` | none |
| Broadcasting / websocket / realtime | none |
| `Notification::send` / `->notify(` | none |
| Notification observers/events/listeners | none |
| `assigned_to` as Operator recipient | none (comments only) |

---

## 15. Documentation Sync

Updated with the N4 design, milestone matrix, recipient rules, retention integration, and route/test counts:

- `rules.md` (§4 legacy + new N4 notification rule; §8 test rules)
- `architecture.md` (§5.5 new flow; §10 security table; §11 no external integration; §12 no caching)
- `design.md` (§4 "Notification bell + dropdown")
- `schema.md` (§2 entity, §3 ERD, §4.8 `notifications`, §5 indexes, §6 `type` enum, §7 relationships + retention)
- `prd.md` (F-014 vs F-014A; in-app vs external clarification)
- `PROGRESS.md` (counts 22 / 90 / 1269 / 6048; N4 status; feature list)
- `README.md`, `SETUP_AND_DOCS.md` (in-app implemented; external still out of scope)

---

## 16. Deviations / Notes

1. **G8 test path.** The initial-approval action **blocks** routing to a Dinas/Unit without an Operator, so G8 (Dinas without Operator → skip + log, no fallback) is exercised at the **service boundary** through an operator-only milestone (M6) — the only realistic path where a routed complaint's Dinas loses its Operator after routing. This is a test-design adaptation, not a rule change.
2. **`UnderReview` via `updateStatus` not mapped.** The canonical M2 lives only in `SuperAdminValidationController::approve`; mapping `UnderReview` in `updateStatus` would double-notify. Deliberately unmapped and documented (see §5).
3. **Pre-existing Faker flake (not N4).** An unrelated, untracked test (`tests/Feature/CommandCenterAnalyticsTest.php`, using `ComplaintCategoryFactory`) can intermittently collide on `complaint_categories.slug` (e.g. `ut-est`, `id-aut`) because Faker is not seeded. It passed in isolation and in 3/3 full-suite runs after; it is **not** caused by N4 (no N4 code touches that factory or test). Reported for transparency; not modified (out of scope).
4. **No cache, no N+1.** Super Admin fan-out is a single query; the dashboard shell adds at most two indexed queries.

---

## 17. Constraints Compliance

| Constraint | Status |
|---|---|
| In-app only (no email/queue/broadcast/realtime) | ✅ |
| Explicit service (no observer/event/listener) | ✅ |
| Operator from `dinas_unit_id` only (never `assigned_to`) | ✅ |
| No self-notification; Admin/Petugas get nothing | ✅ |
| Created inside the business transaction; rollback on failure | ✅ |
| Idempotency UNIQUE + indexes | ✅ |
| Inactive recipients: no new, keep existing | ✅ |
| Owner-scoped read/mark/delete (IDOR closed) | ✅ |
| Minimal `data`; URL resolved at render time | ✅ |
| Read/unread UX; 20/page; badge hidden at 0 / 9+ | ✅ |
| Retention purge deletes notifications before complaint | ✅ |
| No caching; single-query fan-out; no N+1 | ✅ |
| No unrelated refactor; lifecycle/roles/status/deprecated columns unchanged | ✅ |
| Existing 1216 tests stay green | ✅ (1269 green) |
| `phpunit.xml` not switched to SQLite | ✅ (MySQL kept) |
| No test deleted (bell test rewritten) | ✅ |

---

## 18. Conclusion

PHASE N4 is implemented, tested, and documented.

- **Tests:** 1269 passed / 6048 assertions / 0 failures (baseline 1216 → +53).
- **Migrations:** 22 Ran (incl. `notifications`).
- **Routes:** 90.
- **Build:** `npm run build` PASS.
- No N5 work started.

### **READY FOR N4 REVIEW**
