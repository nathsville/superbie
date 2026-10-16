@props([
    'rows' => [],
])

@php
    // Dinas/Unit holding the most ACTIVE reports older than 7 days. Bars use a
    // warning (rose) accent to signal stalled handling. Counts are factual;
    // the ">7 hari" cutoff matches the aging-distribution card. No SLA.
    // Rows arrive pre-sorted by long-age load descending.
    $max = 0;
    foreach ($rows as $r) {
        $max = max($max, (int) ($r['count'] ?? 0));
    }
@endphp

@if (count($rows) > 0)
    <div class="w-full">
        <style>
            .dla-row { position: relative; }
            .dla-tip {
                position: absolute;
                bottom: calc(100% + 8px);
                left: 0;
                white-space: nowrap;
                background: #0F172A;
                color: #FFFFFF;
                font-size: 11px;
                font-weight: 500;
                line-height: 1.35;
                padding: 8px 10px;
                border-radius: 10px;
                opacity: 0;
                pointer-events: none;
                z-index: 30;
                transition: opacity 120ms ease;
            }
            .dla-row:hover .dla-tip { opacity: 1; }
            @media (prefers-reduced-motion: reduce) {
                .dla-tip { transition: none; }
            }
        </style>

        <div class="space-y-3.5">
            @foreach ($rows as $row)
                @php
                    $count   = (int) ($row['count'] ?? 0);
                    $isHot   = $count > 3; // ">3 laporan" → warning badge
                    $barPct  = $max > 0 ? round(($count / $max) * 100, 2) : 0;
                    $oldest  = $row['oldest'] ?? null;
                @endphp

                <div class="dla-row">
                    <div class="flex items-center justify-between gap-3 text-sm mb-1.5">
                        <span class="flex items-center gap-2 min-w-0">
                            <span class="font-medium text-ink truncate">{{ $row['name'] }}</span>
                            @if ($isHot)
                                <span class="dla-attention inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 border border-rose-200/60 whitespace-nowrap">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
                                    </svg>
                                    Perlu Perhatian
                                </span>
                            @endif
                        </span>
                        <span class="shrink-0 font-bold tabular-nums text-rose-600">{{ $count }}</span>
                    </div>

                    <div class="h-2.5 rounded-full bg-surface-sunken overflow-hidden">
                        <div class="h-full rounded-full bg-rose-500 transition-all duration-500 ease-standard"
                             style="width: {{ $barPct }}%" role="presentation"></div>
                    </div>

                    {{-- Hover tooltip: oldest long-age report for this unit --}}
                    @if ($oldest)
                        <div class="dla-tip">
                            <span class="block font-bold mb-0.5">{{ $row['name'] }}</span>
                            <span class="block">{{ $count }} laporan aktif &gt; 7 hari</span>
                            <span class="block text-white/70">
                                Tertua: {{ $oldest['reference_code'] }} • {{ $oldest['category'] }}
                                @if ($oldest['age_days'] !== null) • {{ $oldest['age_days'] }} hari @endif
                            </span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif
