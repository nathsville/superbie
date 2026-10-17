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
    // The page's whole type ladder: 13 for labels and fine print, 17 for reading
    // text and values, 22 for section headings, plus the two display clamps
    // (title/status word, temperature). Surfaces are separated by a step in
    // tone, never by a rule: no borders anywhere on this sheet.
    $labelSm = 'text-[13px] text-ink-faint';
    $labelBand = 'text-[13px] text-ink-soft';
    $valueSm = 'mt-1 text-[17px] font-semibold text-ink tabular-nums';
@endphp

@push('head')
    {{-- The tab panels carry `x-cloak` so they do not flash before Alpine boots.
         Without JavaScript, Alpine never removes `x-cloak`, so re-show them here
         to preserve the "every section stays visible" progressive-enhancement
         contract. --}}
    <noscript><style>[x-cloak]{display:block !important;}</style></noscript>
@endpush

<x-layouts.app :title="$title">
    <div class="min-h-full bg-surface text-ink">

        {{-- ─── Header ─────────────────────────────────────────────────────────
             The same opening as the landing page: the wordmark at the left
             margin, the flat accent tab of plain links, one action at the right
             with the running WITA clock beside it. Solid surface, hairline-free,
             nothing pinned to the viewport. --}}
        <header class="mx-auto max-w-[1200px] px-5 sm:px-8">
            <div class="grid grid-cols-2 items-stretch lg:grid-cols-12">
                <a
                    href="{{ route('home') }}"
                    class="flex items-center py-3 lg:col-span-2 lg:col-start-1"
                    aria-label="SuperBie — halaman utama"
                >
                    <img src="/brand/superbie-logo.svg" alt="SuperBie — Lapor Pak Wali" class="h-10 w-auto">
                </a>

                <div class="flex items-center justify-end gap-6 lg:col-span-7 lg:col-start-6">
                    {{-- The one live reading on the page: the wall clock. It is a
                         real local time, so it earns the space; the dot is a
                         plain marker, not a pulse. --}}
                    <div
                        class="hidden items-center gap-2 sm:flex"
                        x-data="{ now: new Date() }"
                        x-init="setInterval(() => now = new Date(), 1000)"
                    >
                        <span class="wx-live-dot" aria-hidden="true"></span>
                        <span
                            class="text-[17px] font-semibold tabular-nums text-ink"
                            x-text="now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })"
                        >--:--:--</span>
                        <span class="text-[13px] text-ink-soft">WITA</span>
                    </div>

                    @auth
                        <a
                            href="{{ route('citizen.complaint.create') }}"
                            class="ui-animated inline-flex min-h-12 items-center justify-center bg-primary-600 px-6 text-[17px] font-semibold text-white hover:bg-primary-700 active:scale-[0.98]"
                        >Kirim laporan</a>
                    @else
                        <a
                            href="{{ route('register') }}"
                            class="ui-animated inline-flex min-h-12 items-center justify-center bg-primary-600 px-6 text-[17px] font-semibold text-white hover:bg-primary-700 active:scale-[0.98]"
                        >Kirim laporan</a>
                    @endauth
                </div>

                {{-- On a phone the tab drops to its own full-width row under the
                     wordmark, so the three doors stay on screen instead of
                     hiding behind a menu word. --}}
                <nav
                    class="-mx-5 col-span-2 flex items-center gap-4 bg-accent-500 px-5 py-3 sm:-mx-8 sm:px-8 lg:mx-0 lg:col-span-3 lg:col-start-3 lg:gap-6 lg:px-5 lg:py-0"
                    aria-label="Navigasi publik"
                >
                    <a href="{{ route('home') }}" class="whitespace-nowrap text-[17px] font-semibold text-ink transition-colors hover:text-primary-800">Beranda</a>
                    <a href="{{ route('weather.index') }}" aria-current="page" class="whitespace-nowrap text-[17px] font-semibold text-ink underline decoration-2 underline-offset-4 transition-colors hover:text-primary-800">Cuaca</a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="whitespace-nowrap text-[17px] font-semibold text-ink transition-colors hover:text-primary-800">Akun saya</a>
                    @else
                        <a href="{{ route('login') }}" class="whitespace-nowrap text-[17px] font-semibold text-ink transition-colors hover:text-primary-800">Masuk</a>
                    @endauth
                </nav>
            </div>
        </header>

        <main
            id="main-content"
            tabindex="-1"
            class="page-enter mx-auto max-w-[1200px] px-5 pb-20 pt-12 sm:px-8 sm:pt-28"
        >
            {{-- ─── Title ──────────────────────────────────────────────────────
                 The standfirst sits at the left margin, the page title starts on
                 the 42% axis — the landing page's own opening, at the sub-page
                 step of the type ladder. The title is the page's only <h1>. --}}
            <section class="grid grid-cols-1 gap-6 lg:grid-cols-12" data-reveal="rows">
                <div class="lg:col-span-7 lg:col-start-6 lg:row-start-1">
                    <h1 class="font-semibold tracking-[-0.03em] text-ink" style="font-size: clamp(26px, 2.8vw, 40px)">
                        Informasi cuaca
                    </h1>
                    <p class="mt-2 text-[17px] font-semibold text-ink-soft">
                        {{ $locLabel ?: 'Wilayah pantau' }}
                    </p>
                </div>
                <p class="max-w-[42ch] text-pretty text-[17px] leading-relaxed text-ink-body lg:col-span-4 lg:col-start-1 lg:row-start-1 lg:pt-2">
                    Prakiraan cuaca untuk {{ $locLabel ?: 'wilayah pantau' }}. Informasi ini
                    bersifat publik dan tidak memerlukan login.
                </p>
            </section>

            {{-- Location picker — only meaningful when more than one location is
                 configured (owner decision: currently Kota Parepare only). --}}
            @if (count($locations) > 1)
                <nav class="mt-8 flex flex-wrap items-center gap-2" aria-label="Pilih wilayah pantau" data-reveal="rows">
                    @foreach ($locations as $option)
                        @php $isActive = ($activeLocation ?? null) === $option['key']; @endphp
                        <a href="{{ route('weather.location', $option['key']) }}"
                           @if ($isActive) aria-current="page" @endif
                           class="wx-press ui-animated inline-flex min-h-11 items-center px-3 text-[13px] font-semibold sm:px-4 sm:text-[17px]
                                  {{ $isActive
                                        ? 'bg-ink text-white'
                                        : 'bg-surface-sunken text-ink-soft hover:bg-line hover:text-ink' }}">
                            {{ $option['name'] }}
                        </a>
                    @endforeach
                </nav>
            @endif

            @if (! $weather['available'])
                {{-- No data yet: state this honestly, never fabricate values. --}}
                <div class="mt-10 max-w-[52ch] bg-surface-sunken px-6 py-10 sm:px-8">
                    <p class="text-[17px] text-ink-body">{{ $weather['reason'] }}</p>
                    <p class="mt-2 text-[13px] text-ink-soft">Data akan tampil setelah sinkronisasi (terjadwal atau manual) dijalankan.</p>
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
                {{-- A plain index of filled tiles, the same device as the accent
                     tab in the header. The selected tile is solid ink; the rest
                     are quiet fields on the pale step. --}}
                <nav class="mt-12 flex flex-wrap gap-2"
                     role="tablist" aria-label="Bagian informasi cuaca"
                     data-reveal="rows"
                     @keydown.arrow-right.prevent="move(1)"
                     @keydown.arrow-left.prevent="move(-1)"
                     @keydown.home.prevent="tab = tabs[0]; $nextTick(() => $refs[tabs[0]].focus())"
                     @keydown.end.prevent="tab = tabs[tabs.length - 1]; $nextTick(() => $refs[tab].focus())">
                    @foreach ([
                        'ringkasan' => 'Ringkasan Eksekutif',
                        'peta'      => 'Peta Cuaca',
                        'prakiraan' => 'Prakiraan & Analitik',
                    ] as $tabKey => $tabLabel)
                        <button type="button" role="tab"
                                id="weather-tab-{{ $tabKey }}"
                                aria-controls="weather-panel-{{ $tabKey }}"
                                x-ref="{{ $tabKey }}"
                                @click="tab = '{{ $tabKey }}'"
                                :aria-selected="tab === '{{ $tabKey }}' ? 'true' : 'false'"
                                :tabindex="tab === '{{ $tabKey }}' ? 0 : -1"
                                :class="tab === '{{ $tabKey }}'
                                    ? 'bg-ink text-white'
                                    : 'bg-surface-sunken text-ink-soft hover:bg-line hover:text-ink'"
                                class="wx-press inline-flex min-h-12 items-center px-5 text-[17px] font-semibold ui-animated">
                            <span class="truncate">{{ $tabLabel }}</span>
                        </button>
                    @endforeach
                </nav>

                {{-- ─── Tab: Ringkasan Eksekutif ──────────────────────────────── --}}
                <div x-show="tab === 'ringkasan'" x-cloak id="weather-panel-ringkasan" role="tabpanel"
                     aria-labelledby="weather-tab-ringkasan" tabindex="0" class="wx-panel mt-10">
                    {{-- Current conditions: the page's one display reading. The
                         verdict in large type first, the supporting numbers in the
                         ledger beside it, the fine print underneath. --}}
                    <section class="grid grid-cols-1 gap-x-10 gap-y-10 lg:grid-cols-12" data-reveal="rows">
                        <div class="lg:col-span-7">
                            <div class="flex flex-wrap items-baseline gap-x-6 gap-y-2">
                                <h2 class="text-[22px] font-semibold tracking-[-0.02em] text-ink">Kondisi Terkini</h2>
                                <span class="inline-flex items-center gap-2 bg-emerald-50 px-3 py-1 text-[13px] font-semibold text-emerald-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span> Prakiraan
                                </span>
                            </div>

                            <div class="mt-6 flex items-end gap-3">
                                <span class="text-ink tabular-nums" style="font-size: clamp(56px, 7vw, 88px); font-weight: 800; line-height: 0.9">
                                    {{ $current['temperature_c'] !== null ? number_format((float) $current['temperature_c'], 0, ',', '.') : '—' }}
                                </span>
                                <span class="mb-1 text-[22px] font-bold text-primary-600">°C</span>
                            </div>
                            <p class="mt-4 text-[17px] font-semibold text-ink">{{ $current['weather_desc'] ?? 'Data tidak tersedia' }}</p>
                            <p class="mt-1 max-w-[52ch] text-[13px] text-ink-faint">
                                Slot prakiraan BMKG terdekat yang tersimpan{{ $current['forecast_at'] ? ' · ' . \Illuminate\Support\Carbon::parse($current['forecast_at'])->translatedFormat('d M Y, H:i') . ' WITA' : '' }}.
                                Prakiraan atmosfer permukaan {{ $loc['city'] ?? 'wilayah pantau' }}.
                            </p>
                        </div>

                        <dl class="grid grid-cols-2 gap-x-8 gap-y-6 self-center sm:grid-cols-3 lg:col-span-4 lg:col-start-9 lg:grid-cols-2" data-reveal="rows">
                            <div>
                                <dt class="{{ $labelSm }}">Kelembapan</dt>
                                <dd class="{{ $valueSm }}">{{ $current['humidity_pct'] !== null ? $current['humidity_pct'] . ' %' : 'Data tidak tersedia' }}</dd>
                            </div>
                            <div>
                                <dt class="{{ $labelSm }}">Angin</dt>
                                <dd class="{{ $valueSm }}">
                                    {{ $current['wind_speed_kmh'] !== null ? number_format((float) $current['wind_speed_kmh'], 1, ',', '.') . ' km/jam' : 'Data tidak tersedia' }}
                                    @if ($current['wind_dir'])<span class="font-medium text-ink-faint"> ({{ $current['wind_dir'] }})</span>@endif
                                </dd>
                            </div>
                            <div>
                                <dt class="{{ $labelSm }}">Hujan (mm/3 jam)</dt>
                                <dd class="{{ $valueSm }}">{{ $current['rain_mm'] !== null ? number_format((float) $current['rain_mm'], 1, ',', '.') . ' mm' : 'Data tidak tersedia' }}</dd>
                            </div>
                            <div>
                                <dt class="{{ $labelSm }}">Tutupan awan</dt>
                                <dd class="{{ $valueSm }}">{{ $current['cloud_cover_pct'] !== null ? $current['cloud_cover_pct'] . ' %' : 'Data tidak tersedia' }}</dd>
                            </div>
                            <div>
                                <dt class="{{ $labelSm }}">Jarak pandang</dt>
                                <dd class="{{ $valueSm }}">{{ $current['visibility_text'] ?? 'Data tidak tersedia' }}</dd>
                            </div>
                            <div>
                                <dt class="{{ $labelSm }}">Gelombang</dt>
                                <dd class="{{ $valueSm }}">
                                    @if ($wave['wave_height_m'] !== null)
                                        {{ number_format($wave['wave_height_m'], 1, ',', '.') }} m
                                        @if ($wave['wave_cat'])<span class="font-medium text-ink-faint"> ({{ $wave['wave_cat'] }})</span>@endif
                                    @else
                                        Data tidak tersedia
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </section>

                    {{-- Early-warning status on the pale step: a different field
                         from the conditions above, so the verdict reads as its
                         own moment. --}}
                    @if ($status !== null)
                        <section class="-mx-5 mt-16 bg-surface-sunken px-5 py-12 sm:-mx-8 sm:px-8" data-reveal="rise">
                            <div class="grid grid-cols-1 gap-x-10 gap-y-10 lg:grid-cols-12">
                                <div class="lg:col-span-6">
                                    <h2 class="text-[22px] font-semibold tracking-[-0.02em] text-ink">Status Risiko Wilayah</h2>
                                    <p class="mt-6 uppercase {{ $statusTone['text'] }}" style="font-size: clamp(26px, 3vw, 40px); font-weight: 800; letter-spacing: -0.02em">{{ $status->label() }}</p>
                                    <p class="mt-4 max-w-[52ch] text-[17px] leading-relaxed text-ink-body">{{ $status->guidance() }}</p>
                                </div>

                                <div class="lg:col-span-5 lg:col-start-8">
                                    {{-- Three-step indicator: the reached steps are
                                         filled, every step keeps its written label,
                                         so colour is never the only signal. --}}
                                    <div class="grid max-w-[480px] grid-cols-3 gap-4" data-reveal="rows">
                                        @foreach (['Normal' => 0, 'Waspada' => 1, 'Siaga' => 2] as $lvl => $idx)
                                            @php $on = $sev !== null && $sev >= $idx; @endphp
                                            <div>
                                                <span class="block h-2 {{ $on ? $statusTone['dot'] : 'bg-line-strong' }}" aria-hidden="true"></span>
                                                <span class="mt-2 block text-[13px] font-semibold {{ $sev === $idx ? 'text-ink' : 'text-ink-soft' }}">{{ $lvl }}</span>
                                            </div>
                                        @endforeach
                                    </div>

                                    {{-- Honest disclosure of what the status is based on. --}}
                                    <div class="mt-8 bg-surface px-5 py-4">
                                        <p class="text-[16px] leading-relaxed text-ink-body">
                                            Status dihitung dari parameter yang tersedia:
                                            <span class="font-semibold text-ink">{{ implode(', ', array_map(fn ($k) => $paramLabels[$k] ?? $k, $risk['basis'])) ?: 'tidak ada' }}</span>.
                                            @if (! empty($risk['missing']))
                                                Parameter yang belum tersedia (tidak dihitung):
                                                <span class="font-semibold text-ink">{{ implode(', ', array_map(fn ($k) => $paramLabels[$k] ?? $k, $risk['missing'])) }}.</span>
                                            @endif
                                        </p>
                                        <p class="mt-2 text-[16px] leading-relaxed text-ink-soft">
                                            Modul Early Warning System SuperBie. Data gelombang laut berasal dari prakiraan cuaca maritim BMKG{{ $wave['area_name'] ? ' (' . $wave['area_name'] . ')' : '' }}.
                                            Ambang batas bersifat awal dan belum tervalidasi secara resmi.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </section>
                    @else
                        <section class="-mx-5 mt-16 bg-surface-sunken px-5 py-12 sm:-mx-8 sm:px-8">
                            <h2 class="text-[22px] font-semibold tracking-[-0.02em] text-ink">Status Risiko Wilayah</h2>
                            <p class="mt-2 max-w-[52ch] text-[17px] text-ink-body">Status dini belum dapat dihitung karena tidak ada parameter yang tersedia.</p>
                        </section>
                    @endif

                    {{-- Marine (wave) current detail, led by a credited photograph
                         of the harbour the forecast is drawn for. --}}
                    @if ($wave['available'])
                        <section class="mt-16 grid grid-cols-1 gap-x-10 gap-y-8 lg:grid-cols-12">
                            <figure class="lg:col-span-6">
                                <div class="overflow-hidden" data-reveal="shutter">
                                    <img
                                        src="/img/kota/terminal-penumpang-pelabuhan-nusantara-parepare.jpg"
                                        alt=""
                                        width="1400"
                                        height="858"
                                        class="aspect-[4/3] w-full object-cover grayscale"
                                    >
                                </div>
                                <figcaption class="mt-3 max-w-[52ch] text-[13px] text-ink-faint">
                                    Terminal penumpang Pelabuhan Nusantara Parepare.
                                    Foto: Blue tooth7 · CC BY-SA 3.0 · Wikimedia Commons
                                </figcaption>
                            </figure>
                            <div class="lg:col-span-6 lg:col-start-7">
                                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
                                    <h2 class="text-[22px] font-semibold tracking-[-0.02em] text-ink">Kondisi Laut</h2>
                                    <span class="text-[13px] text-ink-faint">
                                        {{ $wave['area_name'] ?? 'Wilayah perairan' }}
                                        @if ($wave['forecast_at']) · {{ \Illuminate\Support\Carbon::parse($wave['forecast_at'])->translatedFormat('d M Y, H:i') }} WITA @endif
                                    </span>
                                </div>
                                <dl class="mt-6 grid grid-cols-2 gap-x-8 gap-y-6" data-reveal="rows">
                                    <div>
                                        <dt class="{{ $labelSm }}">Tinggi gelombang</dt>
                                        <dd class="{{ $valueSm }}">{{ $wave['wave_height_m'] !== null ? number_format($wave['wave_height_m'], 1, ',', '.') . ' m' : 'Data tidak tersedia' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="{{ $labelSm }}">Kategori</dt>
                                        <dd class="{{ $valueSm }}">{{ $wave['wave_cat'] ?? 'Data tidak tersedia' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="{{ $labelSm }}">Arus</dt>
                                        <dd class="{{ $valueSm }}">
                                            {{ $wave['current_speed_kt'] !== null ? number_format($wave['current_speed_kt'], 2, ',', '.') . ' knot' : 'Data tidak tersedia' }}
                                            @if ($wave['current_to'])<span class="font-medium text-ink-faint"> ke {{ $wave['current_to'] }}</span>@endif
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="{{ $labelSm }}">Angin laut</dt>
                                        <dd class="{{ $valueSm }}">{{ $wave['wind_speed_kt'] !== null ? number_format($wave['wind_speed_kt'], 0, ',', '.') . ' knot' : 'Data tidak tersedia' }}</dd>
                                    </div>
                                </dl>
                                <p class="mt-6 max-w-[52ch] text-[16px] leading-relaxed text-ink-soft">{{ config('weather.attribution.bmkg_marine') }} Diolah dari pantauan terakhir prakiraan maritim.</p>
                            </div>
                        </section>
                    @endif
                </div>

                {{-- ─── Tab: Peta Cuaca ───────────────────────────────────────── --}}
                <div x-show="tab === 'peta'" x-cloak id="weather-panel-peta" role="tabpanel"
                     aria-labelledby="weather-tab-peta" tabindex="0" class="wx-panel mt-10" x-data="{ overlay: 'wind' }">
                    @if ($weather['embed']['enabled'])
                        <section>
                            <div class="-mx-5 flex flex-wrap items-center justify-between gap-4 bg-surface-sunken px-5 py-5 sm:-mx-8 sm:px-8" data-reveal="rise">
                                <div>
                                    <h2 class="text-[22px] font-semibold tracking-[-0.02em] text-ink">Peta Cuaca Interaktif</h2>
                                    <p class="mt-1 text-[13px] text-ink-soft">Fokus {{ $locLabel ?: 'wilayah pantau' }}
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
                                                :class="overlay === '{{ $ov }}' ? 'bg-ink text-white' : 'bg-surface text-ink-soft hover:bg-line hover:text-ink'"
                                                class="wx-press inline-flex min-h-11 items-center px-4 text-[13px] font-semibold ui-animated">{{ $ovLabel }}</button>
                                    @endforeach
                                </div>
                            </div>
                            {{-- `data-ui` marks the embedded third-party interface:
                                 the page supplies the frame, Windy draws inside it. --}}
                            <div class="mt-8" data-ui data-reveal="shutter">
                                <iframe
                                    title="Peta cuaca interaktif Windy.com"
                                    src="{{ $weather['embed']['url'] }}?type=wind&lat={{ $loc['latitude'] ?? '' }}&lon={{ $loc['longitude'] ?? '' }}&zoom=9&overlay=wind"
                                    x-bind:src="'{{ $weather['embed']['url'] }}?type=wind&lat={{ $loc['latitude'] ?? '' }}&lon={{ $loc['longitude'] ?? '' }}&zoom=9&overlay=' + overlay"
                                    class="block h-[440px] w-full border-0"
                                    loading="lazy"
                                    referrerpolicy="no-referrer-when-downgrade"
                                    allowfullscreen
                                ></iframe>
                            </div>
                            <p class="mt-4 max-w-[62ch] text-[13px] leading-relaxed text-ink-faint">
                                Peta disediakan oleh Windy.com. Pemuatan peta ini dapat mengirim permintaan ke server pihak ketiga.
                            </p>
                        </section>
                    @else
                        <section class="-mx-5 bg-surface-sunken px-5 py-10 sm:-mx-8 sm:px-8">
                            <p class="text-[17px] text-ink-body">Peta cuaca interaktif tidak diaktifkan.</p>
                        </section>
                    @endif
                </div>

                {{-- ─── Tab: Prakiraan & Analitik ─────────────────────────────── --}}
                <div x-show="tab === 'prakiraan'" x-cloak id="weather-panel-prakiraan" role="tabpanel"
                     aria-labelledby="weather-tab-prakiraan" tabindex="0" class="wx-panel mt-10">
                    {{-- 3-day charts (real stored aggregates only). Each chart is a
                         `data-ui` fragment: the drawing is the software's own
                         output, the heading beside it is the page's own copy. --}}
                    @if (! empty($charts))
                        <section class="grid grid-cols-1 gap-x-10 gap-y-12 lg:grid-cols-2">
                            <h2 class="sr-only">Grafik prakiraan</h2>
                            @foreach ($charts as $chart)
                                <div data-reveal="rise">
                                    <div class="flex items-baseline justify-between gap-3">
                                        <h3 class="text-[17px] font-semibold text-ink">{{ $chart['title'] }}</h3>
                                        <span class="text-[13px] text-ink-faint">{{ trim($chart['unit']) ?: '' }}</span>
                                    </div>
                                    <div class="mt-3" data-ui>
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
                        <section class="mt-16">
                            <h2 class="text-[22px] font-semibold tracking-[-0.02em] text-ink">Prakiraan {{ count($weather['forecast']) }} Hari</h2>
                            <div class="mt-6 grid grid-cols-1 gap-x-10 gap-y-10 lg:grid-cols-3" data-reveal="rows">
                                @foreach ($weather['forecast'] as $day)
                                    <div>
                                        <p class="text-[17px] font-semibold text-ink">{{ \Illuminate\Support\Carbon::parse($day['date'])->translatedFormat('l, d M Y') }}</p>
                                        <p class="mt-1 text-[13px] text-ink-faint">{{ $day['description'] ?? 'Data tidak tersedia' }}</p>

                                        <dl class="mt-5 space-y-2.5">
                                            <div class="flex items-baseline justify-between gap-4">
                                                <dt class="{{ $labelSm }}">Suhu</dt>
                                                <dd class="text-[17px] font-semibold text-ink tabular-nums">
                                                    @if ($day['temp_min'] !== null && $day['temp_max'] !== null)
                                                        {{ number_format($day['temp_min'], 0, ',', '.') }}–{{ number_format($day['temp_max'], 0, ',', '.') }} °C
                                                    @else
                                                        Data tidak tersedia
                                                    @endif
                                                </dd>
                                            </div>
                                            <div class="flex items-baseline justify-between gap-4">
                                                <dt class="{{ $labelSm }}">Angin maks.</dt>
                                                <dd class="text-[17px] font-semibold text-ink tabular-nums">{{ $day['wind_max_kmh'] !== null ? number_format($day['wind_max_kmh'], 1, ',', '.') . ' km/jam' : 'Data tidak tersedia' }}</dd>
                                            </div>
                                            <div class="flex items-baseline justify-between gap-4">
                                                <dt class="{{ $labelSm }}">Hujan (total)</dt>
                                                <dd class="text-[17px] font-semibold text-ink tabular-nums">{{ $day['rain_total_mm'] !== null ? number_format($day['rain_total_mm'], 1, ',', '.') . ' mm' : 'Data tidak tersedia' }}</dd>
                                            </div>
                                        </dl>

                                        @if (! empty($day['slots']))
                                            <div class="mt-6">
                                                <p class="mb-2 text-[13px] text-ink-faint">Per 3 jam</p>
                                                <ul class="space-y-1.5">
                                                    @foreach ($day['slots'] as $slot)
                                                        <li class="flex items-center justify-between gap-3 text-[13px] text-ink-soft">
                                                            <span class="tabular-nums text-ink-faint">{{ $slot['time'] }}</span>
                                                            <span class="flex items-center gap-3">
                                                                <span class="tabular-nums">{{ $slot['temperature_c'] !== null ? number_format($slot['temperature_c'], 0, ',', '.') . '°' : '—' }}</span>
                                                                <span class="tabular-nums text-ink-faint">{{ $slot['rain_mm'] !== null ? number_format($slot['rain_mm'], 1, ',', '.') . ' mm' : '—' }}</span>
                                                                <span class="max-w-[7rem] truncate" title="{{ $slot['weather_desc'] }}">{{ $slot['weather_desc'] ?? '—' }}</span>
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

                    {{-- Per-day wave forecast on the pale step, so the two forecast
                         sections never share one composition. --}}
                    @if (! empty($waveForecast['available']) && ! empty($waveForecast['days']))
                        <section class="-mx-5 mt-16 bg-surface-sunken px-5 py-12 sm:-mx-8 sm:px-8" data-reveal="rise">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
                                <h2 class="text-[22px] font-semibold tracking-[-0.02em] text-ink">Prakiraan Gelombang Laut</h2>
                                @if ($waveForecast['area_name'])
                                    <span class="{{ $labelBand }}">{{ $waveForecast['area_name'] }}</span>
                                @endif
                            </div>
                            <div class="mt-6 grid grid-cols-1 gap-x-10 gap-y-10 lg:grid-cols-3" data-reveal="rows">
                                @foreach ($waveForecast['days'] as $day)
                                    <div>
                                        <p class="text-[17px] font-semibold text-ink">{{ \Illuminate\Support\Carbon::parse($day['date'])->translatedFormat('l, d M Y') }}</p>

                                        <dl class="mt-5 space-y-2.5">
                                            <div class="flex items-baseline justify-between gap-4">
                                                <dt class="{{ $labelBand }}">Tinggi gelombang</dt>
                                                <dd class="text-[17px] font-semibold text-ink tabular-nums">
                                                    @if ($day['wave_min_m'] !== null && $day['wave_max_m'] !== null)
                                                        {{ number_format($day['wave_min_m'], 1, ',', '.') }}–{{ number_format($day['wave_max_m'], 1, ',', '.') }} m
                                                    @else
                                                        Data tidak tersedia
                                                    @endif
                                                </dd>
                                            </div>
                                            <div class="flex items-baseline justify-between gap-4">
                                                <dt class="{{ $labelBand }}">Kategori</dt>
                                                <dd class="text-[17px] font-semibold text-ink">{{ $day['wave_cat'] ?? 'Data tidak tersedia' }}</dd>
                                            </div>
                                            <div class="flex items-baseline justify-between gap-4">
                                                <dt class="{{ $labelBand }}">Angin maks.</dt>
                                                <dd class="text-[17px] font-semibold text-ink tabular-nums">{{ $day['wind_max_kt'] !== null ? number_format($day['wind_max_kt'], 0, ',', '.') . ' knot' : 'Data tidak tersedia' }}</dd>
                                            </div>
                                            <div class="flex items-baseline justify-between gap-4">
                                                <dt class="{{ $labelBand }}">Arus maks.</dt>
                                                <dd class="text-[17px] font-semibold text-ink tabular-nums">{{ $day['current_max_kt'] !== null ? number_format($day['current_max_kt'], 2, ',', '.') . ' knot' : 'Data tidak tersedia' }}</dd>
                                            </div>
                                        </dl>

                                        @if (! empty($day['slots']))
                                            <div class="mt-6">
                                                <p class="mb-2 {{ $labelBand }}">Rincian</p>
                                                <ul class="space-y-1.5">
                                                    @foreach ($day['slots'] as $slot)
                                                        <li class="flex items-center justify-between gap-3 text-[13px] text-ink-soft">
                                                            <span class="tabular-nums">{{ $slot['time'] }}</span>
                                                            <span class="flex items-center gap-3">
                                                                <span class="tabular-nums">{{ $slot['wave_height_m'] !== null ? number_format($slot['wave_height_m'], 1, ',', '.') . ' m' : '—' }}</span>
                                                                <span class="max-w-[7rem] truncate" title="{{ $slot['wave_cat'] }}">{{ $slot['wave_cat'] ?? '—' }}</span>
                                                            </span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            <p class="mt-8 max-w-[62ch] text-[16px] leading-relaxed text-ink-body">
                                {{ config('weather.attribution.bmkg_marine') }}
                                Waktu ditampilkan dalam WITA. Hari pertama disajikan per jam; hari berikutnya per 3 jam.
                            </p>
                        </section>
                    @endif
                </div>
                </div>
            @endif

            {{-- ─── Colophon ───────────────────────────────────────────────────
                 Where, from whom, at which point, as of when — the four facts
                 about this reading, on the pale step above the source line. --}}
            <section class="-mx-5 mt-16 bg-surface-sunken px-5 py-8 sm:-mx-8 sm:px-8" data-reveal="rise">
                <dl class="grid grid-cols-2 gap-x-8 gap-y-6 lg:grid-cols-4" data-reveal="rows">
                    <div>
                        <dt class="{{ $labelBand }}">Wilayah Pantau</dt>
                        {{-- The configured area name (e.g. "Kecamatan Ujung"), so the
                             city entry and the kecamatan entry that share one BMKG
                             adm4 stay visibly distinct. --}}
                        <dd class="mt-1 text-[17px] font-semibold text-ink">{{ $loc['name'] ?? ($locLabel ?: 'Wilayah pantau') }}</dd>
                    </div>
                    <div>
                        <dt class="{{ $labelBand }}">Sumber Data</dt>
                        <dd class="mt-1 text-[17px] font-semibold text-ink">BMKG (prakiraan)</dd>
                    </div>
                    <div>
                        <dt class="{{ $labelBand }}">Koordinat</dt>
                        <dd class="mt-1 text-[17px] font-semibold text-ink tabular-nums">
                            @if ($latText && $lonText)
                                {{ $latText }} · {{ $lonText }}
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="{{ $labelBand }}">Pembaruan Terakhir</dt>
                        <dd class="mt-1 text-[17px] font-semibold text-ink">
                            {{ $weather['generated_at'] ? \Illuminate\Support\Carbon::parse($weather['generated_at'])->translatedFormat('d M Y, H:i') . ' WITA' : 'Belum ada' }}
                        </dd>
                    </div>
                </dl>
            </section>

            {{-- Source attribution --}}
            <p class="mt-8 max-w-[62ch] text-[13px] leading-relaxed text-ink-faint" data-reveal="rise">
                {{ $weather['attribution'] }}
                @if ($weather['generated_at'])
                    <span class="mt-1 block">Terakhir disinkronkan: {{ \Illuminate\Support\Carbon::parse($weather['generated_at'])->translatedFormat('d M Y, H:i') }} WITA.</span>
                @endif
            </p>
        </main>

        {{-- ─── Footer ─────────────────────────────────────────────────────────
             The same closing band as the landing page. --}}
        <footer class="bg-primary-900 text-white">
            <div class="mx-auto flex max-w-[1200px] flex-wrap items-center justify-between gap-x-8 gap-y-4 px-5 py-8 sm:px-8">
                <div>
                    <p class="text-[17px] font-semibold">SuperBie — Lapor Pak Wali</p>
                    <p class="mt-1 text-[13px] text-white/70">Pemerintah Kota Parepare, Sulawesi Selatan</p>
                </div>
                <nav class="flex items-center gap-6" aria-label="Tautan kaki halaman">
                    <a href="{{ route('home') }}" class="text-[17px] font-semibold text-white/85 transition-colors hover:text-white">Beranda</a>
                    <a href="{{ route('weather.index') }}" aria-current="page" class="text-[17px] font-semibold text-accent-400 transition-colors hover:text-accent-300">Cuaca</a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="text-[17px] font-semibold text-white/85 transition-colors hover:text-white">Akun saya</a>
                    @else
                        <a href="{{ route('login') }}" class="text-[17px] font-semibold text-white/85 transition-colors hover:text-white">Masuk</a>
                    @endauth
                </nav>
            </div>
        </footer>
    </div>
</x-layouts.app>
