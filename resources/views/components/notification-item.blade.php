{{--
    PHASE N4 — single notification row (shared by the bell dropdown and the
    notification page).

    The row is NOT a bare anchor: it carries a delete form, and a form cannot
    legally nest inside an <a>. The clickable region is the inner <a> that goes
    through `notifications.open` (which marks the notification read, then
    redirects to the role-appropriate, access-checked target).

    Visual language:
      • A type-aware icon in a pastel circular avatar (left) — the icon + tint
        map to the notification's `type` (document / check / return / star …).
      • Unread rows carry a soft bluish background + a blue unread dot; read
        rows stay clean white.
      • The timestamp is muted (low emphasis) so the eye lands on the title.
      • On the full page the delete button only becomes prominent on hover/focus
        (it stays visible on touch screens where hover does not exist).

    All text is rendered with {{ }} so title/body/data are HTML-escaped (no XSS).
--}}
@props([
    'notification',
    'compact' => false,
])

@php
    $isUnread = $notification->isUnread();
    $when = $notification->created_at?->translatedFormat('d M Y, H:i');

    // Type → [icon path, pastel avatar tone]. Falls back to a generic document
    // icon for any future/unknown type so the row never renders empty.
    [$iconPath, $tone] = match ($notification->type) {
        \App\Services\NotificationService::M2,
        \App\Services\NotificationService::M7 => ['M5 13l4 4L19 7', 'bg-emerald-50 text-emerald-600'],
        \App\Services\NotificationService::M3,
        \App\Services\NotificationService::M6 => ['M9 14l-4-4m0 0l4-4m-4 4h11a4 4 0 010 8h-1', 'bg-accent-50 text-accent-600'],
        \App\Services\NotificationService::M9 => ['M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'bg-accent-50 text-accent-600'],
        \App\Services\NotificationService::M10 => ['M6 18L18 6M6 6l12 12', 'bg-red-50 text-red-600'],
        \App\Services\NotificationService::M8 => ['M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'bg-violet-50 text-violet-600'],
        \App\Services\NotificationService::M11 => ['M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'bg-surface-sunken text-ink-soft'],
        \App\Services\NotificationService::M12 => ['M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z', 'bg-accent-50 text-accent-600'],
        default => ['M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'bg-primary-50 text-primary-600'],
    };
@endphp

<div @class([
    'group relative flex items-start gap-3 px-4 border-b border-surface-sunken last:border-b-0 transition-colors',
    'py-3.5' => ! $compact,
    'py-2.5' => $compact,
    'bg-primary-50 hover:bg-primary-50' => $isUnread,
    'bg-white hover:bg-page' => ! $isUnread,
])>
    <a
        href="{{ route('notifications.open', $notification->id) }}"
        class="flex flex-1 min-w-0 items-start gap-3"
        title="Buka notifikasi"
    >
        {{-- Type-aware icon in a pastel circular avatar. --}}
        <span
            class="mt-0.5 flex shrink-0 items-center justify-center rounded-full {{ $tone }} {{ $compact ? 'h-8 w-8' : 'h-10 w-10' }}"
            aria-hidden="true"
        >
            <svg class="{{ $compact ? 'w-4 h-4' : 'w-5 h-5' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath }}"/>
            </svg>
        </span>

        <span class="flex-1 min-w-0">
            <span class="flex items-center gap-2">
                <span @class([
                    'block text-sm truncate',
                    'font-semibold text-ink' => $isUnread,
                    'font-medium text-ink-body' => ! $isUnread,
                ])>{{ $notification->title }}</span>

                {{-- Blue unread dot indicator (unread rows only). --}}
                @if ($isUnread)
                    <span class="h-2 w-2 shrink-0 rounded-full bg-primary-600" aria-hidden="true"></span>
                    <span class="sr-only">Belum dibaca</span>
                @endif
            </span>

            <span @class([
                'mt-1 block text-xs text-ink-faint',
                'line-clamp-2' => $compact,
                'line-clamp-3' => ! $compact,
            ])>{{ $notification->body }}</span>

            @if ($compact && $when)
                <span class="mt-1.5 block text-[11px] text-ink-soft">{{ $when }}</span>
            @endif
        </span>
    </a>

    @unless ($compact)
        <div class="shrink-0 self-center flex items-center gap-2 pl-1">
            {{-- Muted timestamp — low emphasis so the title stays the focus. --}}
            @if ($when)
                <span class="hidden sm:inline text-[11px] text-ink-soft whitespace-nowrap tabular-nums">{{ $when }}</span>
            @endif

            {{-- Delete: always visible on touch, reveals on hover/focus on pointer devices. --}}
            <form
                method="POST"
                action="{{ route('notifications.destroy', $notification->id) }}"
                class="transition-opacity duration-150 opacity-100 md:opacity-0 md:group-hover:opacity-100 md:group-focus-within:opacity-100"
                data-confirm="Hapus notifikasi ini?"
            >
                @csrf
                @method('DELETE')
                <button
                    type="submit"
                    class="-m-1.5 inline-flex items-center justify-center rounded-full p-3.5 text-ink-ghost hover:text-red-600 hover:bg-red-50 ui-animated"
                    aria-label="Hapus notifikasi"
                    title="Hapus notifikasi"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </form>
        </div>
    @endunless
</div>
