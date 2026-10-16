@props([
    'tone' => 'primary',
])

@php
    $tones = [
        'primary' => 'bg-primary-100 text-primary-600',
        'teal'    => 'bg-accent-100 text-accent-700',
        'success' => 'bg-emerald-50 text-emerald-600',
        'warning' => 'bg-accent-50 text-accent-600',
        'danger'  => 'bg-rose-50 text-rose-600',
        'neutral' => 'bg-surface-sunken text-ink-faint',
    ];
    $tone = $tones[$tone] ?? $tones['primary'];
@endphp

<span {{ $attributes->merge(['class' => "w-9 h-9 rounded-full flex items-center justify-center shrink-0 $tone"]) }}>
    {{ $slot }}
</span>
