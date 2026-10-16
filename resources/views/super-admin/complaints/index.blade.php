@php
    $title = 'Semua Laporan — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';

    // Active filter chips (only for filters that are actually applied).
    // Values are kept RAW here: the chip is escaped ONCE by `{{ }}` at render
    // time, so pre-escaping with e() would double-encode (e.g. O'Brien).
    $activeFilters = [];
    if (!empty($filters['q']))          $activeFilters['q']          = 'Cari: "'.$filters['q'].'"';
    // Only surface a status chip for a status the system actually recognises.
    // An unknown value is ignored by the query, so echoing it would falsely
    // imply the filter was applied.
    if (!empty($filters['status']) && is_string($filters['status']) && \App\Enums\ComplaintStatus::tryFrom($filters['status']) !== null) {
        $activeFilters['status'] = 'Status: '.\App\Enums\ComplaintStatus::tryFrom($filters['status'])->label();
    }
    if (!empty($filters['category']))   $activeFilters['category']   = 'Kategori: '.($categories->firstWhere('id', (int) $filters['category'])?->name ?? '—');
    if (!empty($filters['dinas_unit'])) $activeFilters['dinas_unit'] = 'Dinas/Unit: '.($dinasUnits->firstWhere('id', (int) $filters['dinas_unit'])?->name ?? '—');
    if (!empty($filters['date_from']))  $activeFilters['date_from']  = 'Dari: '.$filters['date_from'];
    if (!empty($filters['date_to']))    $activeFilters['date_to']    = 'Sampai: '.$filters['date_to'];

    $fieldCls = 'w-full px-3.5 py-2.5 text-sm text-ink border border-line rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Semua Laporan</x-slot:header>
    <x-slot:breadcrumb>Pemantauan & inspeksi laporan (read-only)</x-slot:breadcrumb>

    <div class="w-full max-w-full space-y-6">
        {{-- ── Summary bar ──────────────────────────────────────────────────── --}}
        <div class="flex items-center justify-between flex-wrap gap-3">
            <p class="text-sm text-ink-faint">
                Total <strong class="text-ink">{{ $complaints->total() }}</strong> laporan
            </p>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-surface-sunken text-ink-soft border border-line">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                Read-only • Tidak dapat diubah
            </span>
        </div>

        {{-- ── Filter panel (symmetric 4-column grid) ───────────────────────── --}}
        <h2 class="sr-only">Filter dan pencarian laporan</h2>
        <div class="bg-white rounded-2xl border border-line p-5 sm:p-6 shadow-sm card-enter">
            <form method="GET" action="{{ route('super-admin.complaints.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" role="search" aria-label="Filter dan pencarian laporan">
                <div class="sm:col-span-2">
                    <label for="q" class="block text-xs font-semibold text-ink-soft mb-1.5">Cari</label>
                    <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}"
                        placeholder="Nomor registrasi, judul, atau deskripsi"
                        class="{{ $fieldCls }}">
                </div>
                <div>
                    <label for="status" class="block text-xs font-semibold text-ink-soft mb-1.5">Status</label>
                    <select id="status" name="status" class="{{ $fieldCls }}">
                        <option value="">Semua Status</option>
                        {{-- The filter lists only the ACTIVE workflow statuses directly
                             (no group header); legacy (history-only) statuses are not
                             listed, while the backend still accepts their values via
                             the `status` query string. --}}
                        @foreach (array_merge(\App\Enums\ComplaintStatus::activeCases(), \App\Enums\ComplaintStatus::finalOutcomeCases()) as $s)
                            <option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="category" class="block text-xs font-semibold text-ink-soft mb-1.5">Kategori</label>
                    <select id="category" name="category" class="{{ $fieldCls }}">
                        <option value="">Semua Kategori</option>
                        {{-- Super Admin sees the complete, global category list. --}}
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" @selected((string) ($filters['category'] ?? '') === (string) $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="dinas_unit" class="block text-xs font-semibold text-ink-soft mb-1.5">Dinas/Unit Tujuan</label>
                    <select id="dinas_unit" name="dinas_unit" class="{{ $fieldCls }}">
                        <option value="">Semua Dinas/Unit</option>
                        @foreach ($dinasUnits as $unit)
                            <option value="{{ $unit->id }}" @selected((string) ($filters['dinas_unit'] ?? '') === (string) $unit->id)>{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="date_from" class="block text-xs font-semibold text-ink-soft mb-1.5">Tanggal Dari</label>
                    <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" class="{{ $fieldCls }}">
                </div>
                <div>
                    <label for="date_to" class="block text-xs font-semibold text-ink-soft mb-1.5">Tanggal Sampai</label>
                    <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" class="{{ $fieldCls }}">
                </div>
                <div>
                    <label for="sort" class="block text-xs font-semibold text-ink-soft mb-1.5">Urutkan</label>
                    <select id="sort" name="sort" class="{{ $fieldCls }}">
                        <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Terbaru</option>
                        <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Terlama</option>
                        <option value="status" @selected(($filters['sort'] ?? '') === 'status')>Status</option>
                    </select>
                </div>
                <div class="sm:col-span-2 lg:col-span-4 flex items-center justify-end gap-3 pt-1">
                    @if (!empty($activeFilters))
                        <a href="{{ route('super-admin.complaints.index') }}" class="text-sm font-medium text-ink-faint hover:text-ink hover:underline ui-animated">Reset</a>
                    @endif
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        Filter
                    </button>
                </div>
            </form>

            @if (!empty($activeFilters))
                <div class="mt-4 flex flex-wrap gap-2 pt-4 border-t border-surface-sunken">
                    @foreach ($activeFilters as $key => $label)
                        <span class="inline-flex items-center gap-1 text-[11px] font-medium bg-primary-50 text-primary-700 border border-primary-200 px-2.5 py-1 rounded-full">
                            {{ $label }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ── Data table ───────────────────────────────────────────────────── --}}
        <h2 class="sr-only">Daftar laporan</h2>
        <div class="w-full bg-white rounded-2xl border border-line shadow-sm overflow-hidden card-enter">
            @if ($complaints->isEmpty())
                <div class="empty-state py-16 text-center">
                    <svg class="w-12 h-12 text-line-strong mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <p class="text-sm font-semibold text-ink-soft">Belum ada laporan yang sesuai dengan filter.</p>
                    <p class="text-xs text-ink-faint mt-1">Ubah filter atau kata kunci pencarian untuk melihat hasil lain.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <caption class="sr-only">Daftar laporan warga beserta status dan tujuan Dinas/Unit</caption>
                        <thead class="bg-page text-ink-soft text-xs uppercase tracking-wider">
                            <tr>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Nomor Registrasi</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold">Judul / Kategori</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Pelapor</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Tujuan</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Status</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Operator (Dinas/Unit)</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Dikirim</th>
                                <th scope="col" class="text-right px-4 py-3.5 font-semibold whitespace-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-sunken">
                            @foreach ($complaints as $laporan)
                                @php $status = $laporan->status; @endphp
                                <tr class="hover:bg-page/50 transition-colors">
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="font-mono text-xs text-ink-soft bg-page px-2 py-1 rounded-lg border border-line">{{ $laporan->reference_code }}</span>
                                    </td>
                                    <td class="px-4 py-4 min-w-[16rem]">
                                        <p class="font-semibold text-ink line-clamp-2">{{ $laporan->title }}</p>
                                        <p class="text-xs text-ink-faint mt-1">{{ $laporan->category?->name ?? '—' }}</p>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-ink-soft">{{ $laporan->reporter?->name ?? '—' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-ink-soft">
                                        {{ $laporan->dinasUnit?->name ?? 'Belum ditentukan' }}
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="status-badge {{ $status->tailwindBadge() }}">
                                            <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                            {{ $status->label() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-ink-soft">{{ $laporan->dinasUnit?->operator?->name ?? '—' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-xs text-ink-faint">{{ $laporan->submitted_at?->translatedFormat('d M Y') ?? '—' }}</td>
                                    <td class="px-4 py-4 text-right whitespace-nowrap">
                                        <a href="{{ route('super-admin.complaints.show', $laporan) }}" aria-label="Buka laporan {{ $laporan->reference_code }}"
                                           class="inline-flex items-center gap-1 rounded-lg bg-primary-50 px-3 py-1.5 text-xs font-semibold text-primary-600 hover:bg-primary-100 ui-animated">
                                            Buka
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- ── Pagination footer (unified with the card) ────────────────── --}}
                @if ($complaints->hasPages())
                    <div class="px-5 sm:px-6 py-4 border-t border-line bg-surface">
                        {{ $complaints->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-layouts.dashboard>
