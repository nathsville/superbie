@props([
    'points' => [],
    'unit' => '',
    'tone' => '#013FF6',
    'variant' => 'line',
    'decimals' => 1,
    'ariaLabel' => 'Grafik prakiraan',
])

@php
    // Pure inline SVG (no chart library — same pattern as the dashboard charts).
    //
    // HONESTY CONTRACT: every point is a REAL stored value passed in by the
    // caller. A NULL value is treated as a GAP (never coerced to 0). The chart
    // never invents, smooths, or forecasts a value.
    $pts = is_array($points) ? array_values($points) : [];
    $n = count($pts);

    $values = [];
    foreach ($pts as $p) {
        $values[] = $p['value'] ?? null;
    }
    $nonNull = array_values(array_filter($values, static fn ($v) => $v !== null));
    $hasData = $n > 0 && count($nonNull) > 0;

    $w = 560; $h = 220;
    $padL = 56; $padR = 16; $padT = 16; $padB = 40;
    $plotW = $w - $padL - $padR;
    $plotH = $h - $padT - $padB;

    $grid = [];
    $linePts = '';
    $bars = [];
    $xLabels = [];

    if ($hasData) {
        $maxV = (float) max($nonNull);
        $minV = (float) min($nonNull);

        if ($variant === 'bar') {
            $bottom = 0.0;
            $top = $maxV > 0 ? $maxV * 1.1 : 1.0;
        } else {
            $lo = $minV; $hi = $maxV;
            if ($hi - $lo < 0.0001) {
                $hi = $lo + 1.0;
            }
            $pad = ($hi - $lo) * 0.12;
            $bottom = $lo - $pad;
            $top = $hi + $pad;
        }

        $span = max($top - $bottom, 0.0001);

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
            $coords[] = round($xAt($i), 1).','.round($yAt((float) $v), 1);
        }
        $linePts = implode(' ', $coords);

        if ($variant === 'bar') {
            $slotW = $n > 1 ? $plotW / $n : $plotW;
            $barW = max(2.0, $slotW * 0.6);
            $baseY = round($yAt($bottom), 1);
            foreach ($values as $i => $v) {
                if ($v === null) {
                    continue;
                }
                $y = round($yAt((float) $v), 1);
                $bars[] = [
                    'x' => round($xAt($i) - $barW / 2, 1),
                    'y' => $y,
                    'w' => round($barW, 1),
                    'h' => max(0.0, round($baseY - $y, 1)),
                ];
            }
        }

        $step = max(1, (int) ceil($n / 8));
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
            <svg viewBox="0 0 {{ $w }} {{ $h }}" class="w-full h-auto" role="img" aria-label="{{ $ariaLabel }}">
                {{-- No gradient wash and no shadow: the area under the line is one
                     flat tone (printed-plot convention) and the structure comes
                     from the hairline grid. Axis labels carry the value only; the
                     unit is printed in the chart header, one line above. --}}

                {{-- Grid + Y axis (real axis values) --}}
                {{-- `wx-chart-*` classes are pure-CSS reveal helpers (defined in
                     app.css). They animate only transform/opacity, never the
                     values, and are disabled under `prefers-reduced-motion`. --}}
                <g class="wx-chart-area">
                @foreach ($grid as $line)
                    <line x1="{{ $padL }}" y1="{{ $line['y'] }}" x2="{{ $w - $padR }}" y2="{{ $line['y'] }}"
                          class="stroke-line" stroke-width="1"></line>
                    <text x="{{ $padL - 10 }}" y="{{ $line['y'] + 5 }}" text-anchor="end"
                          class="fill-ink-soft" font-size="17">{{ number_format($line['value'], $decimals, ',', '.') }}</text>
                @endforeach

                @if ($variant === 'bar')
                    @foreach ($bars as $b)
                        <rect x="{{ $b['x'] }}" y="{{ $b['y'] }}" width="{{ $b['w'] }}" height="{{ $b['h'] }}"
                              rx="2" fill="{{ $tone }}" fill-opacity="0.85"
                              class="wx-chart-bar" style="animation-delay: {{ $loop->index * 45 }}ms"></rect>
                    @endforeach
                @else
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
                @endif

                {{-- X axis labels --}}
                @foreach ($xLabels as $xl)
                    <text x="{{ $xl['x'] }}" y="{{ $padT + $plotH + 26 }}" text-anchor="middle"
                          class="fill-ink-soft" font-size="17">{{ $xl['label'] }}</text>
                @endforeach
                </g>
            </svg>
        </div>
    </div>
@endif
