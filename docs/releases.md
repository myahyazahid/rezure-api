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

`releases` (migration `2026_08_30_071321_create_releases_table.php`, plus
`2026_09_15_042915_add_signature_and_download_url_to_releases_table.php`): `version`,
`notes` (nullable changelog text), `signature` and `download_url` (both nullable — the
Tauri updater manifest's `windows-x86_64` entry; see the API contract doc), `published_at`.

A release published without `signature`/`download_url` is still valid — it shows up in the
dashboard, `/changelog`, and `/version/latest`'s plain `version`/`notes` fields — it's just
never offered as an auto-update (`VersionController` returns `platforms: {}` for it).

**Versions are `MAJOR.MINOR.PATCH`, nothing else** (`Release::VERSION_PATTERN`, enforced
by `PublishReleaseRequest`): `3.0.1`, never `v3.0.1` or `V.3.0.1`. The client's
`tauri-plugin-updater` can't parse anything else, and the major is what places a release
in its line. The `v` prefix belongs on git tags only.

**Major lines are maintained side by side.** A new major (4.0.0) is a big release; minor
and patch releases (3.0.1, 3.1.0) are fixes and small additions within a line. Each line
lives on its own git branch (`v3`, `v4`, ...) in `rezureapp`, and a client is only ever
offered releases from its own line: a 3.x install gets 3.x updates and never auto-updates
to 4.0. That's decided from the `current_version` the client sends, so there's no column
for it; the major is read off the version string (`Release::majorOf()`).

**"Current" is the highest version, not the latest publish:**

```php
Release::current(3); // newest 3.x release
Release::current();  // newest release of any line
Release::currentPerLine(); // newest of each line, highest line first (dashboard)
```

Ordering by `published_at` would break as soon as two lines are live. A 3.0.2 hotfix
published after 4.0.0 would become the release everyone is offered.

**One row per publish, not one row per version.** Publishing "3.0.1" twice — say, to fix a
typo in the changelog notes — creates a second row rather than overwriting the first.
Between two rows of the same version, the later `published_at` wins.

This was a deliberate simplification: there's no edit/delete UI for a published release
(see [Known gaps](#known-gaps)), so re-publishing is the only way to correct a mistake, and
that only works cleanly if `version` isn't unique. The tradeoff is a `releases` table that
can contain the same version string more than once. That's expected, not a bug.

## Upgrade notice

Because the updater never crosses lines, a 3.x user wouldn't otherwise learn that 4.0 is
out. `upgrade_notices` (single row, like `donate_configs`; `App\Models\UpgradeNotice`)
holds one announcement: `enabled`, `major`, `message`, `url`. `GET /api/v1/version/upgrade`
(`Api\V1\UpgradeNoticeController`) returns it to clients whose `current_version` is on a
lower major, and `204` to everyone else. `rezureapp` shows it as a banner on its Changelog
page, linking to `url` in the browser. It's a link to the website, never an installer:
moving to a new major stays the user's decision.

It starts switched off, and its fields can be filled in while it's off, so the text can be
drafted before a major ships and switched on whenever the maintainer is ready (e.g. once
4.0.1 has shaken out the first bugs). `url` is limited to http(s), since the client opens
it in the system browser.

## Dashboard flow

`GET /dashboard/releases` (`Dashboard\ReleasesController::index`) shows the current release
of each line, the upgrade notice form, a paginated history table, and a publish form.
`POST /dashboard/releases` (`Dashboard\ReleasesController::store`, validated by
`Http\Requests\Dashboard\PublishReleaseRequest`) creates a new row with
`published_at = now()` and redirects back with a flash message.
`PUT /dashboard/releases/upgrade-notice` (`updateUpgradeNotice`, validated by
`UpdateUpgradeNoticeRequest` into its own `upgradeNotice` error bag) saves the notice.

Both take effect immediately — the next call to `GET /api/v1/version/latest`
(`Api\V1\VersionController`) or `/version/upgrade` reflects it, no cache to bust, no queue
involved. Unlike the
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
