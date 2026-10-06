@php $title = 'Command Center — Super Admin'; @endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @php
            $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
        @endphp
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
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
                @if (\Illuminate\Support\Facades\Route::has('super-admin.users.create'))
                    <a href="{{ route('super-admin.users.create') }}" class="inline-flex items-center gap-2 bg-white text-[#1D4ED8] font-semibold text-xs px-4 py-2.5 rounded-xl hover:bg-blue-50 ui-animated shadow-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        Tambah Akun
                    </a>
                @else
                    <span class="inline-flex items-center gap-2 bg-white/20 text-white/80 font-semibold text-xs px-4 py-2.5 rounded-xl cursor-not-allowed"
                          aria-disabled="true"
                          title="Belum tersedia pada baseline ini">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        Tambah Akun
                    </span>
                @endif
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
                @if (\Illuminate\Support\Facades\Route::has('super-admin.users.index'))
                    <a href="{{ route('super-admin.users.index') }}" class="text-xs text-[#2563EB] font-medium hover:underline">Kelola →</a>
                @endif
            </div>
            <div class="space-y-3">
                @php
                    $roleLabels = [
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
            @php
                $quickLinks = [
                    ['route' => 'super-admin.complaints.index', 'label' => 'Semua Laporan'],
                    ['route' => 'super-admin.users.index',     'label' => 'Kelola Pengguna & Peran'],
                    ['route' => 'super-admin.categories.index', 'label' => 'Kategori & Dinas Terkait'],
                    ['route' => 'super-admin.config.index',    'label' => 'Konfigurasi Operasional'],
                    ['route' => 'super-admin.audit.index',     'label' => 'Audit Log & Keamanan'],
                ];
            @endphp
            <div class="space-y-2">
                @foreach ($quickLinks as $link)
                    @if (\Illuminate\Support\Facades\Route::has($link['route']))
                        <a href="{{ route($link['route']) }}" class="flex items-center justify-between p-3 rounded-xl bg-[#F8FAFC] hover:bg-[#DBEAFE] text-sm font-medium text-[#0F172A] hover:text-[#1D4ED8] ui-animated">
                            <span>{{ $link['label'] }}</span>
                            <svg class="w-4 h-4 text-[#475569]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    @else
                        <span class="flex items-center justify-between p-3 rounded-xl bg-[#F8FAFC] text-sm font-medium text-[#94A3B8] cursor-not-allowed"
                              aria-disabled="true"
                              title="Belum tersedia pada baseline ini">
                            <span>{{ $link['label'] }}</span>
                            <span class="text-[10px] font-semibold uppercase tracking-wide text-[#94A3B8]">Soon</span>
                        </span>
                    @endif
                @endforeach
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
            @if (\Illuminate\Support\Facades\Route::has('super-admin.audit.index'))
                <a href="{{ route('super-admin.audit.index') }}" class="text-sm text-[#2563EB] font-medium hover:text-[#1D4ED8] ui-animated">Semua log →</a>
            @endif
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
