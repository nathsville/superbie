@props([
    'rows' => [],
])

@php
    // Proporsi Kasus Aktif (Prompt 8/5 Bagian 1) — read-only ADMIN analytics.
    // Rows arrive pre-sorted + pre-limited (Top 5) from DashboardCacheService,
    // which already excludes wilayah with a zero denominator. NO query here.
    //
    // IMPORTANT: `activeRate` is taken DIRECTLY from the payload — the ratio is
    // NEVER recomputed in Blade. The progress bar only VISUALISES that value; the
    // numeric text ("70%") and the "14 dari 20" caption are always present, so
    // reading never depends on the bar or its colour. No SLA semantics.
    $formatRate = function ($rate) {
        $rate = is_numeric($rate) ? (float) $rate : 0.0;
        $rate = max(0.0, min(100.0, $rate));

        // Integer → no decimals; fractional → 1 decimal (trailing zero trimmed).
        return rtrim(rtrim(number_format($rate, 1, '.', ''), '0'), '.') . '%';
    };
@endphp

<x-dashboard.soft-card
    title="Proporsi Kasus Aktif"
    subtitle="Perbandingan laporan aktif terhadap seluruh laporan pada masing-masing kelurahan.">
    @if (count($rows) > 0)
        <div class="w-full space-y-3.5">
            @foreach ($rows as $i => $row)
                @php
                    $rate   = (float) ($row['activeRate'] ?? 0);
                    $width  = max(0.0, min(100.0, $rate));
                    $active = (int) ($row['active'] ?? 0);
                    $total  = (int) ($row['total'] ?? 0);
                @endphp
                <div>
                    <div class="flex items-center justify-between gap-3 text-sm mb-1.5">
                        <span class="min-w-0 font-medium text-ink truncate">
                            {{ $row['kelurahan'] ?? '—' }}
                            <span class="text-ink-faint font-normal">· {{ $row['kecamatan'] ?? '—' }}</span>
                        </span>
                        <span class="shrink-0 text-sm font-semibold text-ink-body tabular-nums">
                            {{ $formatRate($rate) }}
                        </span>
                    </div>

                    <div class="h-2 rounded-full bg-surface-sunken overflow-hidden">
                        <div class="h-full rounded-full bg-primary-600 transition-all duration-500 ease-standard"
                             style="width: {{ $width }}%" role="presentation"></div>
                    </div>

                    <p class="mt-1 text-xs text-ink-faint tabular-nums">
                        {{ number_format($active) }} dari {{ number_format($total) }} laporan aktif
                    </p>
                </div>
            @endforeach
        </div>
    @else
        <x-dashboard.empty-state message="Belum ada data proporsi kasus aktif per wilayah." />
    @endif
</x-dashboard.soft-card>
