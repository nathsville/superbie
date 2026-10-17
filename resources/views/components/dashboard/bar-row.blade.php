@props([
    'label'    => '',
    'sublabel' => null,
    'count'    => 0,
    'max'      => 1,
    'tone'     => 'primary',
])

@php
    // Width is a factual proportion of the largest bucket (count / max).
    $pct = $max > 0 ? (int) round(($count / $max) * 100) : 0;
    $bars = [
        'primary' => 'bg-primary-600',
        'teal'    => 'bg-success',
        'amber'   => 'bg-accent-500',
        'warning' => 'bg-warning',
        'rose'    => 'bg-error',
        'slate'   => 'bg-ink-ghost',
    ];
    $bar = $bars[$tone] ?? $bars['primary'];
@endphp

{{-- Horizontal bar row — used for aging, category and Dinas/Unit distribution.
     Flat 6px bar on a sunken track; it grows once from the left, then still. --}}
<div>
    <div class="flex items-center justify-between gap-3 text-sm mb-2">
        <span class="font-medium text-ink truncate">
            {{ $label }}
            @if ($sublabel)
                <span class="text-ink-faint font-normal">· {{ $sublabel }}</span>
            @endif
        </span>
        <span class="font-semibold text-ink tabular-nums shrink-0">{{ $count }}</span>
    </div>
    <div class="h-1.5 rounded-[3px] bg-surface-sunken overflow-hidden">
        <div class="chart-bar-x h-full rounded-[3px] {{ $bar }}"
             style="width: {{ $pct }}%" role="presentation"></div>
    </div>
</div>
