@props([
    'readonly' => false,
    'latitude' => null,
    'longitude' => null,
    'label' => 'Peta Lokasi Kejadian',
    'hint' => 'Opsional — geser dan klik/tap pada peta untuk menandai titik lokasi, atau gunakan deteksi lokasi.',
])

@php
    // Coordinates are only ever used as a valid PAIR. A partial pair is treated
    // as "no coordinates" (never rendered as a marker).
    $hasCoords = $latitude !== null && $longitude !== null && $latitude !== '' && $longitude !== '';

    $mapConfig = [
        'readonly'      => (bool) $readonly,
        'lat'           => $hasCoords ? (float) $latitude : null,
        'lng'           => $hasCoords ? (float) $longitude : null,
        'defaultCenter' => config('map.default_center'),
        'defaultZoom'   => config('map.default_zoom'),
        'tileUrl'       => config('map.tile.url'),
        'attribution'   => config('map.tile.attribution'),
        'maxZoom'       => config('map.tile.max_zoom'),
    ];
@endphp

{{-- Reserved `.location-map-slot` from Prompt 3/5, now filled by Prompt 4/5.
     Administrative (Kecamatan/Kelurahan) and text (location_text) concepts stay
     SEPARATE and REQUIRED/unchanged; this only adds an OPTIONAL geographic pin. --}}
<div class="location-map-slot space-y-3">
    @if ($readonly && ! $hasCoords)
        <div class="p-3.5 rounded-xl bg-page border border-line text-xs text-ink-faint">
            Lokasi pada peta belum tersedia.
        </div>
    @else
        <div
            x-data="locationMap({{ Js::from($mapConfig) }})"
            class="space-y-3"
        >
            <div>
                <h4 class="text-xs font-bold text-ink uppercase tracking-wider">{{ $label }}</h4>
                @unless ($readonly)
                    <p class="text-[11px] text-ink-faint mt-0.5">{{ $hint }}</p>
                @endunless
            </div>

            {{-- Map container. Fixed height via CSS so Leaflet always measures a
                 non-zero box; contained stacking context so Leaflet panes never
                 overlap the app chrome. --}}
            <div
                x-ref="mapEl"
                class="location-map-canvas"
                role="region"
                aria-label="{{ $readonly ? 'Peta lokasi laporan (hanya lihat)' : 'Peta lokasi laporan. Pilih titik dengan mengklik peta.' }}"
            ></div>

            {{-- Graceful degradation: if the map library/tiles fail, the form
                 stays fully usable with Kecamatan/Kelurahan/location_text. --}}
            <p
                x-show="mapError"
                x-cloak
                class="text-xs text-accent-700 bg-accent-50 border border-accent-200 rounded-xl px-3 py-2"
            >
                Peta tidak dapat dimuat. Anda tetap dapat mengisi Kecamatan, Kelurahan, dan alamat lokasi.
            </p>

            @unless ($readonly)
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        @click="detectLocation()"
                        aria-label="Deteksi lokasi presisi perangkat saya"
                        class="inline-flex items-center gap-2 px-3.5 py-2 bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold rounded-xl ui-animated shadow-sm"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8a4 4 0 100 8 4 4 0 000-8zm0-6v3m0 14v3m10-10h-3M5 12H2m15.364-5.364l-2.121 2.121M8.757 15.243l-2.121 2.121m0-11.314l2.121 2.121m6.486 6.486l2.121 2.121"/>
                        </svg>
                        Deteksi Lokasi Presisi Saya
                    </button>

                    <button
                        type="button"
                        @click="clearLocation()"
                        x-show="hasPoint"
                        x-cloak
                        aria-label="Hapus titik lokasi yang dipilih"
                        class="inline-flex items-center gap-2 px-3.5 py-2 bg-white hover:bg-surface-sunken text-ink-soft text-xs font-semibold rounded-xl border border-line-strong ui-animated shadow-sm"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Hapus titik lokasi
                    </button>
                </div>

                {{-- Live status for screen readers (never the sole signal). --}}
                <p
                    class="text-xs font-medium"
                    :class="{
                        'text-ink-faint': statusKind === 'idle' || statusKind === 'loading',
                        'text-green-700': statusKind === 'success',
                        'text-red-600': statusKind === 'error',
                    }"
                    aria-live="polite"
                    x-text="statusMessage"
                ></p>

                <p class="text-[11px] text-ink-ghost" x-show="hasPoint" x-cloak>
                    <span x-text="coordinateLabel"></span>
                </p>

                {{-- Hidden inputs carry the final coordinates with the normal form
                     submit. They are NOT a security boundary — the server
                     re-validates everything (range + pair). A server-rendered
                     `value` keeps the current point even if JS never runs. --}}
                <input
                    type="hidden"
                    name="latitude"
                    x-model="latitude"
                    value="{{ $hasCoords ? number_format((float) $latitude, 7, '.', '') : '' }}"
                >
                <input
                    type="hidden"
                    name="longitude"
                    x-model="longitude"
                    value="{{ $hasCoords ? number_format((float) $longitude, 7, '.', '') : '' }}"
                >
            @endunless
        </div>
    @endif
</div>
