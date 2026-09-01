# Fase Development — Rezure

Roadmap pengembangan **Rezure** (clone Laragon dengan fitur tambahan) beserta **Dashboard Laravel** untuk monitoring & manajemen user.

> Catatan: Roadmap ini berasumsi stack **Tauri (Rust) + Vue 3** untuk desktop app dan **Laravel** untuk dashboard/API. Sesuaikan detail teknis jika memilih Electron.

---

## Fase 0 — Persiapan & Riset

- Finalisasi pilihan stack (Tauri vs Electron)
- Riset source code / arsitektur Laragon (fitur apa saja yang wajib ada di MVP)
- Setup repository (desktop app + Laravel API, bisa dipisah jadi 2 repo)
- Setup environment development (Rust toolchain / Node.js, Laravel Sail atau local)

---

## Fase 1 — MVP Core: Service Manager

Tujuan: Rezure bisa menyalakan/mematikan environment dasar seperti Laragon.

- Bundling portable binary: Apache/Nginx, PHP (multi-versi), MySQL/MariaDB
- UI dasar: tombol start/stop per service, indikator status (running/stopped)
- Auto-detect port conflict sebelum start service
- Log viewer sederhana per service
- Konfigurasi disimpan di local config file (JSON/TOML)

---

## Fase 2 — Project & Virtual Host Management

- Auto-detect folder project di direktori `www`/`data`
- Generate virtual host config otomatis (Apache/Nginx `.conf`)
- Auto-edit file `hosts` sistem operasi
- PHP version switcher per-project
- Quick app installer (template Laravel, WordPress, Vue starter)

---

## Fase 3 — Fitur Tambahan (Nilai Jual Rezure)

- Auto HTTPS dengan mkcert terintegrasi
- One-click tunnel (ngrok/cloudflared) untuk share project ke luar
- Terminal terintegrasi
- Docker toggle mode (opsional container vs native binary)
- Built-in database GUI ringan (embedded Adminer-style)
- Project health dashboard lokal (port conflict detector)

---

## Fase 4 — Integrasi Telemetry ke Laravel API

- Generate `device_id` unik saat pertama install
- Modul pengiriman event ke API (async, non-blocking, queue lokal jika offline)
- Endpoint Laravel: `POST /api/v1/telemetry/heartbeat`, `POST /api/v1/telemetry/event`
- Autentikasi ringan: API key per-device + rate limiting
- Setup Laravel Queue untuk proses ingest event (hindari blocking request)
- Toggle "share usage data" di sisi client (opt-out privasi)

---

## Fase 5 — Dashboard Laravel: Analytics Dasar

- Migration & model: `devices`, `events`, `sessions`
- Active users (DAU/WAU/MAU) & retention rate
- Version adoption chart
- Feature usage heatmap
- Session duration rata-rata

---

## Fase 6 — Dashboard Laravel: Error Reporting & Update Checker

- Endpoint untuk auto-report error/crash dari client
- Dashboard listing error terbanyak & device yang terdampak
- Endpoint `GET /api/v1/version/latest` untuk cek update
- Notifikasi update otomatis di client saat versi baru tersedia

---

## Fase 7 — Dashboard Laravel: Lanjutan

- License/key management (jika ada model Rezure Pro/freemium)
- Feature flag (aktif/nonaktifkan fitur dari server tanpa update client)
- Remote config untuk quick-app template (kelola dari dashboard)
- In-app feedback form → tersimpan di dashboard
- Changelog/announcement system

---

## Fase 8 — Testing, Packaging & Distribusi

- Testing di berbagai versi Windows
- Build installer (Tauri bundler / Electron builder)
- Setup auto-update mechanism untuk client
- Dokumentasi penggunaan (README, landing page sederhana)
- Rilis versi awal (beta/public)

---

## Fase 9 — Monitoring Pasca-Rilis & Iterasi

- Pantau data dari dashboard (active users, error rate, feature usage)
- Prioritas perbaikan bug berdasarkan data real
- Iterasi fitur berdasarkan feedback user
