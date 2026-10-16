@props([
    'rows' => [],
])

@php
    // Wilayah dengan Kasus Tertua (Prompt 8/5 Bagian 1) — read-only ADMIN
    // analytics. Rows arrive pre-sorted + pre-limited (Top 5) from
    // DashboardCacheService. This view performs NO query, no metric math and
    // invents nothing.
    //
    // `oldestAgeDays` is ANALYTICAL AGE (whole days since the oldest ACTIVE
    // report's submitted_at) — NOT an SLA, NOT a deadline, NOT "overdue". There
    // is deliberately NO red/amber/green threshold colouring here because no SLA
    // business rule exists; the ranking number is the only ordinal cue.
    $formatDays = function ($days) {
        // Safe: never NaN / Infinity / undefined. Whole days, never negative.
        $days = is_numeric($days) ? (int) $days : 0;

        return max(0, $days);
    };
@endphp

<x-dashboard.soft-card
    title="Wilayah dengan Kasus Tertua"
    subtitle="Kelurahan dengan laporan aktif paling lama menunggu (umur analitis).">
    @if (count($rows) > 0)
        <ol class="w-full space-y-3" role="list">
            @foreach ($rows as $i => $row)
                @php
                    $age   = $formatDays($row['oldestAgeDays'] ?? 0);
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
                        {{ number_format($age) }} hari
                    </span>
                </li>
            @endforeach
        </ol>
    @else
        <x-dashboard.empty-state message="Belum ada data umur kasus per wilayah." />
    @endif
</x-dashboard.soft-card>
