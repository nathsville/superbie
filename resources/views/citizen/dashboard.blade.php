@php
    $title = 'Dashboard Saya';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @php $route = request()->routeIs('citizen.*') ? request()->route()->getName() : ''; @endphp
        <div class="space-y-1">
            <a href="{{ route('citizen.dashboard') }}"
               class="nav-link {{ $route === 'citizen.dashboard' ? 'active' : '' }}"
               @if($route === 'citizen.dashboard') aria-current="page" @endif>
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>
            <a href="{{ route('citizen.complaint.create') }}"
               class="nav-link {{ $route === 'citizen.complaint.create' ? 'active' : '' }}"
               @if($route === 'citizen.complaint.create') aria-current="page" @endif>
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Buat Laporan
            </a>
            <a href="{{ route('citizen.history') }}"
               class="nav-link {{ $route === 'citizen.history' ? 'active' : '' }}"
               @if($route === 'citizen.history') aria-current="page" @endif>
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Riwayat Laporan
            </a>
            <a href="{{ route('citizen.profile.edit') }}"
               class="nav-link {{ $route === 'citizen.profile.edit' ? 'active' : '' }}"
               @if($route === 'citizen.profile.edit') aria-current="page" @endif>
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                Profil Saya
            </a>
        </div>
    </x-slot:navigation>

    <x-slot:header>Dashboard Saya</x-slot:header>
    <x-slot:breadcrumb>Lapor Pak Wali — Panel Masyarakat</x-slot:breadcrumb>

    <x-slot:headerActions>
        <a
            href="{{ route('citizen.complaint.create') }}"
            class="inline-flex items-center gap-2 bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-sm font-semibold px-4 py-2.5 rounded-xl ui-animated shadow-sm hover:shadow-md active:scale-[0.98]"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Buat Laporan
        </a>
    </x-slot:headerActions>

    {{-- Welcome --}}
    <div class="mb-6 card-enter">
        <p class="text-[#475569]">Selamat datang, <strong class="text-[#0F172A]">{{ $user->name }}</strong>. Berikut ringkasan laporan Anda.</p>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8" role="list" aria-label="Ringkasan laporan">
        {{-- Total --}}
        <div class="stat-card stagger-item card-enter" role="listitem">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-[#475569] uppercase tracking-wider">Total Laporan</span>
                <div class="w-9 h-9 rounded-xl bg-[#DBEAFE] flex items-center justify-center">
                    <svg class="w-5 h-5 text-[#2563EB]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-[#0F172A]">{{ $totalLaporan }}</p>
            <p class="text-sm text-[#475569] mt-1">laporan dikirim</p>
        </div>

        {{-- In progress --}}
        <div class="stat-card stagger-item card-enter" role="listitem">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-[#475569] uppercase tracking-wider">Diproses</span>
                <div class="w-9 h-9 rounded-xl bg-yellow-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-yellow-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-[#0F172A]">{{ $laporanProses }}</p>
            <p class="text-sm text-[#475569] mt-1">sedang ditangani</p>
        </div>

        {{-- Resolved --}}
        <div class="stat-card stagger-item card-enter" role="listitem">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-[#475569] uppercase tracking-wider">Selesai</span>
                <div class="w-9 h-9 rounded-xl bg-green-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-[#0F172A]">{{ $laporanSelesai }}</p>
            <p class="text-sm text-[#475569] mt-1">telah diselesaikan</p>
        </div>

        {{-- Rejected --}}
        <div class="stat-card stagger-item card-enter" role="listitem">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-[#475569] uppercase tracking-wider">Ditolak</span>
                <div class="w-9 h-9 rounded-xl bg-red-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-[#0F172A]">{{ $laporanDitolak }}</p>
            <p class="text-sm text-[#475569] mt-1">tidak dapat diproses</p>
        </div>
    </div>

    {{-- Recent complaints --}}
    <div class="bg-white rounded-2xl border border-[#E2E8F0] overflow-hidden card-enter">
        <div class="flex items-center justify-between px-6 py-4 border-b border-[#E2E8F0]">
            <h2 class="text-base font-semibold text-[#0F172A]">Laporan Terbaru</h2>
            <a href="{{ route('citizen.history') }}" class="text-sm text-[#2563EB] font-medium hover:text-[#1D4ED8] ui-animated">
                Lihat semua →
            </a>
        </div>

        @if ($laporanTerbaru->isEmpty())
            <div class="empty-state">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-[#DBEAFE] flex items-center justify-center">
                    <svg class="w-8 h-8 text-[#2563EB]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-[#0F172A] mb-1">Belum ada laporan</h3>
                <p class="text-sm text-[#475569] mb-4">Anda belum pernah mengirim laporan.</p>
                <a href="{{ route('citizen.complaint.create') }}" class="inline-flex items-center gap-2 bg-[#2563EB] text-white text-sm font-semibold px-4 py-2.5 rounded-xl ui-animated hover:bg-[#1D4ED8]">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Buat Laporan Pertama
                </a>
            </div>
        @else
            <div class="divide-y divide-[#E2E8F0]">
                @foreach ($laporanTerbaru as $laporan)
                    <a
                        href="{{ route('citizen.complaint.show', $laporan) }}"
                        class="flex items-start gap-4 px-6 py-4 table-row-hover group"
                    >
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <span class="text-xs font-mono text-[#475569]">{{ $laporan->reference_code }}</span>
                                @php $status = $laporan->status; @endphp
                                <span class="status-badge {{ $status->tailwindBadge() }}">
                                    <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                    {{ $status->label() }}
                                </span>
                            </div>
                            <p class="text-sm font-semibold text-[#0F172A] truncate group-hover:text-[#2563EB] ui-animated">
                                {{ $laporan->title }}
                            </p>
                            <p class="text-xs text-[#475569] mt-0.5">
                                {{ $laporan->category?->name ?? '—' }} •
                                {{ $laporan->submitted_at?->translatedFormat('d M Y') ?? '—' }}
                            </p>
                        </div>
                        <svg class="w-4 h-4 text-[#475569] shrink-0 mt-1 group-hover:translate-x-0.5 ui-animated" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.dashboard>
