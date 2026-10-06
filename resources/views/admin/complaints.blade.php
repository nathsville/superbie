@php
    $title = 'Monitoring Laporan — Admin';

    // NOTE (Prompt 8 / P0-3): No SLA is computed or displayed. SLA is not yet an
    // approved business rule (rules.md §1.5 / prd.md). This view only shows real
    // stored data plus the neutral, factual "umur laporan" (age) derived directly
    // from `submitted_at`, which is not an SLA claim.

    // Active filter chips
    $activeFilters = [];
    if (request()->filled('q'))        $activeFilters['q']        = ['label' => 'Cari: "'.e(request('q')).'"', 'key' => 'q'];
    if (request()->filled('status'))   $activeFilters['status']   = ['label' => 'Status: '.(\App\Enums\ComplaintStatus::from(request('status'))->label()), 'key' => 'status'];
    if (request()->filled('category')) {
        $cat = $categories->firstWhere('id', request('category'));
        if ($cat) $activeFilters['category'] = ['label' => 'Kategori: '.$cat->name, 'key' => 'category'];
    }
    if (request()->filled('sort') && request('sort') !== 'newest')
        $activeFilters['sort'] = ['label' => 'Urut: '.(request('sort') === 'oldest' ? 'Terlama' : 'Status'), 'key' => 'sort'];
@endphp

<style>
/* ── Monitoring Laporan page-specific styles ─────────────────────── */
.kpi-card {
    background: #fff;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.875rem;
    transition: box-shadow 200ms ease, border-color 200ms ease;
}
.kpi-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.06); border-color: #CBD5E1; }
.kpi-icon {
    width: 2.5rem; height: 2.5rem; border-radius: 10px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.kpi-value { font-size: 1.375rem; font-weight: 700; line-height: 1.15; color: #0F172A; }
.kpi-label { font-size: 0.7rem; font-weight: 500; color: #64748B; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 0.1rem; }

.filter-card {
    background: #fff;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 1rem 1.25rem;
}
.filter-input {
    width: 100%;
    font-size: 0.875rem;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
    color: #0F172A;
    background: #F8FAFC;
    outline: none;
    transition: border-color 150ms ease, box-shadow 150ms ease;
}
.filter-input:focus {
    border-color: #0077B6;
    box-shadow: 0 0 0 3px rgba(0,119,182,0.12);
    background: #fff;
}
.btn-filter {
    background: #0077B6; color: #fff;
    font-size: 0.8125rem; font-weight: 600;
    padding: 0.5rem 1rem; border-radius: 8px;
    border: none; cursor: pointer; white-space: nowrap;
    transition: background 150ms ease;
}
.btn-filter:hover { background: #03045E; }
.btn-reset {
    background: #F1F5F9; color: #475569;
    font-size: 0.8125rem; font-weight: 500;
    padding: 0.5rem 0.875rem; border-radius: 8px;
    border: 1px solid #E2E8F0; cursor: pointer; white-space: nowrap;
    transition: background 150ms ease, color 150ms ease;
}
.btn-reset:hover { background: #E2E8F0; color: #0F172A; }

/* Filter chip */
.filter-chip {
    display: inline-flex; align-items: center; gap: 0.375rem;
    padding: 0.25rem 0.625rem 0.25rem 0.75rem;
    background: #CAF0F8; color: #03045E;
    border-radius: 9999px; font-size: 0.7rem; font-weight: 600;
}
.filter-chip a { color: #03045E; line-height: 1; font-size: 0.8rem; }
.filter-chip a:hover { color: #B91C1C; }

/* Table */
.ml-table { width: 100%; font-size: 0.8125rem; border-collapse: collapse; }
.ml-table thead th {
    background: #F8FAFC;
    padding: 0.75rem 1rem;
    text-align: left;
    font-size: 0.6875rem; font-weight: 700;
    color: #64748B; text-transform: uppercase; letter-spacing: 0.06em;
    border-bottom: 1px solid #E2E8F0;
    white-space: nowrap;
}
.ml-table thead th.sortable { cursor: pointer; user-select: none; }
.ml-table thead th.sortable:hover { color: #03045E; }
.ml-table tbody tr {
    border-bottom: 1px solid #F1F5F9;
    transition: background-color 120ms ease;
}
.ml-table tbody tr:hover { background-color: #F0F9FF; }
.ml-table tbody td { padding: 0.75rem 1rem; vertical-align: middle; }

/* Detail button */
.btn-detail {
    display: inline-flex; align-items: center; gap: 0.25rem;
    padding: 0.3rem 0.65rem;
    background: #EFF6FF; color: #1D4ED8;
    border: 1px solid #BFDBFE; border-radius: 7px;
    font-size: 0.75rem; font-weight: 600;
    text-decoration: none;
    transition: background 150ms ease, border-color 150ms ease;
    white-space: nowrap;
}
.btn-detail:hover { background: #DBEAFE; border-color: #93C5FD; }

/* Pagination */
.pagination-wrap {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 0.75rem;
    padding: 0.875rem 1.25rem;
    border-top: 1px solid #E2E8F0;
    font-size: 0.8rem; color: #64748B;
}
</style>

<x-layouts.dashboard :title="$title">
    {{-- ── Navigation slot ───────────────────────────────────────────────── --}}
    <x-slot:navigation>
        <div class="space-y-1">
            <a href="{{ route('admin.dashboard') }}" class="nav-link">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Monitoring
            </a>
            <a href="{{ route('admin.complaints') }}" class="nav-link active" aria-current="page">
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

    {{-- ── Page header slots ─────────────────────────────────────────────── --}}
    <x-slot:header>Monitoring Laporan</x-slot:header>
    <x-slot:breadcrumb>Pantau laporan masyarakat secara read-only</x-slot:breadcrumb>
    <x-slot:headerActions>
        <span style="background:#03045E;color:#fff;font-size:0.8rem;font-weight:700;padding:0.375rem 0.875rem;border-radius:8px;white-space:nowrap;">
            {{ number_format($kpi['total']) }} Laporan Total
        </span>
    </x-slot:headerActions>

    {{-- ══════════════════════════════════════════════════════════════════════
         KPI CARDS
    ══════════════════════════════════════════════════════════════════════════ --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.875rem;margin-bottom:1.25rem;">

        {{-- Total Laporan --}}
        <div class="kpi-card card-enter stagger-item">
            <div class="kpi-icon" style="background:#EFF6FF;">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#1D4ED8" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <div>
                <div class="kpi-value">{{ number_format($kpi['total']) }}</div>
                <div class="kpi-label">Total Laporan</div>
            </div>
        </div>

        {{-- Baru --}}
        <div class="kpi-card card-enter stagger-item">
            <div class="kpi-icon" style="background:#DBEAFE;">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#2563EB" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
            </div>
            <div>
                <div class="kpi-value" style="color:#1D4ED8;">{{ number_format($kpi['baru']) }}</div>
                <div class="kpi-label">Baru / Dikirim</div>
            </div>
        </div>

        {{-- Sedang Diproses --}}
        <div class="kpi-card card-enter stagger-item">
            <div class="kpi-icon" style="background:#E0F2FE;">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#0077B6" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </div>
            <div>
                <div class="kpi-value" style="color:#0077B6;">{{ number_format($kpi['sedang_diproses']) }}</div>
                <div class="kpi-label">Sedang Diproses</div>
            </div>
        </div>

        {{-- Belum Ditugaskan (real count) --}}
        <div class="kpi-card card-enter stagger-item">
            <div class="kpi-icon" style="background:#FFF7ED;">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#C2410C" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
            </div>
            <div>
                <div class="kpi-value" style="color:#C2410C;">{{ number_format($kpi['belum_ditugaskan']) }}</div>
                <div class="kpi-label">Belum Ditugaskan</div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         SEARCH & FILTER
    ══════════════════════════════════════════════════════════════════════════ --}}
    <div class="filter-card card-enter" style="margin-bottom:0.875rem;">
        <form method="GET" action="{{ route('admin.complaints') }}" id="filter-form">
            {{-- Row 1: Search + Dropdowns + Buttons --}}
            <div style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:0.625rem;">

                {{-- Search --}}
                <div style="flex:1;min-width:220px;">
                    <label for="search-q" style="display:block;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.3rem;">Cari</label>
                    <input id="search-q" type="search" name="q" value="{{ request('q') }}"
                           class="filter-input"
                           placeholder="No. referensi, judul, atau nama pelapor…"
                           autocomplete="off">
                </div>

                {{-- Status --}}
                <div style="min-width:145px;">
                    <label for="filter-status" style="display:block;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.3rem;">Status</label>
                    <select id="filter-status" name="status" class="filter-input" style="padding-right:2rem;appearance:none;background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E\");background-repeat:no-repeat;background-position:right 0.5rem center;">
                        <option value="">Semua Status</option>
                        @foreach (\App\Enums\ComplaintStatus::cases() as $st)
                            <option value="{{ $st->value }}" {{ request('status') === $st->value ? 'selected' : '' }}>
                                {{ $st->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Kategori --}}
                <div style="min-width:145px;">
                    <label for="filter-category" style="display:block;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.3rem;">Kategori</label>
                    <select id="filter-category" name="category" class="filter-input" style="padding-right:2rem;appearance:none;background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E\");background-repeat:no-repeat;background-position:right 0.5rem center;">
                        <option value="">Semua Kategori</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Sort --}}
                <div style="min-width:130px;">
                    <label for="filter-sort" style="display:block;font-size:0.7rem;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.3rem;">Urutkan</label>
                    <select id="filter-sort" name="sort" class="filter-input" style="padding-right:2rem;appearance:none;background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E\");background-repeat:no-repeat;background-position:right 0.5rem center;">
                        <option value="newest" {{ request('sort','newest') === 'newest' ? 'selected' : '' }}>Terbaru Dulu</option>
                        <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>Terlama Dulu</option>
                        <option value="status" {{ request('sort') === 'status'  ? 'selected' : '' }}>Per Status</option>
                    </select>
                </div>

                {{-- Buttons --}}
                <div style="display:flex;gap:0.5rem;padding-bottom:0.05rem;">
                    <button type="submit" class="btn-filter">
                        <svg style="display:inline;vertical-align:-2px;margin-right:3px;" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                        Filter
                    </button>
                    @if (request()->hasAny(['q', 'status', 'category', 'sort']))
                        <a href="{{ route('admin.complaints') }}" class="btn-reset" style="display:inline-flex;align-items:center;text-decoration:none;">
                            Reset
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    {{-- ── Active filter chips ────────────────────────────────────────────── --}}
    @if (count($activeFilters))
        <div style="display:flex;flex-wrap:wrap;gap:0.375rem;margin-bottom:0.875rem;">
            @foreach ($activeFilters as $chip)
                <span class="filter-chip">
                    {{ $chip['label'] }}
                    <a href="{{ request()->fullUrlWithoutQuery($chip['key']) }}" title="Hapus filter" aria-label="Hapus {{ $chip['key'] }}">✕</a>
                </span>
            @endforeach
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════
         DATA TABLE
    ══════════════════════════════════════════════════════════════════════════ --}}
    <div style="background:#fff;border:1px solid #E2E8F0;border-radius:12px;overflow:hidden;" class="card-enter">

        {{-- Table header bar --}}
        <div style="padding:0.875rem 1.25rem;border-bottom:1px solid #E2E8F0;display:flex;align-items:center;justify-content:space-between;">
            <div>
                <h2 style="font-size:0.9375rem;font-weight:700;color:#0F172A;margin:0;">Daftar Laporan</h2>
                <p style="font-size:0.75rem;color:#64748B;margin:0.1rem 0 0;">
                    Menampilkan {{ $laporan->firstItem() ?? 0 }}–{{ $laporan->lastItem() ?? 0 }}
                    dari {{ number_format($laporan->total()) }} laporan
                </p>
            </div>
            <span style="font-size:0.7rem;font-weight:700;padding:0.25rem 0.625rem;border-radius:9999px;background:#F0F9FF;color:#0077B6;border:1px solid #BAE6FD;letter-spacing:0.04em;">
                READ-ONLY
            </span>
        </div>

        @if ($laporan->isEmpty())
            <div class="empty-state">
                <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="#CBD5E1" stroke-width="1.5" style="margin:0 auto 0.75rem;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <p style="font-size:0.9rem;color:#64748B;font-weight:500;">Tidak ada laporan yang sesuai dengan filter.</p>
                <p style="font-size:0.8rem;color:#94A3B8;margin-top:0.25rem;">Coba ubah atau reset filter pencarian.</p>
            </div>
        @else
            <div style="overflow-x:auto;">
                <table class="ml-table" aria-label="Tabel monitoring laporan masyarakat">
                    <thead>
                        <tr>
                            <th style="width:130px;">No. Referensi</th>
                            <th>Judul Laporan</th>
                            <th class="hidden sm:table-cell" style="width:130px;">Kategori</th>
                            <th class="hidden md:table-cell" style="width:150px;">Dinas / Unit</th>
                            <th class="hidden lg:table-cell" style="width:120px;">Umur Laporan</th>
                            <th style="width:130px;">Status</th>
                            <th class="hidden lg:table-cell" style="width:120px;">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => request('sort') === 'oldest' ? 'newest' : 'oldest']) }}"
                                   style="color:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:3px;">
                                    Tanggal
                                    <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        @if(request('sort') === 'oldest')
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>
                                        @else
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                        @endif
                                    </svg>
                                </a>
                            </th>
                            <th style="width:80px;text-align:center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($laporan as $item)
                            @php
                                // Factual age derived from stored submitted_at (not an SLA claim).
                                $ageDays = $item->submitted_at ? (int) $item->submitted_at->diffInDays(now()) : null;
                            @endphp
                            <tr>
                                {{-- No. Referensi --}}
                                <td>
                                    <span style="font-family:ui-monospace,'Cascadia Code',monospace;font-size:0.7rem;font-weight:600;color:#475569;background:#F8FAFC;padding:0.15rem 0.4rem;border-radius:5px;border:1px solid #E2E8F0;">
                                        {{ $item->reference_code }}
                                    </span>
                                </td>

                                {{-- Judul + Pelapor --}}
                                <td>
                                    <p style="font-weight:600;color:#0F172A;margin:0;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $item->title }}">
                                        {{ $item->title }}
                                    </p>
                                    <p style="font-size:0.7rem;color:#94A3B8;margin:0.15rem 0 0;">
                                        {{ $item->reporter?->name ?? '—' }}
                                    </p>
                                </td>

                                {{-- Kategori --}}
                                <td class="hidden sm:table-cell" style="color:#475569;font-size:0.75rem;">
                                    {{ $item->category?->name ?? '—' }}
                                </td>

                                {{-- Dinas / Unit --}}
                                <td class="hidden md:table-cell" style="font-size:0.75rem;">
                                    @if($item->category?->dinas_name)
                                        <span style="color:#0F172A;">{{ $item->category->dinas_name }}</span>
                                    @else
                                        <span style="color:#CBD5E1;">—</span>
                                    @endif
                                </td>

                                {{-- Umur Laporan (factual age, not SLA) --}}
                                <td class="hidden lg:table-cell" style="color:#475569;font-size:0.75rem;white-space:nowrap;">
                                    @if (is_null($ageDays))
                                        <span style="color:#CBD5E1;">—</span>
                                    @elseif ($ageDays === 0)
                                        Hari ini
                                    @else
                                        {{ $ageDays }} hari
                                    @endif
                                </td>

                                {{-- Status badge --}}
                                <td>
                                    @php $status = $item->status; @endphp
                                    <span class="status-badge {{ $status->tailwindBadge() }}">
                                        <span style="width:6px;height:6px;border-radius:50%;background:currentColor;flex-shrink:0;" aria-hidden="true"></span>
                                        {{ $status->label() }}
                                    </span>
                                </td>

                                {{-- Tanggal --}}
                                <td class="hidden lg:table-cell" style="color:#64748B;font-size:0.75rem;white-space:nowrap;">
                                    {{ $item->submitted_at?->translatedFormat('d M Y') }}
                                    <br>
                                    <span style="color:#94A3B8;font-size:0.7rem;">{{ $item->submitted_at?->translatedFormat('H:i') }}</span>
                                </td>

                                {{-- Aksi --}}
                                <td style="text-align:center;">
                                    <span class="btn-detail" title="Lihat detail laporan {{ $item->reference_code }}" style="cursor:default;">
                                        Detail
                                        <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- ── Pagination footer ─────────────────────────────────────────── --}}
            @if ($laporan->hasPages())
                <div class="pagination-wrap">
                    <span>
                        Menampilkan
                        <strong style="color:#0F172A;">{{ number_format($laporan->firstItem()) }}–{{ number_format($laporan->lastItem()) }}</strong>
                        dari <strong style="color:#0F172A;">{{ number_format($laporan->total()) }}</strong> laporan
                    </span>
                    <nav aria-label="Navigasi halaman laporan">
                        {{ $laporan->onEachSide(1)->links() }}
                    </nav>
                </div>
            @endif
        @endif
    </div>
</x-layouts.dashboard>
