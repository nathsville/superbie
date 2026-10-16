@props([
    'stages'      => [],
    'activeTotal' => 0,
    'bottleneck'  => null,
])

@php
    // Funnel-style horizontal bars for each workflow stage. Counts are real;
    // the busiest ACTIVE stage is flagged as the bottleneck. Shares are
    // factual proportions of the active pile — no SLA, no forecast.
    $palette = [
        'blue'   => '#013FF6',
        'yellow' => '#FFC14D',
        'indigo' => '#7C3AED',
        'orange' => '#F97316',
        'green'  => '#22C55E',
        'red'    => '#EF4444',
        'gray'   => '#94A3B8',
    ];

    $max = 0;
    foreach ($stages as $s) {
        $max = max($max, (int) ($s['count'] ?? 0));
    }

    // Prompt 3/4 — decision ordering: surface the status holding the largest
    // volume first. Display-only reorder (the bottleneck flag is passed by
    // value, so it stays correct regardless of order). Stable on ties.
    usort($stages, fn ($a, $b) => (int) ($b['count'] ?? 0) <=> (int) ($a['count'] ?? 0));
@endphp

@if (count($stages) > 0)
    <div class="w-full">
        <style>
            .bsl-row { position: relative; }
            .bsl-tip {
                position: absolute;
                bottom: calc(100% + 8px);
                left: 0;
                white-space: nowrap;
                background: #0F172A;
                color: #FFFFFF;
                font-size: 11px;
                font-weight: 600;
                line-height: 1.1;
                padding: 6px 9px;
                border-radius: 8px;
                opacity: 0;
                pointer-events: none;
                z-index: 30;
                transition: opacity 120ms ease;
            }
            .bsl-row:hover .bsl-tip { opacity: 1; }
            @media (prefers-reduced-motion: reduce) {
                .bsl-tip { transition: none; }
            }
        </style>

        <div class="space-y-3.5">
            @foreach ($stages as $stage)
                @php
                    $count   = (int) ($stage['count'] ?? 0);
                    $isMain  = $bottleneck !== null && $stage['value'] === $bottleneck;
                    $barPct  = $max > 0 ? round(($count / $max) * 100, 2) : 0;
                    $hex     = $palette[$stage['color']] ?? '#94A3B8';
                    $share   = $stage['share'] ?? 0;
                    $shareLbl = $stage['share_label'] ?? '';
                @endphp

                <div class="bsl-row">
                    <div class="flex items-center justify-between gap-3 text-sm mb-1.5">
                        <span class="flex items-center gap-2 min-w-0">
                            <span class="w-2.5 h-2.5 rounded-sm shrink-0" style="background: {{ $hex }}"></span>
                            <span class="font-medium text-ink truncate">{{ $stage['label'] }}</span>
                            @if ($isMain)
                                <span class="bsl-bottleneck inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-accent-50 text-accent-700 border border-accent-200/70 whitespace-nowrap">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
                                    </svg>
                                    Titik Penumpukan Utama
                                </span>
                            @endif
                        </span>
                        <span class="shrink-0 font-bold tabular-nums {{ $isMain ? 'text-accent-700' : 'text-ink' }}">
                            {{ number_format($count) }}
                        </span>
                    </div>

                    <div class="h-2.5 rounded-full bg-surface-sunken overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500 ease-standard {{ $isMain ? 'ring-2 ring-accent-400/60' : '' }}"
                             style="width: {{ $barPct }}%; background: {{ $hex }}"
                             role="presentation"></div>
                    </div>

                    {{-- Hover tooltip: share of the active pile --}}
                    <div class="bsl-tip">
                        {{ $stage['label'] }}: {{ number_format($count) }} laporan
                        <span class="text-white/60 font-normal">•</span>
                        {{ $share }}% {{ $shareLbl }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
