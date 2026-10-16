@props([
    'label'     => '',
    'value'     => 0,
    'hint'      => null,
    'tone'      => 'primary',
    'highlight' => false,
])

@php
    $tones = [
        'primary' => ['bg' => 'bg-primary-100', 'text' => 'text-primary-600'],
        'teal'    => ['bg' => 'bg-accent-100', 'text' => 'text-accent-700'],
        'success' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600'],
        'warning' => ['bg' => 'bg-accent-50', 'text' => 'text-accent-600'],
        'danger'  => ['bg' => 'bg-rose-50', 'text' => 'text-rose-600'],
        'neutral' => ['bg' => 'bg-surface-sunken', 'text' => 'text-ink-faint'],
    ];
    $t = $tones[$tone] ?? $tones['primary'];

    $wrap = $highlight
        ? 'bg-gradient-to-br from-primary-600 to-primary-700 text-white shadow-[0_18px_40px_-18px_rgba(29,78,216,0.55)]'
        : 'soft-card soft-card-hover';
    $labelCls = $highlight ? 'text-white/80' : 'text-ink-faint';
    $valueCls = $highlight ? 'text-white' : 'text-ink';
    $hintCls  = $highlight ? 'text-white/75' : 'text-ink-faint';
@endphp

{{-- Compact KPI card — bento style. Values are real counts only. --}}
<div {{ $attributes->merge(['class' => "rounded-3xl p-4 lg:p-5 card-enter stagger-item $wrap"]) }}
     role="listitem">
    <div class="flex items-start justify-between gap-3">
        <span class="text-xs font-medium {{ $labelCls }}">{{ $label }}</span>
        @isset($icon)
            @if ($highlight)
                <span class="w-8 h-8 rounded-full bg-white/15 text-white flex items-center justify-center shrink-0">{{ $icon }}</span>
            @else
                <x-dashboard.icon-chip :tone="$tone">{{ $icon }}</x-dashboard.icon-chip>
            @endif
        @endisset
    </div>

    <div class="flex items-end justify-between gap-3 mt-3">
        <div class="min-w-0">
            <p class="text-2xl lg:text-3xl font-extrabold tabular-nums leading-none {{ $valueCls }}">{{ $value }}</p>
            @if ($hint)
                <p class="text-[11px] mt-1.5 {{ $hintCls }}">{{ $hint }}</p>
            @endif
        </div>
        @isset($chart)
            <div class="w-20 h-12 shrink-0 flex items-end justify-end">
                {{ $chart }}
            </div>
        @endisset
    </div>
</div>
