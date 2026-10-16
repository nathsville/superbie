@php
    $title = 'Informasi Cuaca';
    $current = $weather['current'];
    $wave = $weather['wave'];
    $waveForecast = $weather['wave_forecast'] ?? ['available' => false, 'area_name' => null, 'days' => []];
    $charts = $weather['charts'] ?? [];
    $risk = $weather['risk'];
    $status = $risk['status'] ?? null;
    $loc = $weather['location'];
    $locLabel = $loc
        ? (trim(($loc['city'] ?? '') . ($loc['district'] ? ' — Kec. ' . $loc['district'] : '') . ($loc['village'] ? ', ' . $loc['village'] : ''), " —,") ?: ($loc['name'] ?? null))
        : null;
    $paramLabels = ['rain' => 'hujan', 'wind' => 'angin', 'wave' => 'gelombang'];
    // Coordinates are shown as a magnitude plus a hemisphere label. The stored
    // value is signed (negative = south/west), so displaying the raw negative
    // value next to "LS"/"BB" would be a confusing double negative.
    $fmtCoord = static function ($value, string $positive, string $negative): ?string {
        if ($value === null || $value === '') {
            return null;
        }
        $n = (float) $value;
        $decimals = rtrim(rtrim(number_format(abs($n), 7, '.', ''), '0'), '.');
        return $decimals.'° '.($n < 0 ? $negative : $positive);
    };
    $latText = $fmtCoord($loc['latitude'] ?? null, 'LU', 'LS');
    $lonText = $fmtCoord($loc['longitude'] ?? null, 'BT', 'BB');
    // Early-warning severity index (0 Normal · 1 Waspada · 2 Siaga) for the
    // three-step indicator. Derived from the computed status only.
    $sev = $status === null ? null : match ($status->value) { 'SIAGA' => 2, 'WASPADA' => 1, default => 0 };
    // Severity tones tuned for the light surface (accessible on white).
    $statusTone = match ($status?->value) {
        'SIAGA'   => ['text' => 'text-rose-600',    'dot' => 'bg-rose-500'],
        'WASPADA' => ['text' => 'text-accent-600',   'dot' => 'bg-accent-500'],
        default   => ['text' => 'text-emerald-600', 'dot' => 'bg-emerald-500'],
    };
@endphp

@push('head')
    {{-- The tab panels carry `x-cloak` so they do not flash before Alpine boots.
         Without JavaScript, Alpine never removes `x-cloak`, so re-show them here
         to preserve the "every section stays visible" progressive-enhancement
         contract. --}}
    <noscript><style>[x-cloak]{display:block !important;}</style></noscript>
@endpush

<x-layouts.app :title="$title">
    <div class="min-h-full">
        {{-- Header --}}
        <header x-data="{ scrolled: false }"
                @scroll.window="scrolled = window.scrollY > 8"
                :class="scrolled ? 'is-scrolled' : ''"
                class="wx-header sticky top-0 z-40 bg-white/95 backdrop-blur-sm border-b border-line px-4 sm:px-8 py-3.5">
            <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
                <a href="{{ route('home') }}" class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-xl bg-primary-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <span class="block font-bold text-ink text-base leading-tight truncate">Informasi Cuaca</span>
                        <span class="block text-[10px] text-ink-soft font-semibold uppercase tracking-wider truncate">{{ $loc['city'] ?? ($loc['name'] ?? 'Kota Parepare') }}</span>
                    </div>
                </a>

                <div class="flex items-center gap-3 sm:gap-5">
                    <div class="hidden sm:flex items-center gap-2"
                         x-data="{ now: new Date() }"
                         x-init="setInterval(() => now = new Date(), 1000)">
                        <span class="wx-live-dot" aria-hidden="true"></span>
                        <div class="flex flex-col items-end leading-tight">
                            <span class="font-mono text-lg font-bold text-ink tabular-nums"
                                  x-text="now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })">--:--:--</span>
                            <span class="text-[10px] font-semibold uppercase tracking-wider text-ink-faint">WITA</span>
                        </div>
                    </div>
                    <nav class="flex items-center gap-2" aria-label="Navigasi publik">
                        <a href="{{ route('home') }}" class="text-sm font-medium text-ink-soft hover:text-ink px-3 py-2 rounded-lg hover:bg-surface-sunken ui-animated">
                            Beranda
                        </a>
                        @auth
                            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 bg-primary-600 text-white text-sm font-semibold px-4 py-2 rounded-xl hover:bg-primary-700 ui-animated">
                                Dashboard
                            </a>
                        @endauth
                    </nav>
                </div>
            </div>
        </header>

        <main id="main-content" tabindex="-1" class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8 page-enter">
            {{-- Single page-level heading (WCAG 1.3.1 / 2.4.6). Visually hidden
                 because the header brand already renders the visible title. --}}
            <h1 class="sr-only">Informasi cuaca {{ $locLabel ?: 'wilayah pantau' }}</h1>
            {{-- Info strip --}}
            <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="wx-card card-enter stagger-item rounded-2xl border border-line bg-white px-4 py-3 shadow-sm">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-ink-faint">Wilayah Pantau</p>
                    {{-- The configured area name (e.g. "Kecamatan Ujung"), so the
                         city entry and the kecamatan entry that share one BMKG
                         adm4 stay visibly distinct. --}}
                    <p class="mt-1 text-sm font-semibold text-ink truncate">{{ $loc['name'] ?? ($locLabel ?: 'Wilayah pantau') }}</p>
                </div>
                <div class="wx-card card-enter stagger-item rounded-2xl border border-line bg-white px-4 py-3 shadow-sm">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-ink-faint">Sumber Data</p>
                    <p class="mt-1 text-sm font-semibold text-ink truncate">BMKG (prakiraan)</p>
                </div>
                <div class="wx-card card-enter stagger-item rounded-2xl border border-line bg-white px-4 py-3 shadow-sm">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-ink-faint">Koordinat</p>
                    <p class="mt-1 text-sm font-semibold text-ink tabular-nums truncate">
                        @if ($latText && $lonText)
                            {{ $latText }} · {{ $lonText }}
                        @else
                            —
                        @endif
                    </p>
                </div>
                <div class="wx-card card-enter stagger-item rounded-2xl border border-line bg-white px-4 py-3 shadow-sm">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-ink-faint">Pembaruan Terakhir</p>
                    <p class="mt-1 text-sm font-semibold text-ink truncate">
                        {{ $weather['generated_at'] ? \Illuminate\Support\Carbon::parse($weather['generated_at'])->translatedFormat('d M Y, H:i') . ' WITA' : 'Belum ada' }}
                    </p>
                </div>
            </section>

            <p class="mt-4 text-sm text-ink-soft">
                Prakiraan cuaca untuk {{ $locLabel ?: 'wilayah pantau' }}. Informasi ini bersifat publik dan tidak memerlukan login.
            </p>

            {{-- Location picker — only meaningful when more than one location is
                 configured (owner decision: currently Kota Parepare only). --}}
            @if (count($locations) > 1)
                <nav class="mt-4 flex flex-wrap items-center gap-2" aria-label="Pilih wilayah pantau">
                    @foreach ($locations as $option)
                        @php $isActive = ($activeLocation ?? null) === $option['key']; @endphp
                        <a href="{{ route('weather.location', $option['key']) }}"
                           @if ($isActive) aria-current="page" @endif
                           class="wx-press inline-flex items-center px-3 py-1.5 rounded-full text-sm font-semibold border ui-animated
                                  {{ $isActive
                                        ? 'bg-primary-600 text-white border-primary-600'
                                        : 'bg-white text-ink-soft border-line hover:border-primary-600 hover:text-ink' }}">
                            {{ $option['name'] }}
                        </a>
                    @endforeach
                </nav>
            @endif

            @if (! $weather['available'])
                {{-- No data yet: state this honestly, never fabricate values. --}}
                <div class="mt-6 rounded-2xl border border-line bg-white p-8 text-center shadow-sm card-enter">
                    <p class="text-sm text-ink-soft">{{ $weather['reason'] }}</p>
                    <p class="text-xs text-ink-faint mt-2">Data akan tampil setelah sinkronisasi (terjadwal atau manual) dijalankan.</p>
                </div>
            @else
                {{-- Tab state lives INSIDE `#main-content` so it travels with the
                     content: the progressive-navigation layer (Prompt 26) only
                     swaps `#main-content`'s innerHTML, so an ancestor scope would
                     be lost and Alpine would report "tab is not defined". Without
                     JavaScript every section stays visible; with Alpine the tabs
                     toggle. --}}
                <div x-data="{ tab: 'ringkasan', tabs: ['ringkasan', 'peta', 'prakiraan'],
                        move(step) {
                            const i = this.tabs.indexOf(this.tab);
                            this.tab = this.tabs[(i + step + this.tabs.length) % this.tabs.length];
                            this.$nextTick(() => { const el = this.$refs[this.tab]; if (el) el.focus(); });
                        } }">
                <nav class="mt-6 grid grid-cols-3 gap-2 rounded-2xl border border-line bg-white p-1.5 shadow-sm"
                     role="tablist" aria-label="Bagian informasi cuaca"
                     @keydown.arrow-right.prevent="move(1)"
                     @keydown.arrow-left.prevent="move(-1)"
                     @keydown.home.prevent="tab = tabs[0]; $nextTick(() => $refs[tabs[0]].focus())"
                     @keydown.end.prevent="tab = tabs[tabs.length - 1]; $nextTick(() => $refs[tab].focus())">
                    @foreach ([
                        'ringkasan' => ['label' => 'Ringkasan Eksekutif', 'icon' => 'M4 6h16M4 12h16M4 18h10'],
                        'peta'      => ['label' => 'Peta Cuaca',          'icon' => 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7'],
                        'prakiraan' => ['label' => 'Prakiraan & Analitik', 'icon' => 'M9 19v-6m4 6V5m4 14v-9'],
                    ] as $tabKey => $tabMeta)
                        <button type="button" role="tab"
                                id="weather-tab-{{ $tabKey }}"
                                aria-controls="weather-panel-{{ $tabKey }}"
                                x-ref="{{ $tabKey }}"
                                @click="tab = '{{ $tabKey }}'"
                                :aria-selected="tab === '{{ $tabKey }}' ? 'true' : 'false'"
                                :tabindex="tab === '{{ $tabKey }}' ? 0 : -1"
                                :class="tab === '{{ $tabKey }}'
                                    ? 'bg-primary-600 text-white shadow-sm'
                                    : 'text-ink-soft hover:text-ink hover:bg-surface-sunken'"
                                class="wx-tab wx-press inline-flex items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-xs sm:text-sm font-semibold ui-animated">
                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $tabMeta['icon'] }}"/>
                            </svg>
                            <span class="truncate">{{ $tabMeta['label'] }}</span>
                        </button>
                    @endforeach
                </nav>

                {{-- ─── Tab: Ringkasan Eksekutif ──────────────────────────────── --}}
                <div x-show="tab === 'ringkasan'" x-cloak id="weather-panel-ringkasan" role="tabpanel"
                     aria-labelledby="weather-tab-ringkasan" tabindex="0" class="wx-panel mt-6">
                    {{-- Early-warning status --}}
                    @if ($status !== null)
                        <section class="grid grid-cols-1 lg:grid-cols-5 gap-4">
                            {{-- Current conditions --}}
                            <div class="wx-card lg:col-span-3 rounded-2xl border border-line bg-white p-6 shadow-sm card-enter">
                                <div class="flex items-center justify-between flex-wrap gap-2">
                                    <div>
                                        <h2 class="text-[10px] font-semibold uppercase tracking-[0.18em] text-primary-600">Kondisi Terkini</h2>
                                        <p class="text-xs text-ink-faint mt-1">
                                            Slot prakiraan terdekat{{ $current['forecast_at'] ? ' · ' . \Illuminate\Support\Carbon::parse($current['forecast_at'])->translatedFormat('d M Y, H:i') . ' WITA' : '' }}
                                        </p>
                                    </div>
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-semibold text-emerald-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Prakiraan
                                    </span>
                                </div>

                                <div class="mt-5 flex items-end gap-3">
                                    <span class="text-6xl font-extrabold leading-none text-ink tabular-nums">
                                        {{ $current['temperature_c'] !== null ? number_format((float) $current['temperature_c'], 0, ',', '.') : '—' }}
                                    </span>
                                    <span class="text-2xl font-bold text-primary-600 mb-1">°C</span>
                                </div>
                                <p class="mt-2 text-sm font-semibold text-ink">{{ $current['weather_desc'] ?? 'Data tidak tersedia' }}</p>
                                <p class="text-xs text-ink-faint">Prakiraan atmosfer permukaan {{ $loc['city'] ?? 'wilayah pantau' }}</p>

                                <dl class="mt-5 grid grid-cols-2 sm:grid-cols-3 gap-3">
                                    <div class="wx-tile rounded-xl border border-line bg-page px-3 py-2.5">
                                        <dt class="text-[10px] font-semibold uppercase tracking-wider text-ink-faint">Kelembapan</dt>
                                        <dd class="mt-0.5 text-sm font-bold text-ink tabular-nums">{{ $current['humidity_pct'] !== null ? $current['humidity_pct'] . ' %' : 'Data tidak tersedia' }}</dd>
                                    </div>
                                    <div class="wx-tile rounded-xl border border-line bg-page px-3 py-2.5">
                                        <dt class="text-[10px] font-semibold uppercase tracking-wider text-ink-faint">Angin</dt>
                                        <dd class="mt-0.5 text-sm font-bold text-ink tabular-nums">
                                            {{ $current['wind_speed_kmh'] !== null ? number_format((float) $current['wind_speed_kmh'], 1, ',', '.') . ' km/jam' : 'Data tidak tersedia' }}
                                            @if ($current['wind_dir'])<span class="font-medium text-ink-faint"> ({{ $current['wind_dir'] }})</span>@endif
                                        </dd>
                                    </div>
                                    <div class="wx-tile rounded-xl border border-line bg-page px-3 py-2.5">
                                        <dt class="text-[10px] font-semibold uppercase tracking-wider text-ink-faint">Hujan (mm/3 jam)</dt>
                                        <dd class="mt-0.5 text-sm font-bold text-ink tabular-nums">{{ $current['rain_mm'] !== null ? number_format((float) $current['rain_mm'], 1, ',', '.') . ' mm' : 'Data tidak tersedia' }}</dd>
                                    </div>
                                    <div class="wx-tile rounded-xl border border-line bg-page px-3 py-2.5">
                                        <dt class="text-[10px] font-semibold uppercase tracking-wider text-ink-faint">Tutupan awan</dt>
                                        <dd class="mt-0.5 text-sm font-bold text-ink tabular-nums">{{ $current['cloud_cover_pct'] !== null ? $current['cloud_cover_pct'] . ' %' : 'Data tidak tersedia' }}</dd>
                                    </div>
                                    <div class="wx-tile rounded-xl border border-line bg-page px-3 py-2.5">
                                        <dt class="text-[10px] font-semibold uppercase tracking-wider text-ink-faint">Jarak pandang</dt>
                                        <dd class="mt-0.5 text-sm font-bold text-ink">{{ $current['visibility_text'] ?? 'Data tidak tersedia' }}</dd>
                                    </div>
                                    <div class="wx-tile rounded-xl border border-line bg-page px-3 py-2.5">
                                        <dt class="text-[10px] font-semibold uppercase tracking-wider text-ink-faint">Gelombang</dt>
                                        <dd class="mt-0.5 text-sm font-bold text-ink tabular-nums">
                                            @if ($wave['wave_height_m'] !== null)
                                                {{ number_format($wave['wave_height_m'], 1, ',', '.') }} m
                                                @if ($wave['wave_cat'])<span class="font-medium text-ink-faint"> ({{ $wave['wave_cat'] }})</span>@endif
                                            @else
                                                Data tidak tersedia
                                            @endif
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            {{-- Risk status --}}
                            <div class="wx-card lg:col-span-2 rounded-2xl border border-line bg-white p-6 shadow-sm card-enter">
                                <div class="flex items-center justify-between gap-2">
                                    <div>
                                        <h2 class="text-[10px] font-semibold uppercase tracking-[0.18em] text-primary-600">Status Risiko Wilayah</h2>
                                        <p class="text-xs text-ink-faint mt-1">Early Warning System</p>
                                    </div>
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-line bg-page px-3 py-1 text-[11px] font-semibold text-ink-soft">
                                        EWS
                                    </span>
                                </div>

                                <p class="mt-6 text-[10px] font-semibold uppercase tracking-wider text-ink-faint">Level Kesiapsiagaan</p>
                                <p class="mt-1 text-4xl font-extrabold tracking-tight uppercase {{ $statusTone['text'] }}">{{ $status->label() }}</p>
                                <p class="mt-2 text-xs text-ink-soft">{{ $status->guidance() }}</p>

                                {{-- Three-step indicator --}}
                                <div class="mt-6 grid grid-cols-3 gap-2">
                                    @foreach (['NORMAL' => 0, 'WASPADA' => 1, 'SIAGA' => 2] as $lvl => $idx)
                                        @php $on = $sev !== null && $sev >= $idx; @endphp
                                        <div class="flex items-center gap-2">
                                            <span class="wx-sev-dot {{ $sev === $idx ? 'is-active' : '' }} w-2.5 h-2.5 rounded-full {{ $on ? ($sev === $idx ? $statusTone['dot'] : 'bg-ink-ghost') : 'bg-line' }}"></span>
                                            <span class="text-[11px] font-semibold {{ $sev === $idx ? 'text-ink' : 'text-ink-faint' }}">{{ $lvl }}</span>
                                        </div>
                                    @endforeach
                                </div>

                                {{-- Honest disclosure of what the status is based on. --}}
                                <div class="mt-5 rounded-xl border border-line bg-page px-4 py-3 text-xs text-ink-soft">
                                    <p>
                                        Status dihitung dari parameter yang tersedia:
                                        <span class="font-semibold text-ink">{{ implode(', ', array_map(fn ($k) => $paramLabels[$k] ?? $k, $risk['basis'])) ?: 'tidak ada' }}</span>.
                                        @if (! empty($risk['missing']))
                                            Parameter yang belum tersedia (tidak dihitung):
                                            <span class="font-semibold text-ink">{{ implode(', ', array_map(fn ($k) => $paramLabels[$k] ?? $k, $risk['missing'])) }}.</span>
                                        @endif
                                    </p>
                                    <p class="mt-1 text-ink-faint">
                                        Data gelombang laut berasal dari prakiraan cuaca maritim BMKG{{ $wave['area_name'] ? ' (' . $wave['area_name'] . ')' : '' }}.
                                        Ambang batas bersifat awal dan belum tervalidasi secara resmi.
                                    </p>
                                </div>
                            </div>
                        </section>
                    @else
                        <section class="rounded-2xl border border-line bg-white p-6 shadow-sm card-enter">
                            <h2 class="text-[10px] font-semibold uppercase tracking-[0.18em] text-primary-600">Kondisi Terkini</h2>
                            <p class="mt-2 text-sm text-ink-soft">Status dini belum dapat dihitung karena tidak ada parameter yang tersedia.</p>
                        </section>
                    @endif

                    {{-- Marine (wave) current detail --}}
                    @if ($wave['available'])
                        <section class="wx-card mt-4 rounded-2xl border border-line bg-white p-6 shadow-sm card-enter">
                            <div class="flex items-center justify-between flex-wrap gap-2">
                                <h3 class="text-sm font-bold text-ink">Kondisi Laut</h3>
                                <span class="text-xs text-ink-faint">
                                    {{ $wave['area_name'] ?? 'Wilayah perairan' }}
                                    @if ($wave['forecast_at']) · {{ \Illuminate\Support\Carbon::parse($wave['forecast_at'])->translatedFormat('d M Y, H:i') }} WITA @endif
                                </span>
                            </div>
                            <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <div class="wx-tile rounded-xl border border-line bg-page px-3 py-2.5">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-ink-faint">Tinggi gelombang</p>
                                    <p class="mt-0.5 text-sm font-bold text-ink tabular-nums">{{ $wave['wave_height_m'] !== null ? number_format($wave['wave_height_m'], 1, ',', '.') . ' m' : 'Data tidak tersedia' }}</p>
                                </div>
                                <div class="wx-tile rounded-xl border border-line bg-page px-3 py-2.5">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-ink-faint">Kategori</p>
                                    <p class="mt-0.5 text-sm font-bold text-ink">{{ $wave['wave_cat'] ?? 'Data tidak tersedia' }}</p>
                                </div>
                                <div class="wx-tile rounded-xl border border-line bg-page px-3 py-2.5">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-ink-faint">Arus</p>
                                    <p class="mt-0.5 text-sm font-bold text-ink tabular-nums">
                                        {{ $wave['current_speed_kt'] !== null ? number_format($wave['current_speed_kt'], 2, ',', '.') . ' knot' : 'Data tidak tersedia' }}
                                        @if ($wave['current_to'])<span class="font-medium text-ink-faint"> ke {{ $wave['current_to'] }}</span>@endif
                                    </p>
                                </div>
                                <div class="wx-tile rounded-xl border border-line bg-page px-3 py-2.5">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-ink-faint">Angin laut</p>
                                    <p class="mt-0.5 text-sm font-bold text-ink tabular-nums">{{ $wave['wind_speed_kt'] !== null ? number_format($wave['wind_speed_kt'], 0, ',', '.') . ' knot' : 'Data tidak tersedia' }}</p>
                                </div>
                            </div>
                            <p class="mt-3 text-xs text-ink-faint">{{ config('weather.attribution.bmkg_marine') }}</p>
                        </section>
                    @endif
                </div>

                {{-- ─── Tab: Peta Cuaca ───────────────────────────────────────── --}}
                <div x-show="tab === 'peta'" x-cloak id="weather-panel-peta" role="tabpanel"
                     aria-labelledby="weather-tab-peta" tabindex="0" class="wx-panel mt-6" x-data="{ overlay: 'wind' }">
                    @if ($weather['embed']['enabled'])
                        <section class="wx-card rounded-2xl border border-line bg-white overflow-hidden shadow-sm card-enter">
                            <div class="flex items-center justify-between flex-wrap gap-3 px-5 py-4 border-b border-line">
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-primary-600">Peta Interaktif</p>
                                    <h2 class="text-base font-bold text-ink">Peta Cuaca Interaktif</h2>
                                    <p class="text-xs text-ink-faint">Fokus {{ $locLabel ?: 'wilayah pantau' }}
                                        @if ($latText && $lonText)
                                            · {{ $latText }}, {{ $lonText }}
                                        @endif
                                    </p>
                                </div>
                                {{-- Windy overlay layers (owner-requested). Each maps to a
                                     documented Windy overlay id; no data is fabricated. --}}
                                <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Lapisan peta">
                                    @foreach (['wind' => 'Angin', 'rain' => 'Hujan', 'clouds' => 'Awan', 'temp' => 'Suhu', 'waves' => 'Gelombang'] as $ov => $ovLabel)
                                        <button type="button" @click="overlay = '{{ $ov }}'"
                                                :class="overlay === '{{ $ov }}' ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-ink-soft border-line hover:border-primary-600 hover:text-ink'"
                                                class="wx-press rounded-lg border px-3 py-1.5 text-xs font-semibold ui-animated">{{ $ovLabel }}</button>
                                    @endforeach
                                </div>
                            </div>
                            <iframe
                                title="Peta cuaca interaktif Windy.com"
                                src="{{ $weather['embed']['url'] }}?type=wind&lat={{ $loc['latitude'] ?? '' }}&lon={{ $loc['longitude'] ?? '' }}&zoom=9&overlay=wind"
                                x-bind:src="'{{ $weather['embed']['url'] }}?type=wind&lat={{ $loc['latitude'] ?? '' }}&lon={{ $loc['longitude'] ?? '' }}&zoom=9&overlay=' + overlay"
                                class="w-full h-[440px] border-0"
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"
                                allowfullscreen
                            ></iframe>
                            <p class="px-5 py-3 text-xs text-ink-faint">
                                Peta disediakan oleh Windy.com. Pemuatan peta ini dapat mengirim permintaan ke server pihak ketiga.
                            </p>
                        </section>
                    @else
                        <section class="rounded-2xl border border-line bg-white p-6 shadow-sm card-enter">
                            <p class="text-sm text-ink-soft">Peta cuaca interaktif tidak diaktifkan.</p>
                        </section>
                    @endif
                </div>

                {{-- ─── Tab: Prakiraan & Analitik ─────────────────────────────── --}}
                <div x-show="tab === 'prakiraan'" x-cloak id="weather-panel-prakiraan" role="tabpanel"
                     aria-labelledby="weather-tab-prakiraan" tabindex="0" class="wx-panel mt-6 space-y-6">
                    {{-- 3-day charts (real stored aggregates only) --}}
                    @if (! empty($charts))
                        <section class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <h2 class="sr-only">Grafik prakiraan</h2>
                            @foreach ($charts as $chart)
                                <div class="wx-card card-enter stagger-item rounded-2xl border border-line bg-white p-5 shadow-sm">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <h3 class="text-sm font-bold text-ink">{{ $chart['title'] }}</h3>
                                        <span class="text-[11px] font-semibold text-ink-faint">{{ trim($chart['unit']) ?: '' }}</span>
                                    </div>
                                    <div class="mt-3">
                                        <x-weather.trend-chart
                                            :points="$chart['points']"
                                            :unit="$chart['unit']"
                                            :tone="$chart['tone']"
                                            :variant="$chart['variant']"
                                            :aria-label="$chart['title']" />
                                    </div>
                                </div>
                            @endforeach
                        </section>
                    @endif

                    {{-- Land forecast --}}
                    @if (! empty($weather['forecast']))
                        <section>
                            <h2 class="text-lg font-bold text-ink mb-4">Prakiraan {{ count($weather['forecast']) }} Hari</h2>
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                                @foreach ($weather['forecast'] as $day)
                                    <div class="wx-card card-enter stagger-item rounded-2xl border border-line bg-white p-5 shadow-sm">
                                        <p class="text-sm font-bold text-ink">{{ \Illuminate\Support\Carbon::parse($day['date'])->translatedFormat('l, d M Y') }}</p>
                                        <p class="text-xs text-ink-faint mt-1">{{ $day['description'] ?? 'Data tidak tersedia' }}</p>

                                        <dl class="mt-4 space-y-2 text-sm">
                                            <div class="flex justify-between">
                                                <dt class="text-ink-soft">Suhu</dt>
                                                <dd class="font-semibold text-ink tabular-nums">
                                                    @if ($day['temp_min'] !== null && $day['temp_max'] !== null)
                                                        {{ number_format($day['temp_min'], 0, ',', '.') }}–{{ number_format($day['temp_max'], 0, ',', '.') }} °C
                                                    @else
                                                        Data tidak tersedia
                                                    @endif
                                                </dd>
                                            </div>
                                            <div class="flex justify-between">
                                                <dt class="text-ink-soft">Angin maks.</dt>
                                                <dd class="font-semibold text-ink tabular-nums">{{ $day['wind_max_kmh'] !== null ? number_format($day['wind_max_kmh'], 1, ',', '.') . ' km/jam' : 'Data tidak tersedia' }}</dd>
                                            </div>
                                            <div class="flex justify-between">
                                                <dt class="text-ink-soft">Hujan (total)</dt>
                                                <dd class="font-semibold text-ink tabular-nums">{{ $day['rain_total_mm'] !== null ? number_format($day['rain_total_mm'], 1, ',', '.') . ' mm' : 'Data tidak tersedia' }}</dd>
                                            </div>
                                        </dl>

                                        @if (! empty($day['slots']))
                                            <div class="mt-4 pt-4 border-t border-line">
                                                <p class="text-[10px] font-semibold uppercase tracking-wider text-ink-faint mb-2">Per 3 jam</p>
                                                <ul class="space-y-1.5">
                                                    @foreach ($day['slots'] as $slot)
                                                        <li class="wx-slot flex items-center justify-between text-xs text-ink-soft">
                                                            <span class="font-mono text-ink-faint">{{ $slot['time'] }}</span>
                                                            <span class="flex items-center gap-2">
                                                                <span class="tabular-nums">{{ $slot['temperature_c'] !== null ? number_format($slot['temperature_c'], 0, ',', '.') . '°' : '—' }}</span>
                                                                <span class="text-ink-faint tabular-nums">{{ $slot['rain_mm'] !== null ? number_format($slot['rain_mm'], 1, ',', '.') . ' mm' : '—' }}</span>
                                                                <span class="truncate max-w-[7rem]" title="{{ $slot['weather_desc'] }}">{{ $slot['weather_desc'] ?? '—' }}</span>
                                                            </span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    {{-- Per-day wave forecast --}}
                    @if (! empty($waveForecast['available']) && ! empty($waveForecast['days']))
                        <section>
                            <div class="flex items-baseline justify-between flex-wrap gap-2">
                                <h2 class="text-lg font-bold text-ink">Prakiraan Gelombang Laut</h2>
                                @if ($waveForecast['area_name'])
                                    <span class="text-xs text-ink-faint">{{ $waveForecast['area_name'] }}</span>
                                @endif
                            </div>
                            <div class="mt-4 grid grid-cols-1 lg:grid-cols-3 gap-4">
                                @foreach ($waveForecast['days'] as $day)
                                    <div class="wx-card card-enter stagger-item rounded-2xl border border-line bg-white p-5 shadow-sm">
                                        <p class="text-sm font-bold text-ink">{{ \Illuminate\Support\Carbon::parse($day['date'])->translatedFormat('l, d M Y') }}</p>

                                        <dl class="mt-4 space-y-2 text-sm">
                                            <div class="flex justify-between">
                                                <dt class="text-ink-soft">Tinggi gelombang</dt>
                                                <dd class="font-semibold text-ink tabular-nums">
                                                    @if ($day['wave_min_m'] !== null && $day['wave_max_m'] !== null)
                                                        {{ number_format($day['wave_min_m'], 1, ',', '.') }}–{{ number_format($day['wave_max_m'], 1, ',', '.') }} m
                                                    @else
                                                        Data tidak tersedia
                                                    @endif
                                                </dd>
                                            </div>
                                            <div class="flex justify-between">
                                                <dt class="text-ink-soft">Kategori</dt>
                                                <dd class="font-semibold text-ink">{{ $day['wave_cat'] ?? 'Data tidak tersedia' }}</dd>
                                            </div>
                                            <div class="flex justify-between">
                                                <dt class="text-ink-soft">Angin maks.</dt>
                                                <dd class="font-semibold text-ink tabular-nums">{{ $day['wind_max_kt'] !== null ? number_format($day['wind_max_kt'], 0, ',', '.') . ' knot' : 'Data tidak tersedia' }}</dd>
                                            </div>
                                            <div class="flex justify-between">
                                                <dt class="text-ink-soft">Arus maks.</dt>
                                                <dd class="font-semibold text-ink tabular-nums">{{ $day['current_max_kt'] !== null ? number_format($day['current_max_kt'], 2, ',', '.') . ' knot' : 'Data tidak tersedia' }}</dd>
                                            </div>
                                        </dl>

                                        @if (! empty($day['slots']))
                                            <div class="mt-4 pt-4 border-t border-line">
                                                <p class="text-[10px] font-semibold uppercase tracking-wider text-ink-faint mb-2">Rincian</p>
                                                <ul class="space-y-1.5">
                                                    @foreach ($day['slots'] as $slot)
                                                        <li class="wx-slot flex items-center justify-between text-xs text-ink-soft">
                                                            <span class="font-mono text-ink-faint">{{ $slot['time'] }}</span>
                                                            <span class="flex items-center gap-2">
                                                                <span class="tabular-nums">{{ $slot['wave_height_m'] !== null ? number_format($slot['wave_height_m'], 1, ',', '.') . ' m' : '—' }}</span>
                                                                <span class="truncate max-w-[7rem]" title="{{ $slot['wave_cat'] }}">{{ $slot['wave_cat'] ?? '—' }}</span>
                                                            </span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            <p class="mt-3 text-xs text-ink-faint">
                                {{ config('weather.attribution.bmkg_marine') }}
                                Waktu ditampilkan dalam WITA. Hari pertama disajikan per jam; hari berikutnya per 3 jam.
                            </p>
                        </section>
                    @endif
                </div>
                </div>
            @endif

            {{-- Source attribution --}}
            <p class="mt-6 text-xs text-ink-faint">
                {{ $weather['attribution'] }}
                @if ($weather['generated_at'])
                    <span class="block mt-1 text-ink-faint">Terakhir disinkronkan: {{ \Illuminate\Support\Carbon::parse($weather['generated_at'])->translatedFormat('d M Y, H:i') }} WITA.</span>
                @endif
            </p>
        </main>

        {{-- Footer --}}
        <footer class="bg-page border-t border-line py-8 px-4 text-center text-xs text-ink-soft">
            <p class="font-medium text-ink">SuperBie &mdash; Lapor Pak Wali</p>
            <p class="mt-1">Pemerintah Kota Parepare, Sulawesi Selatan</p>
        </footer>
    </div>
</x-layouts.app>
