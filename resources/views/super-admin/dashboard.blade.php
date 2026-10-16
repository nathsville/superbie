@php $title = 'Command Center — Super Admin'; @endphp

{{--
    Super Admin — Government Operational Command Center (global monitoring).

    DATA INTEGRITY: every figure is a real DB count, an arithmetic derivation of
    real counts, or an honest empty state. No SLA, no forecast, no fabricated
    trend. See PROMPT_27_DASHBOARD_REDESIGN_DISCOVERY_MAPPING.md.
--}}

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @php
            $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
        @endphp
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Command Center — Super Admin</x-slot:header>
    <x-slot:breadcrumb>Pengelolaan dan pengawasan penuh sistem Lapor Pak Wali</x-slot:breadcrumb>

    <div class="w-full max-w-full">

    {{-- ─── KPI cards ──────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6" role="list" aria-label="Statistik sistem">
        <x-dashboard.kpi-card
            label="Total Laporan"
            :value="number_format($totalLaporan)"
            :hint="$laporanAktif.' aktif • '.$laporanSelesai.' selesai'"
            tone="primary">
            <x-slot:icon>
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </x-slot:icon>
        </x-dashboard.kpi-card>

        <x-dashboard.kpi-card
            label="Total Pengguna"
            :value="number_format($totalPengguna)"
            :hint="'+'.$penggunaBaru.' hari ini'"
            tone="teal">
            <x-slot:icon>
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
            </x-slot:icon>
        </x-dashboard.kpi-card>

        <x-dashboard.kpi-card
            label="Laporan Aktif"
            :value="number_format($laporanAktif)"
            hint="Sedang dalam proses penanganan"
            tone="warning">
            <x-slot:icon>
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </x-slot:icon>
        </x-dashboard.kpi-card>

        <x-dashboard.kpi-card
            label="Aktivitas Audit"
            :value="number_format($auditHariIni)"
            hint="Tindakan tercatat hari ini"
            tone="neutral">
            <x-slot:icon>
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </x-slot:icon>
        </x-dashboard.kpi-card>
    </div>

    {{-- ─── Attention / priority ───────────────────────────────────────────── --}}
    @if (isset($attention))
        <div class="mb-6">
            <x-dashboard.attention-banner
                :belum-dinas-unit="$attention['belumDinasUnit']"
                :laporan-tertua="$attention['laporanTertua']" />
        </div>
    @endif

    {{-- ─── Main trend ─────────────────────────────────────────────────────── --}}
    <div class="mb-6">
        <x-dashboard.section-card
            title="Tren Laporan — Masuk vs Selesai"
            subtitle="Volume laporan masuk (submitted_at) dibandingkan penyelesaian (resolved_at) per bulan">
            @if (collect($trenBulanan)->sum(fn ($p) => $p['inflow'] + $p['resolved']) > 0)
                <x-dashboard.trend-chart :series="$trenBulanan" />
            @else
                <x-dashboard.empty-state message="Belum ada data historis laporan." />
            @endif
        </x-dashboard.section-card>
    </div>

    {{-- ─── Secondary analytics: status + users + category + Dinas/Unit ────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
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

        <x-dashboard.section-card title="Distribusi Pengguna" subtitle="Jumlah akun per peran">
            <x-slot:actions>
                @if (\Illuminate\Support\Facades\Route::has('super-admin.users.index'))
                    <a href="{{ route('super-admin.users.index') }}" class="text-sm text-primary-600 font-medium hover:text-primary-700 ui-animated">Kelola →</a>
                @endif
            </x-slot:actions>
            @php
                // Canonical presentation for the four active roles. The card
                // renders EVERY role that actually exists in `$roleBreakdown`
                // (never silently dropping real accounts), so the rows always
                // reconcile with the "Total Pengguna" KPI. Canonical roles keep
                // their order; any other role present in the data (e.g. the
                // historical `petugas`) is appended with a humanised label.
                $roleMeta = [
                    'masyarakat'  => ['label' => 'Masyarakat', 'color' => 'bg-primary-100 text-primary-800'],
                    'operator'    => ['label' => 'Operator', 'color' => 'bg-teal-100 text-teal-800'],
                    'admin'       => ['label' => 'Admin (Monitoring)', 'color' => 'bg-accent-100 text-accent-800'],
                    'super_admin' => ['label' => 'Super Admin', 'color' => 'bg-purple-100 text-purple-800'],
                ];
                $legacyRoleLabels = ['petugas' => 'Petugas (historis)'];

                $roleKeys = array_keys($roleMeta);
                foreach (array_keys($roleBreakdown) as $roleKey) {
                    if (! in_array($roleKey, $roleKeys, true)) {
                        $roleKeys[] = $roleKey;
                    }
                }
            @endphp
            <div class="space-y-3">
                @foreach ($roleKeys as $roleKey)
                    @php
                        $meta = $roleMeta[$roleKey] ?? [
                            'label' => $legacyRoleLabels[$roleKey] ?? ucwords(str_replace('_', ' ', $roleKey)),
                            'color' => 'bg-surface-sunken text-ink-body',
                        ];
                    @endphp
                    <div class="flex items-center justify-between">
                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full {{ $meta['color'] }}">
                            {{ $meta['label'] }}
                        </span>
                        <span class="text-sm font-bold text-ink tabular-nums">{{ number_format($roleBreakdown[$roleKey] ?? 0) }}</span>
                    </div>
                @endforeach
            </div>
        </x-dashboard.section-card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
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

        <x-dashboard.section-card title="Beban Laporan per Dinas/Unit">
            @if (count($dinasDistribution) > 0)
                @php $maxDinas = max(array_column($dinasDistribution, 'count')); @endphp
                <div class="space-y-4">
                    @foreach ($dinasDistribution as $d)
                        <x-dashboard.bar-row :label="$d['name']" :count="$d['count']" :max="$maxDinas" tone="teal" />
                    @endforeach
                </div>
            @else
                <x-dashboard.empty-state message="Belum ada data Dinas/Unit." />
            @endif
        </x-dashboard.section-card>
    </div>

    {{-- ─── Aging (Umur Laporan Aktif) ─────────────────────────────────────── --}}
    <div class="mb-6">
        <x-dashboard.section-card
            title="Umur Laporan Aktif"
            subtitle="Distribusi laporan aktif berdasarkan umur sejak diajukan">
            @if ($aging['total'] > 0)
                @php $maxAging = max(array_column($aging['buckets'], 'count')); @endphp
                <div class="space-y-4">
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
    </div>

    {{-- ─── PHASE 4 — Bagian 2 — Rating Analytics (D-4.1: Super Admin = GLOBAL) ── --}}
    @isset($ratingAnalytics)
        <x-dashboard.rating-analytics :analytics="$ratingAnalytics" :show-dinas-filter="true" :show-dinas-breakdown="true" />
    @endisset

    {{-- ─── Critical monitoring + Audit feed ───────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <div class="lg:col-span-8">
            <x-dashboard.section-card title="Pemantauan Laporan Aktif" subtitle="Laporan berumur tertinggi" :padded="false">
                <x-slot:actions>
                    @if (\Illuminate\Support\Facades\Route::has('super-admin.complaints.index'))
                        <a href="{{ route('super-admin.complaints.index') }}" class="text-sm text-primary-600 font-medium hover:text-primary-700 ui-animated">Semua laporan →</a>
                    @endif
                </x-slot:actions>

                @if ($laporanPerhatian->isEmpty())
                    <x-dashboard.empty-state message="Belum ada laporan aktif." />
                @else
                    <div class="divide-y divide-line">
                        @foreach ($laporanPerhatian as $item)
                            <x-dashboard.monitoring-row :item="$item" />
                        @endforeach
                    </div>
                @endif
            </x-dashboard.section-card>
        </div>

        <div class="lg:col-span-4">
            <x-dashboard.section-card title="Aktivitas Audit Terkini" :padded="false">
                <x-slot:actions>
                    @if (\Illuminate\Support\Facades\Route::has('super-admin.audit.index'))
                        <a href="{{ route('super-admin.audit.index') }}" class="text-sm text-primary-600 font-medium hover:text-primary-700 ui-animated">Semua log →</a>
                    @endif
                </x-slot:actions>

                @if ($auditTerbaru->isEmpty())
                    <x-dashboard.empty-state message="Belum ada catatan aktivitas audit." />
                @else
                    <div class="divide-y divide-line">
                        @foreach ($auditTerbaru as $log)
                            <x-dashboard.audit-item :log="$log" />
                        @endforeach
                    </div>
                @endif
            </x-dashboard.section-card>
        </div>
    </div>

    </div>
</x-layouts.dashboard>
