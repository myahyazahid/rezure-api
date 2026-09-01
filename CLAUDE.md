IMPORTANT to read

dokumentasikan api untuk digunakan rezure app, jadi dokumentasikan api nya ke folder C:\repository\rezure\api-documentation bisa pakai format .md atau yang lainnya

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

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
