@props([
    'rows' => [],
])

@php
    use App\Enums\ComplaintStatus;

    // Active (non-terminal) statuses in workflow order, sourced from the enum so
    // the chart can never drift from the lifecycle. LC-2C: only the ACTIVE
    // lifecycle (submitted, in_progress) — legacy statuses are history-only and
    // are never shown as active work. Colours mirror ComplaintStatus::color() so
    // the enum stays the single source of truth for the colour NAME; this map
    // only resolves that name to a bar hex.
    $statusOrder = ComplaintStatus::activeCases();

    $palette = [
        'blue'   => '#013FF6',
        'yellow' => '#FFC14D',
        'indigo' => '#7C3AED',
        'orange' => '#F97316',
        'green'  => '#22C55E',
        'red'    => '#EF4444',
        'gray'   => '#94A3B8',
    ];

    $hex = fn (ComplaintStatus $s) => $palette[$s->color()] ?? '#94A3B8';

    $maxTotal = 0;
    foreach ($rows as $r) {
        $maxTotal = max($maxTotal, (int) ($r['total'] ?? 0));
    }
@endphp

@if (count($rows) > 0)
    <div class="w-full">
        <style>
            .dsb-seg { position: relative; }
            .dsb-seg-tip {
                position: absolute;
                bottom: calc(100% + 8px);
                left: 50%;
                transform: translateX(-50%);
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
            .dsb-seg-tip::after {
                content: '';
                position: absolute;
                top: 100%;
                left: 50%;
                transform: translateX(-50%);
                border: 5px solid transparent;
                border-top-color: #0F172A;
            }
            .dsb-seg:hover .dsb-seg-tip { opacity: 1; }
            @media (prefers-reduced-motion: reduce) {
                .dsb-seg-tip { transition: none; }
            }
        </style>

        {{-- Legend --}}
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 mb-4 text-[11px] text-ink-soft">
            @foreach ($statusOrder as $status)
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-sm" style="background: {{ $hex($status) }}"></span>
                    {{ $status->label() }}
                </span>
            @endforeach
        </div>

        {{-- Rows: name | stacked bar | total --}}
        <div class="space-y-3.5">
            @foreach ($rows as $row)
                @php
                    $total  = (int) ($row['total'] ?? 0);
                    $barPct = $maxTotal > 0 ? round(($total / $maxTotal) * 100, 2) : 0;
                @endphp
                <div class="flex items-center gap-2.5">
                    <span class="w-20 sm:w-24 shrink-0 truncate text-xs font-medium text-ink"
                          title="{{ $row['name'] }}">
                        {{ $row['name'] }}
                    </span>

                    <div class="flex-1 min-w-0 h-4 rounded-full bg-surface-sunken overflow-hidden">
                        <div class="h-full flex rounded-full overflow-hidden" style="width: {{ $barPct }}%">
                            @foreach ($statusOrder as $status)
                                @php $count = (int) ($row['statuses'][$status->value] ?? 0); @endphp
                                @if ($count > 0)
                                    <div class="dsb-seg h-full"
                                         style="flex: {{ $count }} 1 0%; background: {{ $hex($status) }};"
                                         role="img"
                                         aria-label="{{ $row['name'] }} — {{ $status->label() }}: {{ $count }} laporan">
                                        <span class="dsb-seg-tip" aria-hidden="true">
                                            {{ $row['name'] }} - {{ $status->label() }}: {{ $count }} Laporan
                                        </span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <span class="w-6 shrink-0 text-right text-xs font-bold text-ink tabular-nums">{{ $total }}</span>
                </div>
            @endforeach
        </div>
    </div>
@endif
