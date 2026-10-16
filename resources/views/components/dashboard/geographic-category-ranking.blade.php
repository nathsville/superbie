@props([
    'rows' => [],
])

@php
    // Wilayah × Kategori (Prompt 7/5 Bagian 1) — read-only administrative
    // ranking. Rows arrive pre-sorted + pre-limited (Top 10) from
    // DashboardCacheService. This view performs NO query and invents nothing.
    $max = 0;
    foreach ($rows as $r) {
        $max = max($max, (int) ($r['total'] ?? 0));
    }
@endphp

<x-dashboard.soft-card
    title="Wilayah × Kategori"
    subtitle="Kategori laporan terbanyak pada masing-masing kelurahan.">
    @if (count($rows) > 0)
        <div class="w-full space-y-3.5">
            @foreach ($rows as $row)
                @php
                    $total = (int) ($row['total'] ?? 0);
                    $pct   = $max > 0 ? round(($total / $max) * 100, 1) : 0;
                @endphp
                <div>
                    <div class="flex items-center justify-between gap-3 text-sm mb-1.5">
                        <span class="min-w-0 font-medium text-ink truncate">
                            {{ $row['kelurahan'] }}
                            <span class="text-ink-faint font-normal">· {{ $row['kecamatan'] }}</span>
                        </span>
                        <span class="font-semibold text-ink-soft tabular-nums shrink-0">{{ number_format($total) }}</span>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <span class="inline-flex items-center text-[11px] font-medium px-2 py-0.5 rounded-full bg-primary-50 text-primary-700 border border-primary-200 shrink-0 max-w-[9rem] truncate"
                              title="{{ $row['category'] }}">
                            {{ $row['category'] }}
                        </span>
                        <div class="flex-1 min-w-0 h-2 rounded-full bg-surface-sunken overflow-hidden">
                            <div class="h-full rounded-full bg-primary-600 transition-all duration-500 ease-standard"
                                 style="width: {{ $pct }}%" role="presentation"></div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <x-dashboard.empty-state message="Belum ada data kategori per wilayah." />
    @endif
</x-dashboard.soft-card>
