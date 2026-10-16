@props([
    'values' => [],
    'tone'   => 'primary',
])

@php
    // Mini bar chart (pure CSS/inline). Heights are proportional to the max
    // value in the series — a factual visual, never a fabricated trend.
    $values = array_values(array_map('intval', $values));
    $max    = ! empty($values) ? max($values) : 0;
    $colors = [
        'primary' => 'bg-primary-600/70',
    ];
    $bar = $colors[$tone] ?? $colors['primary'];
@endphp

<div class="flex items-end gap-1 h-full">
    @forelse ($values as $v)
        <span class="flex-1 max-w-[7px] rounded-t {{ $bar }}"
              style="height: {{ $max > 0 ? max(8, (int) round(($v / $max) * 100)) : 8 }}%"></span>
    @empty
        <span class="w-full h-[8%] rounded-t {{ $bar }}"></span>
    @endforelse
</div>
