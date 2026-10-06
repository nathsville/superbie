# Prompt 20 Completion Report

## SuperBie — Lapor Pak Wali

**Scope:** Authentication abuse protection & rate-limit hardening
(registration abuse, forgot-password abuse, login HTTP semantics, auth regression tests).

**Findings in scope:** F-18-04, F-18-15, F-18-16, F-18-21.
**Frozen decision applied:** D-5.

---

## 1. Status

```
PASS WITH DECISION REQUIRED
```

- **Login HTTP semantics (F-18-16):** remediated — rate-limited login now returns **HTTP 429** with a native `Retry-After`, while the login **policy is byte-for-byte unchanged** (D-5).
- **Registration abuse protection (F-18-04 / F-18-15):** **DECISION REQUIRED** — no policy exists in the repository; nothing was implemented and no threshold was invented (§6, §25).
- **Forgot-password abuse protection (F-18-04 / F-18-15):** **DECISION REQUIRED** — no policy exists; nothing was implemented (§7, §25).
- **Password-reset enumeration (§8):** the *request* endpoint is enumeration-safe (**PASS**); the *reset* endpoint distinguishes user/token status (**known gap, DECISION REQUIRED** — not redesigned, §8).
- **F-18-21 (login key redesign):** deliberately **NOT** changed (D-5 forbids a policy/key change).

---

## 2. Authentication Inventory

All routes are the actual repository routes (`routes/web.php`); **no endpoint was added or removed**.

| Endpoint | Method | Route name | Auth | Middleware | Existing Rate Limit | Existing Response | Prompt 20 Action | Status |
|---|---|---|---|---|---|---|---|---|
| Login (form) | GET `/login` | `login` | Guest | `guest` | — | 200 | none | unchanged |
| Login (submit) | POST `/login` | `login.store` | Guest | `guest` | `LoginRequest` → `RateLimiter` key `email|ip`, 5 attempts, 60 s decay | **422** validation (was) | **422 → 429** for the exceeded state only | **PASS** |
| Register (form) | GET `/register` | `register` | Guest | `guest` | — | 200 | none | unchanged |
| Register (submit) | POST `/register` | `register.store` | Guest | `guest` | **none** | 302 / 422 validation | none (no policy) | **DECISION REQUIRED** |
| Forgot password (form) | GET `/forgot-password` | `password.request` | Guest | `guest` | — | 200 | none | unchanged |
| Forgot password (submit) | POST `/forgot-password` | `password.email` | Guest | `guest` | **none** (broker per-email throttle 60 s only) | 302 + generic `status` | none (no policy) | **DECISION REQUIRED** |
| Reset password (form) | GET `/reset-password/{token}` | `password.reset` | Guest | `guest` | — | 200 | none | unchanged |
| Reset password (submit) | POST `/reset-password` | `password.update` | Guest | `guest` | **none** | 302 / 422 / `withErrors` | none (no policy) | **DECISION REQUIRED** |
| Password change | PATCH `/profile` | `citizen.profile.update` | Auth | `auth` + web | **none** | 302 | none (out of scope) | unchanged |
| Logout | POST `/logout` | `logout` | Auth | `auth` + web | **none** (deliberately not throttled) | 302 | none | unchanged |

Flow actually found (not assumed):

```
Route (guest)
 → Form Request (LoginRequest: rules + authorize)
 → Controller (AuthenticatedSessionController::store)
 → LoginRequest::authenticate()  →  Laravel Auth::attempt + RateLimiter
 → Response (redirect / 422 validation / now 429 rate limit)
```

For register / forgot / reset the flow is `Route → inline $request->validate() → Controller → Password broker / User::create → Response`
(there is **no** Form Request and **no** throttle on these three).

---

## 3. Login Policy

Reported verbatim from the implementation (no invented numbers):

```
Key:        email|ip
            (Str::transliterate(Str::lower($this->string('email')) . '|' . $this->ip()))
Threshold:  unchanged — 5 attempts
Window:     unchanged — framework default decay = 60 seconds
Behaviour:  unchanged — hit on failure, clear on success, Lockout event dispatched
Message:    unchanged — "Terlalu banyak percobaan login. Coba lagi dalam :seconds detik."
Response:   429   ← the ONLY change (was 422)
Retry-After: native, from RateLimiter::availableIn()  (never hardcoded)
```

**Change made (single point):** `app/Http/Requests/Auth/LoginRequest.php::ensureIsNotRateLimited()`
now throws `Illuminate\Http\Exceptions\ThrottleRequestsException` (the framework's own 429 exception) instead of `ValidationException`.

Why this is D-5-compliant:

- The `RateLimiter` usage (`tooManyAttempts(..., 5)`, `hit()`, `clear()`, `availableIn()`) is untouched → **key / threshold / window / behaviour unchanged**.
- The `Lockout` event is still dispatched.
- The limiter stays in the Form Request (not converted to route `throttle:` middleware), so the custom *hit-on-failure / clear-on-success* login semantics — which `throttle:` middleware does **not** replicate — are preserved.
- Only the exception type (hence the HTTP status) changed, per §9–§11.
- Invalid credentials still throw `ValidationException` → a normal field error (**not** 429) — §19 satisfied.

---

## 4. Registration Policy

```
DECISION REQUIRED
```

- **Current behaviour:** `RegisteredUserController::store()` validates identity fields (`name`, `email`, `nik`, `phone_number`, `address`, `password`) and creates the account. **No rate limiting of any kind.**
- **Repository evidence of absence of policy:**
  - `rules.md:48` lists only *login, complaint submission, and public tracking* for rate limiting — registration is **not** listed.
  - `prd.md:183` says "Terapkan rate limit dan perlindungan spam yang dapat dikonfigurasi" but only ever specifies the **complaint submission** value (Prompt 17).
  - `prd.md:96` → `TODO: Define requirement — kebijakan moderasi, laporan duplikat, spam, dan konten ilegal`.
  - No captcha, lockout, IP/email/account limit, window, or anti-automation rule exists anywhere.
- **Abuse risk:** an unauthenticated attacker can script account creation (DB growth, identity/NIK/phone exhaustion, resource consumption). No `email_verified_at` gate exists either (D-4: email verification stays disabled).
- **Options to decide (NOT chosen by me):**

  | Option | Scope key | Example window/threshold | Consequence |
  |---|---|---|---|
  | A | client IP | e.g. `N / 10 min` | Simple; risks NAT/shared-IP lockout of legitimate citizens |
  | B | email | e.g. `N / 10 min` | Target-specific; does not stop mass random-email creation |
  | C | IP + email | e.g. `N / 10 min` | Stronger; slightly more state |
  | D | session/device | — | Weakest against distributed scripts |

  Thresholds and windows are **placeholders**; the real values are undefined.
- **Recommended (advisory only):** Option C (IP + email) with the limiter placed at the **route boundary** before validation, mirroring Prompt 17's `complaint-submission` pattern, and values stored in `config/business_rules.php`. **Implementation is withheld pending approval.**
- **Implementation impact:** ~1 named limiter in `AppServiceProvider`, `throttle:` middleware on `POST /register` only, and 1 new Feature test file. No migration, no package.

---

## 5. Forgot-Password Policy

```
DECISION REQUIRED
```

- **Current behaviour:** `PasswordResetController::store()` validates `email`, calls `Password::sendResetLink()`, and **always** returns the same generic flash message. The only protection is the framework password broker's per-email `throttle` (`config/auth.php:100`, `60` seconds) which merely limits *token regeneration* for a given email — it is **not** an HTTP abuse control and does not limit the number of HTTP requests.
- **Repository evidence of absence of policy:** identical to §4 (no forgot-password entry in `rules.md:48`; no threshold/window/key anywhere).
- **Abuse risk:** an attacker can flood `POST /forgot-password` (mail-bombing a victim address, CPU/queue load, broker enumeration attempts).
- **Enumeration result (see §8): PASS for the request endpoint** — the response is identical for existing and non-existing emails.
- **Options to decide (NOT chosen by me):** same key candidates as §4 (IP / email / IP+email / session), applied to `POST /forgot-password` only. Thresholds/windows undefined.
- **Recommended (advisory only):** a route-boundary named limiter keyed on **email + IP**, with the existing generic response preserved unchanged. **Implementation withheld pending approval.**
- **Implementation impact:** ~1 named limiter, `throttle:` on `POST /forgot-password` only, 1 Feature test file. No migration, no package.

---

## 6. Tests

```
Before:  508 tests / 2235 assertions
After:   528 tests / 2313 assertions

Failed:  0
Skipped: 0
```

+20 tests (14 `LoginRateLimitTest` + 6 `PasswordResetEnumerationTest`). No existing test was deleted or weakened; no coverage was reduced.

### New regression tests

**`tests/Feature/LoginRateLimitTest.php` (14)** — proves the security invariant, not a re-designed policy:

1. successful login still redirects to dashboard;
2. wrong credentials remain a **validation** error (not 429);
3. missing input remains a **422** validation failure (never the limiter);
4. threshold = 5: first 5 failures are 422, the 6th is 429 (and even the correct password is blocked while the window is open);
5. the limiter key is proven to be `email|ip` (direct `RateLimiter::attempts()` assertion; no IP-only key exists);
6. rate-limited browser login returns **429** and the user stays a guest;
7. the 429 carries a **native** `Retry-After` (0 < value ≤ 60), not a fake/hardcoded header;
8. the rendered 429 is friendly and leaks nothing (no exception class, no "Too Many Attempts", no cache key, no vendor path);
9. login works again after the window decays (`travel(61)->seconds()`, no `sleep()`);
10. a successful login **clears** the counter (hit-on-failure / clear-on-success preserved);
11. the `Lockout` event is dispatched when rate-limited;
12. the limit is isolated **per email account** (same IP, different account is unaffected);
13. a JSON login gets **429 JSON** (`{"message": ...}`);
14. the login limiter does **not** block unrelated endpoints (`GET /login`, `GET /forgot-password`, `POST /forgot-password` all keep working).

**`tests/Feature/PasswordResetEnumerationTest.php` (6):**

1. existing email → generic `status`, no errors;
2. unknown email → identical generic `status`, no errors;
3. existing vs unknown are **indistinguishable** (same HTTP status, same redirect, same flash text, no field errors);
4. malformed email → normal validation error (separate concern);
5. **characterization** — reset endpoint, unknown email → `passwords.user` message (pins current behaviour for the known gap);
6. **characterization** — reset endpoint, existing email + bad token → `passwords.token` message.

The characterization tests (5–6) deliberately assert the **current** behaviour so the recorded gap (F-20-01) has a verified baseline for a future approved decision; they do not endorse the behaviour.

---

## 7. Build

```
npm run build: PASS   (exit code 0)
```

- Output: `app-*.js 54.33 kB`, `app-*.css 68.99 kB`.
- One **pre-existing, non-blocking** warning is emitted:
  `[plugin laravel:fonts] Optimized font fallbacks require the optional "fontaine" package…`
  This predates Prompt 20 (also present in Prompt 19) and does not affect the exit code. No new asset/config was added, so no new warning was introduced.

---

## 8. Routes

```
Before: 61
After:  61
```

No route was added, removed, or renamed. The login change is **inside** `LoginRequest`; no `throttle:` middleware was added to any route (registration/forgot-password were **not** throttled because no policy is approved). `php artisan route:list` reports **61 routes**.

---

## 9. Database Safety

```
Migrations created:   0
Migrations executed:  0
Data changes:         none
Migration status:     2025_01_01_100008_add_dinas_unit_id_to_users_table ... Pending (unchanged)
```

No `migrate`, `migrate:fresh`, `migrate:refresh`, `db:wipe`, `db:seed`, `DROP`, `TRUNCATE`, `DELETE`, or `ALTER` was run. The rate limiter uses the existing cache/`RateLimiter` infrastructure only.

---

## 10. Regression

| Prompt | Area | Result |
|---|---|---|
| Prompt 15 | Operator Dinas/Unit scope (own-unit visibility, unrouted-category mapping, null-unit denial, cross-unit 404, Super Admin global) | **PASS** |
| Prompt 16 | Last-active Super-Admin invariant (transaction + `FOR UPDATE` lock) | **PASS** |
| Prompt 17 | Complaint submission: 5 / 10 min per user, 429, daily limit 5/day | **PASS** |
| Prompt 19 | `auth.session` password-change session invalidation; `Asia/Makassar` timezone; secure-cookie config; `.env.example` hardening | **PASS** |

Full suite `php artisan test`: **528 passed / 0 failed / 0 skipped**. No Prompt 15–19 security test was modified or removed.

Additional invariant checks:

- complaint submission limit remains **5 / 10 minutes** (unchanged);
- daily complaint limit remains **5 / day** (unchanged);
- login key `email|ip`, threshold, window unchanged (D-5);
- D-2 operator scope untouched (§20);
- roles/statuses/transitions untouched;
- no email verification added (D-4);
- no new package installed; no `composer require`/`npm install`.

---

## 11. Findings Disposition

| Finding | Status | Notes |
|---|---|---|
| F-18-16 — login limit returns 422 | **REMEDIATED** | Now **429** with native `Retry-After`; policy unchanged (D-5) |
| F-18-21 — login key `email|ip` redesign | **DEFERRED (D-5)** | Policy change not approved → key intentionally unchanged |
| F-18-04 / F-18-15 — registration abuse | **DECISION REQUIRED** | No policy; nothing invented |
| F-18-04 / F-18-15 — forgot-password abuse | **DECISION REQUIRED** | No policy; nothing invented |
| Enumeration — forgot-password *request* | **PASS** | Generic response, indistinguishable outcomes |
| **F-20-01 (NEW)** — reset endpoint leaks user vs token status | **DECISION REQUIRED** | `PasswordResetController::update()` surfaces `passwords.user` / `passwords.token` via `withErrors`. Not redesigned (§8 forbids reset-flow redesign without scope). Pinned by characterization tests. |

### F-20-01 detail (new)

`POST /reset-password` returns `__('passwords.user')` for an unknown account and `__('passwords.token')` for a bad/expired token on an existing account. An attacker who already knows an email can therefore confirm account existence from the reset form, and can distinguish "no such account" from "bad token". This is a **response-content** difference, not a status-code difference.

- **In scope?** No. §8 explicitly says not to redesign the password-reset flow in Prompt 20.
- **Impact:** low-to-moderate account-existence oracle (email is already required to reach the endpoint; the request endpoint itself is safe).
- **Options to decide:** (a) collapse both outcomes into one generic message on the reset form; (b) leave as-is (argued acceptable if emails are not secret). **No change made pending approval.**

---

## 12. Files Changed

| File | Change |
|---|---|
| `app/Http/Requests/Auth/LoginRequest.php` | `ensureIsNotRateLimited()` throws `ThrottleRequestsException` (429 + native `Retry-After`) instead of `ValidationException` (422). Added `use` import. Policy code unchanged. |
| `resources/views/errors/429.blade.php` | Message resolution now prefers the explicit `$message` (complaint limiter) and falls back to `$exception->getMessage()` (login limiter, framework-rendered) then a generic default. No framework internals are rendered. |
| `rules.md` | Login rate-limit policy documented as unchanged with HTTP 429; registration + forgot-password recorded as `DECISION REQUIRED`; password-change note cross-references Prompt 20. |
| `architecture.md` | New §5.4 login flow; brute-force row updated with the 429 semantics and the decision gates. |
| `prd.md` | Login rate-limit note (F-005) + general rate-limit section updated with 429 semantics and the two decision gates. |
| `tests/Feature/LoginRateLimitTest.php` | **New** — 14 login rate-limit regression tests. |
| `tests/Feature/PasswordResetEnumerationTest.php` | **New** — 6 enumeration/characterization tests. |
| `PROMPT_20_AUTHENTICATION_ABUSE_PROTECTION_REPORT.md` | **New** — this report. |

---

## 13. Success Criteria

- [x] authentication endpoints audited (actual flow, not assumed);
- [x] login policy unchanged (key / threshold / window / message / flow);
- [x] login key `email|ip` unchanged;
- [x] login rate-limit response is now HTTP 429;
- [x] `Retry-After` is native (`RateLimiter::availableIn()`), not faked/hardcoded;
- [x] registration rate-limit implemented **only if** policy approved → not approved → not implemented (DECISION REQUIRED);
- [x] forgot-password rate-limit implemented **only if** policy approved → not approved → not implemented (DECISION REQUIRED);
- [x] password-reset enumeration behaviour verified (request = PASS; reset = gap documented);
- [x] no new package;
- [x] no migration;
- [x] no destructive database operation;
- [x] no email verification;
- [x] no D-2 change;
- [x] complaint rate limit remains 5 / 10 minutes;
- [x] daily complaint limit remains 5 / day;
- [x] Prompt 19 session hardening still works;
- [x] all Prompt 15–19 security regressions PASS;
- [x] build PASS;
- [x] test suite PASS (528 / 0 failed / 0 skipped);
- [x] final report states the unresolved decision gates honestly.

---

## 14. Final Verdict

```
NOT PRODUCTION READY
```

Prompt 20 closed the login HTTP-semantics finding (F-18-16) without touching the login policy (D-5), verified password-reset enumeration safety, and added 20 regression tests. **Production readiness is still not reached**, because:

- **Prompt 21** — operator lifecycle authorization (F-18-09 / D-2) is outstanding;
- **Prompt 23** — CI/CD, backup, monitoring, scheduler, RPO/RTO/alerting are outstanding;
- **Open decision gates from Prompt 20:** registration abuse policy, forgot-password abuse policy, reset-endpoint enumeration (F-20-01);
- **Earlier open items:** dependency CVE scan, deferred trusted-proxy (D-6), D-3 dependency-removal decision, and lower-priority findings (F-18-30 admin audit redaction, F-18-37 dead views, F-18-39 test CSRF gap);
- **Operational:** rotate the local-dev DB credential exposed in git history (Prompt 19).

> Authentication security was hardened **without inventing authentication business rules.**
