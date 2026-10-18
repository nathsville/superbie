@php
    $title = $market->name . ' — Harga Pasar';
    $labelSm = 'text-[13px] text-ink-faint';
    $labelBand = 'text-[13px] text-ink-soft';
    $groups = $prices->groupBy(fn ($price) => $price->item->category?->name ?? 'Lainnya');
    $maxDate = $prices->max('effective_date');
    $trendItems = $trend['items'];
    $selectedTrendItem = $trend['selected'];
    $trendPoints = $trend['points'];
    $trendTotal = $trend['total'];
    $trendEarliest = $trend['earliest'];
    $trendLatest = $trend['latest'];
@endphp

<x-layouts.app :title="$title">
    <x-skip-link />

    <div class="min-h-full bg-surface text-ink">

        {{-- ─── Header (same opening as the parent page; this is a sub-page) ── --}}
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

                <nav
                    class="-mx-5 col-span-2 flex items-center gap-4 bg-accent-500 px-5 py-3 sm:-mx-8 sm:px-8 lg:mx-0 lg:col-span-4 lg:col-start-3 lg:gap-4 lg:px-5 lg:py-0 xl:gap-6 2xl:col-span-3"
                    aria-label="Navigasi publik"
                >
                    <a href="{{ route('weather.index') }}" class="whitespace-nowrap text-[17px] font-semibold text-ink transition-colors hover:text-primary-800">Cuaca</a>
                    <a href="{{ route('siphp.index') }}" aria-current="page" class="whitespace-nowrap text-[17px] font-semibold text-ink underline decoration-2 underline-offset-4 transition-colors hover:text-primary-800">Harga pasar</a>
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
            {{-- ─── Title ────────────────────────────────────────────────────── --}}
            <section class="grid grid-cols-1 gap-6 lg:grid-cols-12" data-reveal="rows">
                <div class="lg:col-span-7 lg:col-start-6 lg:row-start-1">
                    <h1 class="font-semibold tracking-[-0.03em] text-ink" style="font-size: clamp(26px, 2.8vw, 40px)">
                        {{ $market->name }}
                    </h1>
                    <p class="mt-2 text-[17px] font-semibold text-ink-soft">
                        Harga pasar{{ $market->address ? ' — ' . $market->address : '' }}
                    </p>
                </div>
                <div class="lg:col-span-4 lg:col-start-1 lg:row-start-1">
                    <a
                        href="{{ route('siphp.index') }}"
                        class="ui-animated inline-flex min-h-11 items-center text-[17px] font-semibold text-primary-800 underline decoration-2 underline-offset-4 transition-colors hover:text-primary-700"
                    >
                        &larr; Semua pasar
                    </a>
                    <p class="mt-3 max-w-[42ch] text-pretty text-[17px] leading-relaxed text-ink-body">
                        Harga terbaru barang di {{ $market->name }}, dicatat manual oleh petugas.
                        Halaman ini publik dan tidak memerlukan login.
                    </p>
                </div>
            </section>

            {{-- ─── Ringkasan ────────────────────────────────────────────────── --}}
            <dl class="mt-12 grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2 lg:w-1/2" data-reveal="rows">
                <div>
                    <dt class="{{ $labelBand }}">Terakhir diperbarui</dt>
                    <dd class="mt-1 text-[17px] font-semibold text-ink">
                        {{ $maxDate ? $maxDate->translatedFormat('d M Y') : 'Belum ada' }}
                    </dd>
                </div>
                <div>
                    <dt class="{{ $labelBand }}">Barang tercatat</dt>
                    <dd class="mt-1 text-[17px] font-semibold text-ink tabular-nums">
                        {{ $prices->count() }} barang
                    </dd>
                </div>
            </dl>

            {{-- ─── Harga per kategori ───────────────────────────────────────── --}}
            @forelse ($groups as $categoryName => $rows)
                <section class="mt-12" aria-label="Harga kategori {{ $categoryName }}">
                    <h2 class="text-[22px] font-semibold tracking-[-0.02em] text-ink">{{ $categoryName }}</h2>
                    <div class="mt-4">
                        @foreach ($rows as $row)
                            <div class="flex items-baseline justify-between gap-6 px-3 py-3 {{ $loop->odd ? '' : 'bg-surface-sunken' }}">
                                <p class="min-w-0 text-[17px] font-medium text-ink">
                                    {{ $row->item->name }}
                                    @if ($row->item->unit)
                                        <span class="ml-1 text-[13px] font-normal text-ink-soft">{{ $row->item->unit }}</span>
                                    @endif
                                </p>
                                <p class="shrink-0 text-right text-[17px] font-semibold tabular-nums text-ink">
                                    Rp {{ number_format((float) $row->price, 0, ',', '.') }}
                                    <span class="mt-0.5 block text-[13px] font-normal">
                                        <x-siphp.price-delta
                                            :current="$row->price"
                                            :previous="$previousPrices->get($market->id . '|' . $row->item_id)" />
                                        @if (! $row->effective_date->isSameDay($maxDate))
                                            <span class="block text-ink-soft">per {{ $row->effective_date->translatedFormat('d M Y') }}</span>
                                        @endif
                                    </span>
                                </p>
                            </div>
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="mt-12 bg-surface-sunken px-6 py-10">
                    <p class="text-[17px] text-ink-body">Data harga belum tersedia.</p>
                    <p class="mt-1 text-[13px] text-ink-soft">Harga akan tampil setelah petugas mencatatnya.</p>
                </div>
            @endforelse

            {{-- ─── Tren harga ───────────────────────────────────────────────── --}}
            @if ($trendItems->isNotEmpty())
                <section class="mt-20" aria-label="Tren harga">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1" data-reveal="rise">
                        <h2 class="text-[22px] font-semibold tracking-[-0.02em] text-ink">Tren harga</h2>
                        <p class="{{ $labelSm }}">Hanya nilai yang benar-benar dicatat petugas — tanpa perkiraan.</p>
                    </div>

                    <form method="get" action="{{ route('siphp.market', $market->slug) }}" class="mt-6 flex flex-wrap items-end gap-x-4 gap-y-3">
                        <div>
                            <label for="barang" class="{{ $labelBand }} block font-medium">Barang</label>
                            <select
                                id="barang"
                                name="barang"
                                class="mt-1 min-h-11 bg-surface-sunken px-3 text-[17px] text-ink"
                            >
                                @foreach ($trendItems->groupBy(fn ($item) => $item->category?->name ?? 'Lainnya') as $categoryName => $categoryItems)
                                    <optgroup label="{{ $categoryName }}">
                                        @foreach ($categoryItems as $item)
                                            <option value="{{ $item->id }}" @selected($selectedTrendItem?->id === $item->id)>{{ $item->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <button
                            type="submit"
                            class="ui-animated inline-flex min-h-11 items-center text-[17px] font-semibold text-primary-800 underline decoration-2 underline-offset-4 transition-colors hover:text-primary-700"
                        >Tampilkan</button>
                    </form>

                    @if ($selectedTrendItem)
                        <div class="mt-8" data-reveal="rise">
                            <h3 class="text-[17px] font-semibold text-ink">
                                {{ $selectedTrendItem->name }}
                                @if ($selectedTrendItem->unit)
                                    <span class="ml-1 text-[13px] font-normal text-ink-faint">per {{ $selectedTrendItem->unit }}</span>
                                @endif
                            </h3>
                            <p class="mt-1 {{ $labelSm }}">
                                @if ($trendPoints !== [])
                                    @if ($trendTotal > count($trendPoints))
                                        {{ count($trendPoints) }} pencatatan terakhir dari {{ $trendTotal }} ·
                                    @else
                                        {{ $trendTotal }} pencatatan ·
                                    @endif
                                    @if ($trendEarliest && $trendLatest && $trendEarliest->isSameDay($trendLatest))
                                        {{ $trendLatest->translatedFormat('d M Y') }}
                                    @else
                                        {{ $trendEarliest?->translatedFormat('d M Y') }} – {{ $trendLatest?->translatedFormat('d M Y') }}
                                    @endif
                                @else
                                    Belum ada pencatatan harga untuk barang ini.
                                @endif
                            </p>

                            @if (count($trendPoints) >= 2)
                                <div class="mt-6">
                                    <x-siphp.trend-chart
                                        :points="$trendPoints"
                                        :aria-label="'Tren harga ' . $selectedTrendItem->name . ' di ' . $market->name" />
                                </div>
                            @elseif (count($trendPoints) === 1)
                                <div class="mt-6 bg-surface-sunken px-6 py-8">
                                    <p class="text-[17px] text-ink-body">Grafik tampil setelah minimal dua pencatatan.</p>
                                    <p class="mt-1 text-[13px] text-ink-soft">
                                        Baru satu pencatatan: {{ $trendLatest?->translatedFormat('d M Y') }} —
                                        Rp {{ number_format($trendPoints[0]['value'], 0, ',', '.') }}.
                                    </p>
                                </div>
                            @endif
                        </div>
                    @endif
                </section>
            @endif

            {{-- ─── Toko di pasar ini ────────────────────────────────────────── --}}
            <section class="mt-20" aria-label="Toko di pasar ini">
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1" data-reveal="rise">
                    <h2 class="text-[22px] font-semibold tracking-[-0.02em] text-ink">Toko di {{ $market->name }}</h2>
                    <p class="{{ $labelSm }}">Data diinput manual oleh petugas.</p>
                </div>

                @if ($stores->isEmpty())
                    <div class="mt-6 bg-surface-sunken px-6 py-10">
                        <p class="text-[17px] text-ink-body">Belum ada toko yang terdaftar di pasar ini.</p>
                        <p class="mt-1 text-[13px] text-ink-soft">Toko akan tampil setelah petugas menambahkannya.</p>
                    </div>
                @else
                    <div class="mt-6 grid grid-cols-1 gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($stores as $store)
                            <article data-reveal="rows">
                                @if ($store->photoUrl())
                                    <div class="overflow-hidden" data-reveal="shutter">
                                        <img
                                            src="{{ $store->photoUrl() }}"
                                            alt="Foto {{ $store->name }}"
                                            loading="lazy"
                                            decoding="async"
                                            class="aspect-[4/3] w-full object-cover"
                                        >
                                    </div>
                                @endif
                                <div class="{{ $store->photoUrl() ? 'mt-4' : '' }}">
                                    <h3 class="text-[17px] font-semibold text-ink">{{ $store->name }}</h3>
                                    @if ($store->type)
                                        <p class="mt-1 text-[13px] text-ink-soft">{{ $store->type }}</p>
                                    @endif
                                    @if ($store->address)
                                        <p class="mt-1 text-[13px] leading-relaxed text-ink-faint">{{ $store->address }}</p>
                                    @endif
                                    @if ($store->phone || $store->opening_hours)
                                        <dl class="mt-3 space-y-1 text-[13px]">
                                            @if ($store->phone)
                                                <div class="flex gap-2">
                                                    <dt class="text-ink-faint">Telepon</dt>
                                                    <dd class="font-medium text-ink-body">{{ $store->phone }}</dd>
                                                </div>
                                            @endif
                                            @if ($store->opening_hours)
                                                <div class="flex gap-2">
                                                    <dt class="text-ink-faint">Jam buka</dt>
                                                    <dd class="font-medium text-ink-body">{{ $store->opening_hours }}</dd>
                                                </div>
                                            @endif
                                        </dl>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- ─── Source note ──────────────────────────────────────────────── --}}
            <p class="mt-16 max-w-[62ch] text-[13px] leading-relaxed text-ink-faint" data-reveal="rise">
                {{ config('siphp.source_note') }}
                Modul SIPHP SuperBie — dibangun ulang secara native di portal ini; halaman ini
                tidak mengambil data dari situs lain.
            </p>
        </main>

        {{-- ─── Footer ───────────────────────────────────────────────────────── --}}
        <footer class="bg-primary-900 text-white">
            <div class="mx-auto flex max-w-[1200px] flex-wrap items-center justify-between gap-x-8 gap-y-4 px-5 py-8 sm:px-8">
                <div>
                    <p class="text-[17px] font-semibold">SuperBie — Lapor Pak Wali</p>
                    <p class="mt-1 text-[13px] text-white/70">Pemerintah Kota Parepare, Sulawesi Selatan</p>
                </div>
                <nav class="flex flex-wrap items-center gap-6" aria-label="Tautan kaki halaman">
                    <a href="{{ route('home') }}" class="text-[17px] font-semibold text-white/85 transition-colors hover:text-white">Beranda</a>
                    <a href="{{ route('weather.index') }}" class="text-[17px] font-semibold text-white/85 transition-colors hover:text-white">Cuaca</a>
                    <a href="{{ route('siphp.index') }}" aria-current="page" class="text-[17px] font-semibold text-accent-400 transition-colors hover:text-accent-300">Harga pasar</a>
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
