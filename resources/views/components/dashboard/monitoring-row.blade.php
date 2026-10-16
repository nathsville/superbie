@props([
    'item',
    'href' => null,
])

@php
    $status = $item->status;
    $umur = $item->submitted_at ? (int) $item->submitted_at->diffInDays(now()) : null;
    $umurLabel = is_null($umur) ? '—' : ($umur === 0 ? 'Hari ini' : $umur.' hari');
    $tag = $href ? 'a' : 'div';
@endphp

{{-- Compact monitoring row: ID, kategori & lokasi, Dinas/Unit tujuan, umur, status. --}}
<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    class="flex items-center gap-4 px-5 sm:px-6 py-3.5 table-row-hover {{ $href ? 'group' : '' }}"
>
    <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 mb-1 flex-wrap">
            <span class="text-xs font-mono text-ink-soft">{{ $item->reference_code }}</span>
            <span class="status-badge {{ $status->tailwindBadge() }}">
                <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                {{ $status->label() }}
            </span>
        </div>
        <p class="text-sm font-semibold text-ink truncate {{ $href ? 'group-hover:text-primary-600 ui-animated' : '' }}">
            {{ $item->title }}
        </p>
        <p class="text-xs text-ink-soft mt-0.5 truncate">
            {{ $item->category?->name ?? 'Tanpa Kategori' }}
            @if ($item->location_text)
                • {{ $item->location_text }}
            @endif
            • {{ $item->dinasUnit?->name ?? 'Belum ada Dinas/Unit' }}
        </p>
    </div>
    <div class="text-right shrink-0">
        <p class="text-[10px] uppercase tracking-wide text-ink-faint">Umur</p>
        <p class="text-sm font-bold text-ink whitespace-nowrap">{{ $umurLabel }}</p>
    </div>
</{{ $tag }}>
