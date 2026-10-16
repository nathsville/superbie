@props([
    'title'    => null,
    'subtitle' => null,
    'padded'   => true,
])

@php
    // Soft bento surface. When a title is present the header owns the top
    // padding so the body only needs the remaining sides + bottom.
    $body = $padded
        ? ($title ? 'px-5 sm:px-6 pb-5 sm:pb-6' : 'p-5 sm:p-6')
        : '';
@endphp

<div {{ $attributes->merge(['class' => 'soft-card card-enter flex flex-col']) }}>
    @if ($title)
        <div class="flex items-start justify-between gap-3 px-5 sm:px-6 pt-5 pb-3">
            <div class="min-w-0">
                <h2 class="text-sm font-semibold text-ink">{{ $title }}</h2>
                @if ($subtitle)
                    <p class="text-xs text-ink-faint mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="shrink-0">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="{{ $body }} flex-1">
        {{ $slot }}
    </div>
</div>
