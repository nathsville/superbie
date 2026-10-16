@props([
    'rows' => [],
])

@php
    // Rata-rata Umur Laporan Aktif (Prompt 8/5 Bagian 1) — read-only ADMIN
    // analytics. Rows arrive pre-sorted + pre-limited (Top 5) from
    // DashboardCacheService. NO query, no metric math here.
    //
    // `averageAgeDays` is ANALYTICAL AGE (mean age of the wilayah's ACTIVE
    // reports) — NOT an SLA. One decimal is preserved (never rounded to an
    // integer, never NaN / Infinity / undefined).
    $formatDays = function ($days) {
        $days = is_numeric($days) ? (float) $days : 0.0;
        $days = max(0.0, $days);

        // Always one decimal place, e.g. "12.4 hari" / "10.0 hari".
        return number_format($days, 1, '.', '');
    };
@endphp

<x-dashboard.soft-card
    title="Rata-rata Umur Laporan Aktif"
    subtitle="Rata-rata lama menunggu laporan aktif pada masing-masing kelurahan (umur analitis).">
    @if (count($rows) > 0)
        <ol class="w-full space-y-3" role="list">
            @foreach ($rows as $i => $row)
                @php
                    $avg   = $formatDays($row['averageAgeDays'] ?? 0);
                    $aktiv = (int) ($row['activeCount'] ?? 0);
                @endphp
                <li class="flex items-center gap-3">
                    <span class="shrink-0 w-5 text-right text-xs font-semibold text-ink-faint tabular-nums"
                          aria-hidden="true">{{ $i + 1 }}</span>

                    <span class="min-w-0 flex-1">
                        <span class="block font-medium text-ink truncate">
                            {{ $row['kelurahan'] ?? '—' }}
                            <span class="text-ink-faint font-normal">· {{ $row['kecamatan'] ?? '—' }}</span>
                        </span>
                        <span class="block text-xs text-ink-faint tabular-nums">
                            {{ number_format($aktiv) }} laporan aktif
                        </span>
                    </span>

                    <span class="shrink-0 text-sm font-semibold text-ink-body tabular-nums">
                        {{ $avg }} hari
                    </span>
                </li>
            @endforeach
        </ol>
    @else
        <x-dashboard.empty-state message="Belum ada data rata-rata umur laporan per wilayah." />
    @endif
</x-dashboard.soft-card>
