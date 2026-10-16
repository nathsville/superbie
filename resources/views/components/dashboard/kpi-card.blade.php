@props([
    'label' => '',
    'value' => 0,
    'hint'  => null,
    'tone'  => 'primary',
])

@php
    // Semantic tones reused across all role dashboards (same design system).
    $tones = [
        'primary' => ['bg' => 'bg-primary-100', 'text' => 'text-primary-600'],
        'teal'    => ['bg' => 'bg-accent-100', 'text' => 'text-accent-700'],
        'success' => ['bg' => 'bg-green-100', 'text' => 'text-green-700'],
        'warning' => ['bg' => 'bg-accent-100', 'text' => 'text-accent-700'],
        'danger'  => ['bg' => 'bg-red-100', 'text' => 'text-red-700'],
        'neutral' => ['bg' => 'bg-surface-sunken', 'text' => 'text-ink-soft'],
    ];
    $tone = $tones[$tone] ?? $tones['primary'];
@endphp

{{-- KPI card — visually prominent, compact, honest. No fabricated trend/percentage. --}}
<div {{ $attributes->merge(['class' => 'stat-card card-enter stagger-item']) }} role="listitem">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <span class="text-xs font-semibold text-ink-soft uppercase tracking-wider block">{{ $label }}</span>
            <p class="text-3xl font-bold text-ink mt-1 tabular-nums leading-none">{{ $value }}</p>
            @if ($hint)
                <p class="text-xs text-ink-soft mt-1.5">{{ $hint }}</p>
            @endif
        </div>
        @isset($icon)
            <div class="w-9 h-9 rounded-xl {{ $tone['bg'] }} {{ $tone['text'] }} flex items-center justify-center shrink-0">
                {{ $icon }}
            </div>
        @endisset
    </div>
</div>
