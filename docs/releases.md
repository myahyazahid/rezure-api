# Releases

Internal notes on the Releases feature — the dashboard's one write path, and the backend
for the public `GET /api/v1/version/latest` endpoint. For the public API contract itself,
see `../../api-documentation/telemetry-api.md`; this file covers what's specific to this
repo: the data model, the dashboard flow, and the open gaps.

## What it's for

Fase 6 of the roadmap (`../../docs/laravel-api/rezure-development-phases.md`) calls for an
update-checker: `rezureapp` asks the backend "what's the latest version?" and nudges the
user to update if they're behind. Before this feature there was nowhere to declare that —
the Versions dashboard page only shows *adoption* (what devices report having), not an
authoritative "this is current." Releases is that authority.

## Data model

`releases` (migration `2026_08_30_071321_create_releases_table.php`): `version`, `notes`
(nullable changelog text), `published_at`.

**One row per publish, not one row per version.** Publishing "1.4.0" twice — say, to fix a
typo in the changelog notes — creates a second row rather than overwriting the first.
"Current" is simply whichever row has the latest `published_at`:

```php
Release::current(); // -> latest('published_at')->first()
```

This was a deliberate simplification: there's no edit/delete UI for a published release
(see [Known gaps](#known-gaps)), so re-publishing is the only way to correct a mistake, and
that only works cleanly if `version` isn't unique. The tradeoff is a `releases` table that
can contain the same version string more than once — that's expected, not a bug, and
`Release::current()` is unaffected by it either way.

## Dashboard flow

`GET /dashboard/releases` (`Dashboard\ReleasesController::index`) shows the current release,
a publish form, and a paginated history table. `POST /dashboard/releases`
(`Dashboard\ReleasesController::store`, validated by `Http\Requests\Dashboard\PublishReleaseRequest`)
creates a new row with `published_at = now()` and redirects back with a flash message.

Publishing takes effect immediately — the next call to `GET /api/v1/version/latest`
(`Api\V1\VersionController`) reflects it, no cache to bust, no queue involved. Unlike the
telemetry ingestion endpoints, this is a low-volume, maintainer-triggered write, so there's
no reason to defer it through a job.

## Known gaps

- **No auth.** Same as the rest of the dashboard (see `CLAUDE.md`) — anyone who can reach
  `/dashboard/releases` can publish a release. This is the dashboard's first *write* path,
  which makes the missing auth guard a real gap here in a way it wasn't for the read-only
  pages. Worth prioritizing real auth before this dashboard is reachable outside a trusted
  network.
- **No edit or delete.** Publishing is append-only; fixing a mistake means publishing again
  with corrected notes, not editing history.
- **`notes` is free text.** No structured changelog format, no per-platform notes, no
  severity/urgency flag a client could use to force vs. suggest an update. Fine for now —
  add structure only when a real client update-flow needs it.

## Demo data

`database/seeders/TelemetryDemoSeeder.php` seeds four releases (`seedReleases()`) matching
the version spread already used for the devices/events demo data, so the Releases page and
the Versions adoption chart tell a consistent story in local dev. Not wired into
`DatabaseSeeder::run()` — run explicitly with
`php artisan db:seed --class=TelemetryDemoSeeder`.
