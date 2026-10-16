@php
    $title = 'Master Kategori — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
    $hasFilters = !empty($search) || !empty($status);
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Master Kategori</x-slot:header>
    <x-slot:breadcrumb>Kelola Kategori Laporan &amp; Mapping Dinas/Unit</x-slot:breadcrumb>

    <div class="w-full max-w-full space-y-6 card-enter">
        {{-- Flash --}}
        @if (session('success'))
            <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->has('category'))
            <div class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.74 5a2 2 0 00-3.48 0L3.33 16a2 2 0 001.74 3z"/></svg>
                {{ $errors->first('category') }}
            </div>
        @endif

        {{-- ── Toolbar & filter ─────────────────────────────────────────────── --}}
        <div class="w-full bg-white rounded-2xl border border-line shadow-sm p-4 sm:p-5">
            <div class="flex items-start justify-between flex-wrap gap-3 mb-4">
                <div>
                    <h2 class="text-sm font-semibold text-ink tracking-wide">Filter &amp; Pencarian</h2>
                    <p class="text-sm text-ink-faint mt-1">
                        Total: <strong class="text-ink">{{ $categories->total() }}</strong> kategori
                    </p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="{{ route('super-admin.dinas.index') }}"
                       class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-line hover:bg-page text-ink text-sm font-semibold rounded-xl ui-animated">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        Master Dinas/Unit
                    </a>
                    <a href="{{ route('super-admin.categories.create') }}"
                       class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Tambah Kategori
                    </a>
                </div>
            </div>

            <form method="GET" action="{{ route('super-admin.categories.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3" role="search" aria-label="Pencarian dan filter kategori">
                <div class="sm:col-span-2">
                    <label for="search" class="block text-xs font-semibold text-ink-soft mb-1.5">Cari</label>
                    <input id="search" name="search" type="text" value="{{ $search }}"
                        placeholder="Nama kategori"
                        class="w-full px-3.5 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated">
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

                <div class="sm:col-span-3 flex items-center justify-end gap-2 pt-3 border-t border-surface-sunken">
                    @if ($hasFilters)
                        <a href="{{ route('super-admin.categories.index') }}"
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
        <h2 class="sr-only">Daftar kategori</h2>
        <div class="w-full bg-white rounded-2xl border border-line shadow-sm overflow-hidden">
            @if ($categories->isEmpty())
                <div class="empty-state py-16 text-center">
                    <svg class="w-12 h-12 text-line-strong mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5a2 2 0 011.447.6l8.553 8.553a2 2 0 010 2.828l-5.553 5.553a2 2 0 01-2.828 0L5.6 12.447A2 2 0 015 11V6a2 2 0 012-2z"/></svg>
                    <p class="text-sm font-semibold text-ink-soft">Belum ada kategori.</p>
                    <p class="text-xs text-ink-faint mt-1">Tambahkan kategori baru atau ubah filter pencarian.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <caption class="sr-only">Daftar kategori laporan beserta status, jumlah Dinas/Unit terkait dan jumlah laporan</caption>
                        <thead class="bg-page text-ink-soft text-xs uppercase tracking-wider">
                            <tr>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold min-w-[16rem]">Nama Kategori</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Status</th>
                                <th scope="col" class="text-center px-4 py-3.5 font-semibold whitespace-nowrap">Dinas/Unit</th>
                                <th scope="col" class="text-center px-4 py-3.5 font-semibold whitespace-nowrap">Laporan</th>
                                <th scope="col" class="text-right px-4 py-3.5 font-semibold whitespace-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-sunken">
                            @foreach ($categories as $category)
                                <tr class="hover:bg-page transition-colors">
                                    <td class="px-4 py-4">
                                        <span class="font-semibold text-ink">{{ $category->name }}</span>
                                        @if ($category->description)
                                            <p class="text-xs text-ink-faint mt-1 line-clamp-1">{{ \Illuminate\Support\Str::limit($category->description, 70) }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        @if ($category->is_active)
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
                                    <td class="px-4 py-4 text-center whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-page border border-line px-2.5 py-0.5 text-xs font-semibold text-ink-body">
                                            <svg class="w-3 h-3 text-ink-faint" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1"/></svg>
                                            {{ $category->dinas_units_count }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-center whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-violet-50 border border-violet-100 px-2.5 py-0.5 text-xs font-semibold text-violet-700">
                                            <svg class="w-3 h-3 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            {{ $category->complaints_count }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <a href="{{ route('super-admin.categories.mapping.edit', $category) }}"
                                               aria-label="Mapping kategori {{ $category->name }}" title="Mapping"
                                               class="inline-flex items-center gap-1 rounded-lg bg-page border border-line px-2.5 py-1.5 text-xs font-semibold text-ink-soft hover:bg-surface-sunken ui-animated">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                                Mapping
                                            </a>
                                            <a href="{{ route('super-admin.categories.edit', $category) }}"
                                               aria-label="Ubah kategori {{ $category->name }}" title="Ubah"
                                               class="inline-flex items-center gap-1 rounded-lg bg-primary-50 px-2.5 py-1.5 text-xs font-semibold text-primary-600 hover:bg-primary-100 ui-animated">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                Ubah
                                            </a>
                                            <form method="POST" action="{{ route('super-admin.categories.toggle', $category) }}" class="inline-flex">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit"
                                                    aria-label="{{ $category->is_active ? 'Nonaktifkan' : 'Aktifkan' }} kategori {{ $category->name }}"
                                                    title="{{ $category->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg ui-animated {{ $category->is_active ? 'bg-accent-50 text-accent-600 hover:bg-accent-100' : 'bg-green-50 text-green-600 hover:bg-green-100' }}">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($categories->hasPages())
                    <div class="px-5 sm:px-6 py-4 border-t border-line bg-surface">
                        {{ $categories->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-layouts.dashboard>
