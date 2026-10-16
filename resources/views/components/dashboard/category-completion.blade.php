@props([
    'rows' => [],
])

@php
    // Per-category completion ratio. Colour tier reflects the factual
    // resolved/total ratio only — no SLA, no forecast, no fabricated figure.
    // Rows arrive pre-sorted by completion ascending (most lagging first).
    $tiers = [
        'high' => ['bar' => '#22C55E', 'chip' => 'bg-emerald-50 text-emerald-700 border-emerald-200/70', 'text' => 'text-emerald-600'],
        'mid'  => ['bar' => '#FFC14D', 'chip' => 'bg-accent-50 text-accent-700 border-accent-200/70', 'text' => 'text-accent-600'],
        'low'  => ['bar' => '#EF4444', 'chip' => 'bg-rose-50 text-rose-700 border-rose-200/70', 'text' => 'text-rose-600'],
    ];
@endphp

@if (count($rows) > 0)
    <div class="w-full">
        <style>
            .cpc-row { position: relative; }
            .cpc-tip {
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
            .cpc-row:hover .cpc-tip { opacity: 1; }
            @media (prefers-reduced-motion: reduce) {
                .cpc-tip { transition: none; }
            }
        </style>

        <div class="space-y-3.5">
            @foreach ($rows as $row)
                @php
                    $total    = (int) ($row['total'] ?? 0);
                    $resolved = (int) ($row['resolved'] ?? 0);
                    $percent  = (float) ($row['percent'] ?? 0);
                    $tier     = $tiers[$row['tier'] ?? 'low'] ?? $tiers['low'];
                @endphp

                <div class="cpc-row">
                    <div class="flex items-center justify-between gap-3 text-sm mb-1.5">
                        <span class="font-medium text-ink truncate">{{ $row['name'] }}</span>
                        <span class="flex items-center gap-2 shrink-0">
                            <span class="text-[11px] font-medium text-ink-faint tabular-nums">{{ $resolved }}/{{ $total }}</span>
                            <span class="inline-flex items-center text-[11px] font-bold px-2 py-0.5 rounded-full border tabular-nums {{ $tier['chip'] }}">
                                {{ $percent }}%
                            </span>
                        </span>
                    </div>

                    <div class="h-2.5 rounded-full bg-surface-sunken overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500 ease-standard"
                             style="width: {{ $percent }}%; background: {{ $tier['bar'] }}"
                             role="presentation"></div>
                    </div>

                    {{-- Hover tooltip: exact resolved/total breakdown --}}
                    <div class="cpc-tip">
                        {{ $row['name'] }}: {{ $resolved }} dari {{ $total }} laporan selesai
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
