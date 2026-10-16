@php
    $title = 'Pusat Bantuan — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';

    $activeFilters = [];
    if (!empty($filters['q']))        $activeFilters['q']        = 'Cari: "'.e($filters['q']).'"';
    if (!empty($filters['status']) && is_string($filters['status'])) $activeFilters['status'] = 'Status: '.(\App\Enums\HelpTicketStatus::tryFrom($filters['status'])?->label() ?? $filters['status']);
    if (!empty($filters['category']) && is_string($filters['category'])) $activeFilters['category'] = 'Kategori: '.(\App\Enums\HelpDeskCategory::tryFrom($filters['category'])?->label() ?? $filters['category']);
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Pusat Bantuan</x-slot:header>
    <x-slot:breadcrumb>Penanganan tiket bantuan masyarakat (Super Admin)</x-slot:breadcrumb>

    <div class="w-full max-w-full space-y-6 card-enter">
        @if (session('success'))
            <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('success') }}
            </div>
        @endif

        {{-- ── Filter card ──────────────────────────────────────────────────── --}}
        <div class="w-full bg-white rounded-2xl border border-line shadow-sm p-4 sm:p-5">
            <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
                <h2 class="text-sm font-semibold text-ink tracking-wide">Filter &amp; Pencarian</h2>
                <div class="flex items-center gap-3">
                    <p class="text-sm text-ink-faint">
                        Total: <strong class="text-ink">{{ $tickets->total() }}</strong> tiket
                        @if ($tickets->total() > 0)
                            <span>• menampilkan {{ $tickets->firstItem() }}–{{ $tickets->lastItem() }}</span>
                        @endif
                    </p>
                    @if (!empty($activeFilters))
                        <a href="{{ route('super-admin.help.index') }}" class="text-xs font-semibold text-primary-600 hover:text-primary-700 ui-animated">Reset</a>
                    @endif
                </div>
            </div>

            <form method="GET" action="{{ route('super-admin.help.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3" role="search" aria-label="Filter tiket bantuan">
                <div>
                    <label for="q" class="block text-xs font-semibold text-ink-soft mb-1.5">Cari</label>
                    <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}"
                        placeholder="Nomor tiket atau judul"
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
                            <option value="{{ $cat->value }}" @selected(($filters['category'] ?? '') === $cat->value)>{{ $cat->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="sort" class="block text-xs font-semibold text-ink-soft mb-1.5">Urutkan</label>
                    <div class="flex items-center gap-2">
                        <select id="sort" name="sort"
                            class="flex-1 min-w-0 px-3.5 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated">
                            <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Aktivitas Terbaru</option>
                            <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Tertua</option>
                            <option value="status" @selected(($filters['sort'] ?? '') === 'status')>Status</option>
                        </select>
                        <button type="submit"
                            class="inline-flex items-center justify-center gap-2 shrink-0 px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                            Filter
                        </button>
                    </div>
                </div>
            </form>

            @if (!empty($activeFilters))
                <div class="mt-3 flex flex-wrap gap-2 pt-3 border-t border-surface-sunken">
                    @foreach ($activeFilters as $label)
                        <span class="inline-flex items-center gap-1 text-[11px] font-medium bg-primary-50 text-primary-700 border border-primary-200 px-2.5 py-0.5 rounded-full">{{ $label }}</span>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ── Table card ───────────────────────────────────────────────────── --}}
        <h2 class="sr-only">Daftar tiket bantuan</h2>
        <div class="w-full bg-white rounded-2xl border border-line shadow-sm overflow-hidden">
            @if ($tickets->isEmpty())
                <div class="empty-state py-16 text-center">
                    <svg class="w-12 h-12 text-line-strong mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                    <p class="text-sm font-semibold text-ink-soft">Belum ada tiket bantuan yang sesuai.</p>
                    <p class="text-xs text-ink-faint mt-1">Ubah filter atau kata kunci pencarian untuk melihat hasil lain.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <caption class="sr-only">Daftar tiket bantuan masyarakat beserta status, laporan terkait dan waktu pembaruan</caption>
                        <thead class="bg-page text-ink-soft text-xs uppercase tracking-wider">
                            <tr>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">No. Tiket</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold min-w-[16rem]">Judul / Kategori</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Pemilik</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Status</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Laporan Terkait</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Diperbarui</th>
                                <th scope="col" class="text-right px-4 py-3.5 font-semibold whitespace-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-sunken">
                            @foreach ($tickets as $ticket)
                                @php $status = $ticket->status; @endphp
                                <tr class="hover:bg-page transition-colors">
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="font-mono text-xs text-ink-soft bg-page px-2 py-1 rounded-lg border border-line">{{ $ticket->reference_code }}</span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="font-semibold text-ink line-clamp-2">{{ $ticket->subject }}</p>
                                        <p class="text-xs text-ink-faint mt-1">{{ $ticket->category->label() }}</p>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-ink-soft">{{ $ticket->user?->name ?? '—' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="status-badge {{ $status->tailwindBadge() }}">
                                            <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                            {{ $status->label() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        @if ($ticket->relatedComplaint)
                                            <a href="{{ route('super-admin.complaints.show', $ticket->relatedComplaint) }}"
                                               class="inline-flex items-center gap-1.5 font-mono text-xs font-medium text-primary-700 bg-primary-50 border border-primary-100 px-2.5 py-1 rounded-lg hover:bg-primary-100 ui-animated">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                {{ $ticket->relatedComplaint->reference_code }}
                                            </a>
                                        @else
                                            <span class="text-xs text-ink-faint">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="block text-xs font-medium text-ink-body">{{ $ticket->updated_at?->translatedFormat('d M Y') ?? '—' }}</span>
                                        <span class="block text-[11px] text-ink-faint mt-0.5">{{ $ticket->updated_at?->translatedFormat('H:i') ?? '' }} WITA</span>
                                    </td>
                                    <td class="px-4 py-4 text-right whitespace-nowrap">
                                        <a href="{{ route('super-admin.help.show', $ticket) }}"
                                           aria-label="Buka tiket {{ $ticket->reference_code }}"
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

                @if ($tickets->hasPages())
                    <div class="px-5 sm:px-6 py-4 border-t border-line bg-surface">
                        {{ $tickets->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-layouts.dashboard>
