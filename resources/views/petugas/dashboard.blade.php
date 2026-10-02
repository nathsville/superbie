@php $title = 'Dashboard Petugas'; @endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @php $route = request()->routeIs('petugas.*') ? request()->route()->getName() : ''; @endphp
        <div class="space-y-1">
            <a href="{{ route('petugas.dashboard') }}" class="nav-link {{ $route === 'petugas.dashboard' ? 'active' : '' }}"
               @if($route === 'petugas.dashboard') aria-current="page" @endif>
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>
        </div>
        <div class="mt-6 px-3">
            <p class="text-[10px] font-semibold text-[#475569] uppercase tracking-widest mb-2">Info</p>
            <p class="text-xs text-[#475569]">Anda hanya dapat melihat laporan yang ditugaskan kepada Anda.</p>
        </div>
    </x-slot:navigation>

    <x-slot:header>Dashboard Petugas</x-slot:header>
    <x-slot:breadcrumb>Panel Petugas — Laporan yang ditugaskan</x-slot:breadcrumb>

    {{-- Welcome --}}
    <div class="mb-6 card-enter">
        <p class="text-[#475569]">Selamat datang, <strong class="text-[#0F172A]">{{ $user->name }}</strong>. Berikut laporan yang ditugaskan kepada Anda.</p>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8" role="list" aria-label="Ringkasan tugas">
        <div class="stat-card card-enter stagger-item" role="listitem">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-[#475569] uppercase tracking-wider">Ditugaskan</span>
                <div class="w-9 h-9 rounded-xl bg-[#DBEAFE] flex items-center justify-center">
                    <svg class="w-5 h-5 text-[#2563EB]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-[#0F172A]">{{ $totalDitugaskan }}</p>
            <p class="text-sm text-[#475569] mt-1">total laporan</p>
        </div>

        <div class="stat-card card-enter stagger-item" role="listitem">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-[#475569] uppercase tracking-wider">Perlu Ditangani</span>
                <div class="w-9 h-9 rounded-xl bg-yellow-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-yellow-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-[#0F172A]">{{ $perluDitangani }}</p>
            <p class="text-sm text-[#475569] mt-1">menunggu tindakan</p>
        </div>

        <div class="stat-card card-enter stagger-item" role="listitem">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-[#475569] uppercase tracking-wider">Selesai</span>
                <div class="w-9 h-9 rounded-xl bg-green-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-[#0F172A]">{{ $selesai }}</p>
            <p class="text-sm text-[#475569] mt-1">telah diselesaikan</p>
        </div>
    </div>

    {{-- Complaints table --}}
    <div class="bg-white rounded-2xl border border-[#E2E8F0] overflow-hidden card-enter">
        <div class="px-6 py-4 border-b border-[#E2E8F0]">
            <h2 class="text-base font-semibold text-[#0F172A]">Laporan yang Ditugaskan</h2>
            <p class="text-xs text-[#475569] mt-0.5">Hanya menampilkan laporan yang ditugaskan kepada Anda</p>
        </div>

        @if ($laporanTerbaru->isEmpty())
            <div class="empty-state">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-green-100 flex items-center justify-center">
                    <svg class="w-8 h-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-[#0F172A] mb-1">Tidak ada tugas aktif</h3>
                <p class="text-sm text-[#475569]">Belum ada laporan yang ditugaskan kepada Anda saat ini.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm" aria-label="Daftar laporan yang ditugaskan">
                    <thead>
                        <tr class="bg-[#F8FAFC] border-b border-[#E2E8F0]">
                            <th class="text-left px-6 py-3 text-xs font-semibold text-[#475569] uppercase tracking-wider">Laporan</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-[#475569] uppercase tracking-wider hidden sm:table-cell">Kategori</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-[#475569] uppercase tracking-wider">Status</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-[#475569] uppercase tracking-wider hidden md:table-cell">Tanggal</th>
                            <th class="px-4 py-3"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E8F0]">
                        @foreach ($laporanTerbaru as $laporan)
                            <tr class="table-row-hover">
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-[#0F172A] truncate max-w-[200px]">{{ $laporan->title }}</p>
                                    <p class="text-xs text-[#475569] font-mono mt-0.5">{{ $laporan->reference_code }}</p>
                                </td>
                                <td class="px-4 py-4 hidden sm:table-cell">
                                    <span class="text-[#475569]">{{ $laporan->category?->name ?? '—' }}</span>
                                </td>
                                <td class="px-4 py-4">
                                    @php $status = $laporan->status; @endphp
                                    <span class="status-badge {{ $status->tailwindBadge() }}">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                        {{ $status->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 hidden md:table-cell text-[#475569] text-xs">
                                    {{ $laporan->submitted_at->translatedFormat('d M Y') }}
                                </td>
                                <td class="px-4 py-4">
                                    <a
                                        href="{{ route('petugas.complaint.show', $laporan) }}"
                                        class="text-xs font-medium text-[#2563EB] hover:text-[#1D4ED8] ui-animated"
                                    >
                                        Detail →
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.dashboard>
