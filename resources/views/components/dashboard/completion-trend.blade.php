@props([
    'series' => [],
])

@php
    // Prompt 3/4 — "Tingkat Penyelesaian" compact insight card.
    //
    // The previous full-width monthly chart was too large for the amount of
    // data. It is now a small insight card: the latest factual completion rate
    // (resolved ÷ inflow × 100 for a month with inflow > 0), the change vs the
    // previous month that HAS a factual rate, and a compact sparkline.
    //
    // A month with zero inflow has no factual rate, so it is honestly left as a
    // gap (null), never shown as 0%. No SLA, no forecast, no invented growth.
    $n = count($series);

    $points = [];
    foreach ($series as $p) {
        $in    = (int) ($p['inflow'] ?? 0);
        $out   = (int) ($p['resolved'] ?? 0);
        $points[] = [
            'label'    => $p['label'] ?? '',
            'inflow'   => $in,
            'resolved' => $out,
            'rate'     => $in > 0 ? round(($out / $in) * 100, 1) : null,
        ];
    }

    $hasData = collect($points)->contains(fn ($p) => $p['rate'] !== null);

    // Latest factual rate + change vs the previous non-null month.
    $lastIdx  = $n - 1;
    $lastRate = $lastIdx >= 0 ? $points[$lastIdx]['rate'] : null;
    $prevRate = null;
    for ($i = $lastIdx - 1; $i >= 0; $i--) {
        if ($points[$i]['rate'] !== null) { $prevRate = $points[$i]['rate']; break; }
    }
    $delta = ($lastRate !== null && $prevRate !== null) ? round($lastRate - $prevRate, 1) : null;

    // Compact sparkline over the months that have a factual rate only.
    $spark     = array_values(array_filter($points, fn ($p) => $p['rate'] !== null));
    $sparkN    = count($spark);
    $sw = 320; $sh = 64;
    $spadL = 4; $spadR = 4; $spadT = 8; $spadB = 8;
    $sPlotW = $sw - $spadL - $spadR;
    $sPlotH = $sh - $spadT - $spadB;
    $sxAt = fn (int $i) => $sparkN <= 1 ? $spadL + $sPlotW / 2 : $spadL + ($i * ($sPlotW / ($sparkN - 1)));
    $syAt = fn (float $pct) => $spadT + $sPlotH * (1 - ($pct / 100));

    $sparkLine = '';
    if ($sparkN >= 1) {
        $coords = [];
        foreach ($spark as $i => $p) {
            $coords[] = round($sxAt($i), 1).','.round($syAt((float) $p['rate']), 1);
        }
        $sparkLine = implode(' ', $coords);
    }
@endphp

@if ($hasData && $n > 0)
    <div class="w-full">
        {{-- Headline: latest rate + change vs the previous month --}}
        <div class="flex items-end flex-wrap gap-x-3 gap-y-2">
            <span class="text-3xl font-extrabold tabular-nums text-accent-700 leading-none">
                {{ $lastRate === null ? '—' : $lastRate.'%' }}
            </span>
            <span class="text-[11px] text-ink-faint">tingkat penyelesaian bulan terakhir</span>
        </div>

        @if ($delta !== null)
            <div class="mt-2">
                @if ($delta > 0)
                    <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200/70">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>
                        </svg>
                        +{{ $delta }}% dari bulan sebelumnya
                    </span>
                @elseif ($delta < 0)
                    <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full bg-rose-50 text-rose-700 border border-rose-200/70">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                        {{ $delta }}% dari bulan sebelumnya
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full bg-surface-sunken text-ink-soft border border-line/70">
                        stabil dari bulan sebelumnya
                    </span>
                @endif
            </div>
        @endif

        @if ($sparkN >= 2)
            <svg
                viewBox="0 0 {{ $sw }} {{ $sh }}"
                class="w-full h-auto mt-4"
                role="img"
                aria-label="Tingkat penyelesaian per bulan, dari {{ $spark[0]['label'] }} sampai {{ $spark[$sparkN - 1]['label'] }}"
            >
                <polyline points="{{ $sparkLine }}" fill="none" stroke="#8A5A00" stroke-width="2.5"
                          stroke-linecap="round" stroke-linejoin="round"></polyline>
                @foreach ($spark as $i => $p)
                    <circle cx="{{ round($sxAt($i), 1) }}" cy="{{ round($syAt((float) $p['rate']), 1) }}"
                            r="3" fill="#8A5A00" stroke="#FFFFFF" stroke-width="1.5"></circle>
                @endforeach
            </svg>
            <div class="flex items-center justify-between mt-1 text-[10px] text-ink-faint">
                <span>{{ $spark[0]['label'] }}</span>
                <span>{{ $spark[$sparkN - 1]['label'] }}</span>
            </div>
        @endif
    </div>
@endif
