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
        'teal'    => 'bg-accent-700',
        'amber'   => 'bg-accent-500',
        'rose'    => 'bg-rose-500',
        'slate'   => 'bg-ink-ghost',
    ];
    $bar = $bars[$tone] ?? $bars['primary'];
@endphp

{{-- Horizontal bar row — used for aging, category and Dinas/Unit distribution. --}}
<div>
    <div class="flex items-center justify-between gap-3 text-sm mb-1.5">
        <span class="font-medium text-ink truncate">
            {{ $label }}
            @if ($sublabel)
                <span class="text-ink-faint font-normal">· {{ $sublabel }}</span>
            @endif
        </span>
        <span class="font-semibold text-ink-soft tabular-nums shrink-0">{{ $count }}</span>
    </div>
    <div class="h-2 rounded-full bg-surface-sunken overflow-hidden">
        <div class="h-full rounded-full {{ $bar }} transition-all duration-500 ease-standard"
             style="width: {{ $pct }}%" role="presentation"></div>
    </div>
</div>
