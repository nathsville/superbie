@php
    $title = 'Semua Laporan — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';

    // Active filter chips (only for filters that are actually applied).
    $activeFilters = [];
    if (!empty($filters['q']))          $activeFilters['q']          = 'Cari: "'.e($filters['q']).'"';
    if (!empty($filters['status']))     $activeFilters['status']     = 'Status: '.(\App\Enums\ComplaintStatus::tryFrom($filters['status'])?->label() ?? $filters['status']);
    if (!empty($filters['category']))   $activeFilters['category']   = 'Kategori: '.($categories->firstWhere('id', (int) $filters['category'])?->name ?? '—');
    if (!empty($filters['dinas_unit'])) $activeFilters['dinas_unit'] = 'Dinas/Unit: '.($dinasUnits->firstWhere('id', (int) $filters['dinas_unit'])?->name ?? '—');
    if (!empty($filters['date_from']))  $activeFilters['date_from']  = 'Dari: '.$filters['date_from'];
    if (!empty($filters['date_to']))    $activeFilters['date_to']    = 'Sampai: '.$filters['date_to'];
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Semua Laporan</x-slot:header>
    <x-slot:breadcrumb>Pemantauan & inspeksi laporan (read-only)</x-slot:breadcrumb>

    <div class="mb-4 flex items-center justify-between flex-wrap gap-2">
        <p class="text-sm text-[#64748B]">
            Total: <strong>{{ $complaints->total() }}</strong> laporan
            @if ($complaints->total() > 0)
                <span class="text-[#94A3B8]">• menampilkan {{ $complaints->firstItem() }}–{{ $complaints->lastItem() }}</span>
            @endif
        </p>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            Read-only • Tidak dapat diubah
        </span>
    </div>

    {{-- Search / filter --}}
    <div class="bg-white rounded-2xl border border-[#E2E8F0] p-4 mb-6 card-enter">
        <form method="GET" action="{{ route('super-admin.complaints.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3" role="search">
            <div class="lg:col-span-2">
                <label for="q" class="block text-xs font-semibold text-[#475569] mb-1">Cari</label>
                <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}"
                    placeholder="Nomor registrasi, judul, atau deskripsi"
                    class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB]">
            </div>
            <div>
                <label for="status" class="block text-xs font-semibold text-[#475569] mb-1">Status</label>
                <select id="status" name="status"
                    class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB]">
                    <option value="">Semua Status</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="category" class="block text-xs font-semibold text-[#475569] mb-1">Kategori</label>
                <select id="category" name="category"
                    class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB]">
                    <option value="">Semua Kategori</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}" @selected((string) ($filters['category'] ?? '') === (string) $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="dinas_unit" class="block text-xs font-semibold text-[#475569] mb-1">Dinas/Unit Tujuan</label>
                <select id="dinas_unit" name="dinas_unit"
                    class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB]">
                    <option value="">Semua Dinas/Unit</option>
                    @foreach ($dinasUnits as $unit)
                        <option value="{{ $unit->id }}" @selected((string) ($filters['dinas_unit'] ?? '') === (string) $unit->id)>{{ $unit->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="date_from" class="block text-xs font-semibold text-[#475569] mb-1">Tanggal Dari</label>
                <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}"
                    class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB]">
            </div>
            <div>
                <label for="date_to" class="block text-xs font-semibold text-[#475569] mb-1">Tanggal Sampai</label>
                <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}"
                    class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB]">
            </div>
            <div>
                <label for="sort" class="block text-xs font-semibold text-[#475569] mb-1">Urutkan</label>
                <select id="sort" name="sort"
                    class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB]">
                    <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Terbaru</option>
                    <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Terlama</option>
                    <option value="status" @selected(($filters['sort'] ?? '') === 'status')>Status</option>
                </select>
            </div>
            <div class="lg:col-span-4 flex items-center gap-2">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-700 hover:bg-slate-800 text-white text-sm font-semibold rounded-xl ui-animated">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    Filter
                </button>
                @if (!empty($activeFilters))
                    <a href="{{ route('super-admin.complaints.index') }}" class="text-sm text-[#64748B] hover:underline">Reset</a>
                @endif
            </div>
        </form>

        @if (!empty($activeFilters))
            <div class="mt-3 flex flex-wrap gap-2 pt-3 border-t border-[#F1F5F9]">
                @foreach ($activeFilters as $key => $label)
                    <span class="inline-flex items-center gap-1 text-[11px] font-medium bg-[#EFF6FF] text-[#1D4ED8] border border-[#BFDBFE] px-2 py-0.5 rounded-full">
                        {{ $label }}
                    </span>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-2xl border border-[#E2E8F0] shadow-sm overflow-hidden card-enter">
        @if ($complaints->isEmpty())
            <div class="empty-state py-16 text-center">
                <svg class="w-12 h-12 text-[#CBD5E1] mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <p class="text-sm font-semibold text-[#475569]">Belum ada laporan yang sesuai dengan filter.</p>
                <p class="text-xs text-[#94A3B8] mt-1">Ubah filter atau kata kunci pencarian untuk melihat hasil lain.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-[#F8FAFC] text-[#475569] text-xs uppercase tracking-wider">
                        <tr>
                            <th class="text-left px-4 py-3 whitespace-nowrap">Nomor Registrasi</th>
                            <th class="text-left px-4 py-3">Judul / Kategori</th>
                            <th class="text-left px-4 py-3 whitespace-nowrap">Pelapor</th>
                            <th class="text-left px-4 py-3 whitespace-nowrap">Tujuan</th>
                            <th class="text-left px-4 py-3 whitespace-nowrap">Status</th>
                            <th class="text-left px-4 py-3 whitespace-nowrap">Operator</th>
                            <th class="text-left px-4 py-3 whitespace-nowrap">Dikirim</th>
                            <th class="text-right px-4 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E8F0]">
                        @foreach ($complaints as $laporan)
                            @php $status = $laporan->status; @endphp
                            <tr class="hover:bg-[#F8FAFC]">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="font-mono text-xs text-[#475569] bg-[#F8FAFC] px-2 py-1 rounded-lg border border-[#E2E8F0]">{{ $laporan->reference_code }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-[#0F172A] line-clamp-1">{{ $laporan->title }}</p>
                                    <p class="text-xs text-[#64748B] mt-0.5">{{ $laporan->category?->name ?? '—' }}</p>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-[#475569]">{{ $laporan->reporter?->name ?? '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-[#475569]">
                                    {{ $laporan->dinasUnit?->name ?? 'Belum ditentukan' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="status-badge {{ $status->tailwindBadge() }}">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                        {{ $status->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-[#475569]">{{ $laporan->assignee?->name ?? '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-xs text-[#64748B]">{{ $laporan->submitted_at?->translatedFormat('d M Y') ?? '—' }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('super-admin.complaints.show', $laporan) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-[#2563EB] hover:text-[#1D4ED8] ui-animated">
                                        Buka
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($complaints->hasPages())
        <div class="mt-4">{{ $complaints->links() }}</div>
    @endif
</x-layouts.dashboard>
