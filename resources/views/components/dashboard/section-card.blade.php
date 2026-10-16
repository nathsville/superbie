@props([
    'title'    => null,
    'subtitle' => null,
    'padded'   => true,
])

{{-- Section card — consistent white bento surface, deep radius, soft shadow. --}}
<div {{ $attributes->merge(['class' => 'soft-card overflow-hidden card-enter']) }}>
    @if ($title)
        <div class="flex items-start justify-between gap-3 px-5 sm:px-6 py-4">
            <div class="min-w-0">
                <h2 class="text-base font-semibold text-ink">{{ $title }}</h2>
                @if ($subtitle)
                    <p class="text-xs text-ink-soft mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="shrink-0">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="{{ $padded ? 'p-5 sm:p-6' : '' }}">
        {{ $slot }}
    </div>
</div>
