@php $title = 'Monitoring Laporan — Admin'; @endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        <div class="space-y-1">
            <a href="{{ route('admin.dashboard') }}" class="nav-link">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Monitoring
            </a>
            <a href="{{ route('admin.complaints') }}" class="nav-link active" aria-current="page">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Semua Laporan
            </a>
            <a href="{{ route('admin.audit-log') }}" class="nav-link">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                Audit Log
            </a>
        </div>
    </x-slot:navigation>

    <x-slot:header>Monitoring Seluruh Laporan</x-slot:header>
    <x-slot:breadcrumb>Panel Admin — Pemantauan seluruh laporan yang masuk ke sistem</x-slot:breadcrumb>

    {{-- Filter bar --}}
    <div class="bg-white rounded-2xl border border-[#E2E8F0] p-4 mb-6 card-enter">
        <form method="GET" action="{{ route('admin.complaints') }}" class="flex flex-wrap items-center gap-3">
            <div class="flex-1 min-w-[200px]">
                <label for="status-filter" class="sr-only">Filter Status</label>
                <select id="status-filter" name="status" class="w-full text-sm rounded-xl border border-[#E2E8F0] px-3.5 py-2.5 bg-white text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB]">
                    <option value="">Semua Status</option>
                    @foreach (\App\Enums\ComplaintStatus::cases() as $st)
                        <option value="{{ $st->value }}" {{ request('status') === $st->value ? 'selected' : '' }}>
                            {{ $st->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-sm font-semibold px-4 py-2.5 rounded-xl ui-animated">
                Filter
            </button>
            @if (request()->hasAny(['status']))
                <a href="{{ route('admin.complaints') }}" class="text-xs text-[#475569] hover:underline px-2">
                    Reset
                </a>
            @endif
        </form>
    </div>

    {{-- Complaints table --}}
    <div class="bg-white rounded-2xl border border-[#E2E8F0] overflow-hidden card-enter">
        <div class="px-6 py-4 border-b border-[#E2E8F0] flex items-center justify-between">
            <h2 class="text-base font-semibold text-[#0F172A]">Daftar Laporan (Read-Only)</h2>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-600">
                Mode Monitoring
            </span>
        </div>

        @if ($laporan->isEmpty())
            <div class="empty-state">
                <p class="text-sm text-[#475569]">Tidak ada laporan yang sesuai dengan filter.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm" aria-label="Monitoring laporan sistem">
                    <thead>
                        <tr class="bg-[#F8FAFC] border-b border-[#E2E8F0]">
                            <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#475569] uppercase tracking-wider">No. Referensi</th>
                            <th class="text-left px-4 py-3.5 text-xs font-semibold text-[#475569] uppercase tracking-wider">Judul</th>
                            <th class="text-left px-4 py-3.5 text-xs font-semibold text-[#475569] uppercase tracking-wider hidden sm:table-cell">Kategori</th>
                            <th class="text-left px-4 py-3.5 text-xs font-semibold text-[#475569] uppercase tracking-wider hidden md:table-cell">Petugas</th>
                            <th class="text-left px-4 py-3.5 text-xs font-semibold text-[#475569] uppercase tracking-wider">Status</th>
                            <th class="text-left px-4 py-3.5 text-xs font-semibold text-[#475569] uppercase tracking-wider hidden lg:table-cell">Tanggal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E8F0]">
                        @foreach ($laporan as $item)
                            <tr class="table-row-hover">
                                <td class="px-6 py-4 font-mono text-xs text-[#475569] font-medium">{{ $item->reference_code }}</td>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-[#0F172A] max-w-xs truncate">{{ $item->title }}</p>
                                    <p class="text-xs text-[#475569] mt-0.5 truncate">Pelapor: {{ $item->reporter?->name ?? '—' }}</p>
                                </td>
                                <td class="px-4 py-4 hidden sm:table-cell text-[#475569] text-xs">{{ $item->category?->name ?? '—' }}</td>
                                <td class="px-4 py-4 hidden md:table-cell text-[#475569] text-xs">{{ $item->assignee?->name ?? 'Belum ditugaskan' }}</td>
                                <td class="px-4 py-4">
                                    @php $status = $item->status; @endphp
                                    <span class="status-badge {{ $status->tailwindBadge() }}">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                        {{ $status->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 hidden lg:table-cell text-xs text-[#475569]">
                                    {{ $item->submitted_at->translatedFormat('d M Y H:i') }}
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
