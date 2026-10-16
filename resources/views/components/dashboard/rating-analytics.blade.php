@props([
    'analytics',
    'showDinasFilter' => true,
    'showDinasBreakdown' => true,
])

@php
    // PHASE 4 — Bagian 2 — Rating Analytics (D-4.1 .. D-4.15).
    //
    // AGGREGATE ONLY (D-4.8): this component renders numbers/aggregates and
    // NEVER any reporter identity (name / NIK / phone / address), individual
    // rating record, drill-down, or export control (D-4.14).
    //
    // All values arrive pre-computed by RatingAnalyticsService (aggregate SQL,
    // no N+1). This view performs NO query and invents no metric.
    $options  = $analytics['options'] ?? [];
    $filters  = $analytics['filters'] ?? [];
    $period   = $analytics['period'] ?? ['key' => '6m', 'isCustom' => false];
    $hasData  = (bool) ($analytics['hasData'] ?? false);

    $total      = (int) ($analytics['totalRatings'] ?? 0);
    $average    = $analytics['averageRating'] ?? null;
    $satisfaction = $analytics['satisfactionRate'] ?? null;
    $unrated    = (int) ($analytics['resolvedWithoutRating'] ?? 0);
    $distribution = $analytics['distribution'] ?? [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

    $maxDist = max(1, ...array_values($distribution));

    // Trend bars are proportional to the 1–5 average scale (null = no data).
    $trend = $analytics['trend'] ?? [];
@endphp

<section class="w-full" aria-labelledby="rating-analytics-heading">
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 id="rating-analytics-heading" class="text-base font-semibold text-ink">Analitik Rating Kepuasan</h2>
            <p class="text-xs text-ink-faint mt-0.5">
                Agregat kepuasan masyarakat (hanya angka — tanpa identitas pelapor).
            </p>
        </div>
        <span class="text-xs font-semibold text-ink-soft bg-white border border-line px-3 py-1.5 rounded-full whitespace-nowrap shadow-sm">
            {{ $period['label'] ?? '' }}
        </span>
    </div>

    {{-- ─── Filter bar (D-4.6 period presets + D-4.7 filters) — one aligned row ─ --}}
    <div class="soft-card p-4 sm:p-5 mb-4">
        <form method="GET" action="{{ request()->url() }}" x-data="{ period: '{{ $period['key'] }}' }"
              class="flex flex-wrap items-end gap-3" role="search"
              aria-label="Filter analitik rating">
            <div class="flex-1 min-w-[10rem]">
                <label for="ra-period" class="block text-[11px] font-semibold uppercase tracking-wider text-ink-soft mb-1.5">Periode</label>
                <select id="ra-period" name="period" x-model="period"
                        class="w-full rounded-xl border-line text-sm text-ink focus:border-primary-600 focus:ring-primary-600 ui-animated">
                    @foreach (\App\Services\RatingAnalyticsService::PERIOD_OPTIONS as $value => $label)
                        <option value="{{ $value }}" @selected(($period['key'] ?? '6m') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div x-show="period === 'custom'" x-cloak class="flex-1 min-w-[10rem]">
                <label for="ra-start" class="block text-[11px] font-semibold uppercase tracking-wider text-ink-soft mb-1.5">Tanggal Mulai</label>
                <input id="ra-start" type="date" name="start_date" value="{{ request('start_date') }}"
                       class="w-full rounded-xl border-line text-sm text-ink focus:border-primary-600 focus:ring-primary-600 ui-animated">
            </div>

            <div x-show="period === 'custom'" x-cloak class="flex-1 min-w-[10rem]">
                <label for="ra-end" class="block text-[11px] font-semibold uppercase tracking-wider text-ink-soft mb-1.5">Tanggal Akhir</label>
                <input id="ra-end" type="date" name="end_date" value="{{ request('end_date') }}"
                       class="w-full rounded-xl border-line text-sm text-ink focus:border-primary-600 focus:ring-primary-600 ui-animated">
            </div>

            @if ($showDinasFilter)
                <div class="flex-1 min-w-[10rem]">
                    <label for="ra-dinas" class="block text-[11px] font-semibold uppercase tracking-wider text-ink-soft mb-1.5">Dinas/Unit</label>
                    <select id="ra-dinas" name="dinas_unit_id"
                            class="w-full rounded-xl border-line text-sm text-ink focus:border-primary-600 focus:ring-primary-600 ui-animated">
                        <option value="">Semua Dinas/Unit</option>
                        @foreach ($options['dinasUnits'] ?? [] as $unit)
                            <option value="{{ $unit['id'] }}" @selected((int) $filters['dinas_unit_id'] === (int) $unit['id'])>{{ $unit['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="flex-1 min-w-[10rem]">
                <label for="ra-kategori" class="block text-[11px] font-semibold uppercase tracking-wider text-ink-soft mb-1.5">Kategori</label>
                <select id="ra-kategori" name="category_id"
                        class="w-full rounded-xl border-line text-sm text-ink focus:border-primary-600 focus:ring-primary-600 ui-animated">
                    <option value="">Semua Kategori</option>
                    @foreach ($options['categories'] ?? [] as $category)
                        <option value="{{ $category['id'] }}" @selected((int) $filters['category_id'] === (int) $category['id'])>{{ $category['name'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex-1 min-w-[10rem]">
                <label for="ra-kecamatan" class="block text-[11px] font-semibold uppercase tracking-wider text-ink-soft mb-1.5">Kecamatan</label>
                <select id="ra-kecamatan" name="kecamatan_id"
                        class="w-full rounded-xl border-line text-sm text-ink focus:border-primary-600 focus:ring-primary-600 ui-animated">
                    <option value="">Semua Kecamatan</option>
                    @foreach ($options['kecamatan'] ?? [] as $kecamatan)
                        <option value="{{ $kecamatan['id'] }}" @selected((int) $filters['kecamatan_id'] === (int) $kecamatan['id'])>{{ $kecamatan['name'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex-1 min-w-[10rem]">
                <label for="ra-kelurahan" class="block text-[11px] font-semibold uppercase tracking-wider text-ink-soft mb-1.5">Kelurahan</label>
                <select id="ra-kelurahan" name="kelurahan_id"
                        class="w-full rounded-xl border-line text-sm text-ink focus:border-primary-600 focus:ring-primary-600 ui-animated">
                    <option value="">Semua Kelurahan</option>
                    @foreach ($options['kelurahan'] ?? [] as $kelurahan)
                        <option value="{{ $kelurahan['id'] }}" @selected((int) $filters['kelurahan_id'] === (int) $kelurahan['id'])>{{ $kelurahan['name'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2 shrink-0">
                <button type="submit"
                        class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 ui-animated shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    Terapkan
                </button>
                <a href="{{ request()->url() }}"
                   class="inline-flex items-center justify-center rounded-xl bg-surface-sunken px-4 py-2.5 text-sm font-semibold text-ink-soft hover:bg-line ui-animated">
                    Reset
                </a>
            </div>
        </form>
    </div>

    {{-- ─── KPI row (D-4.2 #1,#2,#8 + D-4.5) — symmetric 4-column grid ───────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4" role="list" aria-label="Ringkasan rating">
        <x-dashboard.soft-stat-card
            label="Jumlah Rating"
            :value="number_format($total)"
            hint="Total rating masuk (periode & filter terpilih)"
            tone="primary" />

        <x-dashboard.soft-stat-card
            label="Rata-rata Rating"
            :value="$average !== null ? number_format($average, 2).' / 5' : '—'"
            hint="Rata-rata seluruh rating"
            tone="teal" />

        <x-dashboard.soft-stat-card
            label="Tingkat Kepuasan"
            :value="$satisfaction !== null ? number_format($satisfaction, 1).'%' : '—'"
            hint="Rating 4–5 dibanding seluruh rating"
            tone="success" />

        <x-dashboard.soft-stat-card
            label="Selesai Tanpa Rating"
            :value="number_format($unrated)"
            hint="Laporan selesai yang belum diberi rating"
            tone="warning" />
    </div>

    @if (! $hasData)
        {{-- D-4.13: NEVER a fabricated 0% satisfaction — an honest empty state. --}}
        <div class="mb-4">
            <x-dashboard.empty-state message="Belum ada rating pada periode/filter yang dipilih." />
        </div>
    @else
        {{-- ─── Distribution 1–5 (D-4.2 #3 / V) ────────────────────────────── --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 mb-4">
            <div class="{{ $showDinasBreakdown ? 'lg:col-span-5' : 'lg:col-span-12' }}">
                <x-dashboard.soft-card title="Distribusi Rating" subtitle="Jumlah rating per bintang (1–5)">
                    <div class="space-y-4">
                        @foreach ($distribution as $score => $count)
                            <x-dashboard.bar-row
                                :label="'★ '.$score"
                                :count="$count"
                                :max="$maxDist"
                                :tone="$score >= 4 ? 'teal' : ($score <= 2 ? 'rose' : 'amber')" />
                        @endforeach
                    </div>
                </x-dashboard.soft-card>
            </div>

            {{-- ─── Per Dinas/Unit (D-4.2 #5 / AB) — GLOBAL viewers only ─────── --}}
            {{-- D-4.9 / R: an Operator must NEVER see a per-Dinas comparison. --}}
            @if ($showDinasBreakdown)
                <div class="lg:col-span-7">
                    <x-dashboard.soft-card title="Rating per Dinas/Unit" subtitle="Jumlah, rata-rata, dan tingkat kepuasan per Dinas/Unit">
                        @if (count($analytics['byDinas'] ?? []) > 0)
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <caption class="sr-only">Rating per Dinas/Unit</caption>
                                    <thead>
                                        <tr class="text-left text-xs text-ink-faint uppercase tracking-wide">
                                            <th scope="col" class="py-3 pr-3 font-semibold">Dinas/Unit</th>
                                            <th scope="col" class="py-3 px-3 font-semibold text-right">Rating</th>
                                            <th scope="col" class="py-3 px-3 font-semibold text-right">Rata-rata</th>
                                            <th scope="col" class="py-3 pl-3 font-semibold text-right">Kepuasan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-line">
                                        @foreach ($analytics['byDinas'] as $row)
                                            <tr class="hover:bg-page transition-colors">
                                                <td class="py-3.5 pr-3 text-ink font-medium">{{ $row['name'] }}</td>
                                                <td class="py-3.5 px-3 text-right tabular-nums text-ink-soft">{{ number_format($row['count']) }}</td>
                                                <td class="py-3.5 px-3 text-right tabular-nums text-ink-soft">{{ $row['average'] !== null ? number_format($row['average'], 2) : '—' }}</td>
                                                <td class="py-3.5 pl-3 text-right tabular-nums text-ink-soft">{{ $row['satisfactionRate'] !== null ? number_format($row['satisfactionRate'], 1).'%' : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <x-dashboard.empty-state message="Belum ada rating per Dinas/Unit." />
                        @endif
                    </x-dashboard.soft-card>
                </div>
            @endif
        </div>

        {{-- ─── Monthly trend (D-4.2 #7 / Z) ───────────────────────────────── --}}
        <div class="mb-4">
            <x-dashboard.soft-card title="Tren Rating per Bulan" subtitle="Rata-rata rating per bulan (basis tanggal rating)">
                <div class="flex items-end gap-2 sm:gap-3 h-48 overflow-x-auto pt-2">
                    @foreach ($trend as $point)
                        @php
                            $avg = $point['average'];
                            // No-data months render NO bar (0%) — the “—” label and the
                            // caption below already communicate “tanpa data”; a visible
                            // stub would imply a real (tiny) rating. Non-null averages
                            // keep a 6% minimum so low scores stay visible.
                            $height = $avg !== null ? max(6, (int) round(($avg / 5) * 100)) : 0;
                        @endphp
                        <div class="flex-1 min-w-[44px] flex flex-col items-center justify-end h-full gap-2">
                            <span class="text-[11px] font-semibold tabular-nums {{ $avg !== null ? 'text-ink' : 'text-ink-faint' }}">
                                {{ $avg !== null ? number_format($avg, 1) : '—' }}
                            </span>
                            <div class="w-full rounded-t-md {{ $avg !== null ? 'bg-gradient-to-t from-primary-600/65 to-primary-600' : 'bg-line' }}"
                                 style="height: {{ $height }}%"
                                 title="{{ $point['label'] }}: {{ $avg !== null ? number_format($avg, 2).' / 5' : 'tidak ada data' }} ({{ number_format($point['count']) }} rating)"></div>
                            <span class="text-[10px] text-ink-faint whitespace-nowrap">{{ $point['label'] }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="text-[11px] text-ink-faint mt-3">Bulan tanpa rating ditampilkan sebagai “—” (tanpa data), bukan rating 0.</p>
            </x-dashboard.soft-card>
        </div>

        {{-- ─── Per kategori (D-4.2 #6 / AA) ───────────────────────────────── --}}
        <x-dashboard.soft-card title="Rating per Kategori" subtitle="Jumlah, rata-rata, dan tingkat kepuasan per kategori">
            @if (count($analytics['byCategory'] ?? []) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <caption class="sr-only">Rating per Kategori</caption>
                        <thead>
                            <tr class="text-left text-xs text-ink-faint uppercase tracking-wide">
                                <th scope="col" class="py-3 pr-3 font-semibold">Kategori</th>
                                <th scope="col" class="py-3 px-3 font-semibold text-right">Rating</th>
                                <th scope="col" class="py-3 px-3 font-semibold text-right">Rata-rata</th>
                                <th scope="col" class="py-3 pl-3 font-semibold text-right">Kepuasan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($analytics['byCategory'] as $row)
                                <tr class="hover:bg-page transition-colors">
                                    <td class="py-3.5 pr-3 text-ink font-medium">{{ $row['name'] }}</td>
                                    <td class="py-3.5 px-3 text-right tabular-nums text-ink-soft">{{ number_format($row['count']) }}</td>
                                    <td class="py-3.5 px-3 text-right tabular-nums text-ink-soft">{{ $row['average'] !== null ? number_format($row['average'], 2) : '—' }}</td>
                                    <td class="py-3.5 pl-3 text-right tabular-nums text-ink-soft">{{ $row['satisfactionRate'] !== null ? number_format($row['satisfactionRate'], 1).'%' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-dashboard.empty-state message="Belum ada rating per kategori." />
            @endif
        </x-dashboard.soft-card>
    @endif
</section>
