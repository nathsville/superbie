@php $title = 'Dashboard Monitoring'; @endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @php $route = request()->routeIs('admin.*') ? request()->route()->getName() : ''; @endphp
        <div class="space-y-1">
            <a href="{{ route('admin.dashboard') }}" class="nav-link {{ $route === 'admin.dashboard' ? 'active' : '' }}"
               @if($route === 'admin.dashboard') aria-current="page" @endif>
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Monitoring
            </a>
            <a href="{{ route('admin.complaints') }}" class="nav-link {{ $route === 'admin.complaints' ? 'active' : '' }}"
               @if($route === 'admin.complaints') aria-current="page" @endif>
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Semua Laporan
            </a>
            <a href="{{ route('admin.audit-log') }}" class="nav-link {{ $route === 'admin.audit-log' ? 'active' : '' }}"
               @if($route === 'admin.audit-log') aria-current="page" @endif>
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                Audit Log
            </a>
        </div>
        {{-- Role notice --}}
        <div class="mt-6 px-3">
            <div class="rounded-xl bg-blue-50 border border-blue-200 p-3">
                <p class="text-[10px] font-bold text-blue-900 uppercase tracking-widest mb-1">Akses Monitoring</p>
                <p class="text-xs text-blue-800 leading-relaxed">
                    Peran Admin hanya memiliki akses pemantauan. Pengelolaan operasional dan sistem dilakukan oleh Operator dan Super Admin.
                </p>
            </div>
        </div>
    </x-slot:navigation>

    <x-slot:header>Monitoring Sistem</x-slot:header>
    <x-slot:breadcrumb>Panel Admin — Pemantauan Laporan & Audit</x-slot:breadcrumb>

    {{-- Welcome --}}
    <div class="mb-6 card-enter">
        <p class="text-[#475569]">Selamat datang, <strong class="text-[#0F172A]">{{ $user->name }}</strong>. Panel ini menyediakan pemantauan metrik secara real-time.</p>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8" role="list" aria-label="Metrik pemantauan">
        <div class="stat-card card-enter stagger-item" role="listitem">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-[#475569] uppercase tracking-wider">Total Laporan</span>
                <div class="w-9 h-9 rounded-xl bg-[#DBEAFE] flex items-center justify-center">
                    <svg class="w-5 h-5 text-[#2563EB]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-[#0F172A]">{{ $totalLaporan }}</p>
            <p class="text-sm text-[#475569] mt-1">sepanjang waktu</p>
        </div>

        <div class="stat-card card-enter stagger-item" role="listitem">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-[#475569] uppercase tracking-wider">Laporan Aktif</span>
                <div class="w-9 h-9 rounded-xl bg-yellow-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-yellow-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-[#0F172A]">{{ $laporanAktif }}</p>
            <p class="text-sm text-[#475569] mt-1">dalam penanganan</p>
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
            <p class="text-3xl font-bold text-[#0F172A]">{{ $laporanSelesai }}</p>
            <p class="text-sm text-[#475569] mt-1">laporan terselesaikan</p>
        </div>

        <div class="stat-card card-enter stagger-item" role="listitem">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-[#475569] uppercase tracking-wider">Aktivitas Hari Ini</span>
                <div class="w-9 h-9 rounded-xl bg-purple-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-[#0F172A]">{{ $auditHariIni }}</p>
            <p class="text-sm text-[#475569] mt-1">tindakan audit tercatat</p>
        </div>
    </div>

    {{-- Main content grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Status Breakdown --}}
        <div class="bg-white rounded-2xl border border-[#E2E8F0] p-6 card-enter">
            <h2 class="text-base font-semibold text-[#0F172A] mb-4">Distribusi Status</h2>
            <div class="space-y-3">
                @foreach ($statusBreakdown as $key => $data)
                    <div class="flex items-center justify-between">
                        <span class="status-badge {{ $data['badge'] }}">
                            <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                            {{ $data['label'] }}
                        </span>
                        <span class="text-sm font-bold text-[#0F172A]">{{ $data['count'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Recent Complaints Monitor --}}
        <div class="lg:col-span-2 bg-white rounded-2xl border border-[#E2E8F0] overflow-hidden card-enter">
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#E2E8F0]">
                <div>
                    <h2 class="text-base font-semibold text-[#0F172A]">Laporan Masuk Terkini</h2>
                    <p class="text-xs text-[#475569]">Mode pemantauan — tidak ada tindakan pengelolaan</p>
                </div>
                <a href="{{ route('admin.complaints') }}" class="text-sm text-[#2563EB] font-medium hover:text-[#1D4ED8] ui-animated">
                    Lihat semua →
                </a>
            </div>

            @if ($laporanTerbaru->isEmpty())
                <div class="empty-state">
                    <p class="text-sm text-[#475569]">Belum ada laporan dalam sistem.</p>
                </div>
            @else
                <div class="divide-y divide-[#E2E8F0]">
                    @foreach ($laporanTerbaru as $laporan)
                        <div class="flex items-start gap-4 px-6 py-4 table-row-hover">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1 flex-wrap">
                                    <span class="text-xs font-mono text-[#475569]">{{ $laporan->reference_code }}</span>
                                    @php $status = $laporan->status; @endphp
                                    <span class="status-badge {{ $status->tailwindBadge() }}">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                        {{ $status->label() }}
                                    </span>
                                </div>
                                <p class="text-sm font-semibold text-[#0F172A] truncate">{{ $laporan->title }}</p>
                                <p class="text-xs text-[#475569] mt-0.5">
                                    Kategori: {{ $laporan->category?->name ?? '—' }} •
                                    {{ $laporan->submitted_at->translatedFormat('d M Y H:i') }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-layouts.dashboard>
