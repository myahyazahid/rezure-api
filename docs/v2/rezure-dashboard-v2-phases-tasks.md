# Rezure Dashboard (Laravel) — v2: Telemetry Ingestion & Analytics

Breakdown fase dan task untuk **rezure-dashboard**, proyek Laravel terpisah yang menerima data telemetry dari Rezure desktop app via API dan menyajikannya sebagai dashboard analytics.

---

## Fase 2.1 — Support / Ticket System

**Tujuan:** Menerima dan mengelola feedback/laporan error dari user Rezure, termasuk lampiran file.

### Tasks
- [ ] Migration tabel `tickets` (`id`, `device_id`, `category` [bug/feature_request/general], `title`, `description`, `status` [open/in_progress/resolved], `rezure_version`, `os_version`, `created_at`)
- [ ] Migration tabel `ticket_attachments` (`id`, `ticket_id`, `file_path`, `file_name`, `file_size`, `mime_type`)
- [ ] Buat endpoint `POST /api/v1/support/tickets` (multipart form-data) untuk menerima ticket + lampiran
- [ ] Buat Form Request untuk validasi payload (judul, deskripsi wajib; validasi tipe & ukuran file lampiran)
- [ ] Implementasi penyimpanan file lampiran (storage disk, dengan validasi ekstensi/mime type untuk keamanan)
- [ ] Terapkan rate limiting pada endpoint (cegah spam ticket)
- [ ] Buat endpoint `GET /api/v1/support/tickets?device_id=...` (opsional, untuk riwayat ticket per device)
- [ ] Buat halaman dashboard: list ticket masuk, dengan filter status & kategori
- [ ] Buat halaman detail ticket: deskripsi lengkap, lampiran (bisa didownload/dilihat), info sistem, dan aksi update status
- [ ] (Opsional) Notifikasi email ke maintainer saat ada ticket baru masuk

---

## Fase 2.2 — Database Schema (Telemetry)

**Tujuan:** Struktur data siap menampung device, event, dan heartbeat.

### Tasks
- [ ] Migration tabel `devices` (`device_id`, `first_seen_at`, `last_seen_at`, `rezure_version`, `os_version`)
- [ ] Migration tabel `events` (`id`, `device_id`, `event_type`, `metadata` JSON, `occurred_at`) dengan unique composite key untuk dedup
- [ ] Migration tabel `heartbeats` (`id`, `device_id`, `rezure_version`, `occurred_at`)
- [ ] Tambahkan index awal pada kolom yang akan sering di-query (`device_id`, `occurred_at`, `event_type`)

---

## Fase 2.3 — Ingestion Endpoint

**Tujuan:** API siap menerima data dari client secara reliable, cepat, dan tidak memblokir request.

### Tasks
- [ ] Buat endpoint `POST /api/v1/telemetry/ingest`
- [ ] Buat Form Request untuk validasi bentuk payload (device_id, events[], heartbeats[])
- [ ] Implementasi auth ringan (API key per-device atau shared key + validasi `device_id`)
- [ ] Terapkan rate limiting pada endpoint
- [ ] Endpoint hanya melakukan validasi cepat lalu dispatch ke Job, return `202 Accepted`

---

## Fase 2.4 — Queue Processing & Dedup

**Tujuan:** Data yang masuk diproses secara asynchronous dan aman dari duplikasi.

### Tasks
- [ ] Buat Job `ProcessTelemetryBatch`
- [ ] Implementasi dedup logic (`firstOrCreate` dengan composite key: `device_id + event_type + occurred_at`)
- [ ] Update/insert record `devices` (`last_seen_at`, `rezure_version` terbaru) saat batch diproses
- [ ] Setup queue worker (database/Redis driver)
- [ ] Uji simulasi: client mengirim ulang data yang sama (retry setelah offline) → pastikan tidak ada data dobel

---

## Fase 2.5 — Dashboard: Overview & Version Adoption

**Tujuan:** Data yang masuk mulai bisa dilihat dalam bentuk yang berguna.

### Tasks
- [ ] Query/service untuk menghitung active users (DAU/WAU/MAU) dari tabel `heartbeats`
- [ ] Halaman Overview: total device, active users, tren mingguan (chart)
- [ ] Query breakdown device per `rezure_version`
- [ ] Halaman/chart Version Adoption

---

## Fase 2.6 — Dashboard: Feature Usage

**Tujuan:** Menjawab pertanyaan "fitur apa yang paling sering dipakai".

### Tasks
- [ ] Query agregasi jumlah event per `event_type`
- [ ] Breakdown metadata event (misal: MySQL vs PHP-FPM mana yang paling sering di-start)
- [ ] Halaman/chart Feature Usage (bar chart atau heatmap sederhana)

---

## Fase 2.7 — Hardening & Review

**Tujuan:** Pastikan sistem ingestion stabil sebelum dianggap "selesai" v2.

### Tasks
- [ ] Review privasi: pastikan hanya data yang direncanakan yang disimpan
- [ ] Uji beban ringan pada endpoint ingestion (simulasi banyak device mengirim bersamaan)
- [ ] Pastikan endpoint tetap responsif meski queue worker sedang penuh/lambat
- [ ] Dokumentasikan payload contract API di `docs/telemetry-contract.md`

---

## Fase 2.8 — Changelog Management

**Tujuan:** Maintainer bisa mengelola entry changelog, dan Rezure app bisa mengambilnya lewat API publik.

### Tasks
- [ ] Migration tabel `changelogs` (`id`, `version`, `title`, `body` [markdown/rich text], `released_at`)
- [ ] CRUD sederhana di dashboard untuk menulis/mengedit entry changelog (create, update, delete)
- [ ] Endpoint publik `GET /api/v1/changelog` (list entry, urut dari terbaru), tanpa perlu auth khusus karena hanya data publik non-sensitif
- [ ] Terapkan rate limiting ringan pada endpoint publik ini juga
- [ ] Uji end-to-end: entry yang dibuat di dashboard bisa diambil dan tampil benar di Rezure app

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
