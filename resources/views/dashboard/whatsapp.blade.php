<x-dashboard-layout title="Whats App" subtitle="Kelola koneksi akun WhatsApp untuk pengiriman pesan otomatis dan notifikasi.">
    <x-slot:actions>
        <button
            type="button"
            id="btn-toggle-qr"
            onclick="toggleQrSection()"
            class="inline-flex items-center gap-2 rounded-lg bg-brand px-3.5 py-2 text-xs font-medium text-white shadow-xs transition-opacity hover:opacity-90"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14M12 5v14" />
            </svg>
            <span>Tautkan WhatsApp Baru</span>
        </button>

        <a
            href="{{ $gowaUrl }}"
            target="_blank"
            rel="noreferrer"
            class="inline-flex items-center gap-2 rounded-lg border border-border bg-surface px-3.5 py-2 text-xs font-medium text-foreground transition-colors hover:bg-surface-raised hover:text-brand"
        >
            <span>Open GoWA</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
                <polyline points="15 3 21 3 21 9" />
                <line x1="10" y1="14" x2="21" y2="3" />
            </svg>
        </a>
    </x-slot:actions>

    <div class="space-y-6">
        {{-- Section: Panel Pairing (QR Code / Nomor WA) --}}
        <div id="pairing-section" class="{{ count($devices) > 0 ? 'hidden' : '' }} rounded-xl border border-border bg-surface p-6 shadow-sm transition-all">
            <div class="flex flex-col gap-4 border-b border-border pb-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-foreground">Tautkan Perangkat WhatsApp</h2>
                        <p class="mt-0.5 text-xs text-muted">Pilih metode penautan menggunakan Scan QR Code atau Nomor WhatsApp.</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    {{-- Tab Switcher --}}
                    <div class="inline-flex rounded-lg border border-border bg-surface-raised p-1">
                        <button
                            type="button"
                            id="tab-btn-qr"
                            onclick="switchPairingTab('qr')"
                            class="inline-flex items-center gap-1.5 rounded-md bg-surface px-3 py-1.5 text-xs font-medium text-foreground shadow-xs transition-all"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="5" height="5" x="3" y="3" rx="1" />
                                <rect width="5" height="5" x="16" y="3" rx="1" />
                                <rect width="5" height="5" x="3" y="16" rx="1" />
                                <path d="M21 16h-3a2 2 0 0 0-2 2v3M21 21v.01M12 7v3a2 2 0 0 1-2 2H7M3 12h.01M12 3h.01M12 16v.01M16 12h1M21 12v.01M12 21v-1" />
                            </svg>
                            <span>Scan QR Code</span>
                        </button>

                        <button
                            type="button"
                            id="tab-btn-phone"
                            onclick="switchPairingTab('phone')"
                            class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium text-muted transition-all hover:text-foreground"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
                            </svg>
                            <span>Nomor WhatsApp</span>
                        </button>
                    </div>

                    <button
                        type="button"
                        onclick="toggleQrSection()"
                        id="btn-close-pairing"
                        class="{{ count($devices) > 0 ? '' : 'hidden' }} rounded-lg border border-border p-1.5 text-muted hover:bg-surface-raised hover:text-foreground"
                        title="Tutup Panel"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            {{-- TAB 1: Scan QR Code --}}
            <div id="tab-content-qr" class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-12">
                {{-- Box QR --}}
                <div class="flex flex-col items-center justify-center lg:col-span-5">
                    <div class="relative flex min-h-[290px] min-w-[290px] flex-col items-center justify-center rounded-2xl border border-neutral-200 bg-white p-5 shadow-inner">
                        {{-- Loading Spinner --}}
                        <div id="qr-loading" class="flex flex-col items-center justify-center py-10">
                            <svg class="h-10 w-10 animate-spin text-brand" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <p class="mt-3 text-xs font-medium text-neutral-600">Membuat Kode QR...</p>
                        </div>

                        {{-- QR Image --}}
                        <img
                            id="qr-image"
                            src=""
                            alt="WhatsApp QR Code"
                            class="hidden h-60 w-60 select-none object-contain"
                        />

                        {{-- Expired State Overlay --}}
                        <div id="qr-expired" class="absolute inset-0 flex hidden flex-col items-center justify-center rounded-2xl bg-white/95 p-6 text-center backdrop-blur-xs">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10" />
                                    <polyline points="12 6 12 12 16 14" />
                                </svg>
                            </div>
                            <h3 class="mt-3 text-sm font-semibold text-neutral-900">QR Code Kedaluwarsa</h3>
                            <p class="mt-1 text-xs text-neutral-600">QR habis masa berlakunya demi keamanan.</p>
                            <button
                                type="button"
                                onclick="fetchQr(true)"
                                class="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-brand px-3 py-1.5 text-xs font-medium text-white shadow-xs transition-opacity hover:opacity-90"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67" />
                                </svg>
                                <span>Muat Ulang QR</span>
                            </button>
                        </div>

                        {{-- Error State --}}
                        <div id="qr-error" class="hidden flex-col items-center justify-center p-6 text-center">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-red-100 text-red-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10" />
                                    <line x1="12" y1="8" x2="12" y2="12" />
                                    <line x1="12" y1="16" x2="12.01" y2="16" />
                                </svg>
                            </div>
                            <h3 id="error-title" class="mt-3 text-sm font-semibold text-neutral-900">Gagal Memuat QR</h3>
                            <p id="error-desc" class="mt-1 text-xs text-neutral-600">Terjadi kendala saat menghubungi server.</p>
                            <button
                                type="button"
                                onclick="fetchQr(true)"
                                class="mt-4 inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs font-medium text-neutral-700 shadow-xs transition-colors hover:bg-neutral-50"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67" />
                                </svg>
                                <span>Coba Lagi</span>
                            </button>
                        </div>
                    </div>

                    {{-- Timer controls --}}
                    <div id="timer-container" class="mt-3 flex hidden w-full max-w-[290px] items-center justify-between text-xs text-muted">
                        <span class="flex items-center gap-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-subtle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10" />
                                <polyline points="12 6 12 12 16 14" />
                            </svg>
                            <span>Kedaluwarsa: <strong id="countdown-sec" class="text-foreground">30</strong> detik</span>
                        </span>

                        <button
                            type="button"
                            onclick="fetchQr(true)"
                            class="inline-flex items-center gap-1 text-xs font-medium text-brand hover:underline"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67" />
                            </svg>
                            <span>Refresh</span>
                        </button>
                    </div>
                </div>

                {{-- Panduan Scan QR --}}
                <div class="flex flex-col justify-center lg:col-span-7">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-muted">Petunjuk Scan QR di WhatsApp Mobile</h3>
                    <ol class="mt-3 space-y-3">
                        <li class="flex items-start gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-border bg-surface-raised text-xs font-semibold text-foreground">1</span>
                            <p class="text-xs text-muted"><strong class="text-foreground">Buka aplikasi WhatsApp</strong> di smartphone Anda.</p>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-border bg-surface-raised text-xs font-semibold text-foreground">2</span>
                            <p class="text-xs text-muted">Ketuk ikon <strong class="text-foreground">Titik Tiga ⋮</strong> (Android) atau menu <strong class="text-foreground">Pengaturan / Settings</strong> (iOS).</p>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-border bg-surface-raised text-xs font-semibold text-foreground">3</span>
                            <p class="text-xs text-muted">Pilih menu <strong class="text-foreground">Perangkat Tertaut (Linked Devices)</strong>, lalu ketuk tombol <strong class="text-foreground">Tautkan Perangkat</strong>.</p>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-border bg-surface-raised text-xs font-semibold text-foreground">4</span>
                            <p class="text-xs text-muted"><strong class="text-foreground">Arahkan kamera smartphone</strong> ke kode QR di samping hingga terdeteksi. Akun akan otomatis terhubung!</p>
                        </li>
                    </ol>
                </div>
            </div>

            {{-- TAB 2: Nomor WhatsApp (Pairing Code) --}}
            <div id="tab-content-phone" class="mt-6 hidden grid grid-cols-1 gap-6 lg:grid-cols-12">
                {{-- Form Input / Hasil Kode Pairing --}}
                <div class="flex flex-col justify-center lg:col-span-5">
                    {{-- State Form Input Nomor --}}
                    <div id="phone-form-state" class="rounded-2xl border border-border bg-surface-raised/40 p-5">
                        <label for="input-phone" class="block text-xs font-medium text-foreground">Nomor WhatsApp Anda</label>
                        <p class="mt-0.5 text-xs text-muted">Masukkan nomor HP akun WhatsApp yang ingin ditautkan.</p>

                        <div class="mt-3 flex rounded-lg border border-border bg-surface shadow-xs focus-within:border-brand">
                            <span class="inline-flex items-center border-r border-border px-3 text-xs font-medium text-muted">
                                +62
                            </span>
                            <input
                                type="tel"
                                id="input-phone"
                                placeholder="81234567890"
                                class="w-full rounded-r-lg bg-transparent px-3 py-2 text-sm text-foreground placeholder-subtle focus:outline-hidden"
                            />
                        </div>

                        <div id="phone-error" class="mt-2 hidden text-xs text-red-500"></div>

                        <button
                            type="button"
                            id="btn-get-code"
                            onclick="submitPairingPhone()"
                            class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-brand px-4 py-2 text-xs font-medium text-white shadow-xs transition-opacity hover:opacity-90"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="18" height="18" x="3" y="3" rx="2" />
                                <path d="m9 12 2 2 4-4" />
                            </svg>
                            <span>Dapatkan Kode Pairing</span>
                        </button>
                    </div>

                    {{-- State Hasil Kode Pairing --}}
                    <div id="phone-code-state" class="relative hidden flex-col items-center justify-center rounded-2xl border border-border bg-surface-raised p-6 text-center shadow-inner">
                        <p class="text-xs font-medium text-muted">Kode Pairing Anda:</p>
                        
                        <div class="mt-3 flex items-center justify-center gap-2">
                            <span id="display-pair-code" class="rounded-xl border border-brand/30 bg-brand/10 px-5 py-3 font-mono text-2xl font-bold tracking-widest text-brand select-all">
                                ---- - ----
                            </span>
                            <button
                                type="button"
                                onclick="copyPairCode()"
                                class="rounded-lg border border-border bg-surface p-2.5 text-muted hover:bg-surface-raised hover:text-foreground"
                                title="Salin Kode"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect width="14" height="14" x="8" y="8" rx="2" ry="2" />
                                    <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2" />
                                </svg>
                            </button>
                        </div>
                        <p id="copy-feedback" class="mt-1.5 hidden text-[11px] text-emerald-500 font-medium">Tersalin ke clipboard!</p>

                        <div class="mt-4 flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-amber-500 animate-pulse"></span>
                            <p class="text-xs text-muted">Menunggu konfirmasi di HP Anda...</p>
                        </div>

                        <button
                            type="button"
                            onclick="resetPhonePairing()"
                            class="mt-4 text-xs font-medium text-brand hover:underline"
                        >
                            Ganti Nomor Lain
                        </button>
                    </div>
                </div>

                {{-- Panduan Pairing via Nomor HP --}}
                <div class="flex flex-col justify-center lg:col-span-7">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-muted">Petunjuk Memasukkan Kode di WhatsApp</h3>
                    <ol class="mt-3 space-y-3">
                        <li class="flex items-start gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-border bg-surface-raised text-xs font-semibold text-foreground">1</span>
                            <p class="text-xs text-muted"><strong class="text-foreground">Buka WhatsApp</strong> di smartphone Anda.</p>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-border bg-surface-raised text-xs font-semibold text-foreground">2</span>
                            <p class="text-xs text-muted">Buka menu <strong class="text-foreground">Perangkat Tertaut (Linked Devices)</strong>, lalu ketuk <strong class="text-foreground">Tautkan Perangkat</strong>.</p>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-border bg-surface-raised text-xs font-semibold text-foreground">3</span>
                            <p class="text-xs text-muted">Di bagian bawah layar pemindai QR, pilih <strong class="text-foreground">"Tautkan dengan nomor telepon saja" (Link with phone number instead)</strong>.</p>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-border bg-surface-raised text-xs font-semibold text-foreground">4</span>
                            <p class="text-xs text-muted"><strong class="text-foreground">Masukkan kode pairing 8 karakter</strong> yang tampil di samping. Akun akan otomatis terhubung!</p>
                        </li>
                    </ol>
                </div>
            </div>
        </div>

        {{-- Section: List Akun WhatsApp Terhubung --}}
        <div class="rounded-xl border border-border bg-surface shadow-sm">
            <div class="flex flex-col gap-2 border-b border-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h2 class="text-base font-semibold text-foreground">Daftar Akun WhatsApp Terhubung</h2>
                        <span id="device-count" class="rounded-full bg-surface-raised px-2.5 py-0.5 text-xs font-medium text-subtle">
                            {{ count($devices) }} Perangkat
                        </span>
                    </div>
                    <p class="mt-0.5 text-xs text-muted">Koneksi WhatsApp yang aktif digunakan oleh sistem.</p>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        id="btn-refresh-devices"
                        onclick="refreshDeviceList()"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface-raised px-3 py-1.5 text-xs font-medium text-muted transition-colors hover:bg-surface hover:text-foreground disabled:opacity-60"
                    >
                        <svg id="icon-refresh-devices" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67" />
                        </svg>
                        <span id="text-refresh-devices">Refresh List</span>
                    </button>
                    <span id="refresh-feedback" class="text-xs text-emerald-500 font-medium hidden">Diperbarui!</span>
                </div>
            </div>

            {{-- Tabel / List Perangkat --}}
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-border text-xs uppercase tracking-wide text-subtle">
                            <th class="px-5 py-3 font-medium">Akun WhatsApp</th>
                            <th class="px-5 py-3 font-medium">Device ID</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3 font-medium">Waktu Dibuat</th>
                            <th class="px-5 py-3 text-right font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="devices-tbody" class="divide-y divide-border">
                        @forelse ($devices as $device)
                            @php
                                $state = strtolower($device['state'] ?? $device['status'] ?? '');
                                $isConnected = in_array($state, ['connected', 'authenticated']);
                                $displayName = $device['display_name'] ?? 'Akun WhatsApp';
                                $deviceId = $device['id'] ?? '-';
                                $createdAt = isset($device['created_at']) ? \Carbon\Carbon::parse($device['created_at'])->translatedFormat('d M Y, H:i') : '-';
                            @endphp
                            <tr id="device-row-{{ $deviceId }}" class="hover:bg-surface-raised/40 transition-colors">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full {{ $isConnected ? 'bg-emerald-500/10 text-emerald-600' : 'bg-neutral-500/10 text-neutral-500' }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="font-medium text-foreground">{{ $displayName }}</p>
                                            <p class="text-xs text-muted">WhatsApp Mobile</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 font-mono text-xs text-subtle">
                                    <span class="truncate block max-w-[180px]" title="{{ $deviceId }}">{{ $deviceId }}</span>
                                </td>
                                <td class="px-5 py-4">
                                    @if ($isConnected)
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-xs font-medium text-emerald-500">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            Terhubung
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-neutral-500/20 bg-neutral-500/10 px-2.5 py-1 text-xs font-medium text-neutral-400">
                                            <span class="h-1.5 w-1.5 rounded-full bg-neutral-400"></span>
                                            Terputus
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-xs text-subtle">
                                    {{ $createdAt }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        @if ($isConnected)
                                            <button
                                                type="button"
                                                onclick="logoutDevice('{{ $deviceId }}', '{{ $displayName }}', 'logout')"
                                                class="rounded-lg border border-border bg-surface px-2.5 py-1.5 text-xs text-muted transition-colors hover:border-red-500/40 hover:bg-red-500/10 hover:text-red-500"
                                                title="Putuskan koneksi WhatsApp ini"
                                            >
                                                Putuskan
                                            </button>
                                        @else
                                            <button
                                                type="button"
                                                onclick="openQrSection('{{ $deviceId }}')"
                                                class="rounded-lg bg-brand px-2.5 py-1.5 text-xs font-medium text-white shadow-xs transition-opacity hover:opacity-90"
                                            >
                                                Tautkan Ulang
                                            </button>
                                            <button
                                                type="button"
                                                onclick="logoutDevice('{{ $deviceId }}', '{{ $displayName }}', 'delete')"
                                                class="rounded-lg border border-border bg-surface px-2.5 py-1.5 text-xs text-muted transition-colors hover:border-red-500/40 hover:bg-red-500/10 hover:text-red-500"
                                                title="Hapus slot perangkat ini dari GoWA"
                                            >
                                                Hapus
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr id="empty-row">
                                <td colspan="5" class="px-5 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-surface-raised text-muted">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
                                            </svg>
                                        </div>
                                        <h3 class="mt-3 text-sm font-semibold text-foreground">Belum ada akun WhatsApp terhubung</h3>
                                        <p class="mt-1 text-xs text-muted max-w-sm">Tautkan akun WhatsApp mobile Anda menggunakan QR Code atau Nomor WhatsApp.</p>
                                        <button
                                            type="button"
                                            onclick="openQrSection()"
                                            class="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-brand px-3.5 py-2 text-xs font-medium text-white shadow-xs transition-opacity hover:opacity-90"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M5 12h14M12 5v14" />
                                            </svg>
                                            <span>Tautkan WhatsApp Sekarang</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Script Interaktif Vanilla JS --}}
    <script>
        (function () {
            let countdownInterval = null;
            let statusInterval = null;
            let remainingSeconds = 30;
            let currentTab = 'qr';
            let activePairCode = '';

            const pairingSection = document.getElementById('pairing-section');
            const btnClosePairing = document.getElementById('btn-close-pairing');
            const tabBtnQr = document.getElementById('tab-btn-qr');
            const tabBtnPhone = document.getElementById('tab-btn-phone');
            const tabContentQr = document.getElementById('tab-content-qr');
            const tabContentPhone = document.getElementById('tab-content-phone');

            const qrImage = document.getElementById('qr-image');
            const qrLoading = document.getElementById('qr-loading');
            const qrExpired = document.getElementById('qr-expired');
            const qrError = document.getElementById('qr-error');
            const timerContainer = document.getElementById('timer-container');
            const countdownSec = document.getElementById('countdown-sec');

            const statusBadge = document.getElementById('status-badge');
            const statusDot = document.getElementById('status-dot');
            const statusText = document.getElementById('status-text');
            const errorTitle = document.getElementById('error-title');
            const errorDesc = document.getElementById('error-desc');
            const devicesTbody = document.getElementById('devices-tbody');
            const deviceCount = document.getElementById('device-count');

            const phoneFormState = document.getElementById('phone-form-state');
            const phoneCodeState = document.getElementById('phone-code-state');
            const inputPhone = document.getElementById('input-phone');
            const phoneError = document.getElementById('phone-error');
            const btnGetCode = document.getElementById('btn-get-code');
            const displayPairCode = document.getElementById('display-pair-code');
            const copyFeedback = document.getElementById('copy-feedback');

            window.switchPairingTab = function (tab) {
                currentTab = tab;
                if (tab === 'qr') {
                    tabBtnQr.className = 'inline-flex items-center gap-1.5 rounded-md bg-surface px-3 py-1.5 text-xs font-medium text-foreground shadow-xs transition-all';
                    tabBtnPhone.className = 'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium text-muted transition-all hover:text-foreground';
                    tabContentQr.classList.remove('hidden');
                    tabContentPhone.classList.add('hidden');
                    fetchQr();
                } else {
                    tabBtnPhone.className = 'inline-flex items-center gap-1.5 rounded-md bg-surface px-3 py-1.5 text-xs font-medium text-foreground shadow-xs transition-all';
                    tabBtnQr.className = 'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium text-muted transition-all hover:text-foreground';
                    tabContentPhone.classList.remove('hidden');
                    tabContentQr.classList.add('hidden');
                    clearInterval(countdownInterval);
                    inputPhone.focus();
                }
            };

            window.toggleQrSection = function () {
                if (pairingSection.classList.contains('hidden')) {
                    openQrSection();
                } else {
                    closeQrSection();
                }
            };

            window.openQrSection = function (deviceId) {
                pairingSection.classList.remove('hidden');
                if (btnClosePairing) btnClosePairing.classList.remove('hidden');
                pairingSection.scrollIntoView({ behavior: 'smooth' });

                if (currentTab === 'qr') {
                    fetchQr(true, deviceId);
                }
            };

            window.closeQrSection = function () {
                clearInterval(countdownInterval);
                clearInterval(statusInterval);
                pairingSection.classList.add('hidden');
            };

            function resetQrStates() {
                clearInterval(countdownInterval);
                qrLoading.classList.remove('hidden');
                qrImage.classList.add('hidden');
                qrExpired.classList.add('hidden');
                qrError.classList.add('hidden');
                timerContainer.classList.add('hidden');

                statusBadge.className = 'inline-flex items-center gap-1.5 rounded-full border border-amber-500/20 bg-amber-500/10 px-2.5 py-1 text-xs font-medium text-amber-500';
                statusDot.className = 'h-2 w-2 rounded-full bg-amber-500 animate-pulse';
                statusText.textContent = 'Memuat QR...';
            }

            function startCountdown(durationMs) {
                clearInterval(countdownInterval);
                remainingSeconds = Math.max(5, Math.floor((durationMs || 30000) / 1000));
                countdownSec.textContent = remainingSeconds;
                timerContainer.classList.remove('hidden');

                countdownInterval = setInterval(function () {
                    remainingSeconds--;
                    countdownSec.textContent = remainingSeconds;

                    if (remainingSeconds <= 0) {
                        clearInterval(countdownInterval);
                        qrExpired.classList.remove('hidden');
                        statusBadge.className = 'inline-flex items-center gap-1.5 rounded-full border border-neutral-500/20 bg-neutral-500/10 px-2.5 py-1 text-xs font-medium text-neutral-500';
                        statusDot.className = 'h-2 w-2 rounded-full bg-neutral-400';
                        statusText.textContent = 'QR Kedaluwarsa';
                    }
                }, 1000);
            }

            function pollStatus(deviceId) {
                clearInterval(statusInterval);
                let statusUrl = '/dashboard/whatsapp/status';
                if (deviceId) {
                    statusUrl += "?device_id=" + encodeURIComponent(deviceId);
                }

                statusInterval = setInterval(function () {
                    fetch(statusUrl, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                        .then(function (res) { return res.json(); })
                        .then(function (data) {
                            if (data && data.connected) {
                                clearInterval(statusInterval);
                                clearInterval(countdownInterval);

                                statusBadge.className = 'inline-flex items-center gap-1.5 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-xs font-medium text-emerald-500';
                                statusDot.className = 'h-2 w-2 rounded-full bg-emerald-500';
                                statusText.textContent = 'Terhubung!';

                                setTimeout(function () {
                                    refreshDeviceList();
                                    closeQrSection();
                                }, 1500);
                            }
                        })
                        .catch(function () {});
                }, 3000);
            }

            window.fetchQr = function (force, deviceId) {
                resetQrStates();

                let url = '/dashboard/whatsapp/qr';
                if (deviceId) {
                    url += "?device_id=" + encodeURIComponent(deviceId);
                }

                fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(function (res) {
                        return res.json().then(function (data) {
                            return { ok: res.ok, status: res.status, data: data };
                        });
                    })
                    .then(function (result) {
                        if (result.ok && result.data && result.data.qr_link) {
                            // Preload image sehingga tidak ada jeda kosong atau flickering
                            const img = new Image();
                            img.onload = function () {
                                qrImage.src = result.data.qr_link;
                                qrLoading.classList.add('hidden');
                                qrImage.classList.remove('hidden');

                                statusText.textContent = 'Scan di WhatsApp';
                                startCountdown(result.data.qr_duration_ms);
                                pollStatus(result.data.device_id || deviceId);
                            };
                            img.onerror = function () {
                                // Fallback jika base64 gagal
                                qrImage.src = result.data.raw_qr_link || result.data.qr_link;
                                qrLoading.classList.add('hidden');
                                qrImage.classList.remove('hidden');

                                statusText.textContent = 'Scan di WhatsApp';
                                startCountdown(result.data.qr_duration_ms);
                                pollStatus(result.data.device_id || deviceId);
                            };
                            img.src = result.data.qr_link;
                        } else {
                            qrLoading.classList.add('hidden');
                            qrError.classList.remove('hidden');
                            statusBadge.className = 'inline-flex items-center gap-1.5 rounded-full border border-red-500/20 bg-red-500/10 px-2.5 py-1 text-xs font-medium text-red-500';
                            statusDot.className = 'h-2 w-2 rounded-full bg-red-500';
                            statusText.textContent = 'Gagal';

                            if (result.status === 401) {
                                errorTitle.textContent = 'Perlu Autentikasi';
                                errorDesc.textContent = result.data.message || 'Pastikan GOWA_USER dan GOWA_PASSWORD sudah diatur di .env.';
                            } else {
                                errorTitle.textContent = 'Gagal Memuat QR';
                                errorDesc.textContent = (result.data && result.data.message) || 'Tidak dapat mengambil QR dari server.';
                            }
                        }
                    })
                    .catch(function () {
                        qrLoading.classList.add('hidden');
                        qrError.classList.remove('hidden');
                        errorTitle.textContent = 'Koneksi Terputus';
                        errorDesc.textContent = 'Gagal terhubung ke server GoWA.';
                        statusText.textContent = 'Error';
                    });
            };

            window.submitPairingPhone = function () {
                phoneError.classList.add('hidden');
                const rawPhone = inputPhone.value.trim();
                if (!rawPhone) {
                    phoneError.textContent = 'Harap masukkan nomor WhatsApp.';
                    phoneError.classList.remove('hidden');
                    return;
                }

                btnGetCode.disabled = true;
                btnGetCode.classList.add('opacity-75');
                btnGetCode.innerHTML = `
                    <svg class="h-3.5 w-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Meminta Kode...</span>
                `;

                fetch('/dashboard/whatsapp/pairing-code', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ phone: rawPhone })
                })
                .then(function (res) {
                    return res.json().then(function (data) {
                        return { ok: res.ok, status: res.status, data: data };
                    });
                })
                .then(function (result) {
                    btnGetCode.disabled = false;
                    btnGetCode.classList.remove('opacity-75');
                    btnGetCode.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="18" height="18" x="3" y="3" rx="2" />
                            <path d="m9 12 2 2 4-4" />
                        </svg>
                        <span>Dapatkan Kode Pairing</span>
                    `;

                    if (result.ok && result.data && result.data.pair_code) {
                        activePairCode = result.data.pair_code;
                        displayPairCode.textContent = result.data.pair_code;
                        phoneFormState.classList.add('hidden');
                        phoneCodeState.classList.remove('hidden');
                        pollStatus();
                    } else {
                        phoneError.textContent = (result.data && result.data.message) || 'Gagal mendapatkan kode pairing.';
                        phoneError.classList.remove('hidden');
                    }
                })
                .catch(function () {
                    btnGetCode.disabled = false;
                    btnGetCode.classList.remove('opacity-75');
                    btnGetCode.innerHTML = `<span>Dapatkan Kode Pairing</span>`;
                    phoneError.textContent = 'Gagal terhubung ke server.';
                    phoneError.classList.remove('hidden');
                });
            };

            window.resetPhonePairing = function () {
                phoneCodeState.classList.add('hidden');
                phoneFormState.classList.remove('hidden');
                inputPhone.focus();
            };

            window.copyPairCode = function () {
                if (!activePairCode) return;
                navigator.clipboard.writeText(activePairCode).then(function () {
                    copyFeedback.classList.remove('hidden');
                    setTimeout(function () {
                        copyFeedback.classList.add('hidden');
                    }, 2000);
                });
            };

            window.refreshDeviceList = function () {
                const btn = document.getElementById('btn-refresh-devices');
                const icon = document.getElementById('icon-refresh-devices');
                const text = document.getElementById('text-refresh-devices');
                const feedback = document.getElementById('refresh-feedback');

                if (btn) btn.disabled = true;
                if (icon) icon.classList.add('animate-spin');
                if (text) text.textContent = 'Memuat...';
                if (feedback) feedback.classList.add('hidden');

                fetch('/dashboard/whatsapp/devices', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(function (res) {
                        return res.json().then(function (data) {
                            return { ok: res.ok, status: res.status, data: data };
                        });
                    })
                    .then(function (result) {
                        if (result.ok && result.data && Array.isArray(result.data.devices)) {
                            renderDevices(result.data.devices);
                            if (feedback) {
                                feedback.classList.remove('hidden');
                                setTimeout(function () {
                                    feedback.classList.add('hidden');
                                }, 2000);
                            }
                        } else {
                            alert((result.data && result.data.message) || 'Gagal memuat daftar perangkat.');
                        }
                    })
                    .catch(function (err) {
                        console.error('Error refreshing devices:', err);
                        alert('Gagal menghubungi server untuk memperbarui daftar perangkat.');
                    })
                    .finally(function () {
                        if (btn) btn.disabled = false;
                        if (icon) icon.classList.remove('animate-spin');
                        if (text) text.textContent = 'Refresh List';
                    });
            };

            function renderDevices(devices) {
                deviceCount.textContent = devices.length + ' Perangkat';

                if (devices.length === 0) {
                    devicesTbody.innerHTML = `
                        <tr id="empty-row">
                            <td colspan="5" class="px-5 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-surface-raised text-muted">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
                                        </svg>
                                    </div>
                                    <h3 class="mt-3 text-sm font-semibold text-foreground">Belum ada akun WhatsApp terhubung</h3>
                                    <p class="mt-1 text-xs text-muted max-w-sm">Tautkan akun WhatsApp mobile Anda menggunakan QR Code atau Nomor WhatsApp.</p>
                                    <button
                                        type="button"
                                        onclick="openQrSection()"
                                        class="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-brand px-3.5 py-2 text-xs font-medium text-white shadow-xs transition-opacity hover:opacity-90"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M5 12h14M12 5v14" />
                                        </svg>
                                        <span>Tautkan WhatsApp Sekarang</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                    return;
                }

                let html = '';
                devices.forEach(function (device) {
                    const state = (device.state || device.status || '').toLowerCase();
                    const isConnected = state === 'connected' || state === 'authenticated';
                    const displayName = device.display_name || 'Akun WhatsApp';
                    const deviceId = device.id || '-';
                    const createdAt = device.created_at ? new Date(device.created_at).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-';

                    html += `
                        <tr id="device-row-${deviceId}" class="hover:bg-surface-raised/40 transition-colors">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full ${isConnected ? 'bg-emerald-500/10 text-emerald-600' : 'bg-neutral-500/10 text-neutral-500'}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="font-medium text-foreground">${escapeHtml(displayName)}</p>
                                        <p class="text-xs text-muted">WhatsApp Mobile</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 font-mono text-xs text-subtle">
                                <span class="truncate block max-w-[180px]" title="${escapeHtml(deviceId)}">${escapeHtml(deviceId)}</span>
                            </td>
                            <td class="px-5 py-4">
                                ${isConnected ? `
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-xs font-medium text-emerald-500">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        Terhubung
                                    </span>
                                ` : `
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-neutral-500/20 bg-neutral-500/10 px-2.5 py-1 text-xs font-medium text-neutral-400">
                                        <span class="h-1.5 w-1.5 rounded-full bg-neutral-400"></span>
                                        Terputus
                                    </span>
                                `}
                            </td>
                            <td class="px-5 py-4 text-xs text-subtle">
                                ${createdAt}
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    ${isConnected ? `
                                        <button
                                            type="button"
                                            onclick="logoutDevice('${escapeHtml(deviceId)}', '${escapeHtml(displayName)}', 'logout')"
                                            class="rounded-lg border border-border bg-surface px-2.5 py-1.5 text-xs text-muted transition-colors hover:border-red-500/40 hover:bg-red-500/10 hover:text-red-500"
                                            title="Putuskan koneksi WhatsApp ini"
                                        >
                                            Putuskan
                                        </button>
                                    ` : `
                                        <button
                                            type="button"
                                            onclick="openQrSection('${escapeHtml(deviceId)}')"
                                            class="rounded-lg bg-brand px-2.5 py-1.5 text-xs font-medium text-white shadow-xs transition-opacity hover:opacity-90"
                                        >
                                            Tautkan Ulang
                                        </button>
                                        <button
                                            type="button"
                                            onclick="logoutDevice('${escapeHtml(deviceId)}', '${escapeHtml(displayName)}', 'delete')"
                                            class="rounded-lg border border-border bg-surface px-2.5 py-1.5 text-xs text-muted transition-colors hover:border-red-500/40 hover:bg-red-500/10 hover:text-red-500"
                                            title="Hapus slot perangkat ini dari GoWA"
                                        >
                                            Hapus
                                        </button>
                                    `}
                                </div>
                            </td>
                        </tr>
                    `;
                });

                devicesTbody.innerHTML = html;
            }

            window.logoutDevice = function (deviceId, name, action) {
                action = action || 'logout';
                const actionText = action === 'delete' ? 'menghapus slot perangkat' : 'memutuskan koneksi WhatsApp untuk';
                if (!confirm('Apakah Anda yakin ingin ' + actionText + ' ' + (name || deviceId) + '?')) {
                    return;
                }

                fetch('/dashboard/whatsapp/devices/' + encodeURIComponent(deviceId) + '?action=' + encodeURIComponent(action), {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data && data.success) {
                        refreshDeviceList();
                    } else {
                        alert((data && data.message) || 'Gagal memproses permintaan.');
                    }
                })
                .catch(function () {
                    alert('Gagal menghubungi server.');
                });
            };

            function escapeHtml(str) {
                return (str || '').replace(/[&<>"']/g, function (m) {
                    return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m];
                });
            }

            // Jika belum ada perangkat terhubung, otomatis load QR pertama kali
            document.addEventListener('DOMContentLoaded', function () {
                const count = {{ count($devices) }};
                if (count === 0) {
                    fetchQr();
                }
            });
        })();
    </script>
</x-dashboard-layout>
