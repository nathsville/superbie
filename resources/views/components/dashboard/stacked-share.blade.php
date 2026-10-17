@props([
    'segments' => [], // [['label' => string, 'count' => int, 'color' => 'bg-…'], …]
])

@php
    // Solid stacked share bar + dot legend.
    //
    // Each slice is a factual share of the total across the given segments. A
    // zero-count segment renders NO slice (never a fabricated sliver) but keeps
    // its legend row, so the reference list stays complete and reconcilable
    // with the card's surrounding readings.
    $total = array_sum(array_map(fn ($segment) => (int) ($segment['count'] ?? 0), $segments));
@endphp

<div class="w-full">
    <div class="flex h-2.5 w-full overflow-hidden rounded-full bg-surface-sunken" role="presentation">
        @if ($total > 0)
            @foreach ($segments as $segment)
                @php $count = (int) ($segment['count'] ?? 0); @endphp
                @continue($count === 0)
                <div class="{{ $segment['color'] }}"
                     style="width: {{ number_format(($count / $total) * 100, 3, '.', '') }}%"
                     title="{{ $segment['label'] }}: {{ number_format($count) }}"></div>
            @endforeach
        @endif
    </div>

    <div class="mt-4 space-y-2.5">
        @foreach ($segments as $segment)
            <div class="flex items-center justify-between gap-3">
                <span class="inline-flex min-w-0 items-center gap-2 text-sm font-medium text-ink">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $segment['color'] }}" aria-hidden="true"></span>
                    <span class="truncate">{{ $segment['label'] }}</span>
                </span>
                <span class="shrink-0 text-sm font-semibold text-ink tabular-nums">{{ number_format((int) ($segment['count'] ?? 0)) }}</span>
            </div>
        @endforeach
    </div>
</div>
