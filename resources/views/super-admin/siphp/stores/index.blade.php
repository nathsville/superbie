@php
    $title = 'Toko Retail — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
    $hasFilters = !empty($search) || !empty($marketId) || !empty($status);
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Direktori Toko Retail (SIPHP)</x-slot:header>
    <x-slot:breadcrumb>Toko retail Parepare — input manual, tampil publik di /siphp</x-slot:breadcrumb>

    <div class="w-full max-w-full space-y-6 card-enter">
        {{-- Flash --}}
        @if (session('success'))
            <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->has('store'))
            <div class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.74 5a2 2 0 00-3.48 0L3.33 16a2 2 0 001.74 3z"/></svg>
                {{ $errors->first('store') }}
            </div>
        @endif

        {{-- ── Toolbar & filter ─────────────────────────────────────────────── --}}
        <div class="w-full bg-white rounded-2xl border border-line shadow-sm p-4 sm:p-5">
            <div class="flex items-start justify-between flex-wrap gap-3 mb-4">
                <div>
                    <h2 class="text-sm font-semibold text-ink tracking-wide">Filter &amp; Pencarian</h2>
                    <p class="text-sm text-ink-faint mt-1">
                        Total: <strong class="text-ink">{{ $stores->total() }}</strong> toko
                    </p>
                </div>
                <a href="{{ route('super-admin.market-stores.create') }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Tambah Toko
                </a>
            </div>

            <h2 class="sr-only">Pencarian dan filter toko</h2>
            <form method="GET" action="{{ route('super-admin.market-stores.index') }}"
                  class="grid grid-cols-1 sm:grid-cols-4 gap-3" role="search" aria-label="Pencarian dan filter toko">
                <div class="sm:col-span-2">
                    <label for="search" class="block text-xs font-semibold text-ink-soft mb-1.5">Cari</label>
                    <input id="search" name="search" type="text" value="{{ $search }}"
                        placeholder="Nama atau jenis toko"
                        class="w-full px-3.5 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated">
                </div>
                <div>
                    <label for="market_id" class="block text-xs font-semibold text-ink-soft mb-1.5">Pasar</label>
                    <select id="market_id" name="market_id"
                        class="w-full px-3.5 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated">
                        <option value="">Semua</option>
                        @foreach ($markets as $market)
                            <option value="{{ $market->id }}" @selected($marketId === $market->id)>{{ $market->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-xs font-semibold text-ink-soft mb-1.5">Status</label>
                    <select id="status" name="status"
                        class="w-full px-3.5 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated">
                        <option value="">Semua</option>
                        <option value="active" @selected($status === 'active')>Aktif</option>
                        <option value="inactive" @selected($status === 'inactive')>Nonaktif</option>
                    </select>
                </div>

                <div class="sm:col-span-4 flex items-center justify-end gap-2 pt-3 border-t border-surface-sunken">
                    @if ($hasFilters)
                        <a href="{{ route('super-admin.market-stores.index') }}"
                           class="inline-flex items-center gap-2 px-4 py-2.5 bg-white hover:bg-surface-sunken text-ink-soft text-sm font-semibold rounded-xl border border-line ui-animated">
                            Reset
                        </a>
                    @endif
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        Filter
                    </button>
                </div>
            </form>
        </div>

        {{-- ── Table ────────────────────────────────────────────────────────── --}}
        <h2 class="sr-only">Daftar toko retail</h2>
        <div class="w-full bg-white rounded-2xl border border-line shadow-sm overflow-hidden">
            @if ($stores->isEmpty())
                <div class="empty-state py-16 text-center">
                    <svg class="w-12 h-12 text-line-strong mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    <p class="text-sm font-semibold text-ink-soft">Belum ada toko retail.</p>
                    <p class="text-xs text-ink-faint mt-1">Tambahkan toko baru atau ubah filter pencarian.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <caption class="sr-only">Daftar toko retail beserta pasar, jenis, telepon, dan status</caption>
                        <thead class="bg-page text-ink-soft text-xs uppercase tracking-wider">
                            <tr>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Foto</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold min-w-[14rem]">Nama Toko</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold min-w-[10rem]">Pasar</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Jenis</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Telepon</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Status</th>
                                <th scope="col" class="text-right px-4 py-3.5 font-semibold whitespace-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-sunken">
                            @foreach ($stores as $store)
                                <tr class="hover:bg-page transition-colors">
                                    <td class="px-4 py-3">
                                        @if ($store->photoUrl())
                                            <img src="{{ $store->photoUrl() }}" alt="Foto {{ $store->name }}"
                                                 loading="lazy" decoding="async"
                                                 class="w-12 h-12 rounded-xl object-cover border border-line">
                                        @else
                                            <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-page border border-line text-ink-faint" aria-hidden="true">
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="font-semibold text-ink">{{ $store->name }}</span>
                                        @if ($store->address)
                                            <p class="text-xs text-ink-faint mt-0.5">{{ $store->address }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5">
                                        @if ($store->market)
                                            <span class="text-ink-soft">{{ $store->market->name }}</span>
                                        @else
                                            <span class="text-xs text-ink-faint">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                        @if ($store->type)
                                            <span class="text-ink-soft">{{ $store->type }}</span>
                                        @else
                                            <span class="text-xs text-ink-faint">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                        @if ($store->phone)
                                            <span class="text-ink-soft">{{ $store->phone }}</span>
                                        @else
                                            <span class="text-xs text-ink-faint">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                        @if ($store->is_active)
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
                                    </td>
                                    <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                        <a href="{{ route('super-admin.market-stores.edit', $store) }}"
                                           aria-label="Ubah toko {{ $store->name }}" title="Ubah"
                                           class="inline-flex items-center gap-1 rounded-lg bg-primary-50 px-3 py-1.5 text-xs font-semibold text-primary-600 hover:bg-primary-100 ui-animated">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            Ubah
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($stores->hasPages())
                    <div class="px-5 sm:px-6 py-4 border-t border-line bg-surface">
                        {{ $stores->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-layouts.dashboard>
