@props([
    'series' => [],
])

@php
    // Decision-oriented multi-line chart: Laporan Masuk vs Laporan Selesai vs
    // Backlog (cumulative backlog), drawn as inline SVG — no chart library,
    // no new dependency.
    //
    // Every value is a real count (submitted_at / resolved_at) or a factual
    // running balance of two real counts (`backlog` = Σ masuk − Σ selesai over
    // the displayed window). No SLA, no forecast, no fabricated figure.
    $n = count($series);

    $inflow   = array_map(fn ($p) => (int) ($p['inflow'] ?? 0), $series);
    $resolved = array_map(fn ($p) => (int) ($p['resolved'] ?? 0), $series);
    $backlog  = array_map(fn ($p) => (int) ($p['backlog'] ?? 0), $series);

    $rawMax = max(1, max(array_merge($inflow ?: [0], $resolved ?: [0], $backlog ?: [0])));

    // Round the axis maximum up to a "nice" number (1/2/5 × 10^n).
    $magnitude = 10 ** (int) floor(log10($rawMax));
    $norm      = $rawMax / $magnitude;
    $nice      = $norm <= 1 ? 1 : ($norm <= 2 ? 2 : ($norm <= 5 ? 5 : 10));
    $maxValue  = (int) max(1, $nice * $magnitude);

    $w = 720; $h = 280;
    $padL = 46; $padR = 18; $padT = 18; $padB = 38;
    $plotW = $w - $padL - $padR;
    $plotH = $h - $padT - $padB;

    $xAt = function (int $i) use ($n, $padL, $plotW) {
        return $n <= 1 ? $padL + $plotW / 2 : $padL + ($i * ($plotW / ($n - 1)));
    };
    $yAt = function (int $v) use ($padT, $plotH, $maxValue) {
        return $padT + $plotH * (1 - ($v / $maxValue));
    };

    $pointsFor = function (array $vals) use ($xAt, $yAt) {
        $out = [];
        foreach ($vals as $i => $v) {
            $out[] = round($xAt($i), 1).','.round($yAt((int) $v), 1);
        }
        return implode(' ', $out);
    };

    $inflowPts   = $pointsFor($inflow);
    $resolvedPts = $pointsFor($resolved);
    $backlogPts  = $pointsFor($backlog);

    // Horizontal grid: 4 intervals → 5 lines, labelled with real axis values.
    $gridLines = [];
    for ($g = 0; $g <= 4; $g++) {
        $val = (int) round($maxValue * (1 - $g / 4));
        $gridLines[] = ['y' => round($yAt($val), 1), 'value' => $val];
    }

    $hasData = array_sum($inflow) + array_sum($resolved) > 0;

    $tipW = 168;
    $tipH = 96;

    // Hover bands: [left, right] per point, split at the midpoints.
    $bands = [];
    for ($i = 0; $i < $n; $i++) {
        $x = $xAt($i);
        $left  = $i === 0     ? $padL          : (($xAt($i - 1) + $x) / 2);
        $right = $i === $n - 1 ? $w - $padR    : (($x + $xAt($i + 1)) / 2);
        $bands[] = ['x' => $x, 'left' => round($left, 1), 'right' => round($right, 1)];
    }
@endphp

@if ($hasData && $n > 0)
    <div class="w-full">
        <style>
            .tl-band { cursor: crosshair; }
            .tl-band .tl-tip { opacity: 0; pointer-events: none; transition: opacity 120ms ease; }
            .tl-band .tl-guide { opacity: 0; transition: opacity 120ms ease; }
            .tl-band:hover .tl-tip { opacity: 1; }
            .tl-band:hover .tl-guide { opacity: 1; }
            @media (prefers-reduced-motion: reduce) {
                .tl-band .tl-tip, .tl-band .tl-guide { transition: none; }
            }
        </style>

        {{-- Local horizontal scroll on narrow screens: the SVG keeps a legible
             minimum width instead of shrinking its axis labels to unreadable
             sizes. Page-level overflow stays at zero (same pattern as the
             heatmap). On desktop the chart is simply `w-full`. --}}
        <div class="overflow-x-auto">
        <svg
            viewBox="0 0 {{ $w }} {{ $h }}"
            class="w-full h-auto min-w-[600px]"
            role="img"
            aria-label="Tren bulanan laporan masuk, laporan selesai, dan backlog"
        >
            <defs>
                <linearGradient id="tlInflow" x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0%" stop-color="#013FF6" stop-opacity="0.22"></stop>
                    <stop offset="100%" stop-color="#013FF6" stop-opacity="0"></stop>
                </linearGradient>
            </defs>

            {{-- Grid + Y axis labels (real axis values) --}}
            @foreach ($gridLines as $line)
                <line x1="{{ $padL }}" y1="{{ $line['y'] }}" x2="{{ $w - $padR }}" y2="{{ $line['y'] }}"
                      stroke="#F1F5F9" stroke-width="1"></line>
                <text x="{{ $padL - 10 }}" y="{{ $line['y'] + 4 }}" text-anchor="end"
                      font-size="11" fill="#94A3B8">{{ number_format($line['value']) }}</text>
            @endforeach

            {{-- Soft area under the inflow line for depth --}}
            @if ($n > 1)
                <polygon points="{{ $padL }},{{ $padT + $plotH }} {{ $inflowPts }} {{ $w - $padR }},{{ $padT + $plotH }}"
                         fill="url(#tlInflow)"></polygon>
            @endif

            {{-- Lines: backlog (amber) → inflow (blue) → resolved (green) --}}
            <polyline points="{{ $backlogPts }}" fill="none" stroke="#FFC14D" stroke-width="2.5"
                      stroke-linecap="round" stroke-linejoin="round"></polyline>
            <polyline points="{{ $inflowPts }}" fill="none" stroke="#013FF6" stroke-width="2.5"
                      stroke-linecap="round" stroke-linejoin="round"></polyline>
            <polyline points="{{ $resolvedPts }}" fill="none" stroke="#8A5A00" stroke-width="2.5"
                      stroke-linecap="round" stroke-linejoin="round"></polyline>

            {{-- Data points --}}
            @foreach ($series as $i => $p)
                <circle cx="{{ round($xAt($i), 1) }}" cy="{{ round($yAt((int) $inflow[$i]), 1) }}" r="3" fill="#013FF6" stroke="#FFFFFF" stroke-width="1.5"></circle>
                <circle cx="{{ round($xAt($i), 1) }}" cy="{{ round($yAt((int) $resolved[$i]), 1) }}" r="3" fill="#8A5A00" stroke="#FFFFFF" stroke-width="1.5"></circle>
                <circle cx="{{ round($xAt($i), 1) }}" cy="{{ round($yAt((int) $backlog[$i]), 1) }}" r="3" fill="#FFC14D" stroke="#FFFFFF" stroke-width="1.5"></circle>
            @endforeach

            {{-- X axis labels --}}
            @foreach ($series as $i => $p)
                <text x="{{ round($xAt($i), 1) }}" y="{{ $padT + $plotH + 22 }}" text-anchor="middle"
                      font-size="11" fill="#64748B">{{ $p['label'] ?? '' }}</text>
            @endforeach

            {{-- Hover bands + tooltips (rendered last so they sit on top) --}}
            @foreach ($series as $i => $p)
                @php
                    $band   = $bands[$i];
                    $tipX   = min(max($band['x'] - $tipW / 2, $padL), $w - $padR - $tipW);
                    $tipY   = 6;
                    $in     = (int) $p['inflow'];
                    $out    = (int) $p['resolved'];
                    $bl     = (int) $p['backlog'];
                @endphp
                <g class="tl-band">
                    <rect x="{{ $band['left'] }}" y="{{ $padT }}" width="{{ $band['right'] - $band['left'] }}"
                          height="{{ $plotH }}" fill="transparent"></rect>

                    <line class="tl-guide" x1="{{ round($band['x'], 1) }}" y1="{{ $padT }}"
                          x2="{{ round($band['x'], 1) }}" y2="{{ $padT + $plotH }}"
                          stroke="#CBD5E1" stroke-width="1" stroke-dasharray="3 3"></line>

                    <g class="tl-tip" transform="translate({{ round($tipX, 1) }}, {{ $tipY }})">
                        <rect width="{{ $tipW }}" height="{{ $tipH }}" rx="12" fill="#0F172A" fill-opacity="0.96"></rect>
                        <text x="14" y="22" font-size="12" font-weight="700" fill="#FFFFFF">{{ $p['label'] ?? '' }}</text>

                        <circle cx="16" cy="42" r="3.5" fill="#5C85FF"></circle>
                        <text x="28" y="46" font-size="11.5" fill="#E2E8F0">Masuk</text>
                        <text x="{{ $tipW - 14 }}" y="46" font-size="11.5" font-weight="700" fill="#FFFFFF" text-anchor="end">{{ number_format($in) }}</text>

                        <circle cx="16" cy="64" r="3.5" fill="#34D399"></circle>
                        <text x="28" y="68" font-size="11.5" fill="#E2E8F0">Selesai</text>
                        <text x="{{ $tipW - 14 }}" y="68" font-size="11.5" font-weight="700" fill="#FFFFFF" text-anchor="end">{{ number_format($out) }}</text>

                        <circle cx="16" cy="86" r="3.5" fill="#FFC14D"></circle>
                        <text x="28" y="90" font-size="11.5" fill="#E2E8F0">Backlog</text>
                        <text x="{{ $tipW - 14 }}" y="90" font-size="11.5" font-weight="700" fill="#FFFFFF" text-anchor="end">{{ number_format($bl) }}</text>
                    </g>
                </g>
            @endforeach
        </svg>
        </div>

        {{-- Legend (three lines) --}}
        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 mt-3 text-xs text-ink-soft">
            <span class="inline-flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-primary-600"></span> Laporan Masuk
            </span>
            <span class="inline-flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-accent-700"></span> Laporan Selesai
            </span>
            <span class="inline-flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-accent-500"></span> Backlog
            </span>
        </div>
    </div>
@endif
