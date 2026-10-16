@php
    $title = 'Riwayat Laporan';

    $activeFilters = [];
    if (! empty($filters['q'])) {
        $activeFilters['q'] = 'Cari: "'.e($filters['q']).'"';
    }
    if (! empty($filters['status']) && is_string($filters['status'])) {
        $activeFilters['status'] = 'Status: '.(\App\Enums\ComplaintStatus::tryFrom($filters['status'])?->label() ?? $filters['status']);
    }
    if (! empty($filters['category']) && is_string($filters['category'])) {
        $cat = $categories->firstWhere('id', (int) $filters['category']);
        $activeFilters['category'] = 'Kategori: '.($cat?->name ?? $filters['category']);
    }
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @php $route = request()->routeIs('citizen.*') ? request()->route()->getName() : ''; @endphp
        @include('citizen.partials.nav', ['currentRoute' => $route])
    </x-slot:navigation>

    <x-slot:header>Riwayat Laporan Saya</x-slot:header>
    <x-slot:breadcrumb>Panel Masyarakat — Semua laporan yang pernah Anda kirim</x-slot:breadcrumb>

    {{-- The "Buat Laporan" action lives ONLY in the sidebar navigation now, so
         the topbar stays clean (no duplicate CTA next to the profile). The
         empty-state below keeps its own contextual "Buat Laporan Sekarang". --}}

    <div class="w-full max-w-full space-y-5 card-enter">

        {{-- ── Filter & pencarian ───────────────────────────────────────────── --}}
        <div class="w-full bg-white rounded-2xl border border-line shadow-sm p-4 sm:p-5">
            <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
                <h2 class="text-sm font-semibold text-ink tracking-wide">Filter &amp; Pencarian</h2>
                <div class="flex items-center gap-3">
                    <p class="text-sm text-ink-faint">
                        Total: <strong class="text-ink">{{ $laporan->total() }}</strong> laporan
                        @if ($laporan->total() > 0)
                            <span>• menampilkan {{ $laporan->firstItem() }}–{{ $laporan->lastItem() }}</span>
                        @endif
                    </p>
                    @if (! empty($activeFilters))
                        <a href="{{ route('citizen.history') }}" class="text-xs font-semibold text-primary-600 hover:text-primary-700 ui-animated">Reset</a>
                    @endif
                </div>
            </div>

            <form method="GET" action="{{ route('citizen.history') }}"
                  class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3"
                  role="search" aria-label="Filter riwayat laporan">
                <div>
                    <label for="q" class="block text-xs font-semibold text-ink-soft mb-1.5">Cari</label>
                    <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}"
                        placeholder="Nomor referensi atau judul"
                        class="w-full px-3.5 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated">
                </div>

                <div>
                    <label for="status" class="block text-xs font-semibold text-ink-soft mb-1.5">Status</label>
                    <select id="status" name="status"
                        class="w-full px-3.5 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated">
                        <option value="">Semua Status</option>
                        @foreach ($statuses as $s)
                            <option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="category" class="block text-xs font-semibold text-ink-soft mb-1.5">Kategori</label>
                    <select id="category" name="category"
                        class="w-full px-3.5 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated">
                        <option value="">Semua Kategori</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" @selected((string) ($filters['category'] ?? '') === (string) $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit"
                        class="inline-flex items-center justify-center gap-2 flex-1 px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        Filter
                    </button>
                    <a href="{{ route('citizen.history') }}"
                        class="inline-flex items-center justify-center shrink-0 px-4 py-2.5 bg-surface-sunken hover:bg-line text-ink-soft text-sm font-semibold rounded-xl ui-animated">
                        Reset
                    </a>
                </div>
            </form>

            @if (! empty($activeFilters))
                <div class="mt-3 flex flex-wrap gap-2 pt-3 border-t border-surface-sunken">
                    @foreach ($activeFilters as $label)
                        <span class="inline-flex items-center gap-1 text-[11px] font-medium bg-primary-50 text-primary-700 border border-primary-200 px-2.5 py-0.5 rounded-full">{{ $label }}</span>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ── Tabel riwayat ────────────────────────────────────────────────── --}}
        <h2 class="sr-only">Daftar riwayat laporan saya</h2>
        <div class="w-full bg-white rounded-2xl border border-line shadow-sm overflow-hidden">
            @if ($laporan->isEmpty())
                <div class="empty-state">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-primary-100 flex items-center justify-center">
                        <svg class="w-8 h-8 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    @if (! empty($activeFilters))
                        <h3 class="text-base font-semibold text-ink mb-1">Belum Ada Laporan yang Sesuai</h3>
                        <p class="text-sm text-ink-soft mb-4">Tidak ada laporan yang cocok dengan filter atau kata kunci Anda.</p>
                        <a href="{{ route('citizen.history') }}" class="inline-flex items-center gap-2 bg-surface-sunken hover:bg-line text-ink-soft text-sm font-semibold px-4 py-2.5 rounded-xl ui-animated">
                            Reset Filter
                        </a>
                    @else
                        <h3 class="text-base font-semibold text-ink mb-1">Belum Ada Riwayat</h3>
                        <p class="text-sm text-ink-soft mb-4">Laporan yang Anda kirimkan akan tercatat di sini.</p>
                        <a href="{{ route('citizen.complaint.create') }}" class="inline-flex items-center gap-2 bg-primary-600 text-white text-sm font-semibold px-4 py-2.5 rounded-xl ui-animated hover:bg-primary-700">
                            Buat Laporan Sekarang
                        </a>
                    @endif
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" aria-label="Riwayat laporan saya">
                        <caption class="sr-only">Daftar laporan yang pernah Anda kirim beserta kategori, status, tanggal, dan tautan detail</caption>
                        <thead class="bg-page text-ink-soft text-xs uppercase tracking-wider">
                            <tr>
                                <th scope="col" class="text-left px-4 sm:px-6 py-3.5 font-semibold whitespace-nowrap">No. Referensi</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold min-w-[16rem]">Judul Laporan</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap hidden sm:table-cell">Kategori</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Status</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap hidden md:table-cell">Tanggal</th>
                                <th scope="col" class="text-right px-4 sm:px-6 py-3.5 font-semibold whitespace-nowrap">Detail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-sunken">
                            @foreach ($laporan as $item)
                                <tr class="hover:bg-page transition-colors">
                                    <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="font-mono text-xs text-ink-soft bg-page px-2 py-1 rounded-lg border border-line">{{ $item->reference_code }}</span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="font-semibold text-ink max-w-xs truncate">{{ $item->title }}</p>
                                        @if ($item->kelurahan || $item->kecamatan)
                                            <p class="text-xs text-ink-faint mt-0.5 truncate">
                                                {{ collect([$item->kelurahan?->name, $item->kecamatan?->name])->filter()->implode(', ') }}
                                            </p>
                                        @elseif ($item->location_text)
                                            <p class="text-xs text-ink-faint mt-0.5 truncate">{{ $item->location_text }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 hidden sm:table-cell text-ink-soft whitespace-nowrap">
                                        {{ $item->category?->name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-4">
                                        @php $status = $item->status; @endphp
                                        <div class="flex flex-col items-start gap-1">
                                            <span class="status-badge {{ $status->tailwindBadge() }}">
                                                <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                                {{ $status->label() }}
                                            </span>
                                            @if ($item->isReturnedToCitizen())
                                                <span class="status-badge {{ \App\Enums\ComplaintReviewState::ReturnedToCitizen->tailwindBadge() }}">
                                                    {{ \App\Enums\ComplaintReviewState::ReturnedToCitizen->label() }}
                                                </span>
                                            @elseif ($item->isAwaitingInitialValidation())
                                                <span class="status-badge {{ \App\Enums\ComplaintReviewState::PendingInitialValidation->tailwindBadge() }}">
                                                    {{ \App\Enums\ComplaintReviewState::PendingInitialValidation->label() }}
                                                </span>
                                            @elseif ($item->isPendingCompletionValidation())
                                                {{-- PHASE 3 — read-only indicator. The status is
                                                     NOT `resolved`; the complaint is awaiting Super
                                                     Admin completion validation. --}}
                                                <span class="status-badge {{ \App\Enums\ComplaintReviewState::PendingCompletionValidation->tailwindBadge() }}">
                                                    {{ \App\Enums\ComplaintReviewState::PendingCompletionValidation->label() }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 hidden md:table-cell whitespace-nowrap">
                                        <span class="block text-xs font-medium text-ink-body">{{ $item->submitted_at?->translatedFormat('d M Y') ?? '—' }}</span>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4 text-right whitespace-nowrap">
                                        <a href="{{ route('citizen.complaint.show', $item) }}"
                                           aria-label="Buka detail laporan {{ $item->reference_code }}"
                                           class="inline-flex items-center gap-1 rounded-lg bg-primary-50 px-3 py-1.5 text-xs font-semibold text-primary-600 hover:bg-primary-100 ui-animated">
                                            Detail
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($laporan->hasPages())
                    <div class="px-5 sm:px-6 py-4 border-t border-line bg-surface">
                        {{ $laporan->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-layouts.dashboard>
