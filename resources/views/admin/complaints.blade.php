@php
    $title = 'Monitoring Laporan — Admin';

    // NOTE (Prompt 8 / P0-3): No SLA is computed or displayed. SLA is not yet an
    // approved business rule (rules.md §1.5 / prd.md). This view only shows real
    // stored data plus the neutral, factual "umur laporan" (age) derived directly
    // from `submitted_at`, which is not an SLA claim.

    // Active filter chips
    $activeFilters = [];
    if (request()->filled('q'))        $activeFilters['q']        = ['label' => 'Cari: "'.e(request('q')).'"', 'key' => 'q'];
    if (request()->filled('status') && is_string(request('status')))   $activeFilters['status']   = ['label' => 'Status: '.(\App\Enums\ComplaintStatus::tryFrom(request('status'))?->label() ?? request('status')), 'key' => 'status'];
    if (request()->filled('category')) {
        $cat = $categories->firstWhere('id', request('category'));
        if ($cat) $activeFilters['category'] = ['label' => 'Kategori: '.$cat->name, 'key' => 'category'];
    }
    if (request()->filled('sort') && request('sort') !== 'newest')
        $activeFilters['sort'] = ['label' => 'Urut: '.(request('sort') === 'oldest' ? 'Terlama' : 'Status'), 'key' => 'sort'];
    if (request('assigned') === 'unassigned')
        $activeFilters['assigned'] = ['label' => 'Belum Ditentukan Dinas/Unit', 'key' => 'assigned'];
@endphp

@push('head')
<style>
/* ── Monitoring Laporan page-specific styles ─────────────────────── */
.kpi-card {
    background: var(--color-surface);
    border: 1px solid var(--color-line);
    border-radius: 12px;
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.875rem;
    transition: box-shadow 200ms ease, border-color 200ms ease;
}
.kpi-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.06); border-color: var(--color-line-strong); }
.kpi-icon {
    width: 2.5rem; height: 2.5rem; border-radius: 10px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.kpi-value { font-size: 1.375rem; font-weight: 700; line-height: 1.15; color: var(--color-ink); }
.kpi-label { font-size: 0.7rem; font-weight: 500; color: var(--color-ink-faint); text-transform: uppercase; letter-spacing: 0.05em; margin-top: 0.1rem; }

/* Filter card — single tidy horizontal row on desktop. */
.filter-card {
    background: var(--color-surface);
    border: 1px solid var(--color-line);
    border-radius: 12px;
    padding: 1rem 1.25rem;
}
.filter-grid {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) 150px 170px 150px auto;
    gap: 0.625rem;
    align-items: end;
}
@media (max-width: 1023px) {
    .filter-grid { grid-template-columns: 1fr 1fr; }
    .filter-grid .filter-field-search { grid-column: 1 / -1; }
}
@media (max-width: 560px) {
    .filter-grid { grid-template-columns: 1fr; }
    .filter-grid .filter-field-search { grid-column: auto; }
}
.filter-field label {
    display: block;
    font-size: 0.7rem; font-weight: 600; color: var(--color-ink-faint);
    text-transform: uppercase; letter-spacing: 0.05em;
    margin-bottom: 0.3rem;
}
.filter-input {
    width: 100%;
    font-size: 0.875rem;
    border: 1px solid var(--color-line);
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
    color: var(--color-ink);
    background: var(--color-page);
    outline: none;
    transition: border-color 150ms ease, box-shadow 150ms ease;
}
.filter-input:focus {
    border-color: var(--color-primary-600);
    box-shadow: 0 0 0 3px rgba(0,119,182,0.12);
    background: var(--color-surface);
}
/* Native <select> chevron. Declared here (a stylesheet, not an inline
   attribute) so the data-URI's double quotes can never terminate the HTML
   attribute early. The previous inline attribute used backslash-escaped
   quotes, which HTML does not treat as escapes: the value was truncated at
   the first quote, leaving a dangling CSS backslash escape that resolves to
   the replacement character and produced a spurious `GET /admin/%EF%BF%BD`
   404. */
.filter-select {
    padding-right: 2rem;
    appearance: none;
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.5rem center;
}
.btn-filter {
    background: var(--color-primary-600); color: var(--color-surface);
    font-size: 0.8125rem; font-weight: 600;
    padding: 0.5rem 1rem; border-radius: 8px;
    border: none; cursor: pointer; white-space: nowrap;
    transition: background 150ms ease;
}
.btn-filter:hover { background: var(--color-primary-800); }
.btn-reset {
    background: var(--color-surface-sunken); color: var(--color-ink-soft);
    font-size: 0.8125rem; font-weight: 500;
    padding: 0.5rem 0.875rem; border-radius: 8px;
    border: 1px solid var(--color-line); cursor: pointer; white-space: nowrap;
    transition: background 150ms ease, color 150ms ease;
}
.btn-reset:hover { background: var(--color-line); color: var(--color-ink); }

/* Filter chip */
.filter-chip {
    display: inline-flex; align-items: center; gap: 0.375rem;
    padding: 0.25rem 0.625rem 0.25rem 0.75rem;
    background: var(--color-primary-100); color: var(--color-primary-800);
    border-radius: 9999px; font-size: 0.7rem; font-weight: 600;
}
.filter-chip a { color: var(--color-primary-800); line-height: 1; font-size: 0.8rem; }
.filter-chip a:hover { color: var(--color-error); }

/* Table */
.ml-table { width: 100%; font-size: 0.8125rem; border-collapse: collapse; }
.ml-table thead th {
    background: var(--color-page);
    padding: 0.75rem 1rem;
    text-align: left;
    font-size: 0.6875rem; font-weight: 700;
    color: var(--color-ink-faint); text-transform: uppercase; letter-spacing: 0.06em;
    border-bottom: 1px solid var(--color-line);
    white-space: nowrap;
}
.ml-table thead th.sortable { cursor: pointer; user-select: none; }
.ml-table thead th.sortable:hover { color: var(--color-primary-800); }
.ml-table tbody tr {
    border-bottom: 1px solid var(--color-line);
    transition: background-color 120ms ease;
}
/* Subtle zebra striping for easier row tracking. */
.ml-table tbody tr:nth-child(even) { background-color: var(--color-surface); }
.ml-table tbody tr:hover { background-color: var(--color-primary-50); }
/* Generous vertical rhythm so stacked rows never feel cramped. */
.ml-table tbody td { padding: 0.9375rem 1rem; vertical-align: middle; }

/* Row cell typography — clearer secondary-text contrast. */
.ml-ref {
    font-family: ui-monospace, 'Cascadia Code', monospace;
    font-size: 0.7rem; font-weight: 600; color: var(--color-ink-body);
    background: var(--color-surface-sunken); padding: 0.2rem 0.45rem;
    border-radius: 5px; border: 1px solid var(--color-line);
    white-space: nowrap;
}
.ml-title {
    font-weight: 700; color: var(--color-ink); margin: 0;
    max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.ml-reporter { font-size: 0.72rem; color: var(--color-ink-faint); margin: 0.35rem 0 0; }
.ml-muted { color: var(--color-ink-soft); font-size: 0.75rem; }
.ml-date { color: var(--color-ink-soft); font-size: 0.75rem; white-space: nowrap; }
.ml-time { color: var(--color-ink-faint); font-size: 0.7rem; }

/* Aksi — clean ghost/secondary pill (read-only: not a link). */
.btn-detail {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.35rem 0.7rem;
    background: var(--color-surface); color: var(--color-primary-700);
    border: 1px solid var(--color-line-strong); border-radius: 8px;
    font-size: 0.75rem; font-weight: 600;
    text-decoration: none;
    white-space: nowrap;
    cursor: default;
}

/* Pagination */
.pagination-wrap {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 0.75rem;
    padding: 0.875rem 1.25rem;
    border-top: 1px solid var(--color-line);
    font-size: 0.8rem; color: var(--color-ink-faint);
}
</style>
@endpush

<x-layouts.dashboard :title="$title">
    {{-- ── Navigation slot ───────────────────────────────────────────────── --}}
    <x-slot:navigation>
        <x-dashboard.nav-rail-link :href="route('admin.dashboard')" label="Monitoring">
            <x-slot:icon>
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </x-slot:icon>
        </x-dashboard.nav-rail-link>
        <x-dashboard.nav-rail-link :href="route('admin.complaints')" label="Monitoring Laporan" :active="true">
            <x-slot:icon>
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </x-slot:icon>
        </x-dashboard.nav-rail-link>
    </x-slot:navigation>

    {{-- ── Page header slots ─────────────────────────────────────────────── --}}
    <x-slot:header>Monitoring Laporan</x-slot:header>
    <x-slot:breadcrumb>Pantau laporan masyarakat secara read-only</x-slot:breadcrumb>

    {{-- ══════════════════════════════════════════════════════════════════════
         KPI CARDS
    ══════════════════════════════════════════════════════════════════════════ --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.875rem;margin-bottom:1.25rem;">

        {{-- Total Laporan --}}
        <div class="kpi-card card-enter stagger-item">
            <div class="kpi-icon" style="background:var(--color-primary-50);">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#0131C1" stroke-width="2">
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
            <div class="kpi-icon" style="background:var(--color-primary-100);">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#013FF6" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
            </div>
            <div>
                <div class="kpi-value" style="color:var(--color-primary-700);">{{ number_format($kpi['baru']) }}</div>
                <div class="kpi-label">Baru / Dikirim</div>
            </div>
        </div>

        {{-- Sedang Diproses --}}
        <div class="kpi-card card-enter stagger-item">
            <div class="kpi-icon" style="background:var(--color-primary-100);">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#013FF6" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </div>
            <div>
                <div class="kpi-value" style="color:var(--color-primary-600);">{{ number_format($kpi['sedang_diproses']) }}</div>
                <div class="kpi-label">Sedang Diproses</div>
            </div>
        </div>

        {{-- Belum Ditentukan Dinas/Unit (real count) — PHASE 6C Decision 3 --}}
        <div class="kpi-card card-enter stagger-item">
            <div class="kpi-icon" style="background:var(--color-accent-50);">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#C2410C" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
            </div>
            <div>
                <div class="kpi-value" style="color:var(--color-warning);">{{ number_format($kpi['belum_dinas_unit']) }}</div>
                <div class="kpi-label">Belum Ditentukan Dinas/Unit</div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         SEARCH & FILTER
    ══════════════════════════════════════════════════════════════════════════ --}}
    <div class="filter-card card-enter" style="margin-bottom:0.875rem;">
        <form method="GET" action="{{ route('admin.complaints') }}" id="filter-form">
            @if (request('assigned') === 'unassigned')
                <input type="hidden" name="assigned" value="unassigned">
            @endif
            {{-- Single tidy horizontal row on desktop (wraps on smaller screens). --}}
            <div class="filter-grid">

                {{-- Search --}}
                <div class="filter-field filter-field-search">
                    <label for="search-q">Cari</label>
                    <input id="search-q" type="search" name="q" value="{{ request('q') }}"
                           class="filter-input"
                           placeholder="No. referensi, judul, atau nama pelapor…"
                           autocomplete="off">
                </div>

                {{-- Status --}}
                <div class="filter-field">
                    <label for="filter-status">Status</label>
                    <select id="filter-status" name="status" class="filter-input filter-select">
                        <option value="">Semua Status</option>
                        {{-- The filter lists only the ACTIVE workflow statuses
                             directly (no group header); legacy (history-only)
                             statuses are not listed, while the backend still
                             accepts their values via the `status` query string. --}}
                        @foreach (array_merge(\App\Enums\ComplaintStatus::activeCases(), \App\Enums\ComplaintStatus::finalOutcomeCases()) as $st)
                            <option value="{{ $st->value }}" {{ request('status') === $st->value ? 'selected' : '' }}>
                                {{ $st->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Kategori --}}
                <div class="filter-field">
                    <label for="filter-category">Kategori</label>
                    <select id="filter-category" name="category" class="filter-input filter-select">
                        <option value="">Semua Kategori</option>
                        {{-- Admin is a GLOBAL monitoring role (no Dinas/Unit), so
                             this list is intentionally the full category set. --}}
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Sort --}}
                <div class="filter-field">
                    <label for="filter-sort">Urutkan</label>
                    <select id="filter-sort" name="sort" class="filter-input filter-select">
                        <option value="newest" {{ request('sort','newest') === 'newest' ? 'selected' : '' }}>Terbaru Dulu</option>
                        <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>Terlama Dulu</option>
                        <option value="status" {{ request('sort') === 'status'  ? 'selected' : '' }}>Per Status</option>
                    </select>
                </div>

                {{-- Buttons --}}
                <div class="filter-field" style="display:flex;gap:0.5rem;">
                    <button type="submit" class="btn-filter">
                        <svg style="display:inline;vertical-align:-2px;margin-right:3px;" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                        Filter
                    </button>
                    @if (request()->hasAny(['q', 'status', 'category', 'sort', 'assigned']))
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
    <div style="background:var(--color-surface);border:1px solid var(--color-line);border-radius:12px;overflow:hidden;" class="card-enter">

        {{-- Table header bar --}}
        <div style="padding:0.875rem 1.25rem;border-bottom:1px solid var(--color-line);display:flex;align-items:center;justify-content:space-between;">
            <div>
                <h2 style="font-size:0.9375rem;font-weight:700;color:var(--color-ink);margin:0;">Daftar Laporan</h2>
                <p style="font-size:0.75rem;color:var(--color-ink-faint);margin:0.1rem 0 0;">
                    Menampilkan {{ $laporan->firstItem() ?? 0 }}–{{ $laporan->lastItem() ?? 0 }}
                    dari {{ number_format($laporan->total()) }} laporan
                </p>
            </div>
            <span style="font-size:0.7rem;font-weight:700;padding:0.25rem 0.625rem;border-radius:9999px;background:var(--color-primary-50);color:var(--color-primary-600);border:1px solid var(--color-primary-200);letter-spacing:0.04em;">
                READ-ONLY
            </span>
        </div>

        @if ($laporan->isEmpty())
            <div class="empty-state">
                <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="#CBD5E1" stroke-width="1.5" style="margin:0 auto 0.75rem;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <p style="font-size:0.9rem;color:var(--color-ink-faint);font-weight:500;">Tidak ada laporan yang sesuai dengan filter.</p>
                <p style="font-size:0.8rem;color:var(--color-ink-ghost);margin-top:0.25rem;">Coba ubah atau reset filter pencarian.</p>
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
                                    <span class="ml-ref">{{ $item->reference_code }}</span>
                                </td>

                                {{-- Judul + Pelapor --}}
                                <td>
                                    <p class="ml-title" title="{{ $item->title }}">
                                        {{ $item->title }}
                                    </p>
                                    <p class="ml-reporter">
                                        {{ $item->reporter?->name ?? '—' }}
                                    </p>
                                </td>

                                {{-- Kategori --}}
                                <td class="hidden sm:table-cell ml-muted">
                                    {{ $item->category?->name ?? '—' }}
                                </td>

                                {{-- Dinas / Unit --}}
                                <td class="hidden md:table-cell" style="font-size:0.75rem;">
                                    @if($item->category?->dinas_name)
                                        <span style="color:var(--color-ink-body);">{{ $item->category->dinas_name }}</span>
                                    @else
                                        <span style="color:var(--color-ink-ghost);">—</span>
                                    @endif
                                </td>

                                {{-- Umur Laporan (factual age, not SLA) --}}
                                <td class="hidden lg:table-cell ml-muted" style="white-space:nowrap;">
                                    @if (is_null($ageDays))
                                        <span style="color:var(--color-ink-ghost);">—</span>
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
                                <td class="hidden lg:table-cell ml-date">
                                    {{ $item->submitted_at?->translatedFormat('d M Y') }}
                                    <br>
                                    <span class="ml-time">{{ $item->submitted_at?->translatedFormat('H:i') }}</span>
                                </td>

                                {{-- Aksi — read-only ghost button. Admin is monitoring
                                     only and has NO detail route, so this is an honest,
                                     non-interactive affordance (never a dead link). --}}
                                <td style="text-align:center;">
                                    <span class="btn-detail"
                                          title="Admin hanya memantau (read-only) — detail laporan dibuka oleh Operator atau Super Admin">
                                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        Lihat
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
                        <strong style="color:var(--color-ink);">{{ number_format($laporan->firstItem()) }}–{{ number_format($laporan->lastItem()) }}</strong>
                        dari <strong style="color:var(--color-ink);">{{ number_format($laporan->total()) }}</strong> laporan
                    </span>
                    <nav aria-label="Navigasi halaman laporan">
                        {{ $laporan->onEachSide(1)->links() }}
                    </nav>
                </div>
            @endif
        @endif
    </div>
</x-layouts.dashboard>
