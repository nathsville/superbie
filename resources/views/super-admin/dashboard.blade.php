@php $title = 'Command Center — Super Admin'; @endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @php $route = request()->routeIs('super-admin.*') ? request()->route()->getName() : ''; @endphp
        <div class="space-y-1">
            <a href="{{ route('super-admin.dashboard') }}" class="nav-link {{ $route === 'super-admin.dashboard' ? 'active' : '' }}"
               @if($route === 'super-admin.dashboard') aria-current="page" @endif>
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>
            <a href="{{ route('super-admin.complaint.index') }}" class="nav-link {{ str_starts_with($route, 'super-admin.complaint') ? 'active' : '' }}"
               @if(str_starts_with($route, 'super-admin.complaint')) aria-current="page" @endif>
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Semua Laporan
            </a>
            <a href="{{ route('super-admin.user.index') }}" class="nav-link {{ str_starts_with($route, 'super-admin.user') ? 'active' : '' }}"
               @if(str_starts_with($route, 'super-admin.user')) aria-current="page" @endif>
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                Kelola Pengguna
            </a>
            <a href="{{ route('super-admin.kategori.index') }}" class="nav-link {{ str_starts_with($route, 'super-admin.kategori') ? 'active' : '' }}"
               @if(str_starts_with($route, 'super-admin.kategori')) aria-current="page" @endif>
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                </svg>
                Kategori & Dinas
            </a>
            <a href="{{ route('super-admin.config.index') }}" class="nav-link {{ str_starts_with($route, 'super-admin.config') ? 'active' : '' }}"
               @if(str_starts_with($route, 'super-admin.config')) aria-current="page" @endif>
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Konfigurasi
            </a>
            <a href="{{ route('super-admin.audit.index') }}" class="nav-link {{ str_starts_with($route, 'super-admin.audit') ? 'active' : '' }}"
               @if(str_starts_with($route, 'super-admin.audit')) aria-current="page" @endif>
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                Audit & Keamanan
            </a>
        </div>
    </x-slot:navigation>

    <x-slot:header>Command Center — Super Admin</x-slot:header>
    <x-slot:breadcrumb>Panel Pengelolaan Penuh Sistem</x-slot:breadcrumb>

    {{-- Welcome banner --}}
    <div class="mb-6 rounded-2xl bg-gradient-to-r from-[#1D4ED8] to-[#0F766E] p-6 text-white card-enter">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-white/20 text-white mb-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    Super Admin • Akses Penuh
                </span>
                <h2 class="text-xl font-bold text-white">Selamat Datang, {{ $user->name }}</h2>
                <p class="text-blue-100 text-sm mt-1">Kelola seluruh sistem Lapor Pak Wali dari satu dashboard terpusat.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('super-admin.user.create') }}" class="inline-flex items-center gap-2 bg-white text-[#1D4ED8] font-semibold text-xs px-4 py-2.5 rounded-xl hover:bg-blue-50 ui-animated shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    Tambah Akun
                </a>
            </div>
        </div>
    </div>

    {{-- System Stat Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8" role="list" aria-label="Statistik sistem">
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
            <p class="text-xs text-[#475569] mt-1">{{ $laporanAktif }} aktif • {{ $laporanSelesai }} selesai</p>
        </div>

        <div class="stat-card card-enter stagger-item" role="listitem">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-[#475569] uppercase tracking-wider">Total Pengguna</span>
                <div class="w-9 h-9 rounded-xl bg-[#CCFBF1] flex items-center justify-center">
                    <svg class="w-5 h-5 text-[#0F766E]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-[#0F172A]">{{ $totalPengguna }}</p>
            <p class="text-xs text-[#475569] mt-1">+{{ $penggunaBaru }} hari ini</p>
        </div>

        <div class="stat-card card-enter stagger-item" role="listitem">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-[#475569] uppercase tracking-wider">Aktivitas Audit</span>
                <div class="w-9 h-9 rounded-xl bg-purple-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-[#0F172A]">{{ $auditHariIni }}</p>
            <p class="text-xs text-[#475569] mt-1">tindakan tercatat hari ini</p>
        </div>

        <div class="stat-card card-enter stagger-item" role="listitem">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-[#475569] uppercase tracking-wider">Tingkat Selesai</span>
                <div class="w-9 h-9 rounded-xl bg-green-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            @php $rate = $totalLaporan > 0 ? round(($laporanSelesai / $totalLaporan) * 100) : 0; @endphp
            <p class="text-3xl font-bold text-[#0F172A]">{{ $rate }}%</p>
            <p class="text-xs text-[#475569] mt-1">dari total laporan masuk</p>
        </div>
    </div>

    {{-- Main Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        {{-- Status Breakdown --}}
        <div class="bg-white rounded-2xl border border-[#E2E8F0] p-6 card-enter">
            <h3 class="text-base font-semibold text-[#0F172A] mb-4">Distribusi Status Laporan</h3>
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

        {{-- User Breakdown by Role --}}
        <div class="bg-white rounded-2xl border border-[#E2E8F0] p-6 card-enter">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-semibold text-[#0F172A]">Distribusi Pengguna</h3>
                <a href="{{ route('super-admin.user.index') }}" class="text-xs text-[#2563EB] font-medium hover:underline">Kelola →</a>
            </div>
            <div class="space-y-3">
                @php
                    $roleLabels = [
                        'masyarakat'  => ['label' => 'Masyarakat', 'color' => 'bg-blue-100 text-blue-800'],
                        'petugas'     => ['label' => 'Petugas', 'color' => 'bg-indigo-100 text-indigo-800'],
                        'operator'    => ['label' => 'Operator', 'color' => 'bg-teal-100 text-teal-800'],
                        'admin'       => ['label' => 'Admin (Monitoring)', 'color' => 'bg-amber-100 text-amber-800'],
                        'super_admin' => ['label' => 'Super Admin', 'color' => 'bg-purple-100 text-purple-800'],
                    ];
                @endphp
                @foreach ($roleLabels as $roleKey => $meta)
                    <div class="flex items-center justify-between">
                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full {{ $meta['color'] }}">
                            {{ $meta['label'] }}
                        </span>
                        <span class="text-sm font-bold text-[#0F172A]">{{ $roleBreakdown[$roleKey] ?? 0 }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Quick Management Links --}}
        <div class="bg-white rounded-2xl border border-[#E2E8F0] p-6 card-enter">
            <h3 class="text-base font-semibold text-[#0F172A] mb-4">Aksi Pengelolaan Cepat</h3>
            <div class="space-y-2">
                <a href="{{ route('super-admin.complaint.index') }}" class="flex items-center justify-between p-3 rounded-xl bg-[#F8FAFC] hover:bg-[#DBEAFE] text-sm font-medium text-[#0F172A] hover:text-[#1D4ED8] ui-animated">
                    <span>Semua Laporan</span>
                    <svg class="w-4 h-4 text-[#475569]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
                <a href="{{ route('super-admin.user.index') }}" class="flex items-center justify-between p-3 rounded-xl bg-[#F8FAFC] hover:bg-[#DBEAFE] text-sm font-medium text-[#0F172A] hover:text-[#1D4ED8] ui-animated">
                    <span>Kelola Pengguna & Peran</span>
                    <svg class="w-4 h-4 text-[#475569]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
                <a href="{{ route('super-admin.kategori.index') }}" class="flex items-center justify-between p-3 rounded-xl bg-[#F8FAFC] hover:bg-[#DBEAFE] text-sm font-medium text-[#0F172A] hover:text-[#1D4ED8] ui-animated">
                    <span>Kategori & Dinas Terkait</span>
                    <svg class="w-4 h-4 text-[#475569]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
                <a href="{{ route('super-admin.config.index') }}" class="flex items-center justify-between p-3 rounded-xl bg-[#F8FAFC] hover:bg-[#DBEAFE] text-sm font-medium text-[#0F172A] hover:text-[#1D4ED8] ui-animated">
                    <span>Konfigurasi Operasional</span>
                    <svg class="w-4 h-4 text-[#475569]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
                <a href="{{ route('super-admin.audit.index') }}" class="flex items-center justify-between p-3 rounded-xl bg-[#F8FAFC] hover:bg-[#DBEAFE] text-sm font-medium text-[#0F172A] hover:text-[#1D4ED8] ui-animated">
                    <span>Audit Log & Keamanan</span>
                    <svg class="w-4 h-4 text-[#475569]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
        </div>
    </div>

    {{-- Audit Log Stream --}}
    <div class="bg-white rounded-2xl border border-[#E2E8F0] overflow-hidden card-enter">
        <div class="flex items-center justify-between px-6 py-4 border-b border-[#E2E8F0]">
            <div>
                <h3 class="text-base font-semibold text-[#0F172A]">Aktivitas Audit Terkini</h3>
                <p class="text-xs text-[#475569]">Jejak audit append-only dari tindakan staf & admin</p>
            </div>
            <a href="{{ route('super-admin.audit.index') }}" class="text-sm text-[#2563EB] font-medium hover:text-[#1D4ED8] ui-animated">Semua log →</a>
        </div>

        @if ($auditTerbaru->isEmpty())
            <div class="empty-state">
                <p class="text-sm text-[#475569]">Belum ada catatan aktivitas audit.</p>
            </div>
        @else
            <div class="divide-y divide-[#E2E8F0]">
                @foreach ($auditTerbaru as $log)
                    <div class="flex items-center justify-between px-6 py-3.5 table-row-hover text-sm">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="w-2 h-2 rounded-full bg-blue-500 shrink-0" aria-hidden="true"></span>
                            <div>
                                <span class="font-medium text-[#0F172A]">{{ $log->actor?->name ?? 'Sistem' }}</span>
                                <span class="text-[#475569] text-xs mx-1">•</span>
                                <span class="font-mono text-xs bg-slate-100 text-slate-700 px-2 py-0.5 rounded">{{ $log->action }}</span>
                            </div>
                        </div>
                        <span class="text-xs text-[#475569] shrink-0">{{ $log->created_at->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.dashboard>
