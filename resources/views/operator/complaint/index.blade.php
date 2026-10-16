@php
    $title = 'Kelola Laporan';
    $currentRoute = request()->routeIs('operator.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        <x-dashboard.nav-rail-link :href="route('operator.dashboard')" label="Dashboard" :active="$currentRoute === 'operator.dashboard'">
            <x-slot:icon>
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            </x-slot:icon>
        </x-dashboard.nav-rail-link>
        <x-dashboard.nav-rail-link :href="route('operator.complaint.index')" label="Kelola Laporan" :active="str_starts_with($currentRoute, 'operator.complaint')">
            <x-slot:icon>
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </x-slot:icon>
        </x-dashboard.nav-rail-link>
    </x-slot:navigation>

    <x-slot:header>Kelola Laporan</x-slot:header>
    <x-slot:breadcrumb>Panel Operator — Daftar dan Tindak Lanjut Laporan</x-slot:breadcrumb>

    <div class="w-full max-w-full space-y-5 card-enter">
        {{-- Flash messages --}}
        @if (session('success'))
            <div class="w-full flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('success') }}
            </div>
        @endif

        {{-- ── Filter bar (full width, grid 4 kolom) ─────────────────────────── --}}
        <div class="w-full bg-white rounded-2xl border border-line shadow-sm p-4 sm:p-5">
            <h2 class="sr-only">Filter daftar laporan</h2>
            <form method="GET" action="{{ route('operator.complaint.index') }}"
                  class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_auto] lg:items-end"
                  role="search" aria-label="Filter daftar laporan">
                {{-- Kolom 1 (dominan): Cari Laporan --}}
                <div class="sm:col-span-2 lg:col-span-1">
                    <label for="search" class="block text-xs font-semibold text-ink-soft mb-1.5">Cari Laporan</label>
                    <div class="relative">
                        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-ink-faint" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input
                            id="search"
                            type="search"
                            name="search"
                            value="{{ $filters['search'] }}"
                            placeholder="Judul atau nomor registrasi..."
                            class="w-full pl-10 pr-4 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated"
                        >
                    </div>
                </div>

                {{-- Kolom 2: Status --}}
                <div>
                    <label for="filter-status" class="block text-xs font-semibold text-ink-soft mb-1.5">Status</label>
                    <select id="filter-status" name="status"
                        class="w-full px-4 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated">
                        <option value="">Semua Status</option>
                        {{-- The filter lists only the ACTIVE workflow statuses directly
                             (no group header). Legacy (history-only) statuses are NOT
                             listed here; the backend still accepts their values via the
                             `status` query string so any existing deep link keeps
                             working. Single source of truth = ComplaintStatus. --}}
                        @foreach (array_merge(\App\Enums\ComplaintStatus::activeCases(), \App\Enums\ComplaintStatus::finalOutcomeCases()) as $s)
                            <option value="{{ $s->value }}" @selected($filters['status'] === $s->value)>
                                {{ $s->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Kolom 3: Kategori --}}
                <div>
                    <label for="filter-category" class="block text-xs font-semibold text-ink-soft mb-1.5">Kategori</label>
                    <select id="filter-category" name="category_id"
                        class="w-full px-4 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated">
                        <option value="">Semua Kategori</option>
                        {{-- Options are SCOPED to the viewer by the controller:
                             an Operator sees ONLY the categories mapped to their own
                             Dinas/Unit (a unit-less Operator sees none), while a Super
                             Admin sees the full global list. "Semua Kategori" therefore
                             means "all categories within this viewer's scope". --}}
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" @selected($filters['category_id'] === $cat->id)>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Kolom 4: Tombol Filter --}}
                {{-- PHASE 6C — Decision 9: the assignment filter (mine / unassigned)
                     was REMOVED. An Operator is scoped by Dinas/Unit; the legacy
                     `assigned_to` column is deprecated and never written. --}}
                <div>
                    <button type="submit"
                        class="w-full lg:w-auto inline-flex items-center justify-center gap-1.5 px-6 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm whitespace-nowrap"
                        aria-label="Terapkan filter">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                        Filter
                    </button>
                </div>

                {{-- Reset --}}
                @if (request()->anyFilled(['search', 'status', 'category_id']))
                    <div class="sm:col-span-2 lg:col-span-4 flex justify-end">
                        <a href="{{ route('operator.complaint.index') }}"
                           class="inline-flex items-center gap-1 text-xs font-semibold text-ink-faint hover:text-primary-600 ui-animated">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            Reset filter
                        </a>
                    </div>
                @endif
            </form>
        </div>

        {{-- ── Tabel daftar laporan (full width) ────────────────────────────── --}}
        <div class="w-full bg-white rounded-2xl border border-line shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 sm:px-6 py-4 border-b border-line">
                <div class="flex items-center gap-2.5">
                    <span class="h-5 w-1.5 rounded-full bg-primary-600" aria-hidden="true"></span>
                    <div>
                        <h2 class="text-base font-semibold text-ink">Daftar Laporan</h2>
                        <p class="text-xs text-ink-faint mt-0.5">Total: {{ $complaints->total() }} laporan</p>
                    </div>
                </div>
            </div>

            @if ($complaints->isEmpty())
                <div class="empty-state py-16">
                    <svg class="w-12 h-12 text-line-strong mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <p class="text-sm font-semibold text-ink-soft">Tidak ada laporan ditemukan</p>
                    <p class="text-xs text-ink-faint mt-1">Coba ubah filter atau kata kunci pencarian.</p>
                </div>
            @else
                {{-- Desktop Table --}}
                <div class="overflow-x-auto hidden md:block">
                    <table class="w-full text-sm">
                        <caption class="sr-only">Daftar laporan yang masuk beserta nomor registrasi, kategori, status, dinas/unit, tanggal kirim, dan tautan tindak lanjut</caption>
                        <thead class="bg-page text-ink-soft text-xs uppercase tracking-wider">
                            <tr>
                                <th scope="col" class="px-4 sm:px-6 py-3.5 text-left font-semibold whitespace-nowrap">Nomor Registrasi</th>
                                <th scope="col" class="px-4 py-3.5 text-left font-semibold min-w-[16rem]">Judul / Kategori</th>
                                <th scope="col" class="px-4 py-3.5 text-left font-semibold whitespace-nowrap">Status</th>
                                <th scope="col" class="px-4 py-3.5 text-left font-semibold whitespace-nowrap">Dinas/Unit</th>
                                <th scope="col" class="px-4 py-3.5 text-left font-semibold whitespace-nowrap">Tanggal Kirim</th>
                                <th scope="col" class="px-4 sm:px-6 py-3.5 text-right font-semibold whitespace-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-sunken">
                            @foreach ($complaints as $laporan)
                                @php $status = $laporan->status; @endphp
                                <tr class="hover:bg-page transition-colors group">
                                    <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="font-mono text-xs font-semibold text-ink-body bg-page px-2.5 py-1 rounded-lg border border-line">
                                            {{ $laporan->reference_code }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="font-semibold text-ink group-hover:text-primary-600 ui-animated line-clamp-1">
                                            {{ $laporan->title }}
                                        </p>
                                        <p class="text-xs text-ink-faint mt-0.5 truncate">
                                            {{ $laporan->category?->name ?? '—' }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="status-badge {{ $status->tailwindBadge() }}">
                                            <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                            {{ $status->label() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="text-xs text-ink-soft">
                                            {{ $laporan->dinasUnit?->name ?? 'Belum ditentukan' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="block text-xs font-medium text-ink-body">{{ $laporan->submitted_at?->translatedFormat('d M Y') ?? '—' }}</span>
                                        @if ($laporan->submitted_at)
                                            <span class="block text-[11px] text-ink-faint">{{ $laporan->submitted_at->translatedFormat('H:i') }} WITA</span>
                                        @endif
                                    </td>
                                    <td class="px-4 sm:px-6 py-4 text-right whitespace-nowrap">
                                        <a href="{{ route('operator.complaint.show', $laporan) }}"
                                           aria-label="Buka laporan {{ $laporan->reference_code }}"
                                           class="inline-flex items-center gap-1 rounded-lg bg-primary-50 px-3 py-1.5 text-xs font-semibold text-primary-600 hover:bg-primary-100 ui-animated">
                                            Buka
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile Card List --}}
                <div class="divide-y divide-surface-sunken md:hidden">
                    @foreach ($complaints as $laporan)
                        @php $status = $laporan->status; @endphp
                        <a href="{{ route('operator.complaint.show', $laporan) }}"
                           class="flex items-start gap-4 px-4 py-4 hover:bg-page transition-colors group">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap mb-1.5">
                                    <span class="font-mono text-[11px] font-semibold text-ink-body bg-page px-2 py-0.5 rounded-lg border border-line">{{ $laporan->reference_code }}</span>
                                    <span class="status-badge {{ $status->tailwindBadge() }}">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                        {{ $status->label() }}
                                    </span>
                                </div>
                                <p class="text-sm font-semibold text-ink group-hover:text-primary-600 ui-animated line-clamp-2">
                                    {{ $laporan->title }}
                                </p>
                                <p class="text-xs text-ink-faint mt-0.5">
                                    {{ $laporan->category?->name ?? '—' }} •
                                    {{ $laporan->submitted_at?->translatedFormat('d M Y') ?? '—' }}
                                </p>
                            </div>
                            <svg class="w-4 h-4 text-line-strong shrink-0 mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    @endforeach
                </div>

                {{-- Pagination footer (kiri: ringkasan, kanan: tombol halaman) --}}
                @if ($complaints->hasPages())
                    <div class="px-5 sm:px-6 py-4 border-t border-line bg-surface">
                        {{ $complaints->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-layouts.dashboard>
