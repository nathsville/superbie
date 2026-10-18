@props([
    'points' => [],
    'tone' => '#013FF6',
    'ariaLabel' => 'Grafik tren harga',
])

@php
    // Pure inline SVG (no chart library — same visual language as the weather
    // and dashboard charts). Points are whole rupiah and the y axis prints
    // them with thousands separators.
    //
    // HONESTY CONTRACT: every point is a REAL stored price passed in by the
    // caller. A NULL value is treated as a GAP (never coerced to 0); the chart
    // never smooths, interpolates or forecasts — the recorded rows are plotted
    // exactly as they stand.
    $pts = is_array($points) ? array_values($points) : [];
    $n = count($pts);

    $values = [];
    foreach ($pts as $p) {
        $values[] = $p['value'] ?? null;
    }

    $nonNull = array_values(array_filter($values, static fn ($v) => $v !== null));
    $hasData = $n > 0 && count($nonNull) > 0;

    $w = 560; $h = 220;
    $padL = 76; $padR = 16; $padT = 16; $padB = 40;
    $plotW = $w - $padL - $padR;
    $plotH = $h - $padT - $padB;

    $grid = [];
    $linePts = '';
    $xLabels = [];

    if ($hasData) {
        $minV = (float) min($nonNull);
        $maxV = (float) max($nonNull);

        $lo = $minV;
        $hi = $maxV;

        if ($hi - $lo < 1.0) {
            // Flat series: open a small REAL window around the single value so
            // the axis labels stay distinguishable — the plotted value itself
            // still comes straight from the stored row.
            $spread = max(100.0, round($lo * 0.01));
            $lo -= $spread;
            $hi += $spread;
        }

        $pad = ($hi - $lo) * 0.12;
        $bottom = $lo - $pad;
        $top = $hi + $pad;
        $span = max($top - $bottom, 1.0);

        $xAt = static function (int $i) use ($n, $padL, $plotW): float {
            return $n <= 1 ? $padL + $plotW / 2 : $padL + ($i * ($plotW / ($n - 1)));
        };
        // Map a value to its y pixel: `bottom` sits at the plot floor, `top` at
        // the plot ceiling.
        $yAt = static function (float $v) use ($padT, $plotH, $span, $bottom): float {
            return $padT + $plotH * (1 - (($v - $bottom) / $span));
        };

        for ($g = 0; $g <= 3; $g++) {
            $val = $top - $span * ($g / 3);
            $grid[] = ['y' => round($yAt($val), 1), 'value' => $val];
        }

        $coords = [];
        foreach ($values as $i => $v) {
            if ($v === null) {
                continue;
            }
            $coords[] = round($xAt($i), 1) . ',' . round($yAt((float) $v), 1);
        }
        $linePts = implode(' ', $coords);

        // Thin out the x labels so up to 30 recordings stay readable.
        $step = max(1, (int) ceil($n / 7));
        foreach ($pts as $i => $p) {
            if ($i % $step !== 0 && $i !== $n - 1) {
                continue;
            }
            $xLabels[] = ['x' => round($xAt($i), 1), 'label' => $p['label'] ?? ''];
        }
    }
@endphp

@if ($hasData)
    <div class="w-full">
        <div class="overflow-x-auto">
            <svg viewBox="0 0 {{ $w }} {{ $h }}" class="h-auto w-full" role="img" aria-label="{{ $ariaLabel }}">
                {{-- No gradient wash and no shadow: the area under the line is
                     one flat tone and the structure comes from the hairline
                     grid, exactly like the weather sheet. `wx-chart-*` are the
                     shared pure-CSS chart reveal helpers (app.css); they
                     animate transform/opacity only — never the values — and
                     stand still under `prefers-reduced-motion`. --}}
                <g class="wx-chart-area">
                    @foreach ($grid as $line)
                        <line x1="{{ $padL }}" y1="{{ $line['y'] }}" x2="{{ $w - $padR }}" y2="{{ $line['y'] }}"
                              class="stroke-line" stroke-width="1"></line>
                        <text x="{{ $padL - 10 }}" y="{{ $line['y'] + 5 }}" text-anchor="end"
                              class="fill-ink-soft" font-size="13">{{ number_format($line['value'], 0, ',', '.') }}</text>
                    @endforeach

                    @if ($n > 1)
                        <polygon points="{{ $padL }},{{ $padT + $plotH }} {{ $linePts }} {{ $w - $padR }},{{ $padT + $plotH }}"
                                 fill="{{ $tone }}" fill-opacity="0.10"></polygon>
                    @endif

                    <polyline points="{{ $linePts }}" fill="none" stroke="{{ $tone }}" stroke-width="2.5"
                              stroke-linecap="round" stroke-linejoin="round" class="wx-chart-line"></polyline>

                    @foreach ($values as $i => $v)
                        @if ($v !== null)
                            @php
                                $cx = $n <= 1 ? $padL + $plotW / 2 : $padL + ($i * ($plotW / ($n - 1)));
                                $cy = $padT + $plotH * (1 - (((float) $v - $bottom) / $span));
                            @endphp
                            <circle cx="{{ round($cx, 1) }}" cy="{{ round($cy, 1) }}" r="2.6" fill="{{ $tone }}"
                                    stroke-width="1.5" class="wx-chart-dot stroke-surface"
                                    style="animation-delay: {{ 220 + $i * 22 }}ms"></circle>
                        @endif
                    @endforeach

                    @foreach ($xLabels as $xl)
                        <text x="{{ $xl['x'] }}" y="{{ $padT + $plotH + 26 }}" text-anchor="middle"
                              class="fill-ink-soft" font-size="13">{{ $xl['label'] }}</text>
                    @endforeach
                </g>
            </svg>
        </div>
    </div>
@endif
