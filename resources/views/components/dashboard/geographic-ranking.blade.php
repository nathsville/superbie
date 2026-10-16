@props([
    'title'        => '',
    'subtitle'     => null,
    'rows'         => [],
    'tone'         => 'primary',
    'emptyMessage' => 'Belum ada data.',
])

@php
    // Read-only administrative ranking (Top Kecamatan / Top Kelurahan /
    // Backlog Wilayah). Rows arrive pre-sorted + pre-limited from
    // DashboardCacheService — this view performs NO query and invents nothing.
    // `sublabel` (e.g. the parent Kecamatan) is optional context.
    $max = 0;
    foreach ($rows as $r) {
        $max = max($max, (int) ($r['count'] ?? 0));
    }
@endphp

<x-dashboard.soft-card :title="$title" :subtitle="$subtitle">
    @if (count($rows) > 0)
        <div class="space-y-3.5">
            @foreach ($rows as $row)
                <x-dashboard.bar-row
                    :label="$row['name']"
                    :sublabel="$row['sublabel'] ?? null"
                    :count="(int) $row['count']"
                    :max="$max"
                    :tone="$tone" />
            @endforeach
        </div>
    @else
        <x-dashboard.empty-state :message="$emptyMessage" />
    @endif
</x-dashboard.soft-card>
