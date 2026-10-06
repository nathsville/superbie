@php $title = 'Dashboard Admin — Lapor Pak Wali'; @endphp

{{--
    Admin dashboard — MONITORING ONLY (read-only).

    DATA INTEGRITY RULE (Prompt 8):
    Every number rendered here is either:
      - read directly from the database (via DashboardCacheService), or
      - derived arithmetically from those real counts, or
      - an honest empty state when no data exists.
    No invented/fake business data, no SLA claims (SLA is not yet an approved
    business rule), no fabricated officer names, districts, OPDs or trends.
--}}

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        <div class="space-y-1">
            <a href="{{ route('admin.dashboard') }}" class="nav-link active" aria-current="page">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Monitoring
            </a>
            <a href="{{ route('admin.complaints') }}" class="nav-link">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Monitoring Laporan
            </a>
            <a href="{{ route('admin.audit-log') }}" class="nav-link">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                Audit Log
            </a>
        </div>
    </x-slot:navigation>

    <x-slot:header>Dashboard Admin</x-slot:header>
    <x-slot:breadcrumb>Pemantauan operasional laporan (read-only)</x-slot:breadcrumb>

    {{-- Read-only notice --}}
    <div class="mb-6 rounded-2xl bg-[#EFF6FF] border border-[#BFDBFE] p-4 flex items-start gap-3 card-enter">
        <svg class="w-5 h-5 text-[#1D4ED8] shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div>
            <p class="text-sm font-semibold text-[#1E3A8A]">Mode Pemantauan (Read-Only)</p>
            <p class="text-xs text-[#1E40AF] mt-0.5">
                Panel ini hanya menampilkan data laporan yang tersimpan di sistem. Admin tidak dapat mengubah laporan,
                status, penugasan, maupun data master.
            </p>
        </div>
    </div>

    {{-- Stat cards — real database counts --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8" role="list" aria-label="Ringkasan laporan">
        <div class="stat-card card-enter stagger-item" role="listitem">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-[#475569] uppercase tracking-wider">Total Laporan</span>
                <div class="w-9 h-9 rounded-xl bg-[#DBEAFE] flex items-center justify-center">
                    <svg class="w-5 h-5 text-[#2563EB]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-[#0F172A]">{{ number_format($totalLaporan) }}</p>
            <p class="text-xs text-[#475569] mt-1">seluruh laporan tercatat</p>
        </div>

        <div class="stat-card card-enter stagger-item" role="listitem">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-[#475569] uppercase tracking-wider">Masuk Hari Ini</span>
                <div class="w-9 h-9 rounded-xl bg-[#DBEAFE] flex items-center justify-center">
                    <svg class="w-5 h-5 text-[#2563EB]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-[#0F172A]">{{ number_format($laporanHariIni) }}</p>
            <p class="text-xs text-[#475569] mt-1">laporan diterima hari ini</p>
        </div>

        <div class="stat-card card-enter stagger-item" role="listitem">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-[#475569] uppercase tracking-wider">Laporan Aktif</span>
                <div class="w-9 h-9 rounded-xl bg-yellow-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-yellow-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-[#0F172A]">{{ number_format($laporanAktif) }}</p>
            <p class="text-xs text-[#475569] mt-1">belum selesai / ditolak / ditutup</p>
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
            <p class="text-3xl font-bold text-[#0F172A]">{{ number_format($laporanSelesai) }}</p>
            <p class="text-xs text-[#475569] mt-1">laporan berstatus selesai</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        {{-- Status breakdown (real) --}}
        <div class="bg-white rounded-2xl border border-[#E2E8F0] p-6 card-enter">
            <h2 class="text-base font-semibold text-[#0F172A] mb-4">Distribusi Status Laporan</h2>
            <div class="space-y-3">
                @foreach ($statusBreakdown as $key => $data)
                    <div class="flex items-center justify-between">
                        <span class="status-badge {{ $data['badge'] }}">
                            <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                            {{ $data['label'] }}
                        </span>
                        <span class="text-sm font-bold text-[#0F172A]">{{ number_format($data['count']) }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Laporan perlu perhatian (real: active statuses, oldest first by age) --}}
        <div class="lg:col-span-2 bg-white rounded-2xl border border-[#E2E8F0] overflow-hidden card-enter">
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#E2E8F0]">
                <div>
                    <h2 class="text-base font-semibold text-[#0F172A]">Laporan Belum Selesai</h2>
                    <p class="text-xs text-[#475569]">Laporan berstatus aktif (diajukan, ditinjau, diproses, menunggu informasi)</p>
                </div>
                <a href="{{ route('admin.complaints') }}" class="text-sm text-[#2563EB] font-medium hover:text-[#1D4ED8] ui-animated">Lihat semua →</a>
            </div>

            @if ($laporanPerhatian->isEmpty())
                <div class="empty-state">
                    <p class="text-sm text-[#475569]">Belum ada laporan yang perlu perhatian.</p>
                </div>
            @else
                <div class="divide-y divide-[#E2E8F0]">
                    @foreach ($laporanPerhatian as $item)
                        @php $daysOld = $item->submitted_at ? (int) $item->submitted_at->diffInDays(now()) : null; @endphp
                        <div class="flex items-start gap-4 px-6 py-4 table-row-hover">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs font-mono text-[#475569]">{{ $item->reference_code }}</span>
                                    @php $status = $item->status; @endphp
                                    <span class="status-badge {{ $status->tailwindBadge() }}">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                        {{ $status->label() }}
                                    </span>
                                </div>
                                <p class="text-sm font-semibold text-[#0F172A] truncate">{{ $item->title }}</p>
                                <p class="text-xs text-[#475569] mt-0.5">
                                    {{ $item->category?->name ?? '—' }} •
                                    Dinas: {{ $item->dinasUnit?->name ?? $item->category?->dinas_name ?? 'Belum ditentukan' }}
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-xs font-semibold text-[#475569]">Umur laporan</p>
                                <p class="text-sm font-bold text-[#0F172A]">
                                    @if (is_null($daysOld))
                                        —
                                    @elseif ($daysOld === 0)
                                        Hari ini
                                    @else
                                        {{ $daysOld }} hari
                                    @endif
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Laporan terbaru (real) --}}
        <div class="lg:col-span-2 bg-white rounded-2xl border border-[#E2E8F0] overflow-hidden card-enter">
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#E2E8F0]">
                <h2 class="text-base font-semibold text-[#0F172A]">Laporan Terbaru</h2>
                <a href="{{ route('admin.complaints') }}" class="text-sm text-[#2563EB] font-medium hover:text-[#1D4ED8] ui-animated">Monitoring Laporan →</a>
            </div>

            @if ($laporanTerbaru->isEmpty())
                <div class="empty-state">
                    <p class="text-sm text-[#475569]">Belum ada laporan masuk.</p>
                </div>
            @else
                <div class="divide-y divide-[#E2E8F0]">
                    @foreach ($laporanTerbaru as $item)
                        <div class="flex items-start gap-4 px-6 py-4 table-row-hover">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs font-mono text-[#475569]">{{ $item->reference_code }}</span>
                                    @php $status = $item->status; @endphp
                                    <span class="status-badge {{ $status->tailwindBadge() }}">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                        {{ $status->label() }}
                                    </span>
                                </div>
                                <p class="text-sm font-semibold text-[#0F172A] truncate">{{ $item->title }}</p>
                                <p class="text-xs text-[#475569] mt-0.5">
                                    {{ $item->category?->name ?? '—' }} •
                                    Pelapor: {{ $item->reporter?->name ?? '—' }}
                                </p>
                            </div>
                            <span class="text-xs text-[#94A3B8] shrink-0 mt-1">
                                {{ $item->submitted_at?->translatedFormat('d M Y') ?? '—' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Audit stream (real) --}}
        <div class="bg-white rounded-2xl border border-[#E2E8F0] overflow-hidden card-enter">
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#E2E8F0]">
                <div>
                    <h2 class="text-base font-semibold text-[#0F172A]">Aktivitas Audit Terkini</h2>
                    <p class="text-xs text-[#475569]">{{ number_format($auditHariIni) }} tindakan hari ini</p>
                </div>
                <a href="{{ route('admin.audit-log') }}" class="text-sm text-[#2563EB] font-medium hover:text-[#1D4ED8] ui-animated">Semua →</a>
            </div>

            @if ($auditTerbaru->isEmpty())
                <div class="empty-state">
                    <p class="text-sm text-[#475569]">Belum ada catatan aktivitas audit.</p>
                </div>
            @else
                <div class="divide-y divide-[#E2E8F0]">
                    @foreach ($auditTerbaru as $log)
                        <div class="px-6 py-3.5 table-row-hover">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-blue-500 shrink-0" aria-hidden="true"></span>
                                <span class="font-medium text-[#0F172A] text-sm truncate">{{ $log->actor?->name ?? 'Sistem' }}</span>
                                <span class="font-mono text-[10px] bg-slate-100 text-slate-700 px-2 py-0.5 rounded">{{ $log->action }}</span>
                            </div>
                            <p class="text-xs text-[#475569] mt-1 ml-4">{{ $log->created_at?->diffForHumans() ?? '—' }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-layouts.dashboard>
