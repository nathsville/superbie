@php $title = 'Riwayat Laporan'; @endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @php $route = request()->routeIs('citizen.*') ? request()->route()->getName() : ''; @endphp
        <div class="space-y-1">
            <a href="{{ route('citizen.dashboard') }}" class="nav-link {{ $route === 'citizen.dashboard' ? 'active' : '' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>
            <a href="{{ route('citizen.complaint.create') }}" class="nav-link {{ $route === 'citizen.complaint.create' ? 'active' : '' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Buat Laporan
            </a>
            <a href="{{ route('citizen.history') }}" class="nav-link {{ $route === 'citizen.history' ? 'active' : '' }}" aria-current="page">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Riwayat Laporan
            </a>
            <a href="{{ route('citizen.profile.edit') }}" class="nav-link {{ $route === 'citizen.profile.edit' ? 'active' : '' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                Profil Saya
            </a>
        </div>
    </x-slot:navigation>

    <x-slot:header>Riwayat Laporan Saya</x-slot:header>
    <x-slot:breadcrumb>Panel Masyarakat — Semua laporan yang pernah Anda kirim</x-slot:breadcrumb>

    <x-slot:headerActions>
        <a href="{{ route('citizen.complaint.create') }}" class="inline-flex items-center gap-2 bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-sm font-semibold px-4 py-2.5 rounded-xl ui-animated shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Buat Laporan Baru
        </a>
    </x-slot:headerActions>

    <div class="bg-white rounded-2xl border border-[#E2E8F0] overflow-hidden card-enter">
        @if ($laporan->isEmpty())
            <div class="empty-state">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-[#DBEAFE] flex items-center justify-center">
                    <svg class="w-8 h-8 text-[#2563EB]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-[#0F172A] mb-1">Belum Ada Riwayat</h3>
                <p class="text-sm text-[#475569] mb-4">Laporan yang Anda kirimkan akan tercatat di sini.</p>
                <a href="{{ route('citizen.complaint.create') }}" class="inline-flex items-center gap-2 bg-[#2563EB] text-white text-sm font-semibold px-4 py-2.5 rounded-xl ui-animated hover:bg-[#1D4ED8]">
                    Buat Laporan Sekarang
                </a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm" aria-label="Riwayat laporan saya">
                    <thead>
                        <tr class="bg-[#F8FAFC] border-b border-[#E2E8F0]">
                            <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#475569] uppercase tracking-wider">No. Referensi</th>
                            <th class="text-left px-4 py-3.5 text-xs font-semibold text-[#475569] uppercase tracking-wider">Judul Laporan</th>
                            <th class="text-left px-4 py-3.5 text-xs font-semibold text-[#475569] uppercase tracking-wider hidden sm:table-cell">Kategori</th>
                            <th class="text-left px-4 py-3.5 text-xs font-semibold text-[#475569] uppercase tracking-wider">Status</th>
                            <th class="text-left px-4 py-3.5 text-xs font-semibold text-[#475569] uppercase tracking-wider hidden md:table-cell">Tanggal</th>
                            <th class="px-4 py-3.5"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E8F0]">
                        @foreach ($laporan as $item)
                            <tr class="table-row-hover">
                                <td class="px-6 py-4 font-mono text-xs text-[#475569] font-medium">
                                    {{ $item->reference_code }}
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-[#0F172A] max-w-xs truncate">{{ $item->title }}</p>
                                    @if ($item->location_text)
                                        <p class="text-xs text-[#475569] mt-0.5 truncate">{{ $item->location_text }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4 hidden sm:table-cell text-[#475569]">
                                    {{ $item->category?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-4">
                                    @php $status = $item->status; @endphp
                                    <span class="status-badge {{ $status->tailwindBadge() }}">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                        {{ $status->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 hidden md:table-cell text-xs text-[#475569]">
                                    {{ $item->submitted_at?->translatedFormat('d M Y') ?? '—' }}
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <a href="{{ route('citizen.complaint.show', $item) }}" class="text-xs font-medium text-[#2563EB] hover:text-[#1D4ED8] ui-animated">
                                        Detail →
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($laporan->hasPages())
                <div class="px-6 py-4 border-t border-[#E2E8F0]">
                    {{ $laporan->links() }}
                </div>
            @endif
        @endif
    </div>
</x-layouts.dashboard>
