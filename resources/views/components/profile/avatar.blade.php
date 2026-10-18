{{--
    Profile photo (account profile redesign).

    Renders the REAL uploaded photo when the account has one, otherwise the
    account's own initial on the brand gradient (never a fabricated placeholder
    image). The optional camera badge is a real <input type="file"> wrapped in a
    <label>, so it is a genuine affordance — the chosen file uploads with the
    surrounding profile form on submit.
--}}
@props([
    'user',
    'editable' => false,
])

@php
    $url     = $user->avatarUrl();
    $initial = $user->initial();
    $size    = 'w-24 h-24 sm:w-28 sm:h-28';
@endphp

<div class="relative shrink-0" x-data="avatarImage">
    @if ($url)
        <img
            src="{{ $url }}"
            alt="Foto profil {{ $user->name }}"
            x-show="!failed"
            x-on:error="fail()"
            x-init="if ($el.complete && $el.naturalWidth === 0) fail()"
            class="{{ $size }} rounded-full object-cover ring-4 ring-white shadow-[0_12px_30px_-16px_rgba(16,24,40,0.45)]"
        >
        {{-- Fallback: shown only when the photo fails to load (missing file /
             unreachable host / broken URL) — never the browser broken-image icon. --}}
        <span
            x-show="failed"
            x-cloak
            class="{{ $size }} rounded-full bg-primary-600 flex items-center justify-center text-white text-4xl font-bold ring-4 ring-white shadow-[0_12px_30px_-16px_rgba(16,24,40,0.45)] select-none"
            aria-hidden="true"
        >{{ $initial }}</span>
    @else
        <span
            class="{{ $size }} rounded-full bg-primary-600 flex items-center justify-center text-white text-4xl font-bold ring-4 ring-white shadow-[0_12px_30px_-16px_rgba(16,24,40,0.45)] select-none"
            aria-hidden="true"
        >{{ $initial }}</span>
    @endif

    @if ($editable)
        <label
            class="absolute -bottom-1 -right-1 w-9 h-9 rounded-full bg-accent-600 hover:bg-accent-700 text-white flex items-center justify-center cursor-pointer ring-4 ring-white ui-animated shadow-sm"
            title="Ubah foto profil"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <input
                type="file"
                name="avatar"
                accept="image/png,image/jpeg,image/webp"
                class="sr-only"
                @change="photoName = ($event.target.files && $event.target.files[0]) ? $event.target.files[0].name : ''; editing = true"
            >
            <span class="sr-only">Pilih foto profil</span>
        </label>
    @endif
</div>
