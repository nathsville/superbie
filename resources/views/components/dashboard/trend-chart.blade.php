@props([
    'series'  => [],
    'variant' => 'standard',
])

@php
    // Monthly inflow vs resolution, rendered with pure translate-free divs
    // (no chart library). Bars are proportional to the largest value across
    // BOTH series so the two are visually comparable. If there is no data at
    // all we show nothing here; the caller owns the honest empty state.
    //
    // Two surfaces, one component:
    //  - `standard` (default) — the quiet chart (operator dashboard).
    //  - `bold`               — brand-pair bars, hover tooltips, click-to-focus
    //                            legend via Alpine (Super Admin).
    $bold = $variant === 'bold';

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
    @if ($bold)
        <div class="w-full" x-data="{ focus: null }">
            {{-- Axis hint + interactive series switches (click = focus, click again = reset). --}}
            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 mb-5">
                <span class="text-[13px] font-medium text-ink-soft">Volume per bulan</span>
                <div class="flex items-center gap-1" role="group" aria-label="Sorot seri chart">
                    <button type="button" @click="focus = focus === 'masuk' ? null : 'masuk'"
                            :class="focus === 'masuk' ? 'bg-primary-50 text-primary-700' : 'text-ink-soft hover:bg-page hover:text-ink'"
                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ui-animated">
                        <span class="h-2.5 w-2.5 rounded-[2px] bg-primary-600" aria-hidden="true"></span>
                        Laporan Masuk
                    </button>
                    <button type="button" @click="focus = focus === 'selesai' ? null : 'selesai'"
                            :class="focus === 'selesai' ? 'bg-accent-100 text-accent-800' : 'text-ink-soft hover:bg-page hover:text-ink'"
                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ui-animated">
                        <span class="h-2.5 w-2.5 rounded-[2px] bg-accent-500" aria-hidden="true"></span>
                        Laporan Selesai
                    </button>
                </div>
            </div>

            <div class="flex items-end gap-1 sm:gap-2" role="img"
                 aria-label="Tren laporan masuk dan selesai per bulan">
                @foreach ($series as $point)
                    @php
                        $inflow   = (int) ($point['inflow'] ?? 0);
                        $resolved = (int) ($point['resolved'] ?? 0);
                        $gap      = (int) ($point['gap'] ?? ($inflow - $resolved));
                        // A zero month renders NO bar (height 0) — a visible stub for
                        // zero would be a fabricated signal. Non-zero values keep a 2%
                        // minimum so tiny counts stay visible.
                        $inH      = $inflow > 0 ? max(2, (int) round(($inflow / $axisMax) * 100)) : 0;
                        $outH     = $resolved > 0 ? max(2, (int) round(($resolved / $axisMax) * 100)) : 0;
                    @endphp
                    <div class="group relative flex min-w-0 flex-1 flex-col items-center gap-2 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600"
                         tabindex="0">
                        {{-- Hover band behind the bars. --}}
                        <div class="pointer-events-none absolute inset-y-0 -inset-x-1 rounded-xl bg-page opacity-0 transition-opacity ui-animated group-hover:opacity-100 group-focus-visible:opacity-100"
                             aria-hidden="true"></div>

                        {{-- Hover / focus tooltip. Anchored inside the plot so it is
                             never clipped by the card edge. --}}
                        <div class="pointer-events-none absolute top-2 z-20 w-max rounded-xl bg-ink px-3 py-2 opacity-0 shadow-lg transition ui-animated group-hover:opacity-100 group-focus-visible:opacity-100 {{ $loop->first ? 'left-0' : ($loop->last ? 'right-0' : 'left-1/2 -translate-x-1/2') }}">
                            <p class="text-[11px] font-semibold text-white whitespace-nowrap">{{ $point['label'] ?? '' }}</p>
                            <p class="mt-1 text-[11px] text-white/80 tabular-nums whitespace-nowrap">Masuk {{ number_format($inflow) }} · Selesai {{ number_format($resolved) }}</p>
                            <p class="mt-0.5 text-[11px] tabular-nums whitespace-nowrap {{ $gap > 0 ? 'text-accent-300' : 'text-white/60' }}">Backlog {{ $gap > 0 ? '+' : '' }}{{ number_format($gap) }}</p>
                        </div>

                        <div class="relative flex h-52 w-full items-end justify-center gap-1 border-b border-line">
                            <div class="chart-bar-y w-full max-w-[22px] rounded-t-[4px] bg-primary-600 transition-opacity ui-animated"
                                 style="height: {{ $inH }}%; animation-delay: {{ $loop->index * 40 }}ms"
                                 :class="focus === 'selesai' ? 'opacity-20' : 'opacity-100'"></div>
                            <div class="chart-bar-y w-full max-w-[22px] rounded-t-[4px] bg-accent-500 transition-opacity ui-animated"
                                 style="height: {{ $outH }}%; animation-delay: {{ $loop->index * 40 + 20 }}ms"
                                 :class="focus === 'masuk' ? 'opacity-20' : 'opacity-100'"></div>
                        </div>

                        <div class="text-center">
                            <span class="block text-xs font-medium text-ink-soft truncate">{{ $point['label'] ?? '' }}</span>
                            <span class="block text-xs font-semibold tabular-nums {{ $gap > 0 ? 'text-error' : ($gap < 0 ? 'text-success' : 'text-ink-faint') }}">
                                {{ $gap > 0 ? '+' : '' }}{{ number_format($gap) }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="w-full">
            {{-- Axis header — factual scale reference (visual axis). --}}
            <div class="flex items-center justify-between gap-3 mb-4">
                <span class="text-[13px] font-medium text-ink-soft">Volume per bulan</span>
                <span class="text-[13px] text-ink-soft">
                    Skala maks <strong class="font-semibold text-ink tabular-nums">{{ number_format($axisMax) }}</strong>
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
                            <div class="chart-bar-y w-1/2 max-w-[18px] rounded-t-[3px] bg-primary-600"
                                 style="height: {{ $inH }}%; animation-delay: {{ $loop->index * 40 }}ms"
                                 title="Masuk: {{ $inflow }}"></div>
                            <div class="chart-bar-y w-1/2 max-w-[18px] rounded-t-[3px] bg-success"
                                 style="height: {{ $outH }}%; animation-delay: {{ $loop->index * 40 + 20 }}ms"
                                 title="Selesai: {{ $resolved }}"></div>
                        </div>
                        <div class="text-center">
                            <span class="block text-xs font-medium text-ink-soft truncate">{{ $point['label'] ?? '' }}</span>
                            <span class="block text-xs tabular-nums {{ $gap > 0 ? 'text-error' : 'text-success' }}">
                                {{ $gap > 0 ? '+' : '' }}{{ $gap }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Legend — factual series reference. --}}
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 mt-4 pt-4 border-t border-line text-xs text-ink-soft">
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-[2px] bg-primary-600"></span> Laporan Masuk
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-[2px] bg-success"></span> Laporan Selesai
                </span>
                <span class="text-ink-faint">Angka di bawah tiap bulan = selisih (masuk − selesai)</span>
            </div>
        </div>
    @endif
@endif
