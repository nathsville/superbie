@php
    $title = 'Cuaca — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Informasi Cuaca</x-slot:header>
    <x-slot:breadcrumb>Konfigurasi ambang dan sinkronisasi data BMKG</x-slot:breadcrumb>

    <div class="w-full max-w-full space-y-6 card-enter">
        @if (session('success'))
            <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                {{ session('error') }}
            </div>
        @endif

        {{-- ── Sync panel ───────────────────────────────────────────────────── --}}
        <div class="w-full bg-white rounded-2xl border border-line shadow-sm p-5 sm:p-6">
            <div class="flex items-start justify-between gap-4 flex-wrap mb-5">
                <div>
                    <h2 class="text-base font-bold text-ink">Sinkronisasi Data BMKG</h2>
                    <p class="text-sm text-ink-faint mt-1 leading-relaxed">
                        Sinkronisasi berjalan <span class="font-semibold text-ink-soft">otomatis setiap 3 jam</span> melalui penjadwal Laravel, dan juga dapat dijalankan <span class="font-semibold text-ink-soft">manual</span>. Keduanya memakai alur yang sama.
                    </p>
                </div>
                <form method="POST" action="{{ route('super-admin.weather.sync') }}" class="shrink-0">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold px-5 py-2.5 rounded-xl ui-animated active:scale-[0.98] shadow-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Jalankan Sinkronisasi
                    </button>
                </form>
            </div>

            {{-- 4 blok metrik statistik --}}
            <dl class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <div class="bg-page border border-line rounded-xl p-4">
                    <dt class="text-xs uppercase tracking-wider text-ink-faint font-semibold">Lokasi Pantau</dt>
                    <dd class="font-bold text-lg text-ink mt-1.5">{{ count($locations) }}</dd>
                </div>
                <div class="bg-page border border-line rounded-xl p-4">
                    <dt class="text-xs uppercase tracking-wider text-ink-faint font-semibold">Titik Tersimpan</dt>
                    <dd class="font-bold text-lg text-ink mt-1.5">
                        {{ $forecastCount }} <span class="text-xs font-medium text-ink-faint">darat</span>
                        · {{ $waveCount }} <span class="text-xs font-medium text-ink-faint">laut</span>
                    </dd>
                </div>
                <div class="bg-page border border-line rounded-xl p-4">
                    <dt class="text-xs uppercase tracking-wider text-ink-faint font-semibold">Horizon</dt>
                    <dd class="font-bold text-lg text-ink mt-1.5">{{ $forecastDays }} <span class="text-xs font-medium text-ink-faint">hari</span></dd>
                </div>
                <div class="bg-page border border-line rounded-xl p-4">
                    <dt class="text-xs uppercase tracking-wider text-ink-faint font-semibold">Sinkron Terakhir</dt>
                    <dd class="font-bold text-sm text-ink mt-1.5">
                        {{ $lastSyncedAt ? \Illuminate\Support\Carbon::parse($lastSyncedAt)->translatedFormat('d M Y, H:i') . ' WITA' : 'Belum pernah' }}
                    </dd>
                </div>
            </dl>

            @if ($sourceEnabled === false)
                <p class="mt-4 flex items-start gap-2 text-xs text-accent-700 bg-accent-50 border border-accent-200 rounded-lg px-3 py-2">
                    <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                    Sumber BMKG sedang dinonaktifkan. Sinkronisasi tidak akan menulis data.
                </p>
            @endif

            <p class="mt-4 text-xs text-ink-faint leading-relaxed">
                Catatan: kode adm4 dan endpoint BMKG telah diverifikasi terhadap respons BMKG yang sebenarnya (Kota Parepare / Ujung / Labukkang). Menambah wilayah pantau dilakukan lewat konfigurasi <span class="font-mono">config/weather.php</span> (<span class="font-mono">locations</span>). Nilai ambang di bawah masih bersifat awal dan belum tervalidasi secara resmi.
            </p>
        </div>

        {{-- ── Monitored locations (multi-location capable) ─────────────────── --}}
        <div class="w-full bg-white rounded-2xl border border-line shadow-sm overflow-hidden">
            <div class="px-5 sm:px-6 py-4 border-b border-line flex items-center gap-2.5">
                <span class="h-5 w-1.5 rounded-full bg-primary-600" aria-hidden="true"></span>
                <h2 class="text-sm font-semibold text-ink tracking-wide">Wilayah Pantau</h2>
            </div>
            <p class="px-5 sm:px-6 pt-4 text-sm text-ink-faint">
                Setiap wilayah memiliki kode <span class="font-mono text-xs">adm4</span> dan wilayah laut tersendiri, sehingga data darat maupun gelombang tidak tercampur.
            </p>
            <div class="overflow-x-auto mt-4">
                <table class="w-full text-sm">
                    <caption class="sr-only">Daftar wilayah pantau beserta kode adm4, wilayah laut, dan jumlah titik data darat serta laut</caption>
                    <thead class="bg-page text-ink-soft text-xs uppercase tracking-wider">
                        <tr>
                            <th scope="col" class="text-left px-4 py-3.5 font-semibold min-w-[15rem]">Wilayah</th>
                            <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">adm4</th>
                            <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Wilayah Laut</th>
                            <th scope="col" class="text-center px-4 py-3.5 font-semibold whitespace-nowrap">Darat</th>
                            <th scope="col" class="text-center px-4 py-3.5 font-semibold whitespace-nowrap">Laut</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-sunken">
                        @foreach ($locations as $loc)
                            <tr class="hover:bg-page transition-colors">
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-semibold text-ink">{{ $loc['name'] }}</span>
                                        @if ($loc['is_default'])
                                            <span class="inline-flex items-center gap-1 rounded-full text-[10px] font-semibold bg-primary-50 text-primary-700 border border-primary-100 px-2 py-0.5">
                                                <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                                Default
                                            </span>
                                        @endif
                                    </div>
                                    @if ($loc['district'] || $loc['village'])
                                        <span class="block text-xs text-ink-faint mt-0.5">
                                            {{ trim(implode(' / ', array_filter([$loc['district'], $loc['village']]))) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="font-mono text-xs text-ink-soft bg-page px-2 py-1 rounded-lg border border-line">{{ $loc['adm4'] }}</span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    @if (!empty($loc['marine_area_code']))
                                        <span class="font-mono text-xs text-ink-soft bg-page px-2 py-1 rounded-lg border border-line">{{ $loc['marine_area_code'] }}</span>
                                    @else
                                        <span class="text-xs text-ink-faint">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-full bg-page border border-line px-2.5 py-0.5 text-xs font-semibold text-ink-body">{{ $loc['land_count'] }}</span>
                                </td>
                                <td class="px-4 py-4 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-full bg-page border border-line px-2.5 py-0.5 text-xs font-semibold text-ink-body">{{ $loc['wave_count'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── Thresholds ───────────────────────────────────────────────────── --}}
        <div class="w-full bg-white rounded-2xl border border-line shadow-sm overflow-hidden">
            <div class="px-5 sm:px-6 py-4 border-b border-line flex items-center gap-2.5">
                <span class="h-5 w-1.5 rounded-full bg-primary-600" aria-hidden="true"></span>
                <h2 class="text-sm font-semibold text-ink tracking-wide">Ambang Status Dini</h2>
            </div>
            <p class="px-5 sm:px-6 pt-4 text-sm text-ink-faint">
                Nilai ini bersifat awal dan belum tervalidasi secara resmi. Nilai disimpan di tabel <span class="font-mono text-xs">app_settings</span>.
            </p>

            <div class="overflow-x-auto mt-4">
                <table class="w-full text-sm">
                    <caption class="sr-only">Daftar ambang status dini beserta nilai aktif, sumber nilai, dan aksi penyimpanan</caption>
                    <thead class="bg-page text-ink-soft text-xs uppercase tracking-wider">
                        <tr>
                            <th scope="col" class="text-left px-4 py-3.5 font-semibold min-w-[16rem]">Ambang</th>
                            <th scope="col" class="text-center px-4 py-3.5 font-semibold whitespace-nowrap">Nilai Aktif</th>
                            <th scope="col" class="text-center px-4 py-3.5 font-semibold whitespace-nowrap">Sumber</th>
                            <th scope="col" class="text-right px-4 py-3.5 font-semibold whitespace-nowrap">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-sunken">
                        @foreach ($settings as $setting)
                            <tr class="hover:bg-page transition-colors">
                                <td class="px-4 py-4">
                                    <span class="font-semibold text-ink">{{ $setting['label'] }}</span>
                                    <span class="block text-xs text-ink-faint mt-0.5">{{ $setting['description'] }}</span>
                                </td>
                                <td class="px-4 py-4 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-full bg-primary-50 text-primary-700 border border-primary-100 px-3 py-1 text-xs font-semibold">
                                        {{ rtrim(rtrim(number_format($setting['effective'], 2, ',', '.'), '0'), ',') }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-center whitespace-nowrap">
                                    @if ($setting['is_overridden'])
                                        <span class="inline-flex items-center gap-1 rounded-full text-[11px] font-semibold bg-accent-50 text-accent-700 border border-accent-200 px-2.5 py-0.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                            Diatur
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full text-[11px] font-semibold bg-surface-sunken text-ink-soft border border-line px-2.5 py-0.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                            Default
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-right whitespace-nowrap">
                                    <form method="POST" action="{{ route('super-admin.weather.setting.update', $setting['key']) }}" class="inline-flex items-center gap-2">
                                        @csrf
                                        @method('PUT')
                                        <input type="number" step="0.01" min="0" name="value"
                                            value="{{ $setting['effective'] }}"
                                            class="w-28 rounded-xl border border-line text-sm px-3 py-2 bg-white transition focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated"
                                            aria-label="Nilai {{ $setting['label'] }}">
                                        <button type="submit"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-primary-50 px-3 py-1.5 text-xs font-semibold text-primary-600 hover:bg-primary-100 ui-animated">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            Simpan
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.dashboard>
