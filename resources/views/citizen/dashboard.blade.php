@php
    $title = 'Dashboard Saya';
@endphp

{{--
    Masyarakat — citizen-facing dashboard. Deliberately NOT a command center:
    it stays focused on the citizen's own reports and the "create report" flow.
    Only the shared visual language is applied for consistency.
--}}

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @php $route = request()->routeIs('citizen.*') ? request()->route()->getName() : ''; @endphp
        @include('citizen.partials.nav', ['currentRoute' => $route])
    </x-slot:navigation>

    <x-slot:header>Dashboard Saya</x-slot:header>
    <x-slot:breadcrumb>Lapor Pak Wali — Panel Masyarakat</x-slot:breadcrumb>

    {{-- The "Buat Laporan" action lives ONLY in the sidebar navigation now, so
         the topbar stays clean (no duplicate CTA next to the profile). --}}

    <div class="w-full max-w-full">

    {{-- Welcome --}}
    <div class="mb-6 card-enter">
        <p class="text-ink-soft">Selamat datang, <strong class="text-ink">{{ $user->name }}</strong>. Berikut ringkasan laporan Anda.</p>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6" role="list" aria-label="Ringkasan laporan">
        <x-dashboard.kpi-card label="Total Laporan" :value="$totalLaporan" hint="Laporan dikirim" tone="primary">
            <x-slot:icon>
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </x-slot:icon>
        </x-dashboard.kpi-card>

        <x-dashboard.kpi-card label="Diproses" :value="$laporanProses" hint="Sedang ditangani" tone="warning">
            <x-slot:icon>
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </x-slot:icon>
        </x-dashboard.kpi-card>

        <x-dashboard.kpi-card label="Selesai" :value="$laporanSelesai" hint="Telah diselesaikan" tone="success">
            <x-slot:icon>
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </x-slot:icon>
        </x-dashboard.kpi-card>

        <x-dashboard.kpi-card label="Ditolak" :value="$laporanDitolak" hint="Tidak dapat diproses" tone="danger">
            <x-slot:icon>
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </x-slot:icon>
        </x-dashboard.kpi-card>
    </div>

    {{-- Recent complaints --}}
    <x-dashboard.section-card title="Laporan Terbaru" :padded="false">
        <x-slot:actions>
            <a href="{{ route('citizen.history') }}"
               class="inline-flex items-center gap-1 rounded-xl bg-surface-sunken hover:bg-line text-primary-600 text-sm font-semibold px-3 py-1.5 ui-animated">
                Lihat semua
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </x-slot:actions>

        @if ($laporanTerbaru->isEmpty())
            <div class="empty-state">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-primary-100 flex items-center justify-center">
                    <svg class="w-8 h-8 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-ink mb-1">Belum ada laporan</h3>
                <p class="text-sm text-ink-soft mb-4">Anda belum pernah mengirim laporan.</p>
                <a href="{{ route('citizen.complaint.create') }}" class="inline-flex items-center gap-2 bg-primary-600 text-white text-sm font-semibold px-4 py-2.5 rounded-xl ui-animated hover:bg-primary-700">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Buat Laporan Pertama
                </a>
            </div>
        @else
            <div class="divide-y divide-line">
                @foreach ($laporanTerbaru as $laporan)
                    <a
                        href="{{ route('citizen.complaint.show', $laporan) }}"
                        class="flex items-center gap-4 px-5 sm:px-6 py-4 hover:bg-page table-row-hover group"
                    >
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                                <span class="font-mono text-xs text-ink-soft bg-page border border-line rounded-md px-2 py-0.5">{{ $laporan->reference_code }}</span>
                                @php $status = $laporan->status; @endphp
                                <span class="status-badge {{ $status->tailwindBadge() }}">
                                    <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                    {{ $status->label() }}
                                </span>
                            </div>
                            <p class="text-sm font-semibold text-ink truncate group-hover:text-primary-600 ui-animated">
                                {{ $laporan->title }}
                            </p>
                            <p class="text-xs text-ink-soft mt-1">
                                {{ $laporan->category?->name ?? '—' }} •
                                {{ $laporan->submitted_at?->translatedFormat('d M Y') ?? '—' }}
                            </p>
                        </div>
                        <span class="w-8 h-8 rounded-full bg-page text-ink-faint flex items-center justify-center shrink-0 group-hover:bg-primary-100 group-hover:text-primary-600 ui-animated">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </x-dashboard.section-card>

    </div>
</x-layouts.dashboard>
