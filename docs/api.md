# Rezure Telemetry API

The full request/response contract for this API — auth, rate limiting, idempotency,
`/telemetry/heartbeat`, `/telemetry/event`, `/version/latest`, error shapes — lives at
`../../api-documentation/telemetry-api.md`, shared with `rezureapp` (the client that
consumes it). Read that file, not this one, for the actual contract.

This file stays as a short pointer rather than a second copy so the two can't drift apart —
update `../../api-documentation/telemetry-api.md` in the same change that changes a
request/response shape, and keep it in sync with the code:

- Routes: `routes/api.php`
- Validation: `app/Http/Requests/Telemetry/HeartbeatRequest.php`,
  `app/Http/Requests/Telemetry/EventRequest.php`
- Processing: `app/Jobs/ProcessHeartbeatJob.php`, `app/Jobs/ProcessEventJob.php`,
  `App\Services\DeviceRegistrar`
- Schema: `devices`, `events`, `device_sessions`, `releases` migrations under
  `database/migrations/`
- Rate limiting: the `api` limiter in `app/Providers/AppServiceProvider.php`

The public website's analytics endpoints (`POST /site/hit`, `GET /site/download`, v4) have
their own contract at `../../api-documentation/website-analytics-api.md`, kept in sync with:

- Validation: `app/Http/Requests/Site/SiteHitRequest.php`
- Processing: `app/Jobs/ProcessSiteHitBatchJob.php`, `app/Jobs/RecordSiteDownloadJob.php`,
  `App\Services\WebAnalytics\SiteHitProcessor`
- Schema: the `web_*` migrations; settings in `config/web-analytics.php`
- Rate limiting: the `site-hits` limiter in `app/Providers/AppServiceProvider.php`

`/version/latest` is backed by the Releases dashboard feature — see
[`releases.md`](releases.md) in this folder for how a release actually gets published (it's
the one write path in an otherwise read-only dashboard, which is why it gets its own doc).
