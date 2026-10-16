@props([
    'rows' => [],
])

@php
    // Beban Kasus Aktif Terbesar (Prompt 8/5 Bagian 1) — read-only ADMIN
    // analytics. Rows arrive pre-sorted + pre-limited (Top 5) from
    // DashboardCacheService. NO query, no metric math here.
    //
    // Terminology is intentionally "Beban Kasus Aktif" to stay DISTINCT from
    // Layer 1's "Wilayah dengan Backlog Tertinggi" (a frozen, separate metric).
    // Primary metric = activeCount; totalCount is supporting context. No SLA.
    $max = 0;
    foreach ($rows as $r) {
        $max = max($max, (int) ($r['activeCount'] ?? 0));
    }
@endphp

<x-dashboard.soft-card
    title="Beban Kasus Aktif Terbesar"
    subtitle="Kelurahan dengan jumlah laporan aktif (belum selesai) terbanyak.">
    @if (count($rows) > 0)
        <ol class="w-full space-y-3.5" role="list">
            @foreach ($rows as $i => $row)
                @php
                    $active = (int) ($row['activeCount'] ?? 0);
                    $total  = (int) ($row['totalCount'] ?? 0);
                    $pct    = $max > 0 ? (int) round(($active / $max) * 100) : 0;
                @endphp
                <li>
                    <div class="flex items-center justify-between gap-3 text-sm mb-1.5">
                        <span class="min-w-0 font-medium text-ink truncate">
                            <span class="text-ink-faint font-normal tabular-nums mr-1" aria-hidden="true">{{ $i + 1 }}.</span>{{ $row['kelurahan'] ?? '—' }}
                            <span class="text-ink-faint font-normal">· {{ $row['kecamatan'] ?? '—' }}</span>
                        </span>
                        <span class="shrink-0 font-semibold text-ink-soft tabular-nums">{{ number_format($active) }}</span>
                    </div>
                    <div class="h-2 rounded-full bg-surface-sunken overflow-hidden">
                        <div class="h-full rounded-full bg-accent-700 transition-all duration-500 ease-standard"
                             style="width: {{ $pct }}%" role="presentation"></div>
                    </div>
                    <p class="mt-1 text-xs text-ink-faint tabular-nums">
                        dari {{ number_format($total) }} laporan
                    </p>
                </li>
            @endforeach
        </ol>
    @else
        <x-dashboard.empty-state message="Belum ada data beban kasus aktif per wilayah." />
    @endif
</x-dashboard.soft-card>
