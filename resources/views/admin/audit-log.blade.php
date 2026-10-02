@php $title = 'Monitoring Audit Log — Admin'; @endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        <div class="space-y-1">
            <a href="{{ route('admin.dashboard') }}" class="nav-link">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Monitoring
            </a>
            <a href="{{ route('admin.complaints') }}" class="nav-link">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Semua Laporan
            </a>
            <a href="{{ route('admin.audit-log') }}" class="nav-link active" aria-current="page">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                Audit Log
            </a>
        </div>
    </x-slot:navigation>

    <x-slot:header>Monitoring Audit Log</x-slot:header>
    <x-slot:breadcrumb>Panel Admin — Jejak audit append-only dari tindakan staf & admin (Read-Only)</x-slot:breadcrumb>

    <div class="bg-white rounded-2xl border border-[#E2E8F0] overflow-hidden card-enter">
        <div class="px-6 py-4 border-b border-[#E2E8F0] flex items-center justify-between">
            <h2 class="text-base font-semibold text-[#0F172A]">Log Aktivitas Sistem</h2>
            <span class="text-xs text-[#475569]">Log tidak dapat diedit atau dihapus</span>
        </div>

        @if ($logs->isEmpty())
            <div class="empty-state">
                <p class="text-sm text-[#475569]">Belum ada data audit log.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm" aria-label="Tabel audit log">
                    <thead>
                        <tr class="bg-[#F8FAFC] border-b border-[#E2E8F0]">
                            <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#475569] uppercase tracking-wider">Aktor</th>
                            <th class="text-left px-4 py-3.5 text-xs font-semibold text-[#475569] uppercase tracking-wider">Aksi</th>
                            <th class="text-left px-4 py-3.5 text-xs font-semibold text-[#475569] uppercase tracking-wider hidden md:table-cell">Entitas Subjek</th>
                            <th class="text-left px-4 py-3.5 text-xs font-semibold text-[#475569] uppercase tracking-wider hidden lg:table-cell">IP Address</th>
                            <th class="text-left px-4 py-3.5 text-xs font-semibold text-[#475569] uppercase tracking-wider">Waktu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E8F0]">
                        @foreach ($logs as $log)
                            <tr class="table-row-hover">
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-[#0F172A]">{{ $log->actor?->name ?? 'Sistem' }}</p>
                                    @if ($log->actor)
                                        <p class="text-xs text-[#475569] capitalize">{{ str_replace('_', ' ', $log->actor->role) }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <span class="font-mono text-xs bg-slate-100 text-slate-800 px-2.5 py-1 rounded-lg">
                                        {{ $log->action }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 hidden md:table-cell text-xs text-[#475569]">
                                    {{ $log->subject_type ?? '—' }} {{ $log->subject_id ? '#' . $log->subject_id : '' }}
                                </td>
                                <td class="px-4 py-4 hidden lg:table-cell text-xs font-mono text-[#475569]">
                                    {{ $log->ip_address ?? '—' }}
                                </td>
                                <td class="px-4 py-4 text-xs text-[#475569]">
                                    {{ $log->created_at->translatedFormat('d M Y H:i:s') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="px-6 py-4 border-t border-[#E2E8F0]">
                    {{ $logs->links() }}
                </div>
            @endif
        @endif
    </div>
</x-layouts.dashboard>
