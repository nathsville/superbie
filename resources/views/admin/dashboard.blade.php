@php
    $title = 'Dashboard Admin — Lapor Pak Wali';

    // Factual derivations only (no fabricated trend/percentage).
    $inflowSeries = collect($trenBulanan)->pluck('inflow')->all();
    $hasTrend     = collect($trenBulanan)->sum(fn ($p) => $p['inflow'] + $p['resolved']) > 0;

    // A factual monthly completion rate needs a month with inflow > 0.
    $hasCompletionTrend = collect($trenBulanan)->contains(fn ($p) => (int) ($p['inflow'] ?? 0) > 0);

    // Geographic Analytics Layer 1 (Prompt 6/5) — read-only administrative
    // rankings. Rows are pre-sorted + pre-limited by DashboardCacheService; this
    // block only reshapes them for the ranking component (no query, no math on
    // the data beyond field mapping). The retained Layer-1 surface is
    // Top Kecamatan / Top Kelurahan.
    $geoAnalytics   = $geographicAnalytics ?? [];
    $topKecamatan   = collect($geoAnalytics['topKecamatan'] ?? [])
        ->map(fn ($r) => ['name' => $r['name'], 'count' => (int) $r['total']])
        ->all();
    $topKelurahan   = collect($geoAnalytics['topKelurahan'] ?? [])
        ->map(fn ($r) => ['name' => $r['name'], 'sublabel' => $r['kecamatan'], 'count' => (int) $r['total']])
        ->all();

    // Geographic Analytics Layer 2A (Prompt 7/5) — read-only administrative
    // analytics: Tingkat Penyelesaian per Wilayah + Tren Kasus per Wilayah. All
    // rows are pre-computed by DashboardCacheService.
    $layer2A           = $geographicLayer2A ?? [];
    $regionalTrend     = $layer2A['regionalTrend'] ?? ['labels' => [], 'series' => []];
    $completionWilayah = $layer2A['completionByKelurahan'] ?? [];
@endphp

{{--
    Admin — Command Center (MONITORING ONLY, read-only).

    Decision-oriented bento layout — streamlined (every row spans full width;
    orphaned cards are promoted to full-width so the grid has no awkward gaps):
      ROW 1  KPI (Total / Masuk Hari Ini / Selesai / Aktif)
      ROW 2  Tren Laporan & Backlog  +  Bottleneck Status Laporan   (7 : 5)
      ROW 3  Tingkat Penyelesaian  +  Persentase Penyelesaian per Kategori (4 : 8)
      ROW 4  Peta Wilayah Aduan (geographic analytics — Prompt 5/5) (full width)
      ROW 5  Top Kecamatan  +  Top Kelurahan                        (6 : 6)
      ROW 6  Tingkat Penyelesaian per Wilayah  +  Tren Kasus per Wilayah (5 : 7)
      ROW 7  Analitik Rating Kepuasan                               (full width)

    DATA INTEGRITY RULE: every number rendered here is either a real DB count
    (via DashboardCacheService), an arithmetic derivation of real counts, or an
    honest empty state. No fabricated officers/districts/OPDs/percentages/trends,
    no invented service-level claims, no anomaly scoring. No SLA.
--}}

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        <x-dashboard.nav-rail-link :href="route('admin.dashboard')" label="Monitoring" :active="true">
            <x-slot:icon>
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </x-slot:icon>
        </x-dashboard.nav-rail-link>
        <x-dashboard.nav-rail-link :href="route('admin.complaints')" label="Monitoring Laporan">
            <x-slot:icon>
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </x-slot:icon>
        </x-dashboard.nav-rail-link>
    </x-slot:navigation>

    <x-slot:header>Dashboard Admin</x-slot:header>
    <x-slot:breadcrumb>Command Center — Admin • Pemantauan operasional laporan (read-only)</x-slot:breadcrumb>

    {{-- ═══ ROW 1 — KPI bento row ═══════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4" role="list" aria-label="Ringkasan laporan">
        <x-dashboard.soft-stat-card
            label="Total Laporan"
            :value="number_format($totalLaporan)"
            :hint="number_format($laporanAktif).' aktif • '.number_format($laporanSelesai).' selesai'"
            tone="primary">
            <x-slot:chart>
                <x-dashboard.mini-bars :values="$inflowSeries" tone="primary" />
            </x-slot:chart>
        </x-dashboard.soft-stat-card>

        <x-dashboard.soft-stat-card
            label="Masuk Hari Ini"
            :value="number_format($laporanHariIni)"
            hint="Laporan diterima hari ini"
            tone="teal">
            <x-slot:icon>
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
            </x-slot:icon>
        </x-dashboard.soft-stat-card>

        <x-dashboard.soft-stat-card
            label="Selesai"
            :value="number_format($laporanSelesai)"
            hint="Laporan berstatus selesai"
            tone="success">
            <x-slot:icon>
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </x-slot:icon>
        </x-dashboard.soft-stat-card>

        <x-dashboard.soft-stat-card
            label="Aktif"
            :value="number_format($laporanAktif)"
            hint="Masih dalam proses penanganan"
            :highlight="true" />
    </div>

    {{-- ═══ ROW 2 — Tren Laporan & Backlog  +  Bottleneck Status ════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 mb-4">
        <div class="lg:col-span-7">
            <x-dashboard.soft-card
                title="Tren Laporan & Backlog"
                subtitle="Perbandingan laporan masuk, selesai, dan backlog per bulan.">
                <x-slot:actions>
                    <span class="text-xs font-semibold text-ink-soft bg-surface-sunken px-3 py-1 rounded-full">
                        {{ count($trenBulanan) }} bulan
                    </span>
                </x-slot:actions>

                @if ($hasTrend)
                    <x-dashboard.trend-lines :series="$trenBulanan" />
                @else
                    <x-dashboard.empty-state message="Belum ada data historis laporan." />
                @endif
            </x-dashboard.soft-card>
        </div>

        <div class="lg:col-span-5">
            <x-dashboard.soft-card
                title="Bottleneck Status Laporan"
                subtitle="Pantauan penumpukan volume laporan pada setiap tahapan alur penanganan.">
                @if ($statusFlow['flow_total'] > 0)
                    <x-dashboard.status-flow
                        :stages="$statusFlow['stages']"
                        :active-total="$statusFlow['active_total']"
                        :bottleneck="$statusFlow['bottleneck']" />
                @else
                    <x-dashboard.empty-state message="Belum ada data status laporan." />
                @endif
            </x-dashboard.soft-card>
        </div>
    </div>

    {{-- ═══ ROW 3 — Tingkat Penyelesaian  +  Penyelesaian per Kategori ══════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 mb-4">
        <div class="lg:col-span-4">
            <x-dashboard.soft-card
                title="Tingkat Penyelesaian"
                subtitle="Laporan selesai terhadap laporan masuk, per bulan.">
                @if ($hasCompletionTrend)
                    <x-dashboard.completion-trend :series="$trenBulanan" />
                @else
                    <x-dashboard.empty-state message="Belum ada data historis laporan." />
                @endif
            </x-dashboard.soft-card>
        </div>

        <div class="lg:col-span-8">
            <x-dashboard.soft-card
                title="Persentase Penyelesaian per Kategori"
                subtitle="Rasio perbandingan laporan selesai terhadap total laporan masuk pada masing-masing kategori.">
                @if (count($kategoriPenyelesaian) > 0)
                    <x-dashboard.category-completion :rows="$kategoriPenyelesaian" />
                @else
                    <x-dashboard.empty-state message="Belum ada data kategori." />
                @endif
            </x-dashboard.soft-card>
        </div>
    </div>

    {{-- ═══ ROW 4 — Peta Wilayah Aduan (geographic analytics, Prompt 5/5) ══ --}}
    {{-- Read-only; administrative counts from kecamatan/kelurahan, pins/heatmap
         ONLY from valid lat+lng pairs. --}}
    <div class="mb-4">
        <x-dashboard.geographic-map :map="$geographicMap" />
    </div>

    {{-- ═══ ROW 5 — Geographic Analytics Layer 1 (Prompt 6/5): Top wilayah ══ --}}
    {{-- Read-only administrative rankings derived from kecamatan_id /
         kelurahan_id. Kept directly beneath the map so the flow reads:
         sebaran (map) → wilayah utama (Top Kecamatan/Kelurahan). No coordinates
         involved; no new route, no migration, no AJAX. --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 mb-4">
        <div class="lg:col-span-6">
            <x-dashboard.geographic-ranking
                title="Top Kecamatan"
                subtitle="Kecamatan dengan jumlah laporan terbanyak."
                :rows="$topKecamatan"
                tone="primary"
                empty-message="Belum ada data Kecamatan." />
        </div>

        <div class="lg:col-span-6">
            <x-dashboard.geographic-ranking
                title="Top Kelurahan"
                subtitle="Kelurahan dengan jumlah laporan terbanyak."
                :rows="$topKelurahan"
                tone="teal"
                empty-message="Belum ada data Kelurahan." />
        </div>
    </div>

    {{-- ═══ ROW 6 — Geographic Analytics Layer 2A (Prompt 7/5) ═════════════ --}}
    {{-- Read-only administrative analytics. Flow continues:
         Layer 1 (ranking wilayah) → Layer 2A (tingkat penyelesaian per wilayah,
         tren kasus per wilayah). No coordinates, no new route, no AJAX. --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 mb-4">
        <div class="lg:col-span-5">
            <x-dashboard.geographic-completion :rows="$completionWilayah" />
        </div>

        <div class="lg:col-span-7">
            <x-dashboard.geographic-trend :trend="$regionalTrend" />
        </div>
    </div>

    {{-- ═══ ROW 7 — Analitik Rating Kepuasan (PHASE 4 — Bagian 2) ═════════ --}}
    {{-- Aggregate-only rating analytics (D-4.1..D-4.15). Read-only; no new
         route, no migration, no individual rating / reporter identity. --}}
    @isset($ratingAnalytics)
        <x-dashboard.rating-analytics :analytics="$ratingAnalytics" :show-dinas-filter="true" :show-dinas-breakdown="true" />
    @endisset
</x-layouts.dashboard>
