@php $title = 'Command Center — Operator'; @endphp

{{--
    Operator — Government Operational Command Center, SCOPED to the operator's
    Dinas/Unit (hybrid visibility rule, Prompt 15). A Super Admin viewing this
    dashboard keeps the global overview. No cross-unit data is ever shown.

    DATA INTEGRITY: real counts / arithmetic derivations / honest empty states.
--}}

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @php $route = request()->routeIs('operator.*') ? request()->route()->getName() : ''; @endphp
        <x-dashboard.nav-rail-link :href="route('operator.dashboard')" label="Dashboard" :active="$route === 'operator.dashboard'">
            <x-slot:icon>
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            </x-slot:icon>
        </x-dashboard.nav-rail-link>
        <x-dashboard.nav-rail-link :href="route('operator.complaint.index')" label="Kelola Laporan" :active="$route === 'operator.complaint.index'">
            <x-slot:icon>
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </x-slot:icon>
        </x-dashboard.nav-rail-link>
    </x-slot:navigation>

    <x-slot:header>Command Center — Operator</x-slot:header>
    <x-slot:breadcrumb>Penanganan laporan sesuai Dinas/Unit</x-slot:breadcrumb>

    {{-- Full-width command surface — every section spans the whole content area
         so no column is left with dead space. --}}
    <div class="w-full max-w-full space-y-6 card-enter">

        {{-- ─── KPI cards (balanced 4-column grid) ─────────────────────────── --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" role="list" aria-label="Ringkasan operasional">
            <x-dashboard.kpi-card
                label="Total Laporan Unit"
                :value="number_format($totalLaporan)"
                hint="Sesuai Dinas/Unit Anda"
                tone="primary">
                <x-slot:icon>
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </x-slot:icon>
            </x-dashboard.kpi-card>

            <x-dashboard.kpi-card
                label="Belum Ditentukan Dinas/Unit"
                :value="number_format($belumDinasUnit)"
                hint="Perlu penentuan tujuan"
                tone="warning">
                <x-slot:icon>
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </x-slot:icon>
            </x-dashboard.kpi-card>

            <x-dashboard.kpi-card
                label="Perlu Tindakan"
                :value="number_format($perluTindakan)"
                hint="Menunggu ditangani"
                tone="danger">
                <x-slot:icon>
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </x-slot:icon>
            </x-dashboard.kpi-card>

            <x-dashboard.kpi-card
                label="Selesai Hari Ini"
                :value="number_format($selesaiHariIni)"
                hint="Diselesaikan hari ini"
                tone="success">
                <x-slot:icon>
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </x-slot:icon>
            </x-dashboard.kpi-card>
        </div>

        {{-- ─── Attention / priority (prominent amber/rose tint) ───────────── --}}
        <x-dashboard.attention-banner
            :belum-dinas-unit="$belumDinasUnit"
            :laporan-tertua="$laporanPerhatian->first()" />

        {{-- ─── Main trend (full width) ────────────────────────────────────── --}}
        <x-dashboard.section-card
            title="Tren Laporan Unit — Masuk vs Selesai"
            subtitle="Volume laporan masuk (submitted_at) dibandingkan penyelesaian (resolved_at) per bulan">
            @if (collect($trenBulanan)->sum(fn ($p) => $p['inflow'] + $p['resolved']) > 0)
                <x-dashboard.trend-chart :series="$trenBulanan" />
            @else
                <x-dashboard.empty-state message="Belum ada data historis laporan." />
            @endif
        </x-dashboard.section-card>

        {{-- ─── Bento row 1: status + category (balanced 2 columns) ─────────── --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <x-dashboard.section-card title="Distribusi Status Laporan">
                {{-- LC-2C: only the four MAIN/active statuses are shown here;
                     history-only statuses (under_review, waiting_for_information,
                     closed) are excluded from this distribution list. --}}
                <div class="space-y-2">
                    @foreach ($statusBreakdown as $key => $data)
                        @continue($data['legacy'] ?? false)
                        <div class="flex items-center justify-between gap-3 rounded-xl border border-line bg-page px-3.5 py-2.5">
                            <span class="status-badge {{ $data['badge'] }}">
                                <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                {{ $data['label'] }}
                            </span>
                            <span class="shrink-0 text-right text-sm font-bold text-ink tabular-nums">{{ number_format($data['count']) }}</span>
                        </div>
                    @endforeach
                </div>
            </x-dashboard.section-card>

            <x-dashboard.section-card title="Kategori Laporan Terbanyak">
                @if (count($kategoriTeratas) > 0)
                    @php $maxKat = max(array_column($kategoriTeratas, 'count')); @endphp
                    <div class="space-y-4">
                        @foreach ($kategoriTeratas as $k)
                            <x-dashboard.bar-row :label="$k['name']" :count="$k['count']" :max="$maxKat" tone="primary" />
                        @endforeach
                    </div>
                @else
                    <x-dashboard.empty-state message="Belum ada data kategori." />
                @endif
            </x-dashboard.section-card>
        </div>

        {{-- ─── Bento row 2: aging (full width, clean horizontal bars) ──────── --}}
        <x-dashboard.section-card
            title="Umur Laporan Aktif"
            subtitle="Distribusi laporan aktif berdasarkan umur sejak diajukan">
            @if ($aging['total'] > 0)
                @php $maxAging = max(array_column($aging['buckets'], 'count')); @endphp
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-8 gap-y-4">
                    @foreach ($aging['buckets'] as $bucket)
                        <x-dashboard.bar-row
                            :label="$bucket['label']"
                            :count="$bucket['count']"
                            :max="$maxAging"
                            :tone="$loop->last ? 'rose' : 'primary'" />
                    @endforeach
                </div>
            @else
                <x-dashboard.empty-state message="Belum ada laporan aktif." />
            @endif
        </x-dashboard.section-card>

        {{-- ─── PHASE 4 — Bagian 2 — Rating Analytics (D-4.1 / D-4.9) ──────── --}}
        {{-- Operator sees ONLY their own Dinas/Unit: no Dinas filter, no per-Dinas
             comparison. Scope is enforced server-side from users.dinas_unit_id. --}}
        @isset($ratingAnalytics)
            <x-dashboard.rating-analytics
                :analytics="$ratingAnalytics"
                :show-dinas-filter="(bool) ($ratingAnalytics['scope']['isGlobal'] ?? false)"
                :show-dinas-breakdown="(bool) ($ratingAnalytics['scope']['isGlobal'] ?? false)" />
        @endisset

    </div>
</x-layouts.dashboard>
