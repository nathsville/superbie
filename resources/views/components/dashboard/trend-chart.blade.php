@props([
    'series' => [],
])

@php
    // Monthly inflow vs resolution, rendered with pure SVG/flex (no chart library).
    // Bars are proportional to the largest value across BOTH series so the two
    // are visually comparable. If there is no data at all we show nothing here;
    // the caller is responsible for the honest empty state.
    $maxValue = 0;
    foreach ($series as $point) {
        $maxValue = max($maxValue, (int) ($point['inflow'] ?? 0), (int) ($point['resolved'] ?? 0));
    }
    $hasData = $maxValue > 0;

    // "Nice" axis maximum (1/2/5 × 10^n) so the scale reads cleanly.
    if ($maxValue > 0) {
        $magnitude = 10 ** (int) floor(log10($maxValue));
        $norm      = $maxValue / $magnitude;
        $nice      = $norm <= 1 ? 1 : ($norm <= 2 ? 2 : ($norm <= 5 ? 5 : 10));
        $axisMax   = (int) max(1, $nice * $magnitude);
    } else {
        $axisMax = 1;
    }
@endphp

@if ($hasData)
    <div class="w-full">
        {{-- Axis header — factual scale reference (visual axis). --}}
        <div class="flex items-center justify-between gap-3 mb-4">
            <span class="text-[11px] font-semibold uppercase tracking-wider text-ink-faint">Volume per bulan</span>
            <span class="text-[11px] text-ink-faint">
                Skala maks <strong class="text-ink tabular-nums">{{ number_format($axisMax) }}</strong>
            </span>
        </div>

        <div class="flex items-end gap-3 sm:gap-4 h-44 overflow-hidden" role="img"
             aria-label="Tren laporan masuk dan selesai per bulan">
            @foreach ($series as $point)
                @php
                    $inflow   = (int) ($point['inflow'] ?? 0);
                    $resolved = (int) ($point['resolved'] ?? 0);
                    $gap      = (int) ($point['gap'] ?? ($inflow - $resolved));
                    // A zero month renders NO bar (height 0) — a visible 2% stub for
                    // zero would be a fabricated signal. Non-zero values keep a 2%
                    // minimum so tiny counts stay visible.
                    $inH      = $inflow > 0 ? max(2, (int) round(($inflow / $axisMax) * 100)) : 0;
                    $outH     = $resolved > 0 ? max(2, (int) round(($resolved / $axisMax) * 100)) : 0;
                @endphp
                <div class="flex-1 flex flex-col items-center gap-2 min-w-0">
                    <div class="w-full flex items-end justify-center gap-1 h-32 border-b border-line">
                        <div class="w-1/2 max-w-[18px] rounded-t-md bg-gradient-to-t from-primary-600/65 to-primary-600 transition-all duration-500 ease-standard"
                             style="height: {{ $inH }}%" title="Masuk: {{ $inflow }}"></div>
                        <div class="w-1/2 max-w-[18px] rounded-t-md bg-gradient-to-t from-accent-700/65 to-accent-700 transition-all duration-500 ease-standard"
                             style="height: {{ $outH }}%" title="Selesai: {{ $resolved }}"></div>
                    </div>
                    <div class="text-center">
                        <span class="block text-[11px] font-medium text-ink-soft truncate">{{ $point['label'] ?? '' }}</span>
                        <span class="block text-[10px] tabular-nums {{ $gap > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                            {{ $gap > 0 ? '+' : '' }}{{ $gap }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Legend — tidy, aligned pills. --}}
        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 mt-4 pt-4 border-t border-line text-xs text-ink-soft">
            <span class="inline-flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-sm bg-primary-600"></span> Laporan Masuk
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-sm bg-accent-700"></span> Laporan Selesai
            </span>
            <span class="text-ink-faint">Angka di bawah tiap bulan = selisih (masuk − selesai)</span>
        </div>
    </div>
@endif
