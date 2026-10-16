@props([
    'rows' => [],
])

@php
    // Per-category accumulation: total masuk (neutral grey) vs belum selesai
    // (contrast colour). Bars are factual proportions of the busiest category;
    // rows are pre-sorted by active backlog (highest first). No SLA/forecast.
    $max = 0;
    foreach ($rows as $r) {
        $max = max($max, (int) ($r['total'] ?? 0));
    }
@endphp

@if (count($rows) > 0)
    <div class="w-full">
        <style>
            .kbl-tip {
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
            .kbl-row:hover .kbl-tip { opacity: 1; }
            @media (prefers-reduced-motion: reduce) {
                .kbl-tip { transition: none; }
            }
        </style>

        <div class="space-y-4">
            @foreach ($rows as $row)
                @php
                    $total    = (int) ($row['total'] ?? 0);
                    $active   = (int) ($row['active'] ?? 0);
                    $resolved = (int) ($row['resolved'] ?? 0);
                    // LC-2C: rejection and legacy are kept DISTINCT so a legacy row
                    // is never misread as a rejection.
                    $rejected = (int) ($row['rejected'] ?? 0);
                    $legacy   = (int) ($row['legacy'] ?? 0);
                    $totalPct = $max > 0 ? round(($total / $max) * 100, 1) : 0;
                    $activePct = $max > 0 ? round(($active / $max) * 100, 1) : 0;
                @endphp

                <div class="kbl-row relative">
                    <div class="flex items-center justify-between gap-3 text-sm mb-1.5">
                        <span class="font-medium text-ink truncate">{{ $row['name'] }}</span>
                        <span class="shrink-0 tabular-nums text-xs">
                            <span class="font-bold text-orange-600">{{ $active }}</span>
                            <span class="text-ink-faint">aktif dari</span>
                            <span class="font-semibold text-ink-soft">{{ $total }}</span>
                            <span class="text-ink-faint">total</span>
                        </span>
                    </div>

                    {{-- Dual bar: total (neutral) behind, belum selesai (contrast) in front --}}
                    <div class="space-y-1">
                        <div class="h-2 rounded-full bg-surface-sunken overflow-hidden">
                            <div class="h-full rounded-full bg-line-strong transition-all duration-500 ease-standard"
                                 style="width: {{ $totalPct }}%" role="presentation"></div>
                        </div>
                        <div class="h-2 rounded-full bg-surface-sunken overflow-hidden">
                            <div class="h-full rounded-full bg-orange-500 transition-all duration-500 ease-standard"
                                 style="width: {{ $activePct }}%" role="presentation"></div>
                        </div>
                    </div>

                    {{-- Hover tooltip: Total Masuk / Sudah Selesai / Masih Aktif --}}
                    <div class="kbl-tip">
                        <span class="block font-bold mb-0.5">{{ $row['name'] }}</span>
                        <span class="block">Total masuk: <strong>{{ $total }}</strong></span>
                        <span class="block">Sudah selesai: <strong>{{ $resolved }}</strong></span>
                        <span class="block">Masih aktif: <strong>{{ $active }}</strong></span>
                        @if ($rejected > 0)
                            <span class="block text-white/70">Ditolak: {{ $rejected }}</span>
                        @endif
                        @if ($legacy > 0)
                            <span class="block text-white/70">Status historis: {{ $legacy }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
