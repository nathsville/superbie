@php
    $title = 'Kelurahan — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
    $hasFilters = !empty($search) || !empty($kecamatanId);
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Master Kelurahan</x-slot:header>
    <x-slot:breadcrumb>Kelola data wilayah Kelurahan</x-slot:breadcrumb>

    <div class="w-full max-w-full space-y-6 card-enter">
        {{-- Flash --}}
        @if (session('success'))
            <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->has('kelurahan'))
            <div class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.74 5a2 2 0 00-3.48 0L3.33 16a2 2 0 001.74 3z"/></svg>
                {{ $errors->first('kelurahan') }}
            </div>
        @endif

        {{-- ── Toolbar & filter ─────────────────────────────────────────────── --}}
        <div class="w-full bg-white rounded-2xl border border-line shadow-sm p-4 sm:p-5">
            <div class="flex items-start justify-between flex-wrap gap-3 mb-4">
                <div>
                    <h2 class="text-sm font-semibold text-ink tracking-wide">Filter &amp; Pencarian</h2>
                    <p class="text-sm text-ink-faint mt-1">
                        Total: <strong>{{ $kelurahan->total() }}</strong> Kelurahan
                        @if ($hasFilters)
                            <span>• hasil terfilter</span>
                        @endif
                    </p>
                </div>
                <a href="{{ route('super-admin.kelurahan.create') }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Tambah Kelurahan
                </a>
            </div>

            <h2 class="sr-only">Pencarian dan filter Kelurahan</h2>
            <form method="GET" action="{{ route('super-admin.kelurahan.index') }}"
                  class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end"
                  role="search" aria-label="Pencarian dan filter Kelurahan">
                <div>
                    <label for="search" class="block text-xs font-semibold text-ink-soft mb-1.5">Cari</label>
                    <input id="search" name="search" type="text" value="{{ $search }}"
                        placeholder="Nama atau kode Kelurahan"
                        class="w-full px-3.5 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated">
                </div>
                <div>
                    <label for="kecamatan_id" class="block text-xs font-semibold text-ink-soft mb-1.5">Kecamatan</label>
                    <select id="kecamatan_id" name="kecamatan_id"
                        class="w-full px-3.5 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated">
                        <option value="">Semua</option>
                        @foreach ($kecamatanList as $kec)
                            <option value="{{ $kec->id }}" @selected($kecamatanId === $kec->id)>{{ $kec->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    @if ($hasFilters)
                        <a href="{{ route('super-admin.kelurahan.index') }}"
                           class="inline-flex items-center gap-2 px-4 py-2.5 bg-white hover:bg-surface-sunken text-ink-soft text-sm font-semibold rounded-xl border border-line ui-animated">
                            Reset
                        </a>
                    @endif
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        Filter
                    </button>
                </div>
            </form>
        </div>

        {{-- ── Table ────────────────────────────────────────────────────────── --}}
        <h2 class="sr-only">Daftar Kelurahan</h2>
        <div class="w-full bg-white rounded-2xl border border-line shadow-sm overflow-hidden">
            @if ($kelurahan->isEmpty())
                <div class="empty-state py-16 text-center">
                    <svg class="w-12 h-12 text-line-strong mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <p class="text-sm font-semibold text-ink-soft">Belum ada data Kelurahan.</p>
                    <p class="text-xs text-ink-faint mt-1">Tambahkan Kelurahan baru atau ubah kata kunci pencarian.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <caption class="sr-only">Daftar Kelurahan beserta kecamatan, kode wilayah, dan jumlah laporan</caption>
                        <thead class="bg-page text-ink-soft text-xs uppercase tracking-wider">
                            <tr>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold min-w-[15rem]">Nama</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold min-w-[13rem]">Kecamatan</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Kode</th>
                                <th scope="col" class="text-center px-4 py-3.5 font-semibold whitespace-nowrap">Laporan</th>
                                <th scope="col" class="text-right px-4 py-3.5 font-semibold whitespace-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-sunken">
                            @foreach ($kelurahan as $item)
                                <tr class="hover:bg-page transition-colors">
                                    <td class="px-4 py-4">
                                        <span class="font-semibold text-ink">{{ $item->name }}</span>
                                    </td>
                                    <td class="px-4 py-4">
                                        @if ($item->kecamatan)
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-50 border border-primary-100 px-2.5 py-0.5 text-xs font-semibold text-primary-700">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                                {{ $item->kecamatan->name }}
                                            </span>
                                        @else
                                            <span class="text-xs text-ink-faint">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        @if ($item->code)
                                            <span class="font-mono text-xs text-ink-soft bg-page px-2 py-1 rounded-lg border border-line">{{ $item->code }}</span>
                                        @else
                                            <span class="text-xs text-ink-faint">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-center whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-violet-50 border border-violet-100 px-2.5 py-0.5 text-xs font-semibold text-violet-700">
                                            <svg class="w-3 h-3 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            {{ $item->complaints_count }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-right whitespace-nowrap">
                                        <a href="{{ route('super-admin.kelurahan.edit', $item) }}"
                                           aria-label="Ubah Kelurahan {{ $item->name }}" title="Ubah"
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

                @if ($kelurahan->hasPages())
                    <div class="px-5 sm:px-6 py-4 border-t border-line bg-surface">
                        {{ $kelurahan->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-layouts.dashboard>
