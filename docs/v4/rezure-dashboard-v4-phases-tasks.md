# Rezure Dashboard (Laravel) — v4: Website Analytics

Roadmap fitur **analytics untuk website Rezure** (`rezure.redscale.my.id`, repo `rezure_websites`). Sistemnya first-party dan self-hosted di API ini, dengan dashboard lengkap setara Google Analytics / Matomo / Plausible yang disesuaikan untuk Rezure. Pertanyaan yang dijawab: dari mana pengunjung datang, halaman apa yang mereka baca, siapa yang download, dan berapa yang akhirnya benar-benar memakai aplikasinya.

> **Status:** diimplementasikan 2026-10-24 (Fase 4.1–4.12, sebagian 4.13). Task yang masih terbuka ditandai `[ ]`. Lihat juga "Catatan Implementasi" di akhir dokumen untuk hal yang berbeda dari rencana awal.

---

## Keputusan yang Sudah Diambil

| Topik | Keputusan | Alasan |
|---|---|---|
| Privasi | Tanpa pembatasan: semua data pengunjung boleh dikumpulkan (lihat bagian berikut) | Keputusan owner, 2026-10-23 |
| Parser user-agent | `matomo/device-detector` | Paling akurat untuk browser/OS/device/brand/model, sekaligus mendeteksi ±1.000 jenis bot |
| Peta | `chartjs-chart-geo` + `world-atlas` | Ini plugin Chart.js, jadi warna dan dark mode ikut sistem `themeColor()` yang sudah ada di `resources/js/app.js`. Mendukung choropleth negara dan bubble map kota (dari lat/lon) |
| Retensi | Data mentah disimpan 13 bulan (400 hari), rekap harian selamanya | 13 bulan cukup untuk perbandingan year-over-year lengkap dengan filter. Rekap ukurannya kecil, jadi aman disimpan selamanya |
| Cara tracking | First-party dan self-hosted, tanpa Google Analytics atau layanan pihak ketiga lain | Data milik sendiri dan bisa di-join dengan telemetry desktop serta tabel `blogs`/`releases` |
| Geolocation | Tetap ip-api gratis (bukan MaxMind) | Keputusan owner 2026-10-24: pengunjung masih sedikit. Evaluasi ulang kalau lokasi mulai banyak yang kosong (batas 45 request/menit) |
| User flow | `chartjs-chart-sankey` | Disetujui 2026-10-24 |
| Atribusi website → app | Dibuat: `devices.first_seen_ip` + `app:attribute-web-installs` | Disetujui 2026-10-24 |
| Web Vitals | Paket `web-vitals` di repo website | Disetujui 2026-10-24 |

## Kebijakan Data (Pengecualian dari Prinsip Privasi v3)

Prinsip privasi di v3 (lokasi cukup negara, IP tidak disimpan) dan poin 5 CLAUDE.md **tidak berlaku** untuk website analytics. Di domain ini yang disimpan meliputi: IP mentah, visitor ID persisten (cookie + localStorage), lokasi sampai kota dan koordinat, user-agent lengkap, serta URL lengkap beserta query string.

- Pengecualian ini **hanya berlaku untuk tabel `web_*`**. Telemetry desktop (`devices`, `events`, `device_sessions`) tetap mengikuti aturan v3, kecuali task opsional di Fase 4.12 yang butuh konfirmasi tersendiri.
- Catatan risiko (bukan blocker): IP ditambah ID persisten tergolong data pribadi menurut GDPR (pengunjung dari Uni Eropa) dan UU PDP No. 27/2022 (Indonesia). Minimal yang disarankan adalah halaman Privacy Policy di website yang menjelaskan data apa saja yang dikumpulkan. Banner consent tidak dibuat kecuali diminta.

---

## Arsitektur

```
WEBSITE (rezure_websites, VitePress)
  .vitepress/theme/analytics/  ── batch via sendBeacon ──▶  POST /api/v1/site/hit
  link download                ── navigasi ──────────────▶  GET  /api/v1/site/download ──302──▶ installer

API (laravel-api)
  POST /site/hit → SiteHitRequest (validasi cepat) → 202
                 → ProcessSiteHitBatchJob  [queue "web"]
                     ├─ bot?          → web_bot_hit_summary (hanya hitungan)
                     ├─ parse UA      → matomo/device-detector (cache per UA)
                     ├─ geolocation   → GeolocationResolver::resolveLocation() (cache per IP)
                     ├─ klasifikasi   → TrafficSourceClassifier (channel & source)
                     └─ simpan        → web_visitors, web_sessions, web_pageviews,
                                        web_events, web_clicks, web_errors
  Scheduler      → app:generate-web-summaries (D-1 & D-2) → web_*_summary
                 → model:prune (retensi 400 hari)
  Dashboard      → /dashboard/website/* (Blade + Chart.js)
```

Pola yang ditiru dari kode yang sudah ada:
- Validasi di Form Request, penyimpanan di job antre, respons 202. Sama dengan `EventController` / `ProcessEventJob`.
- Idempotensi lewat UUID dari client + unique key (`createOrFirst`). Sama dengan `events.client_event_id`.
- Rekap harian yang aman di-run ulang (upsert). Sama dengan `GenerateTrafficSummaries`.
- Controller dashboard invokable + view Blade + Chart.js dengan warna dari token CSS.
- Queue `web` dipisah supaya lonjakan traffic website tidak menunda telemetry desktop di queue `default`.

---

## Data yang Dikumpulkan

| Kategori | Data | Sumber |
|---|---|---|
| Identitas | `visitor_id` (UUID, disimpan di cookie `_rz_vid` 2 tahun + localStorage), `session_id` (berakhir setelah 30 menit tidak aktif atau saat UTM berubah), nomor kunjungan, tanggal kunjungan pertama | tracker |
| Jaringan | IP mentah, ISP/ASN (opsional, lihat Keputusan Terbuka #1) | server |
| Lokasi | Dari IP: negara, region, kota, kode pos, lat/lon, timezone. Dari browser: timezone dan bahasa | server + tracker |
| Perangkat | Dari UA: browser + versi + engine, OS + versi, device type, brand, model. Dari browser: resolusi layar, viewport, pixel ratio, CPU cores, memory, touch, tipe koneksi (4g/wifi), preferensi dark/light OS, tema yang dipilih di website | server + tracker |
| Halaman | URL lengkap, path, query string, hash, title, referrer lengkap, urutan halaman dalam sesi | tracker |
| Sumber | Referrer host, UTM (source/medium/campaign/term/content), click ID (gclid, fbclid, msclkid), channel hasil klasifikasi | tracker + server |
| Engagement | Waktu aktif per halaman (hanya dihitung saat tab terlihat dan ada interaksi dalam 30 detik terakhir), scroll depth maksimum, milestone scroll 25/50/75/90/100 | tracker |
| Klik | Semua klik: posisi (x dalam % lebar, y dalam px), lebar viewport, CSS selector, tag, teks elemen, penanda rage click | tracker |
| Event | Download, outbound link, CTA, copy code, pencarian docs (teks query), 404, blog dibaca sampai habis, ganti tema, error JS | tracker |
| Performa | LCP, INP, CLS, FCP, TTFB, DOM load, page load | tracker |
| Bot | Nama dan kategori bot. Hanya disimpan sebagai hitungan harian dan tidak masuk statistik utama | server |

Catatan: Safari (ITP) membatasi umur cookie/localStorage yang ditulis JavaScript menjadi 7 hari. Akibatnya, data returning visitor dari Safari kurang akurat untuk jeda kunjungan lebih dari seminggu.

---

## Kontrak Payload

Satu request berisi satu batch hit dari satu tab. Body dikirim sebagai `text/plain` supaya `sendBeacon` tidak memicu preflight CORS; isinya JSON. CORS default Laravel (`api/*`, semua origin) sudah cukup.

```json
{
  "v": 1,
  "visitor_id": "6f1c…",
  "session_id": "a83e…",
  "sent_at": "2026-10-23T08:00:05.120Z",
  "context": {
    "is_new_visitor": true, "visit_number": 1, "first_visit_at": "2026-10-23T08:00:00Z",
    "screen": [1920, 1080], "viewport": [1440, 900], "dpr": 1.5,
    "language": "id-ID", "languages": ["id-ID", "en-US"], "timezone": "Asia/Jakarta",
    "color_scheme": "dark", "site_theme": "dark",
    "connection": "4g", "cpu_cores": 8, "memory_gb": 8, "touch_points": 0
  },
  "hits": [
    { "type": "pageview", "id": "…", "pageview_id": "p1", "at": "…", "url": "https://rezure.redscale.my.id/guide/installation?utm_source=reddit", "title": "Installation | Rezure", "referrer": "https://www.reddit.com/r/laravel/…" },
    { "type": "event", "id": "…", "pageview_id": "p1", "at": "…", "name": "download_click", "label": "3.1.0", "props": { "asset": "Rezure_3.1.0_x64-setup.exe" } },
    { "type": "click", "id": "…", "pageview_id": "p1", "at": "…", "x_pct": 42.5, "y_px": 1310, "page_height": 4200, "viewport_width": 1440, "selector": "a.download-btn", "text": "Download for Windows", "is_rage": false },
    { "type": "vitals", "id": "…", "pageview_id": "p1", "at": "…", "lcp_ms": 1840, "inp_ms": 96, "cls": 0.02, "fcp_ms": 900, "ttfb_ms": 210, "dom_load_ms": 1200, "page_load_ms": 2100 },
    { "type": "error", "id": "…", "pageview_id": "p1", "at": "…", "message": "…", "source": "…", "line": 12, "column": 8, "stack": "…" },
    { "type": "engagement", "id": "…", "pageview_id": "p1", "at": "…", "engaged_ms": 48200, "max_scroll_pct": 87, "scroll_milestones": [25, 50, 75] }
  ]
}
```

- Setiap hit punya `id` berupa UUID untuk idempotensi, jadi retry tidak membuat duplikat.
- Server mengoreksi jam client dengan rumus `occurred_at = received_at − (sent_at − at)`. Hit yang waktunya lebih dari 7 hari lalu atau ada di masa depan dibuang.
- IP, User-Agent, dan `Accept-Language` diambil server dari request, bukan dari payload.

---

## Model Data

Semua timestamp disimpan dalam UTC. Rekap per tanggal memakai `app.display_timezone` (Asia/Jakarta) supaya batas "hari ini" sama dengan yang terlihat di dashboard. Ini sengaja dibuat berbeda dari rekap desktop (`hourly_traffic_summary`) yang memakai tanggal UTC.

### Tabel mentah

| Tabel | Kolom utama | Unique / Index |
|---|---|---|
| `web_visitors` | `visitor_uuid`, first/last_seen_at, sessions_count, pageviews_count, first-touch (landing path, referrer host, channel, utm_*), data terakhir (ip, country, city, browser, os, device_type), first_download_at, `device_id` (nullable, Fase 4.12) | unique `visitor_uuid`; index `first_seen_at`, `last_seen_at` |
| `web_sessions` | `session_uuid`, `web_visitor_id`, started/ended_at, duration & engaged seconds, jumlah pageview/event, is_engaged, is_bounce, has_conversion, entry/exit path, referrer url/host, channel, source, utm_*, click_id, ip, country/region/city/postal/lat/lon/geo_timezone, browser/version/engine, os/version, device type/brand/model, screen, viewport, dpr, language(s), timezone, color_scheme, site_theme, connection, cpu/memory, is_new_visitor, visit_number | unique `session_uuid`; index `started_at`, `(web_visitor_id, started_at)`, `(channel, started_at)`, `(country_code, started_at)`, `(device_type, started_at)`, `(browser, started_at)`, `(utm_campaign, started_at)` |
| `web_pageviews` | `pageview_uuid`, session, visitor, url, path, query_string, hash, title, referrer_url, sequence, occurred_at, engaged_ms, max_scroll_pct, is_entry, is_exit, is_not_found, lcp/inp/cls/fcp/ttfb, dom_load_ms, page_load_ms | unique `pageview_uuid`; index `occurred_at`, `(path, occurred_at)`, `(web_session_id, sequence)` |
| `web_events` | `event_uuid`, session, visitor, pageview, name, label, props (JSON), path, occurred_at | unique `event_uuid`; index `(name, occurred_at)`, `(name, label, occurred_at)` |
| `web_clicks` | `click_uuid`, pageview, path, x_pct, y_px, page_height, viewport_bucket, selector, tag, text, is_rage, occurred_at | unique `click_uuid`; index `(path, viewport_bucket, occurred_at)` |
| `web_errors` | `error_uuid`, session, pageview, fingerprint, message, source, line, column, stack, path, browser, occurred_at | unique `error_uuid`; index `(fingerprint, occurred_at)` |
| `web_goals` | name, type (`event` / `pageview`), match (nama event atau pola path dengan wildcard), label_match, is_active | — |
| `web_funnels` | name, steps (JSON berisi urutan step `event`/`pageview` + match), scope (`session` / `visitor`), window_hours | — |

### Tabel rekap

Penamaan mengikuti tabel yang sudah ada, `hourly_traffic_summary` / `country_traffic_summary`.

| Tabel | Isi | Unique |
|---|---|---|
| `web_daily_summary` | visitors, new_visitors, sessions, engaged_sessions, bounces, pageviews, total_duration, total_engaged, downloads, conversions | `date` |
| `web_hourly_summary` | visitors, sessions, pageviews | `(date, hour)` |
| `web_daily_page_summary` | pageviews, visitors, entries, exits, bounces, total_engaged_ms, avg_scroll, not_found | `(date, path)` |
| `web_daily_dimension_summary` | visitors, sessions, pageviews, bounces, total_duration, conversions | `(date, dimension, value)`. Dimension: channel, source, referrer_host, utm_source/medium/campaign, country, region, city, language, browser, os, device_type, screen, dan lainnya |
| `web_bot_hit_summary` | hits | `(date, bot_name)` |

### Aturan query

- **Unique visitors tidak boleh dijumlah antar hari.** Untuk rentang tanggal, unique visitors selalu dihitung dengan `COUNT(DISTINCT web_visitor_id)` dari `web_sessions` (pakai index `started_at`). Metrik yang memang bisa dijumlah (pageviews, sessions, durasi) diambil dari rekap.
- Tanpa filter, dashboard membaca rekap (cepat). Kalau ada filter, untuk data hari ini, atau untuk realtime, dashboard membaca tabel mentah yang sudah ber-index.
- Hasil query dashboard di-cache 1 menit (periode yang mencakup hari ini) atau 1 jam (periode yang sudah lewat) per kombinasi filter, kecuali halaman Realtime dan Visitors.

---

## Halaman Dashboard

Dashboard ini menjadi section baru **Website** di sidebar:
- Route prefix: `/dashboard/website`
- Route name: `dashboard.website.*`
- Controller: `App\Http\Controllers\Dashboard\Website\`
- View: `resources/views/dashboard/website/`

| Halaman | Route | Fase |
|---|---|---|
| Overview | `/dashboard/website` | 4.4 |
| Realtime | `/realtime` | 4.4 |
| Pages + detail halaman | `/pages`, `/pages/detail?path=` | 4.5 |
| Content (Blog, Docs, 404) | `/content` | 4.5 |
| Acquisition | `/acquisition` | 4.6 |
| Audience & Geography | `/audience` | 4.7 |
| Visitors + profil + sesi | `/visitors`, `/visitors/{webVisitor}`, `/sessions/{webSession}` | 4.7 |
| Technology | `/technology` | 4.8 |
| Behavior | `/behavior` | 4.9 |
| Click Heatmap | `/heatmap?path=` | 4.9 |
| Events, Goals, Funnels | `/events`, `/goals`, `/funnels` | 4.10 |
| Performance | `/performance` | 4.11 |
| Errors (website) | `/errors` | 4.11 |
| Conversion (website → app) | `/conversion` | 4.12 |

Fitur yang berlaku di semua halaman:
- Rentang tanggal custom.
- Perbandingan dengan periode sebelumnya atau tahun lalu.
- Filter global lewat query string, sehingga URL bisa dibagikan.
- Klik baris tabel mana pun untuk drill-down.
- Export CSV.

---

## Fase 4.1 — Fondasi Data & Ingestion

**Tujuan:** Endpoint siap menerima data lengkap dari website: cepat, idempoten, dan tidak mengganggu telemetry desktop.

### Tasks
- [x] Install `matomo/device-detector`, lalu buat service `App\Services\WebAnalytics\UserAgentParser`. Hasil parse di-cache per hash UA, dan service ini sekaligus mendeteksi bot
- [ ] Pindahkan geolocation ke MaxMind GeoLite2 City lokal (lihat Keputusan Terbuka #1). Tambahkan `GeolocationResolver::resolveLocation()` yang mengembalikan negara, region, kota, kode pos, lat/lon, dan timezone. `resolveCountryCode()` untuk desktop dibiarkan seperti sekarang. Patuhi `.ai/rules/config.md`: `cache_tags` di `config/geoip.php` harus tetap kosong
- [x] Buat `config/web-analytics.php` berisi: allowed hosts, retensi, timeout sesi, ukuran batch maksimum, serta daftar search engine / social / developer community / AI assistant untuk klasifikasi channel
- [x] Migrasi + model + factory untuk semua tabel mentah (`web_visitors`, `web_sessions`, `web_pageviews`, `web_events`, `web_clicks`, `web_errors`, `web_goals`, `web_funnels`) dan `web_bot_hit_summary`
- [x] Endpoint `POST /api/v1/site/hit`:
  - [x] `SiteHitController` + `SiteHitRequest`. Body `text/plain` di-decode di `prepareForValidation`, dan satu batch maksimal 50 hit
  - [x] Controller men-dispatch `ProcessSiteHitBatchJob` ke queue `web`, membawa IP, UA, `Accept-Language`, dan `received_at`, lalu membalas `202`
- [x] Rate limiter `site-hits` per IP (misalnya 240/menit) di `AppServiceProvider`, lengkap dengan docblock alasan seperti limiter lain. Route dilepas dari limiter global `api` (30/menit per IP terlalu ketat untuk pengunjung di balik NAT kantor/kampus), sama seperti route sticker files
- [x] Isi `ProcessSiteHitBatchJob`:
  - [x] Buang hit dari host di luar allowlist
  - [x] Hit dari bot tidak disimpan, hanya ditambahkan ke hitungan `web_bot_hit_summary`
  - [x] Koreksi jam client
  - [x] Upsert visitor → session → pageview/event/click/error/vitals/engagement dengan `createOrFirst` pada UUID
- [x] Counter di session/visitor hanya di-increment kalau row-nya benar-benar baru (`wasRecentlyCreated`). Dengan begitu retry job tidak menggandakan angka
- [x] Hit `engagement`/`vitals` yang datang sebelum pageview-nya: job di-`release()` lalu dicoba ulang (maksimal 3 kali). Kalau tetap tidak ketemu, hit dibuang
- [x] `TrafficSourceClassifier`:
  - [x] Dari referrer + UTM + click ID, tentukan channel: Direct, Organic Search, Paid Search, Social, Paid Social, Email, Campaign, Developer Communities, AI Assistants, atau Referral
  - [x] Normalisasi source (misalnya `www.google.co.id` → Google)
  - [x] Referrer dari host sendiri tidak dihitung sebagai sumber baru
- [x] Definisi bounce mengikuti GA4:
  - Sesi disebut engaged kalau engaged time ≥ 10 detik, ada ≥ 2 pageview, atau terjadi konversi goal
  - Bounce = sesi yang tidak engaged
- [x] Endpoint `GET /api/v1/site/download`:
  - [x] Mencatat event `download` lewat job. Visitor/session diambil dari query string kalau ada
  - [x] Lalu redirect `302` ke `download_url` rilis terbaru di tabel `releases`, dengan fallback ke halaman GitHub releases
- [x] Feature test:
  - [x] Validasi dan respons 202
  - [x] Job ter-dispatch ke queue `web`
  - [x] Idempotensi: batch yang dikirim dua kali tidak membuat duplikat dan counter tidak dobel
  - [x] Host asing dan bot dibuang
  - [x] Klasifikasi channel
  - [x] Koreksi jam client
  - [x] Redirect download
- [x] Dokumentasikan kedua endpoint di `C:\repository\rezure\api-documentation\website-analytics-api.md` dan `docs/api.md`

---

## Fase 4.2 — Tracker di Website

**Tujuan:** Website mengirim semua data di atas tanpa memperlambat halaman.

### Tasks (repo `rezure_websites`)
- [x] Buat modul `.vitepress/theme/analytics/` (TypeScript, tanpa library selain `web-vitals`) dan pasang di `enhanceApp({ router })`. Modul hanya aktif di browser (tidak saat SSR build) dan hanya kalau hostname ada di allowlist
- [x] Identitas pengunjung:
  - [x] `visitor_id` di cookie `_rz_vid` (2 tahun, SameSite=Lax), dengan localStorage sebagai cadangan
  - [x] `session_id` + `last_activity_at` di localStorage. Sesi baru dibuat kalau tidak aktif lebih dari 30 menit atau UTM berubah
  - [x] `visit_number` dan `first_visit_at`
- [x] Pageview dikirim saat load pertama dan setiap `router.onAfterRouteChange`. Untuk navigasi SPA, referrer diisi URL halaman sebelumnya
- [x] Engagement: hitung waktu aktif (tab terlihat dan ada interaksi dalam 30 detik terakhir) serta scroll depth maksimum dan milestone-nya. Dikirim saat pindah halaman, `pagehide`, atau `visibilitychange`
- [x] Pengiriman:
  - [x] Hit dikumpulkan dalam antrian dan di-flush setiap 5 detik, saat antrian sudah ≥ 20 hit, dan saat `pagehide`
  - [x] Dikirim lewat `navigator.sendBeacon`, dengan fallback `fetch` + `keepalive`
- [x] Event otomatis:
  - [x] Outbound link
  - [x] Klik elemen `[data-track]` (CTA)
  - [x] Copy code block
  - [x] Pencarian docs (input local search, dengan debounce)
  - [x] Halaman 404
  - [x] Blog dibaca sampai akhir artikel
  - [x] Ganti tema dark/light
- [x] Klik: rekam semua klik beserta posisi relatif terhadap dokumen, selector, dan teks. Deteksi rage click (≥ 3 klik dalam 700 ms dalam radius 30 px)
- [x] Error JS ditangkap lewat `window.onerror` dan `unhandledrejection`
- [x] Web Vitals lewat paket `web-vitals` (LCP, INP, CLS, FCP, TTFB) ditambah `PerformanceNavigationTiming`
- [x] Link download di `LatestRelease.vue` diarahkan ke `GET /api/v1/site/download` dengan menyertakan `vid`/`sid`
- [x] Opt-out untuk owner: membuka website dengan `?rz_ignore=1` menyimpan flag di localStorage, dan tracker berhenti mengirim. Tujuannya supaya traffic sendiri tidak mengotori data
- [x] Abaikan browser dengan `navigator.webdriver` dan halaman prerender
- [ ] Cek dengan uBlock Origin + EasyPrivacy bahwa path `/api/v1/site/hit` tidak terblokir. Kalau terblokir, ganti nama path sebelum rilis
- [x] Pakai ulang env `VITE_REZURE_API_BASE` yang sudah ada

---

## Fase 4.3 — Rekap Harian & Mesin Query

**Tujuan:** Dashboard tetap cepat walau data mentah terus bertambah, dan semua halaman memakai sistem filter dan periode yang sama.

### Tasks
- [x] Migrasi + model untuk tabel rekap (`web_daily_summary`, `web_hourly_summary`, `web_daily_page_summary`, `web_daily_dimension_summary`)
- [x] Command `app:generate-web-summaries {--date=} {--from=} {--to=}`. Memakai upsert sehingga aman di-run ulang, dan bisa dipakai untuk backfill rentang tanggal
- [x] Jadwal harian: rekap D-1 dan D-2, dihitung per tanggal di `app.display_timezone`. D-2 ikut direkap untuk menangkap hit yang terlambat masuk dari antrian
- [x] `WebAnalyticsFilters`, DTO yang dibangun dari query string:
  - [x] Rentang tanggal: preset Hari ini, Kemarin, 7/30/90 hari, 12 bulan, atau custom
  - [x] Mode perbandingan: periode sebelumnya atau tahun lalu
  - [x] Filter dimensi: page, entry page, channel, source, utm_*, country, region, city, browser, OS, device type, language, new/returning, goal
- [x] Query builder yang memilih sumber data otomatis:
  - [x] Pakai rekap kalau tidak ada filter dan rentangnya sebelum hari ini
  - [x] Pakai tabel mentah kalau ada filter atau rentangnya mencakup hari ini
  - [x] Unique visitors selalu dihitung dengan `COUNT(DISTINCT)` dari `web_sessions`
- [x] Granularitas grafik otomatis: per jam untuk ≤ 2 hari, per hari untuk ≤ 90 hari, per minggu/bulan di atas itu. Tetap bisa diubah manual
- [x] Cache hasil query per kombinasi filter (1 menit kalau mencakup hari ini, 1 jam kalau tidak)
- [x] Retensi:
  - [x] Pasang `Prunable` pada model mentah untuk menghapus data lebih dari 400 hari, termasuk visitor yang tidak punya sesi dalam 400 hari
  - [x] Jadwalkan `model:prune` harian
- [x] Seeder `WebAnalyticsDemoSeeder` yang menghasilkan data realistis (±90 hari, ratusan ribu pageview). Gunanya supaya dashboard bisa dikembangkan dan query bisa di-`EXPLAIN` sebelum data asli banyak
- [x] Test:
  - [x] Rekap per jam/hari/halaman/dimensi benar
  - [x] Re-run tidak menggandakan angka
  - [x] Unique visitors lintas hari tidak dijumlah
  - [x] Filter dan perbandingan periode bekerja

---

## Fase 4.4 — Shell Dashboard, Overview & Realtime

**Tujuan:** Halaman utama website analytics yang langsung menjawab "website sedang bagaimana?".

### Tasks
- [x] Grup **Website** di sidebar `dashboard-layout`, berisi sub-menu ke semua halaman v4, plus badge jumlah visitor realtime
- [x] Komponen toolbar bersama:
  - [x] Pemilih rentang tanggal (pakai ulang `x-dashboard.date-picker`)
  - [x] Toggle perbandingan
  - [x] Chip filter aktif
  - [x] Semua state disimpan di query string sehingga URL bisa dibagikan
- [x] Drill-down: klik baris di tabel mana pun menambahkannya sebagai filter ke seluruh halaman
- [x] Komponen tabel bersama dengan sorting, pencarian, pagination, dan export CSV (pola `DevicesExportController`)
- [x] Overview, bagian KPI:
  - [x] Tile: unique visitors, sessions, pageviews, pages/session, bounce rate, rata-rata durasi sesi, rata-rata engaged time, downloads, download conversion rate
  - [x] Setiap tile menampilkan delta vs periode pembanding dan sparkline (pakai ulang `x-dashboard.stat-tile`)
- [x] Overview, grafik utama:
  - [x] Pemilih metrik dan garis periode pembanding
  - [x] Anotasi vertikal untuk tanggal rilis (`releases`) dan publish blog (`blogs`), supaya lonjakan traffic bisa dikaitkan dengan penyebabnya. Dibuat sebagai plugin Chart.js kecil, tanpa dependency baru
- [x] Overview, ringkasan: top pages, top channels/sources, top countries, pembagian device, top events
- [x] Halaman Realtime:
  - [x] Visitor aktif dalam 5 menit terakhir
  - [x] Pageview per menit selama 30 menit terakhir
  - [x] Halaman, sumber, dan negara/kota yang sedang aktif
  - [x] Feed hit terbaru
  - [x] Data diambil dengan polling endpoint JSON setiap 10 detik
- [x] Tile "Website visitors" di Overview dashboard utama
- [x] Kartu kesehatan ingest website di samping `ingest-health-card`: kapan hit terakhir diterima, backlog queue `web`, failed job
- [x] Feature test:
  - [x] Semua halaman butuh auth
  - [x] Halaman ter-render dengan data dari factory
  - [x] Filter dan periode diterapkan
  - [x] Endpoint JSON realtime

---

## Fase 4.5 — Pages & Content

**Tujuan:** Tahu konten mana yang bekerja dan mana yang bermasalah.

### Tasks
- [x] Halaman Pages:
  - [x] Tabel semua halaman: pageviews, unique visitors, rata-rata engaged time, rata-rata scroll depth, entries, exit rate, bounce rate, LCP p75
  - [x] Tab All / Entry pages / Exit pages
- [x] Detail per halaman:
  - [x] Tren traffic
  - [x] Sumber traffic ke halaman ini
  - [x] Halaman sebelum dan sesudahnya
  - [x] Distribusi scroll depth
  - [x] Pembagian device
  - [x] Link ke click heatmap
- [x] Blog, per post (join ke `blogs` lewat slug):
  - [x] Views dan pembaca unik
  - [x] Rata-rata waktu baca
  - [x] Read-through rate (dibaca sampai habis dibagi views)
  - [x] Sumber traffic
  - [x] Views dalam 7 hari pertama setelah publish, supaya post lama dan baru bisa dibandingkan dengan adil
- [x] Docs:
  - [x] Ranking halaman guide
  - [x] Query pencarian docs terbanyak
  - [x] Query yang diikuti pengunjung keluar dari website (indikasi dokumentasinya kurang)
- [x] 404: daftar path yang tidak ditemukan beserta referrer-nya, sebagai daftar broken link yang perlu diperbaiki

---

## Fase 4.6 — Acquisition

**Tujuan:** Tahu channel mana yang mendatangkan pengunjung dan download.

### Tasks
- [x] Channel: tabel + tren stacked per channel, dengan visitors, sessions, bounce, durasi, downloads, dan conversion rate
- [x] Sources dan referrer: dari source yang sudah dinormalisasi, bisa drill-down ke URL referrer lengkap (halaman persis mana yang menautkan ke Rezure)
- [x] Rincian per kelompok:
  - [x] Search engines
  - [x] Social networks
  - [x] Developer communities: GitHub, Stack Overflow, Reddit, dev.to, Laracasts, Hacker News
  - [x] AI assistants: ChatGPT, Perplexity, Claude, Gemini, Copilot
- [x] UTM campaigns: tabel source / medium / campaign / term / content
- [x] Landing page per channel
- [x] Atribusi konversi download: first-touch (dari `web_visitors`) dan last-touch (dari sesi saat download), ditampilkan berdampingan

---

## Fase 4.7 — Audience, Geography & Visitor Explorer

**Tujuan:** Tahu siapa pengunjungnya, dari mana asalnya, dan apakah mereka kembali.

### Tasks
- [x] Install `chartjs-chart-geo` + `world-atlas`, lalu registrasi di `resources/js/app.js` dengan warna dari token tema (light/dark)
- [x] Geografi:
  - [x] Peta choropleth negara
  - [x] Tabel negara yang bisa drill-down ke region lalu ke kota
  - [x] Bubble map kota dari lat/lon
- [x] Pola kunjungan:
  - [x] New vs returning per hari
  - [x] Distribusi frekuensi kunjungan (`visit_number`)
  - [x] Recency: jumlah hari sejak kunjungan terakhir
- [x] Cohort retention mingguan: dari visitor yang pertama kali datang di minggu X, berapa persen yang kembali di minggu X+1, X+2, dan seterusnya
- [x] Bahasa dan timezone browser
- [x] Visitor log:
  - [x] Daftar visitor terbaru dengan lokasi, device, jumlah sesi dan pageview, sumber pertama, dan status sudah download atau belum
  - [x] Bisa difilter dan dicari, termasuk cari berdasarkan IP
- [x] Profil visitor: ringkasan + timeline semua sesi, setiap pageview dan event, IP, lokasi, dan perangkat (pola mirip `device-detail.blade.php`)
- [x] Detail sesi:
  - [x] Urutan halaman beserta engaged time dan scroll per halaman
  - [x] Event, klik, dan error
  - [x] Web Vitals

---

## Fase 4.8 — Technology & Bot Traffic

**Tujuan:** Keputusan teknis website (kompatibilitas, layout responsif) diambil berdasarkan data.

### Tasks
- [x] Browser + versi, engine, OS + versi, device type, brand dan model
- [x] Resolusi layar, bucket lebar viewport (mobile / tablet / desktop / wide), pixel ratio
- [x] Preferensi warna OS dibandingkan tema yang dipilih di website, tipe koneksi, CPU cores, dan memory
- [x] Bot traffic: tren hit bot, serta bot teratas per nama dan kategori (crawler search engine, AI crawler, monitoring, dan lainnya)

---

## Fase 4.9 — Behavior & Click Heatmap

**Tujuan:** Memahami bagaimana pengunjung bergerak dan berinteraksi di dalam website.

### Tasks
- [x] Distribusi dalam bentuk histogram (bukan hanya rata-rata): engaged time, scroll depth, kedalaman sesi (pages/session), dan durasi sesi
- [x] User flow (entry page → langkah 2 → langkah 3 → exit):
  - [x] Ditampilkan sebagai diagram Sankey (lihat Keputusan Terbuka #2)
  - [x] Node bisa diklik untuk drill-down
- [x] Heatmap jam × hari dalam seminggu, dengan pola yang sama seperti halaman Traffic desktop
- [x] Rage click: elemen dan halaman dengan rage click terbanyak
- [x] Click heatmap per halaman (lihat Keputusan Terbuka #4):
  - [x] Halaman website asli dimuat di iframe, lalu titik/gradien klik di-overlay dari `x_pct`/`y_px`
  - [x] Filter per bucket viewport dan rentang tanggal
  - [x] Tabel elemen yang paling sering diklik

---

## Fase 4.10 — Events, Goals & Funnels

**Tujuan:** Mengukur aksi yang penting, bukan hanya pageview.

### Tasks
- [x] Events: tabel per nama event (total, unique visitors, sessions, rata-rata per sesi), dengan drill-down ke `label` dan key di `props`
- [x] Goals:
  - [x] CRUD dari dashboard, memakai Form Request seperti modul dashboard lain
  - [x] Tipe goal: event (nama + label opsional) atau pageview (pola path dengan wildcard)
  - [x] Goal default "Download" dibuat oleh seeder
- [x] Konversi: jumlah dan conversion rate per goal, dengan breakdown per channel, source, landing page, negara, dan device
- [x] Funnel builder:
  - [x] Definisi step berupa pageview atau event
  - [x] Scope per sesi atau per visitor, dengan batas waktu
  - [x] Menampilkan jumlah per step, drop-off %, median waktu antar step, dan breakdown per dimensi
  - [x] Funnel disimpan di `web_funnels`
- [x] Test:
  - [x] Pencocokan wildcard pada goal
  - [x] Perhitungan funnel berurutan (step harus terjadi sesuai urutan)
  - [x] Scope sesi vs visitor

---

## Fase 4.11 — Performance & Website Errors

**Tujuan:** Website tetap cepat dan bebas error di perangkat pengunjung yang sesungguhnya.

### Tasks
- [x] Core Web Vitals p75 (LCP, INP, CLS, FCP, TTFB): keseluruhan, per halaman, per device type, dan tren harian
- [x] Distribusi good / needs improvement / poor sesuai ambang Google
- [x] Halaman paling lambat dan timing page load
- [x] Errors:
  - [x] Dikelompokkan per fingerprint (pesan + source + baris)
  - [x] Untuk tiap kelompok: jumlah, visitor terdampak, first/last seen, browser, halaman
  - [x] Halaman detail berisi contoh stack trace

---

## Fase 4.12 — Konversi Website → App (Lintas Produk)

**Tujuan:** Menyambung perjalanan dari kunjungan website sampai menjadi user aktif aplikasi. Ini sekaligus mengisi tahap "download" yang saat ini belum terlacak di funnel Fase 3.6.

### Tasks
- [x] Funnel agregat per periode: visitors → membuka halaman /download → download (lewat redirect endpoint) → device baru (first `app_opened`) → masih aktif setelah 7 hari
- [x] Update `DashboardMetricsService::productFunnel()` dan halaman Funnel desktop supaya tahap download memakai data nyata
- [x] Downloads per versi rilis dan per channel
- [x] Command terjadwal yang mengambil `download_count` asset rilis dari GitHub API, untuk membandingkan download lewat website dengan total download di GitHub
- [x] (Opsional, perlu konfirmasi — Keputusan Terbuka #3) Atribusi per individu:
  - [x] Simpan IP pertama device desktop saat ingest
  - [x] Cocokkan dengan IP download di website dalam jendela 72 jam, lalu isi `web_visitors.device_id`
  - [x] Ini membuka dua laporan baru: "channel/campaign mana yang menghasilkan user aktif" dan "perjalanan di website dari user yang aktif"

---

## Fase 4.13 — Hardening & Operasional

**Tujuan:** Siap menerima traffic nyata untuk jangka panjang.

### Tasks
- [ ] Load test endpoint `site/hit` dengan burst ratusan request per detik, dan pastikan respons tetap cepat
- [ ] Jalankan `EXPLAIN` pada semua query dashboard utama terhadap data seeder besar, lalu tambah/ubah index kalau perlu
- [ ] Konfigurasi worker produksi untuk queue `web` (Supervisor menjalankan `queue:work --queue=web` terpisah dari `default`). Evaluasi pindah ke Redis kalau volumenya tinggi
- [x] Jalankan seluruh test suite dan Pint

---

## Dependency Baru

| Paket | Repo | Untuk | Status |
|---|---|---|---|
| `matomo/device-detector` | laravel-api (composer) | Parse UA + deteksi bot | Disetujui |
| `chartjs-chart-geo` + `world-atlas` | laravel-api (npm) | Peta negara dan kota | Disetujui |
| `geoip2/geoip2` + database GeoLite2 City | laravel-api (composer) | Lokasi sampai level kota tanpa batas rate | Perlu persetujuan (#1) |
| `chartjs-chart-sankey` | laravel-api (npm) | Diagram user flow | Perlu persetujuan (#2) |
| `web-vitals` | rezure_websites (npm) | Core Web Vitals | Perlu persetujuan (#5) |

## Keputusan Terbuka

1. **Geolocation pindah ke MaxMind GeoLite2 lokal.**
   - Masalahnya: saat ini dipakai ip-api gratis (lewat HTTP, maksimal 45 request/menit, `IPAPI_KEY` belum diisi). Jumlah IP unik dari website jauh lebih banyak daripada desktop, jadi batas itu akan cepat tembus dan data lokasi jadi kosong.
   - Kebutuhan GeoLite2: paket `geoip2/geoip2`, akun MaxMind gratis (`MAXMIND_LICENSE_KEY`), dan update database terjadwal (`geoip:update` mingguan).
   - Bonus: ada database GeoLite2-ASN untuk mengetahui ISP/organisasi pengunjung.
   - Geolocation desktop ikut pindah ke driver yang sama.
2. **`chartjs-chart-sankey` untuk user flow.** Alternatif tanpa dependency adalah tabel bertingkat, tapi lebih sulit dibaca.
3. **Atribusi per individu dari website ke app (Fase 4.12).** Ini mengubah data telemetry desktop (menyimpan IP pertama device), yang berada di luar pengecualian privasi website. Jadi butuh konfirmasi terpisah.
4. **Click heatmap memuat website di dalam iframe dashboard.** Perlu dipastikan server website tidak mengirim `X-Frame-Options: DENY` atau CSP `frame-ancestors` yang memblokir domain dashboard.
5. **`web-vitals` di repo website** (±2 KB). Alternatifnya `PerformanceObserver` manual, tapi INP sulit dihitung akurat tanpa paket ini.

## Dependency ke Proyek Lain

- **rezure_websites:** Fase 4.2 (tracker) dan link download di `LatestRelease.vue`. Website perlu di-deploy ulang.
- **Rezure desktop app:** tidak ada perubahan.
- **Server/deploy:** worker untuk queue `web`, dua jadwal baru di scheduler yang sudah berjalan, dan env `MAXMIND_LICENSE_KEY` kalau #1 disetujui.

## Di Luar Scope v4

- Session replay (rekaman layar pengunjung)
- A/B testing
- Alert otomatis (misalnya notifikasi WhatsApp saat traffic melonjak) dan laporan mingguan terjadwal
- Multi-website; v4 hanya untuk `rezure.redscale.my.id`

## Urutan Pengerjaan yang Disarankan

1. Fase 4.1 → 4.2. Data harus mulai terkumpul sedini mungkin, karena histori baru ada sejak tracker live.
2. Fase 4.3: rekap dan mesin query, fondasi untuk semua halaman dashboard.
3. Fase 4.4: Overview dan Realtime, bagian yang paling cepat terasa manfaatnya.
4. Fase 4.5 → 4.6 → 4.7 → 4.8
5. Fase 4.9 → 4.10 → 4.11
6. Fase 4.12. Butuh data download minimal beberapa minggu supaya hasilnya bermakna.
7. Fase 4.13. Sebagian bisa dicicil sejak Fase 4.3 dengan data seeder.

---

## Catatan Implementasi (2026-10-24)

Hal yang dikerjakan berbeda dari rencana di atas, dan alasannya:

- **Geolocation tetap ip-api.** `GeolocationResolver::resolveLocation()` sudah dibuat (negara sampai kota, lat/lon, timezone, di-cache per IP), tetapi drivernya tidak dipindah ke MaxMind. Task 4.1 tentang MaxMind sengaja dibiarkan terbuka.
- **Counter sesi/visitor dihitung ulang dari data, bukan di-increment.** `WebSessionAggregator` menghitung ulang jumlah pageview, durasi, bounce, entry/exit, dan urutan pageview dari row yang ada setiap kali batch diproses. Hasilnya sama-sama idempoten, dan juga tahan terhadap pageview yang datang tidak berurutan.
- **Hit yang datang sebelum pageview-nya** tidak memakai `release()` untuk seluruh job. Hanya hit yang tertunda yang di-dispatch ulang (maksimal 3 putaran, jeda 30/60 detik), jadi batch yang sudah tersimpan tidak diproses ulang.
- **Referrer selain http(s) tidak dihitung sebagai sumber.** Contohnya `javascript://host/…`: secara teknis punya host, tetapi tampil sebagai link di halaman Acquisition.
- **Rekap dipakai lebih luas dari rencana.** Rekap dipakai untuk ringkasan, time series, breakdown dimensi, dan tabel halaman setiap kali tidak ada filter dan rekap untuk semua hari lampau di rentang tersebut lengkap. Hari ini tetap dibaca dari tabel mentah. Unique visitors selalu dihitung dari `web_sessions`, hanya untuk nilai teratas. Test `WebAnalyticsQueryTest::test_rollups_give_the_same_numbers_as_the_raw_rows` membuktikan hasil rekap identik dengan hasil dari data mentah.
- **Bug lama ikut diperbaiki.** `@theme` di `resources/css/app.css` menjadi `@theme static`. Sebelumnya Tailwind v4 membuang variabel `--color-series-1..5` karena tidak dipakai di class mana pun, sehingga chart multi-garis (termasuk di dashboard desktop) tergambar tanpa warna.
- **Funnel desktop (Fase 3.6)** sekarang mengisi tahap "Download" dengan jumlah download dari website. Persentasenya tetap berbasis jumlah install, karena install juga bisa datang langsung dari GitHub.
- **Seeder demo:** `php artisan db:seed --class=WebAnalyticsDemoSeeder` mengisi data realistis 90 hari (±3.000 visitor, ±12.000 pageview, ±24.000 klik) lalu menjalankan rekap. Jangan dijalankan di produksi.

### Yang perlu dilakukan saat deploy

- `php artisan migrate`, lalu `npm run build`.
- Worker juga harus memproses queue `web`, misalnya `php artisan queue:work --queue=default,web`. Tanpa worker ini hit website akan menumpuk di tabel `jobs`.
- Scheduler sudah berjalan. Jadwal yang ditambahkan: `app:generate-web-summaries` (00:20 WIB), `model:prune` (02:00 WIB), `app:sync-github-download-counts` (23:50 WIB), dan `app:attribute-web-installs` (setiap jam).
- Env di `.env.example`: `WEB_ANALYTICS_ALLOWED_HOSTS`, `WEB_ANALYTICS_RETENTION_DAYS`, `WEB_ANALYTICS_QUEUE`, `WEB_ANALYTICS_GITHUB_REPO`.
- Website: set `VITE_REZURE_API_BASE` (sudah ada) dan `VITE_REZURE_ANALYTICS_HOSTS` (baru), build ulang, lalu deploy.
