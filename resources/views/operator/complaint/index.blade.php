@php
    $title = 'Kelola Laporan';
    $currentRoute = request()->routeIs('operator.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        <div class="space-y-1">
            <a href="{{ route('operator.dashboard') }}"
               class="nav-link {{ $currentRoute === 'operator.dashboard' ? 'active' : '' }}"
               @if($currentRoute === 'operator.dashboard') aria-current="page" @endif>
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>
            <a href="{{ route('operator.complaint.index') }}"
               class="nav-link {{ str_starts_with($currentRoute, 'operator.complaint') ? 'active' : '' }}"
               @if(str_starts_with($currentRoute, 'operator.complaint')) aria-current="page" @endif>
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Kelola Laporan
            </a>
        </div>
    </x-slot:navigation>

    <x-slot:header>Kelola Laporan</x-slot:header>
    <x-slot:breadcrumb>Panel Operator — Daftar dan Tindak Lanjut Laporan</x-slot:breadcrumb>

    {{-- Flash messages --}}
    @if (session('success'))
        <div class="mb-4 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('success') }}
        </div>
    @endif

    {{-- Search & Filter Form --}}
    <div class="bg-white rounded-2xl border border-[#E2E8F0] p-4 mb-6 card-enter">
        <form method="GET" action="{{ route('operator.complaint.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-3" role="search">
            {{-- Search --}}
            <div class="xl:col-span-2">
                <label for="search" class="block text-xs font-semibold text-[#475569] mb-1">Cari Laporan</label>
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#94A3B8]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input
                        id="search"
                        type="search"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Judul atau nomor registrasi..."
                        class="w-full pl-9 pr-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB] ui-animated"
                    >
                </div>
            </div>

            {{-- Status filter --}}
            <div>
                <label for="filter-status" class="block text-xs font-semibold text-[#475569] mb-1">Status</label>
                <select id="filter-status" name="status"
                    class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB] ui-animated">
                    <option value="">Semua Status</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}" @selected(request('status') === $s->value)>
                            {{ $s->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Category filter --}}
            <div>
                <label for="filter-category" class="block text-xs font-semibold text-[#475569] mb-1">Kategori</label>
                <select id="filter-category" name="category_id"
                    class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB] ui-animated">
                    <option value="">Semua Kategori</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Assignment filter + button --}}
            <div class="flex flex-col gap-1">
                <label for="filter-assignment" class="block text-xs font-semibold text-[#475569]">Penugasan</label>
                <div class="flex gap-2">
                    <select id="filter-assignment" name="assignment"
                        class="flex-1 px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB] ui-animated">
                        <option value="">Semua</option>
                        <option value="mine" @selected(request('assignment') === 'mine')>Ditugaskan ke Saya</option>
                        <option value="unassigned" @selected(request('assignment') === 'unassigned')>Belum Ditugaskan</option>
                    </select>
                    <button type="submit"
                        class="shrink-0 inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-sm font-semibold rounded-xl ui-animated shadow-sm"
                        aria-label="Terapkan filter">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                        Filter
                    </button>
                </div>
            </div>

            {{-- Reset --}}
            @if (request()->anyFilled(['search', 'status', 'category_id', 'assignment']))
                <div class="sm:col-span-2 lg:col-span-4 xl:col-span-5 flex justify-end">
                    <a href="{{ route('operator.complaint.index') }}"
                       class="inline-flex items-center gap-1 text-xs text-[#64748B] hover:text-[#2563EB] ui-animated">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Reset filter
                    </a>
                </div>
            @endif
        </form>
    </div>

    {{-- Complaint table --}}
    <div class="bg-white rounded-2xl border border-[#E2E8F0] overflow-hidden card-enter">
        <div class="flex items-center justify-between px-6 py-4 border-b border-[#E2E8F0]">
            <div>
                <h2 class="text-base font-semibold text-[#0F172A]">Daftar Laporan</h2>
                <p class="text-xs text-[#64748B] mt-0.5">Total: {{ $complaints->total() }} laporan</p>
            </div>
        </div>

        @if ($complaints->isEmpty())
            <div class="empty-state py-16">
                <svg class="w-12 h-12 text-[#CBD5E1] mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <p class="text-sm font-semibold text-[#475569]">Tidak ada laporan ditemukan</p>
                <p class="text-xs text-[#94A3B8] mt-1">Coba ubah filter atau kata kunci pencarian.</p>
            </div>
        @else
            {{-- Desktop Table --}}
            <div class="overflow-x-auto hidden md:block">
                <table class="w-full text-sm" aria-label="Daftar laporan">
                    <thead>
                        <tr class="bg-[#F8FAFC] border-b border-[#E2E8F0]">
                            <th class="px-6 py-3 text-left text-xs font-semibold text-[#475569] uppercase tracking-wider whitespace-nowrap">Nomor Registrasi</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-[#475569] uppercase tracking-wider">Judul / Kategori</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-[#475569] uppercase tracking-wider whitespace-nowrap">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-[#475569] uppercase tracking-wider whitespace-nowrap">Operator</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-[#475569] uppercase tracking-wider whitespace-nowrap">Tanggal Kirim</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-[#475569] uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#F1F5F9]">
                        @foreach ($complaints as $laporan)
                            @php $status = $laporan->status; @endphp
                            <tr class="table-row-hover group">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="font-mono text-xs text-[#475569] bg-[#F8FAFC] px-2 py-1 rounded-lg border border-[#E2E8F0]">
                                        {{ $laporan->reference_code }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-[#0F172A] group-hover:text-[#2563EB] ui-animated line-clamp-1">
                                        {{ $laporan->title }}
                                    </p>
                                    <p class="text-xs text-[#64748B] mt-0.5">
                                        {{ $laporan->category?->name ?? '—' }}
                                    </p>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="status-badge {{ $status->tailwindBadge() }}">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                        {{ $status->label() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-xs text-[#475569]">
                                        {{ $laporan->assignee?->name ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-[#64748B]">
                                    {{ $laporan->submitted_at?->translatedFormat('d M Y') ?? '—' }}
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <a href="{{ route('operator.complaint.show', $laporan) }}"
                                       class="inline-flex items-center gap-1 text-xs font-semibold text-[#2563EB] hover:text-[#1D4ED8] ui-animated">
                                        Buka
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile Card List --}}
            <div class="divide-y divide-[#F1F5F9] md:hidden">
                @foreach ($complaints as $laporan)
                    @php $status = $laporan->status; @endphp
                    <a href="{{ route('operator.complaint.show', $laporan) }}"
                       class="flex items-start gap-4 px-4 py-4 table-row-hover group">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <span class="font-mono text-[11px] text-[#64748B]">{{ $laporan->reference_code }}</span>
                                <span class="status-badge {{ $status->tailwindBadge() }}">
                                    <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                    {{ $status->label() }}
                                </span>
                            </div>
                            <p class="text-sm font-semibold text-[#0F172A] group-hover:text-[#2563EB] ui-animated line-clamp-2">
                                {{ $laporan->title }}
                            </p>
                            <p class="text-xs text-[#64748B] mt-0.5">
                                {{ $laporan->category?->name ?? '—' }} •
                                {{ $laporan->submitted_at?->translatedFormat('d M Y') ?? '—' }}
                            </p>
                        </div>
                        <svg class="w-4 h-4 text-[#CBD5E1] shrink-0 mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Pagination --}}
    @if ($complaints->hasPages())
        <div class="mt-4 flex justify-center">
            {{ $complaints->links() }}
        </div>
    @endif
</x-layouts.dashboard>
