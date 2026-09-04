# Rezure Dashboard (Laravel) — v3: Business Insight & Traffic Analytics

Roadmap fase lanjutan dashboard, fokus ke analytics granular (traffic by hour, geografi, retention, dll) sebagai data asset untuk keputusan bisnis/produk.

---

## Fase 3.1 — Geolocation & Timestamp Granularity

**Tujuan:** Data yang masuk punya informasi waktu presisi jam dan lokasi negara.

### Tasks
- [ ] Tambahkan kolom `country_code` (2 huruf) pada tabel `events`/`heartbeats`
- [ ] Implementasi geolocation lookup saat ingest (MaxMind GeoLite2 atau `torann/geoip`) — proses IP on-the-fly, **jangan simpan IP mentah**, hanya simpan hasil `country_code`
- [ ] Pastikan `occurred_at` disimpan dalam UTC dengan presisi jam/menit (bukan cuma tanggal)
- [ ] Buat helper/service untuk convert `country_code` → nama negara lengkap (`league/iso3166` atau lookup table) untuk ditampilkan di dashboard

---

## Fase 3.2 — Summary Tables (Fondasi Performa)

**Tujuan:** Query analytics tidak membebani tabel raw yang terus bertumbuh.

### Tasks
- [ ] Buat tabel `hourly_traffic_summary` (`date`, `hour`, `event_count`)
- [ ] Buat tabel `country_traffic_summary` (`date`, `country_code`, `device_count`)
- [ ] Buat scheduled job (Laravel Scheduler) yang generate/update summary tables ini secara harian
- [ ] Pastikan dashboard query dari tabel summary, bukan langsung scan tabel `events`/`heartbeats` mentah

---

## Fase 3.3 — Dashboard: Traffic Insight

**Tujuan:** Visualisasi traffic pattern mirip seller center e-commerce.

### Tasks
- [ ] Chart "Traffic by Hour" — heatmap 24 jam
- [ ] Chart "Traffic by Day of Week" — pola weekday vs weekend
- [ ] Halaman/tabel "Geographic Distribution" — nama negara lengkap + jumlah user, diurutkan terbanyak
- [ ] Chart "Growth per Region" — tren pertumbuhan per negara dari waktu ke waktu

---

## Fase 3.4 — User Behavior Insight

**Tujuan:** Insight lebih dalam dari sekadar active user count.

### Tasks
- [ ] Query & chart "Session Length Distribution" (bukan rata-rata saja, tapi sebaran/histogram)
- [ ] Implementasi "New vs Returning Device" — breakdown harian
- [ ] Cohort retention table — dari device yang install minggu X, berapa % masih aktif di minggu X+1, X+2, dst
- [ ] Churn indicator — tandai device yang tidak mengirim heartbeat lebih dari N hari (misal 30 hari) sebagai "kemungkinan uninstall"

---

## Fase 3.5 — Technical/Environment Insight

**Tujuan:** Insight untuk keputusan teknis (testing, kompatibilitas, optimasi).

### Tasks
- [ ] Chart "OS Breakdown" (Windows 10 vs 11, dst) dari `os_version` yang sudah dikirim sejak v2
- [ ] Chart "Top Combo Stack" — kombinasi versi PHP + MySQL yang paling umum dipakai bersamaan (dari metadata event `service_started`)
- [ ] (Opsional, lanjutan) Rata-rata RAM/CPU device jika data ini nantinya ditambahkan ke payload telemetry — evaluasi kebutuhan sebelum menambah pengumpulan data baru

---

## Fase 3.6 — Product/Business Funnel

**Tujuan:** Melihat di titik mana user paling banyak "hilang" dalam perjalanan memakai Rezure.

### Tasks
- [ ] Definisikan tahapan funnel: download → install (first `app_opened`) → aktif 7 hari (masih ada heartbeat setelah 7 hari)
- [ ] Query & visualisasi funnel drop-off antar tahap
- [ ] (Opsional) Tag `utm_source` pada link download di website, kirim sebagai bagian dari event `app_opened` pertama kali — untuk tahu channel distribusi paling efektif (GitHub, website, forum, dll)

---

## Fase 3.7 — Public Aggregate Endpoint (Opsional)

**Tujuan:** Data agregat non-sensitif bisa dikonsumsi pihak lain (misal ditampilkan di website).

### Tasks
- [ ] Endpoint `GET /api/v1/stats/public` — hanya angka agregat (misal total active users), tanpa data granular
- [ ] Rate limiting ketat pada endpoint ini
- [ ] Review sebelum publish: pastikan tidak ada data yang bisa disalahgunakan kompetitor atau melanggar privasi

---

## Prinsip Privasi (berlaku di semua fase ini)

- Level lokasi cukup **negara**, tidak sampai kota/koordinat presisi
- IP address tidak pernah disimpan mentah — hanya hasil geolocation (`country_code`)
- Semua insight berbasis data agregat & anonim (per `device_id`, bukan identitas personal)

---

## Dependency ke Proyek Lain

Fase-fase di roadmap ini **tidak membutuhkan perubahan apapun di sisi Rezure desktop app** — semua data granular (waktu, OS, metadata service) sudah terkirim sejak fondasi telemetry v2. Geolocation diproses di sisi server dari IP request yang masuk, bukan dikirim dari client. Fase 3.6 (funnel dengan `utm_source`) opsional membutuhkan penyesuaian kecil di link download `rezure-website`, bukan di app.

## Urutan Pengerjaan yang Disarankan

1. Fase 3.1 → 3.2 (fondasi data & performa, harus lebih dulu)
2. Fase 3.3 (traffic insight — value paling cepat terlihat)
3. Fase 3.4 (butuh histori data lebih panjang untuk retention/cohort bermakna)
4. Fase 3.5
5. Fase 3.6
6. Fase 3.7 (opsional, evaluasi kebutuhan dulu sebelum expose publik)
