@props([
    'rows' => [],
])

@php
    // Completion Rate per Wilayah (Prompt 7/5 Bagian 3) — completed = resolved +
    // rejected (closed is NOT completed). Rows arrive pre-sorted + pre-limited
    // (Top 5) from DashboardCacheService. This view performs NO query.
    //
    // The rate is always shown as TEXT (e.g. "70%") so reading never depends on
    // the progress-bar colour alone. Formatting trims trailing zeros so an
    // integer rate reads "70%" and a fractional one reads "87.5%".
    $tiers = [
        'high' => ['bar' => '#22C55E', 'chip' => 'bg-emerald-50 text-emerald-700 border-emerald-200/70'],
        'mid'  => ['bar' => '#FFC14D', 'chip' => 'bg-accent-50 text-accent-700 border-accent-200/70'],
        'low'  => ['bar' => '#EF4444', 'chip' => 'bg-rose-50 text-rose-700 border-rose-200/70'],
    ];

    $formatRate = function ($rate) {
        // Safe formatting: never NaN / Infinity / undefined. Clamp to [0,100].
        $rate = is_numeric($rate) ? (float) $rate : 0.0;
        $rate = max(0.0, min(100.0, $rate));

        // Integer → no decimals; fractional → 1 decimal (trailing zero trimmed).
        return rtrim(rtrim(number_format($rate, 1, '.', ''), '0'), '.') . '%';
    };
@endphp

<x-dashboard.soft-card
    title="Tingkat Penyelesaian per Wilayah"
    subtitle="Kelurahan dengan tingkat penyelesaian laporan tertinggi (selesai + ditolak).">
    @if (count($rows) > 0)
        <div class="w-full space-y-3.5">
            @foreach ($rows as $row)
                @php
                    $total     = (int) ($row['total'] ?? 0);
                    $completed = (int) ($row['completed'] ?? 0);
                    $rate      = (float) ($row['completionRate'] ?? 0);
                    $tier      = $rate >= 70 ? $tiers['high'] : ($rate >= 40 ? $tiers['mid'] : $tiers['low']);
                    $width     = max(0.0, min(100.0, $rate));
                @endphp

                <div>
                    <div class="flex items-center justify-between gap-3 text-sm mb-1.5">
                        <span class="min-w-0 font-medium text-ink truncate">
                            {{ $row['kelurahan'] }}
                            <span class="text-ink-faint font-normal">· {{ $row['kecamatan'] }}</span>
                        </span>
                        <span class="flex items-center gap-2 shrink-0">
                            <span class="text-[11px] font-medium text-ink-faint tabular-nums">{{ $completed }}/{{ $total }}</span>
                            <span class="inline-flex items-center text-[11px] font-bold px-2 py-0.5 rounded-full border tabular-nums {{ $tier['chip'] }}">
                                {{ $formatRate($rate) }}
                            </span>
                        </span>
                    </div>

                    <div class="h-2.5 rounded-full bg-surface-sunken overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500 ease-standard"
                             style="width: {{ $width }}%; background: {{ $tier['bar'] }}"
                             role="presentation"></div>
                    </div>

                    {{-- Text alternative: completion is readable without the bar. --}}
                    <p class="sr-only">
                        {{ $row['kelurahan'] }} ({{ $row['kecamatan'] }}): {{ $completed }} dari {{ $total }} laporan selesai atau ditolak, tingkat penyelesaian {{ $formatRate($rate) }}.
                    </p>
                </div>
            @endforeach
        </div>
    @else
        <x-dashboard.empty-state message="Belum ada data penyelesaian per wilayah." />
    @endif
</x-dashboard.soft-card>
