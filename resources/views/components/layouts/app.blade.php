<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ $description ?? 'SuperBie — Lapor Pak Wali: Platform pengaduan masyarakat Kota Parepare' }}">
    @php
        // Page titles either OMIT the brand (this layout appends it) or already
        // INCLUDE it. Guard against emitting "… — Lapor Pak Wali — Lapor Pak Wali".
        $pageTitle = $title ?? 'SuperBie';
        $documentTitle = str_contains($pageTitle, 'Lapor Pak Wali')
            ? $pageTitle
            : $pageTitle.' — Lapor Pak Wali';
    @endphp
    <title>{{ $documentTitle }}</title>
    {{-- Server-derived, non-reversible cache identity (Prompt 26).
         Namespaces the in-memory navigation cache by user + role + Dinas/Unit so
         snapshots can never leak across users, roles, or units. Absent for
         guests -> the client disables page caching entirely (safe fallback). --}}
    @auth
        <meta name="navigation-identity" content="{{ hash_hmac('sha256', auth()->id().'|'.auth()->user()->role.'|'.(auth()->user()->dinas_unit_id ?? 'null'), (string) config('app.key')) }}">
    @endauth
    {{-- Branded favicon. public/favicon.ico exists but is 0 bytes, so the SVG
         is declared first; browsers that support it never request the broken .ico. --}}
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/favicon.svg">
    <meta name="theme-color" content="#013FF6">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="h-full font-sans bg-page text-ink antialiased">
    {{ $slot }}

    @if (session('success'))
        <div
            id="toast-success"
            role="alert"
            aria-live="assertive"
            aria-atomic="true"
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 5000)"
            x-transition:leave="transition ease-in duration-300"
            x-transition:leave-start="opacity-100 transform translate-y-0"
            x-transition:leave-end="opacity-0 transform translate-y-2"
            class="fixed bottom-5 right-5 z-50 flex items-center gap-3 rounded-xl bg-success text-white px-5 py-3.5 shadow-lg ui-animated"
        >
            <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <span class="text-sm font-medium">{{ session('success') }}</span>
            <button @click="show = false" class="ml-2 text-white/80 hover:text-white" aria-label="Tutup notifikasi">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @endif

    @if (session('error'))
        <div
            id="toast-error"
            role="alert"
            aria-live="assertive"
            aria-atomic="true"
            x-data="{ show: true }"
            x-show="show"
            x-transition:leave="transition ease-in duration-300"
            x-transition:leave-start="opacity-100 transform translate-y-0"
            x-transition:leave-end="opacity-0 transform translate-y-2"
            class="fixed bottom-5 right-5 z-50 flex items-center gap-3 rounded-xl bg-error text-white px-5 py-3.5 shadow-lg"
        >
            <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <span class="text-sm font-medium">{{ session('error') }}</span>
            <button @click="show = false" class="ml-2 text-white/80 hover:text-white" aria-label="Tutup notifikasi">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @endif

    @stack('scripts')
</body>
</html>
