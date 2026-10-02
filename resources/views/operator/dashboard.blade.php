@php $title = 'Dashboard Operator'; @endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @php $route = request()->routeIs('operator.*') ? request()->route()->getName() : ''; @endphp
        <div class="space-y-1">
            <a href="{{ route('operator.dashboard') }}" class="nav-link {{ $route === 'operator.dashboard' ? 'active' : '' }}"
               @if($route === 'operator.dashboard') aria-current="page" @endif>
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>
            <a href="{{ route('operator.complaint.index') }}" class="nav-link {{ $route === 'operator.complaint.index' ? 'active' : '' }}"
               @if($route === 'operator.complaint.index') aria-current="page" @endif>
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Kelola Laporan
            </a>
        </div>
    </x-slot:navigation>

    <x-slot:header>Dashboard Operator</x-slot:header>
    <x-slot:breadcrumb>Panel Operator — Pengelolaan Operasional</x-slot:breadcrumb>

    {{-- Welcome --}}
    <div class="mb-6 card-enter">
        <p class="text-[#475569]">Selamat datang, <strong class="text-[#0F172A]">{{ $user->name }}</strong>. Berikut ikhtisar operasional laporan.</p>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8" role="list" aria-label="Ringkasan operasional">
        <div class="stat-card card-enter stagger-item" role="listitem">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-[#475569] uppercase tracking-wider">Total Laporan</span>
                <div class="w-9 h-9 rounded-xl bg-[#DBEAFE] flex items-center justify-center">
                    <svg class="w-5 h-5 text-[#2563EB]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-[#0F172A]">{{ $totalLaporan }}</p>
            <p class="text-sm text-[#475569] mt-1">total laporan masuk</p>
        </div>

        <div class="stat-card card-enter stagger-item" role="listitem">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-[#475569] uppercase tracking-wider">Belum Ditugaskan</span>
                <div class="w-9 h-9 rounded-xl bg-orange-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-orange-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-[#0F172A]">{{ $belumDitugaskan }}</p>
            <p class="text-sm text-[#475569] mt-1">perlu petugas</p>
        </div>

        <div class="stat-card card-enter stagger-item" role="listitem">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-[#475569] uppercase tracking-wider">Perlu Tindakan</span>
                <div class="w-9 h-9 rounded-xl bg-yellow-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-yellow-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-[#0F172A]">{{ $perluTindakan }}</p>
            <p class="text-sm text-[#475569] mt-1">menunggu review</p>
        </div>

        <div class="stat-card card-enter stagger-item" role="listitem">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-[#475569] uppercase tracking-wider">Selesai Hari Ini</span>
                <div class="w-9 h-9 rounded-xl bg-green-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-[#0F172A]">{{ $selesaiHariIni }}</p>
            <p class="text-sm text-[#475569] mt-1">diselesaikan hari ini</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Status breakdown --}}
        <div class="bg-white rounded-2xl border border-[#E2E8F0] p-6 card-enter">
            <h2 class="text-base font-semibold text-[#0F172A] mb-4">Breakdown Status</h2>
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

        {{-- Recent complaints --}}
        <div class="lg:col-span-2 bg-white rounded-2xl border border-[#E2E8F0] overflow-hidden card-enter">
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#E2E8F0]">
                <h2 class="text-base font-semibold text-[#0F172A]">Laporan Terbaru</h2>
                <a href="{{ route('operator.complaint.index') }}" class="text-sm text-[#2563EB] font-medium hover:text-[#1D4ED8] ui-animated">Kelola →</a>
            </div>

            @if ($laporanTerbaru->isEmpty())
                <div class="empty-state">
                    <p class="text-sm text-[#475569]">Belum ada laporan masuk.</p>
                </div>
            @else
                <div class="divide-y divide-[#E2E8F0]">
                    @foreach ($laporanTerbaru as $laporan)
                        <a href="{{ route('operator.complaint.show', $laporan) }}" class="flex items-start gap-4 px-6 py-4 table-row-hover group">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs font-mono text-[#475569]">{{ $laporan->reference_code }}</span>
                                    @php $status = $laporan->status; @endphp
                                    <span class="status-badge {{ $status->tailwindBadge() }}">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                        {{ $status->label() }}
                                    </span>
                                </div>
                                <p class="text-sm font-semibold text-[#0F172A] truncate group-hover:text-[#2563EB] ui-animated">{{ $laporan->title }}</p>
                                <p class="text-xs text-[#475569] mt-0.5">
                                    {{ $laporan->category?->name ?? '—' }} •
                                    Petugas: {{ $laporan->assignee?->name ?? 'Belum ditugaskan' }}
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
    </div>
</x-layouts.dashboard>
