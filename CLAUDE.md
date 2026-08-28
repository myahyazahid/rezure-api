<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>

# CLAUDE.md

This file provides guidance to Claude (and other AI coding agents) when working on the Rezure Dashboard / API codebase.

---

## Project Overview

This is the **Laravel API + Dashboard** for **Rezure**, a Tauri-based desktop local dev environment manager (see the companion `rezure` desktop app repo). This backend serves two purposes:

1. **Telemetry ingestion API** — receives usage events/heartbeats from Rezure desktop clients
2. **Dashboard** — internal-facing analytics UI to monitor active users, feature usage, errors, and version adoption

This is a **supporting/internal service**, not the product itself — prioritize reliability of ingestion (never lose or block on client events) and clarity of the data model over UI polish.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel |
| Database | MySQL/MariaDB (match production; SQLite acceptable for local dev) |
| Queue | Laravel Queue (database or Redis driver) |
| Auth (API) | Per-device API key (lightweight — not full user auth) |
| Frontend (Dashboard) | Blade + lightweight charting (Chart.js/ApexCharts) or Vue, depending on what's scaffolded — check `resources/` before assuming |

---

## Core Domain Concepts

- **Device**: a single Rezure installation, identified by a client-generated `device_id` (UUID). Not tied to a user account — this is anonymous usage telemetry, not a login system.
- **Event**: a discrete action reported by a client (e.g. `app_opened`, `service_started`, `error_reported`)
- **Session/Heartbeat**: periodic pings while the app is active, used to derive session duration and active-user counts
- **Version**: the Rezure app version reporting the event — used for version adoption tracking

See [`docs/data-model.md`](docs/data-model.md) if present, or the latest migrations in `database/migrations/` for the current authoritative schema.

---

## Architectural Principles (non-negotiable)

1. **Ingestion must never block or fail loudly on the client's behalf.** Telemetry endpoints should validate quickly, then push to a **queued job** for actual processing/storage. Don't do heavy DB writes synchronously in the request cycle.
2. **Idempotency matters.** Clients may retry sends after being offline — use `firstOrCreate`/unique composite keys (mirroring the existing WhatsApp/email notification dedup pattern from the vision-stc project) to avoid duplicate events skewing analytics.
3. **Auth is lightweight by design.** Devices are identified by API key/`device_id`, not full user accounts. Don't introduce a heavier auth system (e.g. Sanctum user login) for the ingestion endpoints without discussing — the dashboard/admin side may warrant real auth, the ingestion side should stay simple.
4. **Rate limit ingestion endpoints.** These are public-facing (any installed client can hit them) — apply Laravel's rate limiting to prevent abuse.
5. **Privacy-conscious by default.** Only store what's needed for the stated analytics purpose (usage patterns, errors, version adoption). Don't add new PII collection without confirming it's actually needed — clients have an opt-out toggle for telemetry sharing, and the backend should honor "no data" gracefully, not error.
6. **Dashboard queries should not run against live ingestion tables unindexed.** Add indexes deliberately for whatever the dashboard aggregates by (device, event_type, created_at, version) — analytics queries tend to scan large ranges.

---

## Coding Conventions

- Follow standard Laravel conventions (PSR-12, `php artisan pint` if configured)
- Business logic for ingestion/aggregation belongs in **Jobs** and **Services**, not fat controllers
- Use **Form Requests** for validating incoming telemetry payloads — don't validate inline in the controller
- Migrations are the only way schema changes happen — no manual DB edits
- Write Eloquent queries with intention — avoid N+1s in dashboard aggregation views (`with()`/`withCount()` where relevant)

### Commits
Follow [Conventional Commits](https://www.conventionalcommits.org/): `feat:`, `fix:`, `docs:`, `refactor:`, `test:`, `chore:`

---

## What NOT to Do

- Don't process telemetry synchronously in the request — always queue
- Don't add full user-authentication to client-facing ingestion endpoints — that's not what devices are
- Don't collect additional device/user data beyond what's defined in the current event schema without confirming scope first
- Don't write raw SQL for dashboard charts when Eloquent/query builder is sufficient — keep queries reviewable
- Don't couple this repo's API contract changes to the desktop app without checking `rezure`'s `docs/roadmap.md` for what version expects what payload shape

---

## Useful Commands

```bash
php artisan serve            # run local dev server
php artisan migrate          # run migrations
php artisan queue:work       # process queued telemetry jobs (required for ingestion to actually persist)
php artisan test             # run test suite
./vendor/bin/pint            # format PHP (if configured)
```

---

## When Making Changes

1. Check whether the change affects the **API contract** with the Rezure desktop client — if so, confirm version compatibility (older clients may still send the old payload shape)
2. Keep ingestion endpoints fast and queue-backed — don't add synchronous work to the hot path
3. Add/update indexes if a change affects how the dashboard aggregates data
4. Run `php artisan test` and `./vendor/bin/pint` before considering a task done
5. If a change requires deviating from a principle above, explain why in the PR description