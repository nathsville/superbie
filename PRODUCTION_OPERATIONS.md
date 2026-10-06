# SuperBie - Lapor Pak Wali: Production Operations

**Status:** operational readiness record (Prompt 23).
**Applies to:** the Laravel monolith only (Blade + Alpine.js + Tailwind + Vite, MySQL).

This document is the single operational reference for running the application in
production. It deliberately separates three states so nothing is overstated:

| Tag | Meaning |
|---|---|
| **IMPLEMENTED** | Exists in the repository and is verified by tests / commands. |
| **REQUIRED** | A production prerequisite that the runtime/infrastructure must provide. It is not code in this repository, and this repository cannot satisfy it on its own. |
| **DECISION REQUIRED** | A policy or target that is **not defined anywhere** in the repository. It must be decided by the owner before production. No value has been invented. |

> **Anti-overengineering.** No infrastructure was introduced. There is no Docker,
> Kubernetes, Redis, Elasticsearch, Prometheus, Grafana, Sentry, New Relic,
> cloud provider SDK, Terraform, Ansible, Supervisor, systemd unit, or external
> cron provider in this repository. Anything of that kind is listed as
> **DECISION REQUIRED** and is only described generically.

---

## 1. Current operational inventory (evidence-based)

| Area | State | Evidence |
|---|---|---|
| Application server | **REQUIRED** - no server config is committed for production. | Only `docker/nginx/default.conf` exists (see 1.1). |
| Scheduler definition | **IMPLEMENTED** - one command scheduled. | `routes/console.php` -> `Schedule::command('complaints:purge-expired')->dailyAt('02:00')`. |
| Scheduler trigger | **DECISION REQUIRED** - the mechanism that runs `schedule:run` is not defined. | See section 3. |
| Queue connection | **IMPLEMENTED (config only)** - `database`. | `config/queue.php`, `.env.example` `QUEUE_CONNECTION=database`. |
| Queued jobs | **NONE** - the app dispatches no jobs. | `app/Jobs` absent; no `ShouldQueue`/`dispatch()` usage in `app/`. |
| Failed jobs table | **IMPLEMENTED (schema)** - `failed_jobs`, `jobs` tables exist. | `0001_01_01_000002_create_jobs_table.php`. |
| Queue worker | **REQUIRED only if a job is ever added** - none needed today. | See section 4. |
| Health endpoint | **IMPLEMENTED** - `GET /up`. | `bootstrap/app.php` `health: '/up'`. |
| Monitoring / alerting | **DECISION REQUIRED** - no monitoring, thresholds, or alert channels defined. | See section 5. |
| Application logging | **IMPLEMENTED** - `stack`/`single` file channel. | `config/logging.php`, `.env.example` `LOG_CHANNEL`/`LOG_STACK`. |
| Backup | **REQUIRED - not implemented.** | No backup script/command/schedule exists. See section 7. |
| Restore | **REQUIRED - not implemented.** | No restore procedure in the repo. See section 7. |
| CI | **IMPLEMENTED** - secret-free GitHub Actions workflow. | `.github/workflows/ci.yml`. |
| Deployment automation | **DECISION REQUIRED** - no deploy target is known. | See section 9. |
| Dependency advisories | **VERIFIED at Prompt 23** - none open. | `composer audit` and `npm audit` both clean. |

### 1.1 Orphaned `docker/nginx/default.conf`

A tracked file `docker/nginx/default.conf` exists (committed in `bf75450`) but
`SETUP_AND_DOCS.md` and `architecture.md` both state that **Docker is not the
runtime for this project**. The file is a leftover; it is not used, not
referenced by any compose file, and there is no `Dockerfile`/`docker-compose.yml`.
It is recorded here as a documentation/cleanup inconsistency (not deleted in
Prompt 23, to keep the change surgical). No Docker runtime was introduced.

---

## 2. Production environment configuration

Production runs from a real `.env` that is **never committed** (`.env` is
git-ignored). Create it from the committed template:

```bash
cp .env.example .env
php artisan key:generate
```

The committed `.env.example` is the template and already carries the hardened
defaults. The following **must hold in the production `.env`**:

| Key | Production value | Why |
|---|---|---|
| `APP_ENV` | `production` | Enables production code paths. |
| `APP_DEBUG` | `false` | Prevents stack traces / env leakage. |
| `APP_KEY` | generated, unique per environment | Encryption / session integrity. |
| `APP_URL` | real HTTPS URL | Correct URL generation. |
| `APP_TIMEZONE` | `Asia/Makassar` | Frozen decision D-1. |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `id` | Indonesian UI. |
| `LOG_LEVEL` | `warning` (recommended) | `debug` (template default) is too noisy for production. |
| `SESSION_SECURE_COOKIE` | `true` | Cookie only over HTTPS. |
| `SESSION_DRIVER` | `database` | Sessions survive restarts. |
| `CACHE_STORE` | `database` | No external cache (no Redis). |
| `QUEUE_CONNECTION` | `database` | See section 4. |
| `MAIL_MAILER` | (unchanged) | MVP sends no notifications. |
| `DB_*` | production MySQL | Real credentials, never committed. |

> **`APP_ENV=local` in `.env.example` is intentional.** The template is used for
> local bootstrap (`composer setup` copies it to `.env`); shipping
> `APP_ENV=production` there would break local setup. The production value is set
> in the production `.env`, not in the template.

Caches must be rebuilt after every deployment:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
# and, if the application is ever put into maintenance during deploy:
php artisan down --retry=60   # then: php artisan up
```

---

## 3. Scheduler

**IMPLEMENTED.** One scheduled task is defined:

| Command | Frequency | Purpose |
|---|---|---|
| `complaints:purge-expired` | daily at 02:00 (app timezone `Asia/Makassar`) | Permanent deletion of complaints 5 years after `submitted_at` (retention rule). |

The command supports `--dry-run` (report only) and `--force` (required to run
outside non-production environments).

**REQUIRED.** Laravel's scheduler does **not** run by itself. The host must invoke
it once per minute:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

**DECISION REQUIRED.** The concrete production mechanism is not defined anywhere
in the repository (there is no cron entry, no systemd timer, no platform
scheduler config, and no deployment target). Choose **one** and document it:

- system cron on the host;
- a platform scheduler (if the eventual host provides one);
- a container/PaaS scheduler (only if the deployment target turns out to be one).

Do not assume any of these. The purge frequency itself (02:00 daily) is a frozen
business rule and must **not** be changed.

Verify the schedule is registered with:

```bash
php artisan schedule:list
```

---

## 4. Queue and failed jobs

**Current reality:** the application dispatches **no jobs**. `QUEUE_CONNECTION`
is `database`, the `jobs` and `failed_jobs` tables exist, but nothing is queued
(retention runs as a synchronous console command, not a job).

Therefore:

- **No queue worker is required today.**
- **REQUIRED, only if a queued job is ever added:** a long-running worker such as
  `php artisan queue:work --tries=3` must be kept alive by the host. The
  keep-alive mechanism (process supervisor / platform worker / systemd) is a
  **DECISION REQUIRED** item tied to the (unknown) deployment target.
- **REQUIRED, only if jobs are ever added:** a periodic `php artisan queue:retry`
  / failed-job review, and `queue:prune-failed` housekeeping.

Do **not** add a worker speculatively: with zero jobs it is unused infrastructure.

---

## 5. Health and monitoring

**IMPLEMENTED - health endpoint.** `GET /up` is registered by
`bootstrap/app.php` (`health: '/up'`). It returns `200` when the application can
boot and `500` on a fatal boot error. It is exempt from maintenance mode and
returns no sensitive data.

Use it as the liveness/readiness probe:

```bash
curl -fsS https://<host>/up    # expect HTTP 200
```

**DECISION REQUIRED - monitoring and alerting.** The repository defines **no**
monitoring stack, **no** metric thresholds, **no** alert channels, and **no**
on-call process. Do not invent them. The owner must decide, at minimum:

| Question | Status |
|---|---|
| What is monitored (uptime, 5xx rate, DB availability, disk, scheduled-job success)? | **DECISION REQUIRED** |
| What thresholds trigger an alert? | **DECISION REQUIRED** |
| Where do alerts go (email, chat, ticket)? | **DECISION REQUIRED** |
| Who is on call, and what is the escalation path? | **DECISION REQUIRED** |
| Is an external APM/SaaS tooling approved? (Not present; `architecture.md` calls it optional.) | **DECISION REQUIRED** |

**Minimum manual baseline (no tooling, no invented numbers):**

- Watch the Laravel log (`storage/logs/laravel.log`) and the web server access log.
- Poll `GET /up`.
- Confirm the daily `complaints:purge-expired` run appears in the schedule log.

---

## 6. Logging and secret hygiene

**IMPLEMENTED.**

- Logging uses Laravel's `stack` -> `single` file channel (`config/logging.php`).
- Application code does not log complaint contents, passwords, tokens, or
  personal data. The only `Log::` calls in `app/` are retention file-cleanup
  errors that log `complaint_id` / `attachment_id` / the exception message only
  (`app/Services/ComplaintRetentionService.php`).
- `.env` is git-ignored; `phpunit.xml` contains **no** DB credentials
  (verified by `EnvironmentHardeningTest`).

**REQUIRED.**

- Keep `LOG_LEVEL=warning` (or `error`) in production; never `debug`.
- Ensure `storage/logs` is writable and rotated by the host (no log-rotation
  policy is defined here -> **DECISION REQUIRED** if retention of logs matters).
- `APP_DEBUG=false` so exception pages never leak config.

**ROTATION REQUIRED - credential exposed in git history.** A real local-dev
database credential was committed in the initial commit `bf75450` (inside
`phpunit.xml`; since removed from the working tree by Prompt 19). The value is
**permanently present in git history** and must be treated as compromised:

1. Change that MySQL user's password on the actual database server.
2. Do **not** rewrite git history to "remove" it (history rewrite is forbidden
   and unnecessary once the credential is rotated).
3. Going forward, keep test credentials only in the git-ignored `.env`.

---

## 7. Backup and restore

**Status: REQUIRED - not implemented.**

There is **no** backup script, **no** artisan backup command, **no** scheduled
backup, and **no** restore procedure in the repository. Nothing about backup is
automatic today.

### 7.1 What must be protected

| Asset | Where | Notes |
|---|---|---|
| MySQL database | `DB_DATABASE` | Complaints, users, audit logs, settings. Primary asset. |
| Complaint attachments | Laravel `local` disk (`storage/app/private`) | Files are deleted by retention; a DB-only backup would orphan or lose them. |
| Application `.env` | server only | Contains `APP_KEY`; without it encrypted data/sessions are unrecoverable. Store in a secret manager, not in the DB backup. |
| Built assets | `public/build` | Reproducible from source via `npm run build`; no backup needed. |

### 7.2 Backup capability

A database backup can be produced with the MySQL client that ships with the
runtime (`mysqldump`). This is a **capability**, not a configured policy:

```bash
mysqldump --single-transaction --routines --triggers \
  -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_DATABASE" > superbie-$(date +%F).sql
```

Attachments must be archived separately (e.g. an archive of
`storage/app/private`) so a restore is complete.

### 7.3 DECISION REQUIRED - backup policy

None of the following is defined anywhere; do **not** invent it:

| Question | Status |
|---|---|
| Backup frequency (hourly / daily / weekly)? | **DECISION REQUIRED** |
| Retention of backups (how many, how long)? | **DECISION REQUIRED** |
| Destination (local disk / network share / off-site)? | **DECISION REQUIRED** |
| Encryption at rest of backups? | **DECISION REQUIRED** |
| Who owns and monitors backup success? | **DECISION REQUIRED** |
| **RPO** (max acceptable data loss)? | **DECISION REQUIRED** |
| **RTO** (max acceptable downtime)? | **DECISION REQUIRED** |

### 7.4 DECISION REQUIRED - restore procedure

No restore procedure is defined. A production restore is a **manual, owner-
approved** operation and must never be executed by tooling from this repository
(restoring into production is forbidden here). A future restore must:

1. Restore the database into a **non-production** target first and validate.
2. Restore attachments consistently with the database snapshot.
3. Be rehearsed on a non-production environment before launch (`prd.md` requires
   a tested restore before MVP sign-off).

### 7.5 Restore readiness

`php artisan migrate:status` reports `2025_01_01_100008` as **Pending** by design
(it is intentionally not applied). A restore target must therefore end up with
the same schema state as the source snapshot; the restore validation must
reproduce the exact migration state, not a "latest migrations" assumption.

---

## 8. Continuous integration

**IMPLEMENTED.** `.github/workflows/ci.yml` runs on every push to `main` and on
every pull request. It is intentionally minimal and **deployment-target
independent**:

1. checkout;
2. install PHP 8.4 + extensions;
3. install Node;
4. `composer install`;
5. copy `.env.example` -> `.env`, `php artisan key:generate`;
6. `npm ci`;
7. `npm run build`;
8. `php artisan migrate --force` against an **ephemeral MySQL service container**
   (`superbie_testing`, port 3306);
9. `php artisan test`;
10. `composer audit` + `npm audit` (informational only, non-blocking).

Guarantees:

- **No deployment step** and **no production secrets**. CI never touches a
  production system or database; the DB is a throwaway container.
- CI injects `DB_PORT=3306` etc. as real environment variables, which override
  the local-dev defaults (`3307`) in `phpunit.xml` and `.env`.
- CI **does not** run `migrate:fresh`/`db:wipe`/`db:seed`; it only migrates the
  throwaway CI database.

**DECISION REQUIRED.** Whether the project's real repository is GitHub (and thus
whether this workflow is active as-is) is not determinable from the SuperBie
sources: the local git `origin`/`upstream` point at an unrelated project
(`superpowers`). The workflow is written for GitHub Actions because GitHub is
the only hosting evidence; if the production repository differs, move the
workflow to that platform (it has no GitHub-specific deployment coupling).

---

## 9. Deployment

**DECISION REQUIRED - the deployment target is unknown.**

There is no deployment automation, and none is created here: creating deploy
automation for an unknown target would be guesswork and is explicitly out of
scope.

| Question | Status |
|---|---|
| Hosting target (VPS / shared host / PaaS / on-prem)? | **DECISION REQUIRED** |
| Web server (nginx / Apache) and TLS termination? | **DECISION REQUIRED** |
| Deployment method (git pull / artifact / CI deploy)? | **DECISION REQUIRED** |
| Zero-downtime strategy or maintenance window? | **DECISION REQUIRED** |
| Reverse proxy / trusted proxies? | **DECISION REQUIRED** (see 9.1) |

### 9.1 Trusted proxies (carried over: D-6)

No `trustProxies` configuration exists and none was added: doing so without
deployment evidence would be a guess about the network topology. If the
production host sits behind a reverse proxy / load balancer, trusted proxies
must be configured **at deploy time** based on the actual proxy addresses.

### 9.2 Deployment checklist (manual, owner-driven)

- [ ] Production `.env` set (section 2); `APP_DEBUG=false`, `APP_ENV=production`.
- [ ] `php artisan migrate --force` reviewed (no destructive migrations).
- [ ] A fresh backup taken immediately before migrating (section 7).
- [ ] `npm run build` executed; `public/build` present.
- [ ] `php artisan config:cache route:cache view:cache` executed.
- [ ] `storage` and `bootstrap/cache` writable.
- [ ] HTTPS enforced; `SESSION_SECURE_COOKIE=true`.
- [ ] `GET /up` returns `200`.
- [ ] Super Admin account provisioned securely (never a default password).
- [ ] Scheduler trigger installed (section 3).
- [ ] Logs reachable and monitored (sections 5-6).

---

## 10. Incident / recovery runbook

> Generic, tool-agnostic steps only. Specific tools and thresholds are
> **DECISION REQUIRED** (section 5).

| Situation | First actions |
|---|---|
| `GET /up` returns 500 | Check `storage/logs/laravel.log`; check MySQL reachability; check `bootstrap/cache` writability; roll back the last deploy if it correlates. |
| Site unreachable | Check web server + PHP-FPM/host health; check DB; check disk space; use `php artisan down`/`up` if a controlled window is needed. |
| Data corruption / bad migration | Stop writes (`php artisan down`); restore from the most recent backup into a **non-production** target first, validate, then follow the owner-approved restore procedure. |
| Retention purge failed | Inspect the command output/log; it is idempotent and safe to re-run; `--dry-run` first. It never rolls back committed DB deletions over a file error. |
| Suspected credential leak | Rotate the credential; rotate `APP_KEY` only with a planned session reset (it invalidates encrypted sessions). |

---

## 11. Decision register (Prompt 23)

| ID | Decision | Status |
|---|---|---|
| P23-D1 | Production hosting / deployment target | **DECISION REQUIRED** |
| P23-D2 | Scheduler trigger mechanism (cron/systemd/platform) | **DECISION REQUIRED** |
| P23-D3 | Backup frequency, retention, destination, encryption | **DECISION REQUIRED** |
| P23-D4 | **RPO** and **RTO** targets | **DECISION REQUIRED** |
| P23-D5 | Restore procedure ownership + rehearsal environment | **DECISION REQUIRED** |
| P23-D6 | Monitoring scope, alert thresholds, channels, on-call | **DECISION REQUIRED** |
| P23-D7 | Log retention / rotation policy | **DECISION REQUIRED** |
| P23-D8 | Whether the production repo is GitHub (CI platform) | **DECISION REQUIRED** |
| P23-D9 | Queue worker keep-alive (only if a job is ever added) | **DECISION REQUIRED (conditional)** |
| P23-D10 | Trusted proxies / reverse-proxy config (carried from D-6) | **DECISION REQUIRED (deploy-time)** |

---

## 12. Explicitly out of scope (anti-overengineering)

The following were **not** introduced, because there is no requirement or
evidence for them: Docker/Compose, Kubernetes, Redis, Elasticsearch,
Prometheus, Grafana, Sentry, New Relic, any cloud provider (AWS/GCP/Azure),
Terraform, Ansible, Supervisor, systemd units, external cron providers, and any
deployment pipeline for an unknown target. If any of these is ever needed, it
must be raised as an explicit decision first.
