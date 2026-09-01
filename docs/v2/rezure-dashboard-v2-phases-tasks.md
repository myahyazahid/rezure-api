# Rezure Dashboard (Laravel) — v2: Telemetry Ingestion & Analytics

Breakdown fase dan task untuk **rezure-dashboard**, proyek Laravel terpisah yang menerima data telemetry dari Rezure desktop app via API dan menyajikannya sebagai dashboard analytics.

---

## Fase 2.1 — Support / Ticket System ✅ (2026-09-01)

**Tujuan:** Menerima dan mengelola feedback/laporan error dari user Rezure, termasuk lampiran file.

> `rezure_version` diimplementasikan sebagai kolom `app_version`, menyamakan penamaan dengan
> `devices`/`events`/`device_sessions`. Ditambahkan `client_ticket_id` (uuid, client-generated)
> sebagai idempotency key — tidak ada di daftar field awal, tapi setiap endpoint write lain di
> app ini dedupe pakai id serupa, dan submission multipart lebih rawan di-retry daripada JSON
> kecil. Lihat `app/Http/Requests/Support/TicketRequest.php` dan
> `c:\repository\rezure\api-documentation\telemetry-api.md` untuk kontrak lengkap.

### Tasks
- [x] Migration tabel `tickets` (`id`, `device_id`, `category` [bug/feature_request/general], `title`, `description`, `status` [open/in_progress/resolved], `rezure_version`, `os_version`, `created_at`)
- [x] Migration tabel `ticket_attachments` (`id`, `ticket_id`, `file_path`, `file_name`, `file_size`, `mime_type`)
- [x] Buat endpoint `POST /api/v1/support/tickets` (multipart form-data) untuk menerima ticket + lampiran
- [x] Buat Form Request untuk validasi payload (judul, deskripsi wajib; validasi tipe & ukuran file lampiran)
- [x] Implementasi penyimpanan file lampiran (storage disk, dengan validasi ekstensi/mime type untuk keamanan) — private `local` disk, nama file random (tidak pernah nama asli), tidak pernah disk `public`
- [x] Terapkan rate limiting pada endpoint (cegah spam ticket) — limiter `support` khusus (10/jam per device, 5/jam per IP), ditumpuk di atas limiter `api` global
- [x] Buat endpoint `GET /api/v1/support/tickets?device_id=...` (opsional, untuk riwayat ticket per device)
- [x] Buat halaman dashboard: list ticket masuk, dengan filter status & kategori
- [x] Buat halaman detail ticket: deskripsi lengkap, lampiran (bisa didownload/dilihat), info sistem, dan aksi update status
- [ ] (Opsional) Notifikasi email ke maintainer saat ada ticket baru masuk

---

## Fase 2.2 — Database Schema (Telemetry) ✅ (dibangun sebelum sesi ini)

**Tujuan:** Struktur data siap menampung device, event, dan heartbeat.

> Dibangun dengan bentuk yang sedikit berbeda dari daftar di bawah, tapi tujuannya tercapai:
> `heartbeats` diimplementasikan sebagai `device_sessions` (per-sesi, bukan per-ping — bisa
> menghitung durasi session, bukan cuma "device X mengirim heartbeat"). Kode adalah source of
> truth; lihat migration di `database/migrations/`.

### Tasks
- [x] Migration tabel `devices` (`device_id`, `first_seen_at`, `last_seen_at`, `rezure_version`, `os_version`)
- [x] Migration tabel `events` (`id`, `device_id`, `event_type`, `metadata` JSON, `occurred_at`) dengan unique composite key untuk dedup
- [x] Migration tabel `heartbeats` (`id`, `device_id`, `rezure_version`, `occurred_at`) — sebagai `device_sessions`
- [x] Tambahkan index awal pada kolom yang akan sering di-query (`device_id`, `occurred_at`, `event_type`)

---

## Fase 2.3 — Ingestion Endpoint ✅ (dibangun sebelum sesi ini)

**Tujuan:** API siap menerima data dari client secara reliable, cepat, dan tidak memblokir request.

> Dibangun sebagai dua endpoint terpisah (`POST /telemetry/heartbeat`, `POST /telemetry/event`)
> alih-alih satu endpoint batch `/telemetry/ingest` — payload lebih sederhana per-request dan
> validasinya lebih ketat per jenis data. Tujuan "validasi cepat lalu dispatch job, return 202"
> tetap sama persis.

### Tasks
- [x] Buat endpoint `POST /api/v1/telemetry/ingest` — sebagai `POST /telemetry/heartbeat` + `POST /telemetry/event`
- [x] Buat Form Request untuk validasi bentuk payload (device_id, events[], heartbeats[])
- [x] Implementasi auth ringan (API key per-device atau shared key + validasi `device_id`) — `device_id` sebagai identitas, tanpa API key (lihat catatan "Known gap" di telemetry-api.md)
- [x] Terapkan rate limiting pada endpoint
- [x] Endpoint hanya melakukan validasi cepat lalu dispatch ke Job, return `202 Accepted`

---

## Fase 2.4 — Queue Processing & Dedup ✅ (dibangun sebelum sesi ini)

**Tujuan:** Data yang masuk diproses secara asynchronous dan aman dari duplikasi.

### Tasks
- [x] Buat Job `ProcessTelemetryBatch` — sebagai `ProcessHeartbeatJob` + `ProcessEventJob`
- [x] Implementasi dedup logic (`firstOrCreate` dengan composite key) — key-nya `device_id` + client-generated id (`client_event_id`/`client_session_id`), bukan `occurred_at` (client bisa retry dengan `occurred_at` yang sama tapi id berbeda kalau pakai timestamp sebagai key, jadi id eksplisit lebih aman)
- [x] Update/insert record `devices` (`last_seen_at`, `rezure_version` terbaru) saat batch diproses — via `DeviceRegistrar::upsert()`, dipakai bersama oleh semua job termasuk `ProcessTicketSubmissionJob`
- [x] Setup queue worker (database/Redis driver)
- [x] Uji simulasi: client mengirim ulang data yang sama (retry setelah offline) → pastikan tidak ada data dobel

---

## Fase 2.5 — Dashboard: Overview & Version Adoption ✅ (dibangun sebelum sesi ini)

**Tujuan:** Data yang masuk mulai bisa dilihat dalam bentuk yang berguna.

### Tasks
- [x] Query/service untuk menghitung active users (DAU/WAU/MAU) dari tabel `heartbeats` — dari `events`/`device_sessions` via `DashboardMetricsService::activeUserSummary()`
- [x] Halaman Overview: total device, active users, tren mingguan (chart)
- [x] Query breakdown device per `rezure_version`
- [x] Halaman/chart Version Adoption

---

## Fase 2.6 — Dashboard: Feature Usage ✅ (dibangun sebelum sesi ini)

**Tujuan:** Menjawab pertanyaan "fitur apa yang paling sering dipakai".

### Tasks
- [x] Query agregasi jumlah event per `event_type`
- [x] Breakdown metadata event (misal: MySQL vs PHP-FPM mana yang paling sering di-start) — via `event_name`
- [x] Halaman/chart Feature Usage (bar chart atau heatmap sederhana)

---

## Fase 2.7 — Hardening & Review ✅ (2026-09-01, sebagian)

**Tujuan:** Pastikan sistem ingestion stabil sebelum dianggap "selesai" v2.

### Tasks
- [x] Review privasi: pastikan hanya data yang direncanakan yang disimpan — `Ticket`/`TicketAttachment` hanya menyimpan field yang bisa ditelusuri ke daftar field roadmap ini, plus `client_ticket_id` (uuid acak, bukan data identitas — kategori sama dengan `event_id`/`session_id` yang sudah diterima). Tidak menyimpan IP uploader, tidak strip EXIF dari gambar (follow-up opsional, tidak diminta roadmap ini).
- [x] Uji beban ringan pada endpoint ingestion (simulasi banyak device mengirim bersamaan) — baru untuk `POST /support/tickets` (test rate-limit 429 di `SupportTicketsApiTest`); endpoint telemetry lama belum punya test serupa
- [ ] Pastikan endpoint tetap responsif meski queue worker sedang penuh/lambat — belum diuji
- [x] Dokumentasikan payload contract API — di `c:\repository\rezure\api-documentation\telemetry-api.md` (bukan `docs/telemetry-contract.md` — kontrak API rezureapp hidup di luar repo ini, lihat CLAUDE.md)

---

## Fase 2.8 — Changelog Management ✅ (2026-09-01)

**Tujuan:** Maintainer bisa mengelola entry changelog, dan Rezure app bisa mengambilnya lewat API publik.

### Tasks
- [x] Migration tabel `changelogs` (`id`, `version`, `title`, `body` [markdown/rich text], `released_at`)
- [x] CRUD sederhana di dashboard untuk menulis/mengedit entry changelog (create, update, delete) — satu halaman index, mode edit lewat query param `?edit={id}`
- [x] Endpoint publik `GET /api/v1/changelog` (list entry, urut dari terbaru), tanpa perlu auth khusus karena hanya data publik non-sensitif — dibatasi 20 entry terbaru
- [x] Terapkan rate limiting ringan pada endpoint publik ini juga — limiter `api` global sudah cukup, tidak ada limiter khusus
- [ ] Uji end-to-end: entry yang dibuat di dashboard bisa diambil dan tampil benar di Rezure app — belum bisa diuji, `rezureapp` belum memanggil endpoint ini

---

## Dependency ke Proyek Lain

Fase 2.1 (Support/Ticket) independen — bisa dikerjakan dan ditest lebih dulu tanpa menunggu bagian telemetry manapun; Rezure app hanya butuh endpoint ini siap untuk fase Support/Ticket Menu di roadmap-nya. Fase 2.2–2.4 (telemetry) bisa dikerjakan sepenuhnya independen (tidak butuh Rezure desktop app berjalan — bisa ditest pakai payload dummy/Postman). Fase 2.5–2.6 butuh data nyata mengalir masuk, idealnya setelah Rezure desktop app sudah mengirim data uji coba. Fase 2.8 (Changelog) juga independen dari telemetry, tapi Rezure app butuh endpoint ini siap untuk fase Changelog Menu di roadmap-nya.

## Urutan Pengerjaan yang Disarankan

1. Fase 2.1 (Support/Ticket — prioritas awal, langsung berguna dan tidak bergantung pada apapun)
2. Fase 2.2 → 2.3 → 2.4 (fondasi telemetry, bisa dikerjakan duluan & independen)
3. Fase 2.5 (setelah ada data nyata atau data uji coba masuk)
4. Fase 2.6
5. Fase 2.7
6. Fase 2.8 (bisa dikerjakan kapan saja, paralel dengan fase lain — tidak bergantung pada telemetry)
