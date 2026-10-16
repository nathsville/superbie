@php
    $title = 'Validasi Laporan ' . $complaint->reference_code . ' — Super Admin';
    $status = $complaint->status;
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';

    // Form re-population values for the approve form. `old()` can hold a crafted
    // ARRAY (e.g. `category_id[]=x`), so it is coerced to a scalar string here —
    // never cast an array inline (that raises "Array to string conversion").
    // Injected into the Alpine `x-data` JS object via Js::from() (JS-safe),
    // NOT via `{{ }}` (HTML-escape only — a quote would break out of the string).
    $oldCategoryId = old('category_id', $complaint->category_id);
    $oldCategoryId = is_scalar($oldCategoryId) ? (string) $oldCategoryId : (string) $complaint->category_id;
    $oldDinasId    = old('dinas_unit_id', '');
    $oldDinasId    = is_scalar($oldDinasId) ? (string) $oldDinasId : '';
    $oldReason     = old('reason', '');
    $oldReason     = is_scalar($oldReason) ? (string) $oldReason : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Validasi Awal Laporan</x-slot:header>
    <x-slot:breadcrumb>Tinjau laporan, koreksi kategori bila perlu, tentukan Dinas/Unit tujuan</x-slot:breadcrumb>

    <div class="w-full max-w-full space-y-6 card-enter">
        @if (session('success'))
            <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('success') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
                <p class="font-semibold mb-1">Terjadi kesalahan:</p>
                <ul class="list-disc list-inside space-y-0.5 text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Status & Reference Banner --}}
        <div class="bg-white rounded-2xl border border-line p-5 sm:p-6 shadow-sm">
            <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-3 flex-wrap">
                        <span class="text-[11px] font-semibold text-ink-faint uppercase tracking-wider">Nomor Registrasi</span>
                        <span class="font-mono text-sm font-bold text-ink bg-surface-sunken px-3 py-1 rounded-lg border border-line">{{ $complaint->reference_code }}</span>
                        <span class="status-badge {{ $status->tailwindBadge() }}">
                            <span class="w-2 h-2 rounded-full bg-current" aria-hidden="true"></span>
                            {{ $status->label() }}
                        </span>
                        <span class="status-badge {{ $complaint->review_state?->tailwindBadge() ?? 'bg-surface-sunken text-ink-soft' }}">
                            {{ $complaint->review_state?->label() ?? 'Legacy' }}
                        </span>
                    </div>
                    <h2 class="text-xl font-bold text-ink mt-3">{{ $complaint->title }}</h2>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ink-faint mt-2">
                        <span>Dikirim: {{ $complaint->submitted_at?->translatedFormat('d F Y, H:i') ?? '—' }} WITA</span>
                        @if ($complaint->category)
                            <span>Kategori saat ini: <strong class="text-ink-body">{{ $complaint->category->name }}</strong></span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Bento grid — 8:4 split (main content left, actions right) --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            {{-- ── Left column (main content, 8/12) ─────────────────────────── --}}
            <div class="lg:col-span-8 space-y-6">

                <div class="bg-white rounded-2xl border border-line p-6 shadow-sm space-y-5">
                    <div class="flex items-center gap-2.5">
                        <span class="h-5 w-1.5 rounded-full bg-primary-600" aria-hidden="true"></span>
                        <h2 class="text-sm font-semibold text-ink tracking-wide">Rincian Pengaduan</h2>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="rounded-xl bg-page border border-surface-sunken px-4 py-3">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-ink-faint">Pelapor</span>
                            <p class="text-sm font-semibold text-ink mt-1">{{ $complaint->reporter?->name ?? '—' }}</p>
                        </div>
                        <div class="rounded-xl bg-page border border-surface-sunken px-4 py-3">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-ink-faint">Kontak Pelapor</span>
                            <p class="text-sm text-ink mt-1">{{ $complaint->reporter_email ?? '—' }}</p>
                        </div>
                        <div class="rounded-xl bg-page border border-surface-sunken px-4 py-3">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-ink-faint">Kecamatan</span>
                            <p class="text-sm text-ink mt-1">{{ $complaint->kecamatan?->name ?? '—' }}</p>
                        </div>
                        <div class="rounded-xl bg-page border border-surface-sunken px-4 py-3">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-ink-faint">Kelurahan</span>
                            <p class="text-sm text-ink mt-1">{{ $complaint->kelurahan?->name ?? '—' }}</p>
                        </div>
                        <div class="rounded-xl bg-page border border-surface-sunken px-4 py-3 sm:col-span-2">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-ink-faint">Lokasi / Alamat Detail</span>
                            <p class="text-sm text-ink mt-1">{{ $complaint->location_text ?? '—' }}</p>
                        </div>
                    </div>

                    <div class="text-sm text-ink-body leading-relaxed whitespace-pre-line border-t border-surface-sunken pt-4">
                        {{ $complaint->description }}
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-line p-6 shadow-sm space-y-5">
                    <div class="flex items-center gap-2.5">
                        <span class="h-5 w-1.5 rounded-full bg-accent-700" aria-hidden="true"></span>
                        <h2 class="text-sm font-semibold text-ink tracking-wide">Lampiran Berkas ({{ $complaint->attachments->count() }})</h2>
                    </div>
                    @if ($complaint->attachments->isEmpty())
                        <p class="text-xs text-ink-faint italic">Tidak ada lampiran.</p>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach ($complaint->attachments as $attachment)
                                <a href="{{ route('operator.complaint.attachment', [$complaint, $attachment]) }}"
                                   target="_blank" rel="noopener"
                                   class="flex items-center gap-3 p-3.5 rounded-xl border border-line hover:border-primary-600 hover:bg-primary-50/50 ui-animated group">
                                    <div class="w-9 h-9 rounded-lg bg-primary-100 text-primary-600 flex items-center justify-center shrink-0">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold text-ink truncate group-hover:text-primary-600 ui-animated">{{ $attachment->original_name }}</p>
                                        <p class="text-[11px] text-ink-faint mt-0.5">{{ $attachment->humanSize() }}</p>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>{{-- end left column --}}

            {{-- ── Right column (validation actions, 4/12) ──────────────────── --}}
            <div class="lg:col-span-4 space-y-5">

                {{-- APPROVE --}}
                <div class="bg-white rounded-2xl border border-emerald-200 border-l-4 border-l-emerald-500 p-5 sm:p-6 shadow-sm space-y-4">
                    <div class="flex items-center gap-2.5">
                        <span class="h-5 w-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                        <h2 class="text-sm font-semibold text-emerald-800 tracking-wide">Setujui &amp; Teruskan</h2>
                    </div>

                    <form method="POST" action="{{ route('super-admin.validation.approve', $complaint) }}" class="space-y-4" x-data="{
                        categoryId: {{ Js::from($oldCategoryId) }},
                        dinasId: {{ Js::from($oldDinasId) }},
                        optionsByCategory: {{ Js::from($dinasOptionsByCategory) }},
                        get mapped() { return this.optionsByCategory[this.categoryId] || []; },
                        get selectedDinas() { return this.mapped.find(d => String(d.id) === String(this.dinasId)); },
                        onCategoryChange() { this.dinasId = ''; },
                    }">
                        @csrf

                        <div>
                            <label for="category_id" class="block text-xs font-semibold text-ink-soft mb-1.5">Kategori Final <span class="text-red-500">*</span></label>
                            <select id="category_id" name="category_id" x-model="categoryId" @change="onCategoryChange()" required
                                class="w-full px-3.5 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated">
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}" @selected($oldCategoryId === (string) $cat->id)>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-ink-faint mt-1.5">Mengubah kategori akan dicatat pada audit log.</p>
                        </div>

                        <div>
                            <label for="dinas_unit_id" class="block text-xs font-semibold text-ink-soft mb-1.5">Dinas/Unit Tujuan <span class="text-red-500">*</span></label>

                            <p x-show="mapped.length === 0" x-cloak
                               class="text-xs text-accent-700 bg-accent-50 border border-accent-200 rounded-xl px-3 py-2.5">
                                Kategori ini belum memiliki Dinas/Unit tujuan aktif. Perbaiki mapping master data terlebih dahulu.
                            </p>

                            <select x-show="mapped.length > 0" id="dinas_unit_id" name="dinas_unit_id" x-model="dinasId"
                                :required="mapped.length > 0"
                                class="w-full px-3.5 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated">
                                <option value="">-- Pilih Dinas/Unit --</option>
                                <template x-for="unit in mapped" :key="unit.id">
                                    <option :value="unit.id" x-text="unit.name + (unit.has_operator ? '' : ' (belum ada Operator)')"></option>
                                </template>
                            </select>

                            <p class="text-[11px] mt-1.5"
                               :class="selectedDinas && selectedDinas.has_operator ? 'text-emerald-700' : 'text-accent-700'">
                                <template x-if="selectedDinas && selectedDinas.has_operator">
                                    <span>Operator penerima: <strong x-text="selectedDinas.operator_name"></strong></span>
                                </template>
                                <template x-if="selectedDinas && !selectedDinas.has_operator">
                                    <span>Dinas/Unit ini belum memiliki Operator. Validasi akan ditolak.</span>
                                </template>
                            </p>
                        </div>

                        <button type="submit"
                            :disabled="mapped.length === 0"
                            class="w-full inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold px-4 py-2.5 rounded-xl ui-animated shadow-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Setujui Validasi
                        </button>
                    </form>
                </div>

                {{-- RETURN --}}
                <div class="bg-white rounded-2xl border border-orange-200 border-l-4 border-l-orange-500 p-5 sm:p-6 shadow-sm space-y-4">
                    <div class="flex items-center gap-2.5">
                        <span class="h-5 w-1.5 rounded-full bg-orange-500" aria-hidden="true"></span>
                        <h2 class="text-sm font-semibold text-orange-800 tracking-wide">Kembalikan ke Pelapor</h2>
                    </div>
                    <form method="POST" action="{{ route('super-admin.validation.return', $complaint) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label for="reason" class="block text-xs font-semibold text-ink-soft mb-1.5">Alasan / Hal yang Harus Diperbaiki <span class="text-red-500">*</span></label>
                            <textarea id="reason" name="reason" rows="4" required minlength="10" maxlength="2000"
                                placeholder="Contoh: Lokasi kejadian belum jelas. Mohon lengkapi alamat dan lampirkan foto kondisi terbaru."
                                class="w-full px-3.5 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated">{{ $oldReason }}</textarea>
                            <p class="text-[11px] text-ink-faint mt-1.5">Alasan ini akan ditampilkan kepada pelapor.</p>
                        </div>
                        <button type="submit"
                            class="w-full inline-flex items-center justify-center gap-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl ui-animated shadow-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                            Kembalikan ke Pelapor
                        </button>
                    </form>
                </div>

                {{-- Riwayat status --}}
                <div class="bg-white rounded-2xl border border-line border-l-4 border-l-violet-400 p-5 sm:p-6 shadow-sm space-y-4">
                    <div class="flex items-center gap-2.5">
                        <span class="h-5 w-1.5 rounded-full bg-violet-400" aria-hidden="true"></span>
                        <h2 class="text-sm font-semibold text-ink tracking-wide">Riwayat Status</h2>
                    </div>
                    @if ($complaint->statusHistories->isEmpty())
                        <p class="text-xs text-ink-faint italic">Belum ada riwayat.</p>
                    @else
                        <ol class="relative border-l-2 border-primary-200 ml-2 space-y-4 my-1">
                            @foreach ($complaint->statusHistories as $history)
                                <li class="ml-4">
                                    <span class="absolute -left-1.5 mt-1.5 w-3 h-3 rounded-full bg-primary-600 ring-4 ring-white"></span>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="status-badge {{ (\App\Enums\ComplaintStatus::tryFrom($history->to_status)?->tailwindBadge()) ?? 'bg-surface-sunken text-ink-soft' }}">
                                            <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                            {{ \App\Enums\ComplaintStatus::tryFrom($history->to_status)?->label() ?? $history->to_status }}
                                        </span>
                                        @if ($history->changedBy)
                                            <span class="text-[11px] text-ink-faint">oleh {{ $history->changedBy->name }}</span>
                                        @endif
                                    </div>
                                    <time class="block text-[11px] text-ink-faint mt-1">{{ $history->created_at?->translatedFormat('d M Y, H:i') ?? '—' }} WITA</time>
                                    @if ($history->note)
                                        <p class="text-xs text-ink-soft mt-1 bg-page p-2 rounded-lg border border-line">{{ $history->note }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>

            </div>{{-- end right column --}}

        </div>{{-- end grid --}}
    </div>
</x-layouts.dashboard>
