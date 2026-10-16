@props([
    'trend' => ['labels' => [], 'series' => []],
])

@php
    // Tren Kasus per Wilayah (Prompt 7/5 Bagian 2) — monthly inflow per Kelurahan
    // for the Top-5 wilayah. Inline SVG (no chart library, no new dependency),
    // mirroring the existing `trend-lines` pattern. All values are real counts
    // supplied by DashboardCacheService; this view performs NO query.
    $labels = $trend['labels'] ?? [];
    $series = $trend['series'] ?? [];
    $n      = count($labels);

    // Distinct colours so series never rely on a single hue; the legend prints
    // each name + window total so information is available WITHOUT hover/colour.
    $palette = ['#013FF6', '#8A5A00', '#FFC14D', '#7C3AED', '#DB2777'];

    $flat = [];
    foreach ($series as $s) {
        foreach (($s['data'] ?? []) as $v) {
            $flat[] = (int) $v;
        }
    }
    $rawMax = $flat === [] ? 1 : max(1, max($flat));

    // Round the axis maximum up to a "nice" number (1/2/5 × 10^n).
    $magnitude = 10 ** (int) floor(log10($rawMax));
    $norm      = $rawMax / $magnitude;
    $nice      = $norm <= 1 ? 1 : ($norm <= 2 ? 2 : ($norm <= 5 ? 5 : 10));
    $maxValue  = (int) max(1, $nice * $magnitude);

    $w = 720; $h = 280;
    $padL = 46; $padR = 18; $padT = 18; $padB = 38;
    $plotW = $w - $padL - $padR;
    $plotH = $h - $padT - $padB;

    $xAt = fn (int $i) => $n <= 1 ? $padL + $plotW / 2 : $padL + ($i * ($plotW / ($n - 1)));
    $yAt = fn (int $v) => $padT + $plotH * (1 - ($v / $maxValue));

    $hasData = array_sum($flat) > 0;

    $gridLines = [];
    for ($g = 0; $g <= 4; $g++) {
        $val = (int) round($maxValue * (1 - $g / 4));
        $gridLines[] = ['y' => round($yAt($val), 1), 'value' => $val];
    }

    // Accessible text alternative: one sentence per series (name + total).
    $summaryParts = [];
    foreach ($series as $s) {
        $summaryParts[] = ($s['name'] ?? '') . ': ' . number_format((int) ($s['total'] ?? 0)) . ' laporan';
    }
    $ariaLabel = 'Tren jumlah laporan per bulan untuk kelurahan teratas. ' . implode('; ', $summaryParts);
@endphp

@if ($n > 0 && count($series) > 0 && $hasData)
    <x-dashboard.soft-card
        title="Tren Kasus per Wilayah"
        subtitle="Jumlah laporan masuk per bulan untuk kelurahan dengan volume tertinggi.">
        <div class="w-full">
            {{-- Local horizontal scroll on narrow screens so axis labels stay
                 legible; page-level overflow stays at zero (same pattern as the
                 main trend chart / heatmap). --}}
            <div class="overflow-x-auto">
                <svg
                    viewBox="0 0 {{ $w }} {{ $h }}"
                    class="w-full h-auto min-w-[600px]"
                    role="img"
                    aria-label="{{ $ariaLabel }}"
                >
                    {{-- Grid + Y axis labels (real axis values) --}}
                    @foreach ($gridLines as $line)
                        <line x1="{{ $padL }}" y1="{{ $line['y'] }}" x2="{{ $w - $padR }}" y2="{{ $line['y'] }}"
                              stroke="#F1F5F9" stroke-width="1"></line>
                        <text x="{{ $padL - 10 }}" y="{{ $line['y'] + 4 }}" text-anchor="end"
                              font-size="11" fill="#94A3B8">{{ number_format($line['value']) }}</text>
                    @endforeach

                    {{-- One polyline + data points per Kelurahan --}}
                    @foreach ($series as $si => $s)
                        @php
                            $color = $palette[$si % count($palette)];
                            $data  = array_map('intval', $s['data'] ?? []);
                            $pts   = [];
                            for ($i = 0; $i < $n; $i++) {
                                $pts[] = round($xAt($i), 1) . ',' . round($yAt($data[$i] ?? 0), 1);
                            }
                        @endphp
                        <polyline points="{{ implode(' ', $pts) }}" fill="none" stroke="{{ $color }}" stroke-width="2.5"
                                  stroke-linecap="round" stroke-linejoin="round"></polyline>
                        @for ($i = 0; $i < $n; $i++)
                            <circle cx="{{ round($xAt($i), 1) }}" cy="{{ round($yAt($data[$i] ?? 0), 1) }}" r="3"
                                    fill="{{ $color }}" stroke="#FFFFFF" stroke-width="1.5"></circle>
                        @endfor
                    @endforeach

                    {{-- X axis labels --}}
                    @foreach ($labels as $i => $label)
                        <text x="{{ round($xAt($i), 1) }}" y="{{ $padT + $plotH + 22 }}" text-anchor="middle"
                              font-size="11" fill="#64748B">{{ $label }}</text>
                    @endforeach
                </svg>
            </div>

            {{-- Legend: name + window total (text, not colour-only) --}}
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 mt-3 text-xs text-ink-soft">
                @foreach ($series as $si => $s)
                    <span class="inline-flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full" style="background: {{ $palette[$si % count($palette)] }}"></span>
                        <span class="font-medium text-ink">{{ $s['name'] }}</span>
                        <span class="text-ink-faint tabular-nums">({{ number_format((int) ($s['total'] ?? 0)) }})</span>
                    </span>
                @endforeach
            </div>
        </div>
    </x-dashboard.soft-card>
@else
    <x-dashboard.soft-card
        title="Tren Kasus per Wilayah"
        subtitle="Jumlah laporan masuk per bulan untuk kelurahan dengan volume tertinggi.">
        <x-dashboard.empty-state message="Belum ada data tren wilayah." />
    </x-dashboard.soft-card>
@endif
