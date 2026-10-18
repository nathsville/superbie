@php
    $title = 'Menuju JDIH Kota Parepare';
    // The page's type ladder mirrors the other public sheets: 13 for labels and
    // fine print, 17 for reading text and values, 22 for section headings.
    $labelSm = 'text-[13px] text-ink-faint';
@endphp

<x-layouts.app
    :title="$title"
    description="Pemberitahuan sebelum meninggalkan SuperBie menuju situs JDIH Kota Parepare — Jaringan Dokumentasi dan Informasi Hukum (jdih.pareparekota.go.id)."
>
    <x-skip-link />

    <div class="min-h-full bg-surface text-ink">

        {{-- ─── Header ─────────────────────────────────────────────────────────
             The same opening as the other public sheets: the wordmark at the
             left margin, the flat accent tab of plain links, one action at the
             right with the running WITA clock. --}}
        <header class="mx-auto max-w-[1200px] px-5 sm:px-8">
            <div class="grid grid-cols-2 items-stretch lg:grid-cols-12">
                <a
                    href="{{ route('home') }}"
                    class="flex items-center py-3 lg:col-span-2 lg:col-start-1"
                    aria-label="SuperBie — halaman utama"
                >
                    <img src="/brand/superbie-logo.svg" alt="SuperBie — Lapor Pak Wali" class="h-10 w-auto">
                </a>

                <div class="flex items-center justify-end gap-6 lg:col-span-7 lg:col-start-6">
                    <div
                        class="hidden items-center gap-2 sm:flex"
                        x-data="{ now: new Date() }"
                        x-init="setInterval(() => now = new Date(), 1000)"
                    >
                        <span class="wx-live-dot" aria-hidden="true"></span>
                        <span
                            class="text-[17px] font-semibold tabular-nums text-ink"
                            x-text="now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })"
                        >--:--:--</span>
                        <span class="text-[13px] text-ink-soft">WITA</span>
                    </div>

                    @auth
                        <a
                            href="{{ route('citizen.complaint.create') }}"
                            class="ui-animated inline-flex min-h-12 items-center justify-center bg-primary-600 px-6 text-[17px] font-semibold text-white hover:bg-primary-700 active:scale-[0.98]"
                        >Kirim laporan</a>
                    @else
                        <a
                            href="{{ route('register') }}"
                            class="ui-animated inline-flex min-h-12 items-center justify-center bg-primary-600 px-6 text-[17px] font-semibold text-white hover:bg-primary-700 active:scale-[0.98]"
                        >Kirim laporan</a>
                    @endauth
                </div>

                {{-- The same three-link public tab as the other sheets; the
                     notice page holds no seat in it, so no item is current. --}}
                <nav
                    class="-mx-5 col-span-2 flex items-center gap-4 bg-accent-500 px-5 py-3 sm:-mx-8 sm:px-8 lg:mx-0 lg:col-span-4 lg:col-start-3 lg:gap-4 lg:px-5 lg:py-0 xl:gap-6 2xl:col-span-3"
                    aria-label="Navigasi publik"
                >
                    <a href="{{ route('weather.index') }}" class="whitespace-nowrap text-[17px] font-semibold text-ink transition-colors hover:text-primary-800">Cuaca</a>
                    <a href="{{ route('siphp.index') }}" class="whitespace-nowrap text-[17px] font-semibold text-ink transition-colors hover:text-primary-800">Harga pasar</a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="whitespace-nowrap text-[17px] font-semibold text-ink transition-colors hover:text-primary-800">Akun saya</a>
                    @else
                        <a href="{{ route('login') }}" class="whitespace-nowrap text-[17px] font-semibold text-ink transition-colors hover:text-primary-800">Masuk</a>
                    @endauth
                </nav>
            </div>
        </header>

        <main
            id="main-content"
            tabindex="-1"
            class="page-enter mx-auto max-w-[1200px] px-5 pb-20 pt-12 sm:px-8 sm:pt-28"
        >
            {{-- ─── Title ──────────────────────────────────────────────────────
                 The standfirst at the left margin, the notice's subject on the
                 42% axis — the same opening as the other public sheets. --}}
            <section class="grid grid-cols-1 gap-6 lg:grid-cols-12" data-reveal="rows">
                <div class="lg:col-span-7 lg:col-start-6 lg:row-start-1">
                    <h1 class="font-semibold tracking-[-0.03em] text-ink" style="font-size: clamp(26px, 2.8vw, 40px)">
                        Kamu akan meninggalkan SuperBie
                    </h1>
                    <p class="mt-2 text-[17px] font-semibold text-ink-soft">
                        JDIH Kota Parepare — Jaringan Dokumentasi dan Informasi Hukum
                    </p>
                </div>
                <p class="max-w-[42ch] text-pretty text-[17px] leading-relaxed text-ink-body lg:col-span-4 lg:col-start-1 lg:row-start-1 lg:pt-2">
                    Tautan ini menuju situs di luar portal SuperBie. Halaman ini
                    ditampilkan lebih dulu supaya kamu tahu ke mana tautan membawa
                    sebelum kamu membukanya.
                </p>
            </section>

            {{-- ─── Notice field ───────────────────────────────────────────────
                 The one object on the page: the destination and the single
                 action that opens it. Nothing here acts on its own — no timer,
                 no meta refresh, no scripted redirect; the exit happens only
                 from the explicit click. --}}
            <section class="mt-16" data-reveal="rise">
                <div class="-mx-5 bg-surface-sunken px-5 py-10 sm:-mx-8 sm:px-8 sm:py-12">
                    <div class="grid grid-cols-1 gap-8 lg:grid-cols-12">
                        <div class="lg:col-span-7 lg:col-start-6">
                            <p class="{{ $labelSm }}">Alamat tujuan</p>
                            <p class="mt-1 text-[17px] font-semibold text-ink" style="overflow-wrap:anywhere">{{ $destination }}</p>
                            <p class="mt-2 {{ $labelSm }} leading-relaxed">
                                Situs tujuan terbuka di tab baru dan dikelola di luar
                                portal ini; SuperBie tidak menyalin isinya.
                            </p>

                            <div class="mt-8 flex flex-wrap items-center gap-x-8 gap-y-4">
                                <a
                                    href="{{ $destination }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="ui-animated inline-flex min-h-12 items-center justify-center bg-primary-600 px-6 text-[17px] font-semibold text-white hover:bg-primary-700 active:scale-[0.98]"
                                >Lanjutkan ke situs JDIH<span class="sr-only"> (situs eksternal, terbuka di tab baru)</span></a>

                                <a
                                    href="{{ route('home') }}"
                                    class="text-[17px] font-semibold text-primary-700 underline decoration-2 underline-offset-4 transition-colors hover:text-primary-800"
                                >Kembali ke beranda</a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        {{-- ─── Footer ─────────────────────────────────────────────────────────
             The same closing band as the other public sheets. --}}
        <footer class="bg-primary-900 text-white">
            <div class="mx-auto flex max-w-[1200px] flex-wrap items-center justify-between gap-x-8 gap-y-4 px-5 py-8 sm:px-8">
                <div>
                    <p class="text-[17px] font-semibold">SuperBie — Lapor Pak Wali</p>
                    <p class="mt-1 text-[13px] text-white/70">Pemerintah Kota Parepare, Sulawesi Selatan</p>
                </div>
                <nav class="flex flex-wrap items-center gap-6" aria-label="Tautan kaki halaman">
                    <a href="{{ route('home') }}" class="text-[17px] font-semibold text-white/85 transition-colors hover:text-white">Beranda</a>
                    <a href="{{ route('weather.index') }}" class="text-[17px] font-semibold text-white/85 transition-colors hover:text-white">Cuaca</a>
                    <a href="{{ route('siphp.index') }}" class="text-[17px] font-semibold text-white/85 transition-colors hover:text-white">Harga pasar</a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="text-[17px] font-semibold text-white/85 transition-colors hover:text-white">Akun saya</a>
                    @else
                        <a href="{{ route('login') }}" class="text-[17px] font-semibold text-white/85 transition-colors hover:text-white">Masuk</a>
                    @endauth
                </nav>
            </div>
        </footer>
    </div>
</x-layouts.app>
