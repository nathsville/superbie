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

    @php
        // Attention decides whether the brand row splits 7/5 or the command
        // band takes the full width. Same derivation as the banner itself.
        $hasAttention = isset($attention)
            && ((int) ($attention['belumDinasUnit'] ?? 0) > 0 || ! empty($attention['laporanTertua']));
    @endphp

    <div class="w-full space-y-5">

    {{-- ─── Brand row — blue command band + yellow attention band ──────────── --}}
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-12">
        <section class="card-enter rounded-[1.5rem] bg-primary-600 px-6 py-6 text-white sm:px-8 sm:py-7 {{ $hasAttention ? 'lg:col-span-7' : 'lg:col-span-12' }}"
                 aria-label="Ringkasan sistem">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between lg:gap-10">
                <div class="min-w-0">
                    <p class="text-[13px] font-medium text-white/75">Laporan Aktif</p>
                    <p class="mt-1.5 text-[56px] font-semibold leading-[0.95] tracking-[-0.03em] tabular-nums sm:text-[68px]">{{ number_format($laporanAktif) }}</p>
                </div>

                <div class="grid grid-cols-3 gap-x-6 gap-y-4 lg:gap-x-9">
                    <div class="min-w-0">
                        <p class="text-[24px] font-semibold leading-none tracking-[-0.01em] tabular-nums sm:text-[28px]">{{ number_format($totalLaporan) }}</p>
                        <p class="mt-2 text-[13px] text-white/75">Total Laporan</p>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[24px] font-semibold leading-none tracking-[-0.01em] tabular-nums sm:text-[28px]">{{ number_format($totalPengguna) }}</p>
                        <p class="mt-2 text-[13px] text-white/75">Total Pengguna</p>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[24px] font-semibold leading-none tracking-[-0.01em] tabular-nums sm:text-[28px]">{{ number_format($auditHariIni) }}</p>
                        <p class="mt-2 text-[13px] text-white/75">Aktivitas Audit</p>
                    </div>
                </div>
            </div>
        </section>

        @if ($hasAttention)
            <div class="lg:col-span-5">
                <x-dashboard.attention-banner
                    variant="bold"
                    :belum-dinas-unit="$attention['belumDinasUnit']"
                    :laporan-tertua="$attention['laporanTertua']" />
            </div>
        @endif
    </div>

    {{-- ─── Trend (8) + aging heat ramp (4) ────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-12">
        <div class="lg:col-span-8">
            <x-dashboard.section-card title="Tren Laporan" class="h-full">
                @if (collect($trenBulanan)->sum(fn ($p) => $p['inflow'] + $p['resolved']) > 0)
                    <x-dashboard.trend-chart :series="$trenBulanan" variant="bold" />
                @else
                    <x-dashboard.empty-state message="Belum ada data historis laporan." />
                @endif
            </x-dashboard.section-card>
        </div>

        <div class="lg:col-span-4">
            <x-dashboard.section-card title="Umur Laporan Aktif" class="h-full">
                @if ($aging['total'] > 0)
                    @php
                        $maxAging = max(array_column($aging['buckets'], 'count'));
                        // Heat ramp: fresh blue → amber → deep amber → red.
                        $agingTones = ['primary', 'amber', 'warning', 'rose'];
                    @endphp
                    <div class="space-y-4">
                        @foreach ($aging['buckets'] as $bucket)
                            <x-dashboard.bar-row
                                :label="$bucket['label']"
                                :count="$bucket['count']"
                                :max="$maxAging"
                                :tone="$agingTones[$loop->index] ?? 'rose'" />
                        @endforeach
                    </div>
                @else
                    <x-dashboard.empty-state message="Belum ada laporan aktif." />
                @endif
            </x-dashboard.section-card>
        </div>
    </div>

    {{-- ─── Status (7) + users (5) — stacked shares ────────────────────────── --}}
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-12">
        <div class="lg:col-span-7">
            <x-dashboard.section-card title="Distribusi Status Laporan" class="h-full">
                {{-- LC-2C: only the four MAIN/active statuses are shown here;
                     history-only statuses (under_review, waiting_for_information,
                     closed) are excluded from this distribution list. --}}
                @php
                    $statusSolids = [
                        'submitted'   => 'bg-primary-600',
                        'in_progress' => 'bg-purple-500',
                        'resolved'    => 'bg-success',
                        'rejected'    => 'bg-error',
                    ];
                    $statusSegments = [];
                    foreach ($statusBreakdown as $key => $data) {
                        if ($data['legacy'] ?? false) {
                            continue;
                        }
                        $statusSegments[] = [
                            'label' => $data['label'],
                            'count' => (int) $data['count'],
                            'color' => $statusSolids[$key] ?? 'bg-ink-ghost',
                        ];
                    }
                @endphp
                <x-dashboard.stacked-share :segments="$statusSegments" />
            </x-dashboard.section-card>
        </div>

        <div class="lg:col-span-5">
            <x-dashboard.section-card title="Distribusi Pengguna" class="h-full">
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
                        'masyarakat'  => ['label' => 'Masyarakat', 'color' => 'bg-primary-600'],
                        'operator'    => ['label' => 'Operator', 'color' => 'bg-teal-500'],
                        'admin'       => ['label' => 'Admin (Monitoring)', 'color' => 'bg-accent-500'],
                        'super_admin' => ['label' => 'Super Admin', 'color' => 'bg-purple-500'],
                    ];
                    $legacyRoleLabels = ['petugas' => 'Petugas (historis)'];

                    $roleKeys = array_keys($roleMeta);
                    foreach (array_keys($roleBreakdown) as $roleKey) {
                        if (! in_array($roleKey, $roleKeys, true)) {
                            $roleKeys[] = $roleKey;
                        }
                    }

                    $roleSegments = [];
                    foreach ($roleKeys as $roleKey) {
                        $meta = $roleMeta[$roleKey] ?? [
                            'label' => $legacyRoleLabels[$roleKey] ?? ucwords(str_replace('_', ' ', $roleKey)),
                            'color' => 'bg-ink-ghost',
                        ];
                        $roleSegments[] = [
                            'label' => $meta['label'],
                            'count' => (int) ($roleBreakdown[$roleKey] ?? 0),
                            'color' => $meta['color'],
                        ];
                    }
                @endphp
                <x-dashboard.stacked-share :segments="$roleSegments" />
            </x-dashboard.section-card>
        </div>
    </div>

    {{-- ─── Category (5) + Dinas/Unit load (7) ─────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-12">
        <div class="lg:col-span-5">
            <x-dashboard.section-card title="Kategori Laporan Terbanyak" class="h-full">
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

        <div class="lg:col-span-7">
            <x-dashboard.section-card title="Beban Laporan per Dinas/Unit" class="h-full">
                @if (count($dinasDistribution) > 0)
                    @php $maxDinas = max(array_column($dinasDistribution, 'count')); @endphp
                    <div class="space-y-4">
                        @foreach ($dinasDistribution as $d)
                            <x-dashboard.bar-row :label="$d['name']" :count="$d['count']" :max="$maxDinas" tone="primary" />
                        @endforeach
                    </div>
                @else
                    <x-dashboard.empty-state message="Belum ada data Dinas/Unit." />
                @endif
            </x-dashboard.section-card>
        </div>
    </div>

    {{-- ─── PHASE 4 — Bagian 2 — Rating Analytics (D-4.1: Super Admin = GLOBAL) ── --}}
    @isset($ratingAnalytics)
        <x-dashboard.rating-analytics
            :analytics="$ratingAnalytics"
            :show-dinas-filter="true"
            :show-dinas-breakdown="true"
            variant="bold" />
    @endisset

    {{-- ─── Critical monitoring + Audit feed ───────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <div class="lg:col-span-7">
            <x-dashboard.section-card title="Pemantauan Laporan Aktif" :padded="false" class="h-full">
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

        <div class="lg:col-span-5">
            <x-dashboard.section-card title="Aktivitas Audit Terkini" :padded="false" class="h-full">
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
