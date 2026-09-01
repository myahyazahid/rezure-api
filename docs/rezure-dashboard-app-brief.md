# App Brief — Rezure Dashboard (Laravel)

## Apa ini

Dashboard internal berbasis Laravel yang menerima data telemetry dari aplikasi desktop Rezure (via API), lalu menampilkannya sebagai analytics untuk dipantau oleh maintainer/developer Rezure sendiri.

**Ini bukan produk yang dipakai end-user Rezure** — ini tools internal, jadi audiens-nya cuma kamu (dan mungkin co-maintainer nanti), bukan publik.

## Target pengguna

Maintainer project Rezure — teknis, sudah familiar dengan istilah developer (versi, device, event, dll), gak butuh onboarding atau penjelasan berlebihan di UI.

## Preferensi visual

- Belum ada preferensi warna spesifik seperti Rezure desktop app — bisa disamakan (merah, modern) untuk konsistensi brand, atau dibuat netral/berbeda karena ini tools internal, bukan produk yang dipublikasikan. (Silakan tentukan bersama Claude Design.)
- Karena ini dashboard data, prioritaskan **keterbacaan angka & grafik** di atas gaya visual yang ramai

## Screen / area utama yang perlu didesain

### 1. Overview / Home
Ringkasan cepat kondisi Rezure di lapangan:
- Total active users (DAU/WAU/MAU)
- Total device terdaftar
- Tren pertumbuhan (grafik sederhana, mingguan/bulanan)

### 2. Version Adoption
- Breakdown user per versi Rezure yang terpasang (chart/tabel)
- Berguna untuk tahu kapan aman deprecate versi lama

### 3. Feature Usage
- Fitur mana yang paling sering dipakai (service mana yang paling sering di-start, quick app apa yang paling sering diinstall, dll)
- Bisa berbentuk heatmap atau bar chart sederhana

### 4. Error / Crash Reports (fase lanjutan, opsional untuk didesain sekarang)
- List error yang dilaporkan client, dengan jumlah device terdampak
- Detail per error (kapan terjadi, versi Rezure, ringkasan log)

### 5. Devices (opsional)
- List device/installasi (device_id, versi, terakhir aktif) — untuk debugging saat perlu, bukan fitur utama yang sering diakses

## Sifat teknis yang perlu dipertimbangkan dalam desain

- Ini dashboard **read-only** buat sebagian besar layar — fokus ke menyajikan data dengan jelas, bukan banyak form/input
- Data yang ditampilkan adalah **agregat & anonim** (berbasis device_id, bukan akun user) — tidak ada nama/identitas personal yang perlu ditonjolkan di UI
- Kemungkinan diakses dari desktop browser saja (bukan mobile-first), karena ini tools internal
- Grafik/chart adalah elemen utama — perlu dipikirkan library charting yang dipakai (Chart.js/ApexCharts) dan bagaimana data kosong/belum ada ditampilkan (empty state)

## Yang TIDAK perlu didesain sekarang

- Sistem autentikasi kompleks (multi-user role, dll) — kemungkinan cukup login sederhana untuk maintainer
- Fitur license/feature flag management (masih rencana jauh, belum prioritas)
- Tampilan untuk end-user Rezure — dashboard ini murni internal
