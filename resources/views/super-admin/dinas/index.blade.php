@php
    $title = 'Dinas/Unit — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
    $hasFilters = !empty($search) || !empty($statusFilter);
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Master Dinas/Unit</x-slot:header>
    <x-slot:breadcrumb>Kelola Dinas/Unit dan Mapping Kategori</x-slot:breadcrumb>

    <div class="w-full max-w-full space-y-6 card-enter">
        {{-- Flash --}}
        @if (session('success'))
            <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('success') }}
            </div>
        @endif

        {{-- ── Toolbar & filter ─────────────────────────────────────────────── --}}
        <div class="w-full bg-white rounded-2xl border border-line shadow-sm p-4 sm:p-5">
            <div class="flex items-start justify-between flex-wrap gap-3 mb-4">
                <div>
                    <h2 class="text-sm font-semibold text-ink tracking-wide">Filter &amp; Pencarian</h2>
                    <p class="text-sm text-ink-faint mt-1">
                        Total: <strong>{{ $totalUnits }}</strong> Dinas/Unit
                        @if ($dinasUnits->total() !== $totalUnits)
                            <span>• menampilkan {{ $dinasUnits->total() }} hasil terfilter</span>
                        @endif
                    </p>
                </div>
                <a href="{{ route('super-admin.dinas.create') }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Tambah Dinas/Unit
                </a>
            </div>

            <h2 class="sr-only">Pencarian dan filter Dinas/Unit</h2>
            <form method="GET" action="{{ route('super-admin.dinas.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3" role="search" aria-label="Pencarian dan filter Dinas/Unit">
                <div class="sm:col-span-2">
                    <label for="search" class="block text-xs font-semibold text-ink-soft mb-1.5">Cari</label>
                    <input id="search" name="search" type="text" value="{{ $search }}"
                        placeholder="Nama atau kode Dinas/Unit"
                        class="w-full px-3.5 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated">
                </div>
                <div>
                    <label for="status" class="block text-xs font-semibold text-ink-soft mb-1.5">Status</label>
                    <select id="status" name="status"
                        class="w-full px-3.5 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated">
                        <option value="">Semua</option>
                        <option value="active" @selected($statusFilter === 'active')>Aktif</option>
                        <option value="inactive" @selected($statusFilter === 'inactive')>Nonaktif</option>
                    </select>
                </div>

                <div class="sm:col-span-3 flex items-center justify-end gap-2 pt-3 border-t border-surface-sunken">
                    @if ($hasFilters)
                        <a href="{{ route('super-admin.dinas.index') }}"
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
        <h2 class="sr-only">Daftar Dinas/Unit</h2>
        <div class="w-full bg-white rounded-2xl border border-line shadow-sm overflow-hidden">
            @if ($dinasUnits->isEmpty())
                <div class="empty-state py-16 text-center">
                    <svg class="w-12 h-12 text-line-strong mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    <p class="text-sm font-semibold text-ink-soft">Belum ada Dinas/Unit.</p>
                    <p class="text-xs text-ink-faint mt-1">Tambahkan Dinas/Unit baru atau ubah filter pencarian.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <caption class="sr-only">Daftar Dinas/Unit beserta kode, status, operator, jumlah kategori, dan jumlah laporan</caption>
                        <thead class="bg-page text-ink-soft text-xs uppercase tracking-wider">
                            <tr>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold min-w-[15rem]">Nama</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Kode</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Status</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold min-w-[13rem]">Operator</th>
                                <th scope="col" class="text-center px-4 py-3.5 font-semibold whitespace-nowrap">Kategori</th>
                                <th scope="col" class="text-center px-4 py-3.5 font-semibold whitespace-nowrap">Laporan</th>
                                <th scope="col" class="text-right px-4 py-3.5 font-semibold whitespace-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-sunken">
                            @foreach ($dinasUnits as $unit)
                                <tr class="hover:bg-page transition-colors">
                                    <td class="px-4 py-4">
                                        <span class="font-semibold text-ink">{{ $unit->name }}</span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        @if ($unit->code)
                                            <span class="font-mono text-xs text-ink-soft bg-page px-2 py-1 rounded-lg border border-line">{{ $unit->code }}</span>
                                        @else
                                            <span class="text-xs text-ink-faint">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        @if ($unit->is_active)
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
                                    <td class="px-4 py-4">
                                        @if ($unit->operator)
                                            <span class="block text-sm font-semibold text-ink">{{ $unit->operator->name }}</span>
                                            <span class="block text-xs text-ink-faint mt-0.5 truncate">{{ $unit->operator->email }}</span>
                                        @else
                                            <span class="text-xs text-ink-faint italic">Belum ada</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-center whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-page border border-line px-2.5 py-0.5 text-xs font-semibold text-ink-body">
                                            <svg class="w-3 h-3 text-ink-faint" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5a2 2 0 011.447.6l8.553 8.553a2 2 0 010 2.828l-5.553 5.553a2 2 0 01-2.828 0L5.6 12.447A2 2 0 015 11V6a2 2 0 012-2z"/></svg>
                                            {{ $unit->categories_count }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-center whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-violet-50 border border-violet-100 px-2.5 py-0.5 text-xs font-semibold text-violet-700">
                                            <svg class="w-3 h-3 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            {{ $unit->complaints_count }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-right whitespace-nowrap">
                                        <a href="{{ route('super-admin.dinas.edit', $unit) }}"
                                           aria-label="Ubah Dinas/Unit {{ $unit->name }}" title="Ubah"
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

                @if ($dinasUnits->hasPages())
                    <div class="px-5 sm:px-6 py-4 border-t border-line bg-surface">
                        {{ $dinasUnits->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-layouts.dashboard>
