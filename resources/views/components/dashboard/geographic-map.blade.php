@props([
    'map' => [],
])

@php
    use Illuminate\Support\Js;

    // All values come from DashboardCacheService::geographicMap() — real counts
    // and real coordinate pairs only. No DB access happens in this view.
    $totalReports  = (int) ($map['totalReports'] ?? 0);
    $withCoords    = (int) ($map['reportsWithCoordinates'] ?? 0);
    $withoutCoords = (int) ($map['reportsWithoutCoordinates'] ?? 0);
    $points        = $map['points'] ?? [];
    $byKecamatan   = $map['byKecamatan'] ?? [];
    $byKelurahan   = $map['byKelurahan'] ?? [];

    $topKelurahan  = array_slice($byKelurahan, 0, 3);

    // Only the fields the map needs cross into JS. No NIK / phone / address /
    // email / notes / password is ever serialised here.
    $mapConfig = [
        'points'        => $points,
        'defaultCenter' => config('map.default_center'),
        'defaultZoom'   => config('map.default_zoom'),
        'tileUrl'       => config('map.tile.url'),
        'attribution'   => config('map.tile.attribution'),
        'maxZoom'       => config('map.tile.max_zoom'),
    ];
@endphp

{{-- Peta Wilayah Aduan (Prompt 5/5) — geographic analytics card.
     READ-ONLY. Administrative counts come from kecamatan_id/kelurahan_id;
     pins/heatmap come ONLY from complaints with a valid lat+lng PAIR. --}}
<x-dashboard.soft-card
    x-data="geographicMap({{ Js::from($mapConfig) }})"
    title="🗺 Peta Wilayah Aduan"
    subtitle="Gambaran sebaran laporan berdasarkan wilayah kelurahan.">

    @if ($totalReports === 0)
        {{-- No reports at all → honest empty state, no map is initialised. --}}
        <x-dashboard.empty-state message="Belum ada data laporan untuk dipetakan." />
    @else
        <x-slot:actions>
            <div class="flex items-center gap-1 bg-surface-sunken rounded-full p-1" role="group" aria-label="Mode tampilan peta">
                <button
                    type="button"
                    @click="setMode('all')"
                    :aria-pressed="mode === 'all'"
                    :class="mode === 'all' ? 'bg-white text-ink shadow-sm' : 'text-ink-soft hover:text-ink'"
                    class="px-3 py-1 text-xs font-semibold rounded-full ui-animated"
                >Semua</button>
                <button
                    type="button"
                    @click="setMode('pin')"
                    :aria-pressed="mode === 'pin'"
                    :class="mode === 'pin' ? 'bg-white text-ink shadow-sm' : 'text-ink-soft hover:text-ink'"
                    class="px-3 py-1 text-xs font-semibold rounded-full ui-animated"
                >Pin</button>
                <button
                    type="button"
                    @click="setMode('heatmap')"
                    :aria-pressed="mode === 'heatmap'"
                    :class="mode === 'heatmap' ? 'bg-white text-ink shadow-sm' : 'text-ink-soft hover:text-ink'"
                    class="px-3 py-1 text-xs font-semibold rounded-full ui-animated"
                >Heatmap</button>
            </div>
        </x-slot:actions>

        <div class="space-y-4">
            @if ($withCoords === 0)
                {{-- No coordinate pairs → the map is not rendered; the
                     administrative stats below still render honestly. --}}
                <div class="rounded-xl border border-dashed border-line-strong bg-page px-4 py-8 text-center">
                    <p class="text-sm font-medium text-ink-soft">Belum ada titik lokasi laporan.</p>
                    <p class="text-xs text-ink-faint mt-1">Laporan tanpa koordinat tetap terhitung pada statistik wilayah di bawah.</p>
                </div>
            @else
                {{-- Map canvas. Fixed responsive height via CSS so Leaflet always
                     measures a non-zero box; isolated stacking context. --}}
                <div
                    x-ref="mapEl"
                    class="geographic-map-canvas"
                    role="region"
                    aria-label="Peta sebaran laporan berdasarkan koordinat lokasi laporan"
                ></div>

                {{-- Graceful degradation if Leaflet/tiles fail to load. --}}
                <p
                    x-show="mapError"
                    x-cloak
                    class="text-xs text-accent-700 bg-accent-50 border border-accent-200 rounded-xl px-3 py-2"
                >
                    Peta tidak dapat dimuat. Statistik wilayah tetap tersedia di bawah.
                </p>

                {{-- Heatmap mode unavailable (plugin failed to register). --}}
                <p
                    x-show="heatUnavailable && mode === 'heatmap'"
                    x-cloak
                    class="text-xs text-accent-700 bg-accent-50 border border-accent-200 rounded-xl px-3 py-2"
                >
                    Mode heatmap tidak tersedia pada perangkat ini. Gunakan mode Pin atau Semua.
                </p>

                {{-- Heatmap legend (only meaningful in heatmap mode). --}}
                <div
                    x-show="mode === 'heatmap'"
                    x-cloak
                    class="flex items-center gap-2 text-[11px] text-ink-faint"
                >
                    <span>Kerapatan rendah</span>
                    <span class="h-2 w-24 rounded-full" style="background: linear-gradient(90deg, #013FF6, #22D3EE, #84CC16, #FACC15, #EF4444);" aria-hidden="true"></span>
                    <span>Kerapatan tinggi</span>
                </div>
            @endif

            {{-- Summary + top-Kelurahan insight (administrative counts). --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="rounded-xl bg-page border border-line px-3 py-2.5">
                    <p class="text-[11px] font-medium text-ink-faint uppercase tracking-wider">Total Laporan</p>
                    <p class="text-lg font-bold text-ink tabular-nums">{{ number_format($totalReports) }}</p>
                </div>
                <div class="rounded-xl bg-page border border-line px-3 py-2.5">
                    <p class="text-[11px] font-medium text-ink-faint uppercase tracking-wider">Dengan Titik</p>
                    <p class="text-lg font-bold text-ink tabular-nums">{{ number_format($withCoords) }}</p>
                </div>
                <div class="rounded-xl bg-page border border-line px-3 py-2.5">
                    <p class="text-[11px] font-medium text-ink-faint uppercase tracking-wider">Tanpa Titik</p>
                    <p class="text-lg font-bold text-ink tabular-nums">{{ number_format($withoutCoords) }}</p>
                </div>
            </div>

            @if (count($topKelurahan) > 0)
                <div>
                    <p class="text-xs font-semibold text-ink-soft mb-2">Kelurahan dengan laporan terbanyak</p>
                    <ul class="space-y-1.5">
                        @foreach ($topKelurahan as $row)
                            <li class="flex items-center justify-between text-xs">
                                <span class="text-ink font-medium truncate">
                                    {{ $row['name'] }}
                                    <span class="text-ink-faint font-normal">· {{ $row['kecamatan'] }}</span>
                                </span>
                                <span class="text-ink-soft font-semibold tabular-nums shrink-0 ml-2">{{ number_format($row['total']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($withCoords > 0 && $withoutCoords > 0)
                <p class="text-[11px] text-ink-faint">
                    {{ number_format($withoutCoords) }} laporan tanpa koordinat tetap terhitung pada statistik wilayah.
                </p>
            @endif
        </div>
    @endif
</x-dashboard.soft-card>
