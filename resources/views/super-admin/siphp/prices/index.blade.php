@php
    $title = 'Harga Pasar — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Harga Pasar (SIPHP)</x-slot:header>
    <x-slot:breadcrumb>Input manual harga per pasar dan tanggal — tampil publik di /siphp</x-slot:breadcrumb>

    <div class="w-full max-w-full space-y-6 card-enter">
        {{-- Flash --}}
        @if (session('success'))
            <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('success') }}
            </div>
        @endif

        {{-- Info --}}
        <div class="w-full bg-white rounded-2xl border border-line shadow-sm p-4 sm:p-5">
            <div class="flex items-start justify-between flex-wrap gap-3">
                <div class="max-w-2xl">
                    <h2 class="text-sm font-semibold text-ink tracking-wide">Catat Harga Harian</h2>
                    <p class="text-sm text-ink-faint mt-1">
                        Pilih pasar untuk mencatat harga seluruh <strong class="text-ink-soft">{{ $activeItemsCount }}</strong> barang aktif pada satu tanggal.
                        Satu harga disimpan per kombinasi <em>pasar × barang × tanggal</em>; menyimpan ulang tanggal yang sama memperbarui nilainya.
                    </p>
                    <p class="text-xs text-ink-faint mt-2">{{ config('siphp.source_note') }}</p>
                </div>
                <a href="{{ route('super-admin.market-items.index') }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-line hover:bg-page text-ink text-sm font-semibold rounded-xl ui-animated">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    Barang Pasar
                </a>
            </div>
        </div>

        {{-- ── Daftar pasar ─────────────────────────────────────────────────── --}}
        <h2 class="sr-only">Daftar pasar</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            @forelse ($markets as $market)
                <div class="bg-white rounded-2xl border border-line shadow-sm p-5 flex flex-col gap-3 card-enter">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h3 class="text-sm font-semibold text-ink">{{ $market->name }}</h3>
                            <p class="text-xs text-ink-faint mt-0.5">Terakhir dicatat:
                                @if ($market->last_price_date)
                                    <strong class="text-ink-soft">{{ \Illuminate\Support\Carbon::parse($market->last_price_date)->translatedFormat('d M Y') }}</strong>
                                @else
                                    <span>belum ada</span>
                                @endif
                            </p>
                        </div>
                        @if ($market->is_active)
                            <span class="status-badge border bg-green-50 text-green-700 border-green-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                Aktif
                            </span>
                        @else
                            <span class="status-badge border bg-surface-sunken text-ink-soft border-line">
                                <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                Nonaktif
                            </span>
                        @endif
                    </div>

                    <p class="text-xs text-ink-faint">
                        <strong class="text-ink-soft">{{ $market->prices_count }}</strong> baris harga tercatat
                    </p>

                    <div class="mt-auto">
                        <a href="{{ route('super-admin.market-prices.catat', $market) }}"
                           class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Catat Harga
                        </a>
                    </div>
                </div>
            @empty
                <div class="sm:col-span-2 xl:col-span-3 bg-white rounded-2xl border border-line shadow-sm empty-state py-16 text-center">
                    <p class="text-sm font-semibold text-ink-soft">Belum ada pasar.</p>
                    <p class="text-xs text-ink-faint mt-1">Tambahkan pasar terlebih dahulu di menu Master Pasar.</p>
                </div>
            @endforelse
        </div>

        {{-- ── Entri terbaru ────────────────────────────────────────────────── --}}
        <h2 class="sr-only">Entri harga terbaru</h2>
        <div class="w-full bg-white rounded-2xl border border-line shadow-sm overflow-hidden">
            <div class="px-5 sm:px-6 py-4 border-b border-line">
                <h3 class="text-sm font-semibold text-ink tracking-wide">Entri Terbaru</h3>
                <p class="text-xs text-ink-faint mt-0.5">15 baris harga terakhir yang dicatat.</p>
            </div>

            @if ($recent->isEmpty())
                <div class="empty-state py-14 text-center">
                    <svg class="w-12 h-12 text-line-strong mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1"/></svg>
                    <p class="text-sm font-semibold text-ink-soft">Belum ada harga tercatat.</p>
                    <p class="text-xs text-ink-faint mt-1">Pilih pasar di atas untuk mulai mencatat harga.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <caption class="sr-only">Daftar entri harga pasar terbaru</caption>
                        <thead class="bg-page text-ink-soft text-xs uppercase tracking-wider">
                            <tr>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Tanggal</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold min-w-[10rem]">Pasar</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold min-w-[12rem]">Barang</th>
                                <th scope="col" class="text-right px-4 py-3.5 font-semibold whitespace-nowrap">Harga</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-sunken">
                            @foreach ($recent as $row)
                                <tr class="hover:bg-page transition-colors">
                                    <td class="px-4 py-3.5 whitespace-nowrap text-ink-soft">
                                        {{ $row->effective_date?->translatedFormat('d M Y') ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="font-semibold text-ink">{{ $row->market?->name ?? '—' }}</span>
                                    </td>
                                    <td class="px-4 py-3.5 text-ink-soft">
                                        {{ $row->item?->name ?? '—' }}
                                        @if ($row->item?->unit)
                                            <span class="text-xs text-ink-faint">({{ $row->item->unit }})</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 text-right whitespace-nowrap font-semibold text-ink">
                                        Rp {{ number_format((float) $row->price, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-layouts.dashboard>
