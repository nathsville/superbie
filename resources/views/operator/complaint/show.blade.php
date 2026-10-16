@php
    $title = 'Detail Laporan ' . $complaint->reference_code;
    $status = $complaint->status;
    $currentRoute = request()->routeIs('operator.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        <x-dashboard.nav-rail-link :href="route('operator.dashboard')" label="Dashboard" :active="$currentRoute === 'operator.dashboard'">
            <x-slot:icon>
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            </x-slot:icon>
        </x-dashboard.nav-rail-link>
        <x-dashboard.nav-rail-link :href="route('operator.complaint.index')" label="Kelola Laporan" :active="str_starts_with($currentRoute, 'operator.complaint')">
            <x-slot:icon>
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </x-slot:icon>
        </x-dashboard.nav-rail-link>
    </x-slot:navigation>

    <x-slot:header>Detail Laporan</x-slot:header>
    <x-slot:breadcrumb>Panel Operator — Tinjau dan Tindak Lanjut Laporan</x-slot:breadcrumb>

    <div class="w-full max-w-full space-y-6 card-enter">
        {{-- Flash messages --}}
        @if (session('success'))
            <div class="w-full flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('success') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="w-full bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-xl" role="alert">
                <p class="font-semibold mb-1">Terjadi kesalahan:</p>
                <ul class="list-disc list-inside space-y-0.5 text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ── Header laporan (full width) ──────────────────────────────────── --}}
        <div class="w-full bg-white rounded-2xl border border-line p-5 sm:p-6 shadow-sm">
            <div class="flex flex-col md:flex-row md:items-start justify-between gap-5">
                <div class="min-w-0">
                    <div class="flex items-center gap-3 flex-wrap">
                        <span class="text-xs font-semibold text-ink-faint uppercase tracking-wider">Nomor Registrasi</span>
                        <span class="font-mono text-sm font-bold text-ink bg-surface-sunken px-3 py-1 rounded-lg border border-line">
                            {{ $complaint->reference_code }}
                        </span>
                        <span class="status-badge {{ $status->tailwindBadge() }}">
                            <span class="w-2 h-2 rounded-full bg-current" aria-hidden="true"></span>
                            {{ $status->label() }}
                        </span>
                    </div>
                    <h2 class="text-xl font-bold text-ink mt-3">{{ $complaint->title }}</h2>
                    <dl class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-4 text-xs">
                        <div>
                            <dt class="font-semibold text-ink-faint uppercase tracking-wide text-[11px]">Tanggal Kirim</dt>
                            <dd class="text-ink-body mt-0.5 font-medium">{{ $complaint->submitted_at?->translatedFormat('d F Y, H:i') ?? '—' }} WITA</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-ink-faint uppercase tracking-wide text-[11px]">Kategori</dt>
                            <dd class="text-ink-body mt-0.5 font-medium">
                                {{ $complaint->category?->name ?? '—' }}
                                @if ($complaint->category?->dinas_name)
                                    <span class="text-ink-faint font-normal">({{ $complaint->category->dinas_name }})</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-ink-faint uppercase tracking-wide text-[11px]">Dinas/Unit Tujuan</dt>
                            <dd class="text-ink-body mt-0.5 font-medium">{{ $complaint->dinasUnit?->name ?? 'Belum ditentukan' }}</dd>
                        </div>
                    </dl>
                </div>
                <div class="shrink-0">
                    {{-- PHASE 2R — the handling Operator is resolved AUTOMATICALLY
                         from the Dinas/Unit (`complaints.dinas_unit_id` →
                         `DinasUnit::operator()`). It is READ-ONLY here: an Operator
                         can never assign/reassign. --}}
                    <div class="flex items-center gap-2.5 px-3.5 py-2.5 bg-page rounded-xl border border-line">
                        <span class="w-8 h-8 rounded-lg bg-primary-50 text-primary-600 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </span>
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold text-ink-faint uppercase tracking-wide">Penanggung Jawab</p>
                            <p class="text-xs text-ink font-medium truncate">
                                {{ $complaint->resolvedOperator()?->name ?? 'Belum ada Operator' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Bento grid 8:4 ──────────────────────────────────────────────────
             LEFT (8) = Rincian Pengaduan → Lampiran Berkas → Catatan Internal →
             Respons Publik.
             RIGHT (4) = Perbarui Status → Kategori & Tujuan → Rating Kepuasan
             Pelapor (compact) → Riwayat Status.
             Both columns are flex columns that stretch to the row height and their
             LAST card grows (`flex-1`), so the two columns always end flush at the
             bottom — no leftover empty space at the foot of either column.
             The tall Lokasi Laporan card (map + Ubah Lokasi form) is rendered FULL
             WIDTH below this grid so the two columns above stay balanced. --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            {{-- ══ Left column (8/12) ═══════════════════════════════════════════ --}}
            <div class="lg:col-span-8 flex flex-col gap-6 [&>*:last-child]:flex-1">

                {{-- Rincian Pengaduan --}}
                <div class="bg-white rounded-2xl border border-line p-6 shadow-sm space-y-4">
                    <div class="flex items-center gap-2.5">
                        <span class="h-5 w-1.5 rounded-full bg-primary-600" aria-hidden="true"></span>
                        <h2 class="text-sm font-bold text-ink uppercase tracking-wider">Rincian Pengaduan</h2>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div class="p-3 bg-page rounded-xl border border-line">
                            <span class="font-semibold text-ink-faint">Pelapor</span>
                            <p class="text-ink mt-0.5 font-medium">{{ $complaint->reporter?->name ?? '—' }}</p>
                        </div>
                        <div class="p-3 bg-page rounded-xl border border-line">
                            <span class="font-semibold text-ink-faint">Lokasi</span>
                            <p class="text-ink mt-0.5">{{ $complaint->location_text ?? '—' }}</p>
                        </div>
                    </div>
                    <div class="text-sm text-ink-body leading-relaxed whitespace-pre-line border-t border-surface-sunken pt-4">
                        {{ $complaint->description }}
                    </div>
                </div>

                {{-- Lampiran Berkas --}}
                <div class="bg-white rounded-2xl border border-line p-6 shadow-sm space-y-4">
                    <div class="flex items-center gap-2.5">
                        <span class="h-5 w-1.5 rounded-full bg-primary-600" aria-hidden="true"></span>
                        <h2 class="text-sm font-bold text-ink uppercase tracking-wider">Lampiran Berkas ({{ $complaint->attachments->count() }})</h2>
                    </div>
                    @if ($complaint->attachments->isEmpty())
                        <p class="text-xs text-ink-faint italic">Tidak ada lampiran.</p>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach ($complaint->attachments as $attachment)
                                <a href="{{ route('operator.complaint.attachment', [$complaint, $attachment]) }}"
                                   target="_blank"
                                   class="flex items-center gap-3 p-3.5 rounded-xl border border-line hover:border-primary-600 hover:bg-primary-50/50 ui-animated group">
                                    <div class="w-9 h-9 rounded-lg bg-primary-100 text-primary-600 flex items-center justify-center shrink-0">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold text-ink truncate group-hover:text-primary-600 ui-animated">{{ $attachment->original_name }}</p>
                                        <p class="text-[11px] text-ink-faint mt-0.5">{{ $attachment->humanSize() }}</p>
                                    </div>
                                    <span class="inline-flex items-center gap-1 shrink-0 rounded-lg bg-primary-50 px-2.5 py-1 text-[11px] font-semibold text-primary-600 group-hover:bg-primary-100 ui-animated">
                                        Unduh
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Catatan Internal (Operator/Staff only — NEVER shown to Citizen) --}}
                <div class="bg-white rounded-2xl border border-accent-200 p-6 shadow-sm space-y-4">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <span class="h-5 w-1.5 rounded-full bg-accent-400" aria-hidden="true"></span>
                        <h2 class="text-sm font-bold text-accent-800 uppercase tracking-wider flex items-center gap-2">
                            <svg class="w-4 h-4 text-accent-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            Catatan Internal
                        </h2>
                        <span class="ml-auto inline-flex items-center gap-1 px-2.5 py-1 bg-accent-100 text-accent-700 text-[10px] font-semibold rounded-full border border-accent-200">
                            <span aria-hidden="true">🔒</span>
                            Tidak Terlihat oleh Pelapor
                        </span>
                    </div>
                    @if ($complaint->internalNotes->isEmpty())
                        <p class="text-xs text-ink-faint italic">Belum ada catatan internal.</p>
                    @else
                        <div class="space-y-3">
                            @foreach ($complaint->internalNotes as $note)
                                <div class="p-4 rounded-xl bg-accent-50 border border-accent-100 space-y-1.5">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-semibold text-accent-800">{{ $note->author?->name ?? 'Sistem' }}</span>
                                        <span class="text-accent-600">{{ $note->created_at?->translatedFormat('d M Y, H:i') ?? '—' }} WITA</span>
                                    </div>
                                    <p class="text-xs text-ink-body leading-relaxed whitespace-pre-line">{{ $note->body }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Add Internal Note Form --}}
                    <form method="POST" action="{{ route('operator.complaint.add-note', $complaint) }}" class="pt-3 border-t border-accent-100">
                        @csrf
                        <input type="hidden" name="visibility" value="internal">
                        <label for="note-internal-body" class="block text-xs font-semibold text-accent-800 mb-1.5">Tambah Catatan Internal</label>
                        <textarea
                            id="note-internal-body"
                            name="body"
                            rows="3"
                            placeholder="Tulis catatan internal di sini..."
                            class="w-full px-4 py-2.5 text-sm border border-accent-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent-400/40 focus:border-accent-400 resize-none ui-animated"
                            required
                            maxlength="5000"
                        ></textarea>
                        <div class="flex justify-end mt-2">
                            <button type="submit"
                                class="inline-flex items-center gap-2 px-4 py-2.5 bg-accent-600 hover:bg-accent-700 text-white text-xs font-semibold rounded-xl ui-animated shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Simpan Catatan Internal
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Respons Publik --}}
                <div class="bg-white rounded-2xl border border-line p-6 shadow-sm space-y-4">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <span class="h-5 w-1.5 rounded-full bg-primary-600" aria-hidden="true"></span>
                        <h2 class="text-sm font-bold text-ink uppercase tracking-wider flex items-center gap-2">
                            <svg class="w-4 h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                            Respons Publik
                        </h2>
                        <span class="ml-auto inline-flex items-center gap-1 px-2.5 py-1 bg-primary-50 text-primary-700 text-[10px] font-semibold rounded-full border border-primary-200">
                            <span aria-hidden="true">💬</span>
                            Terlihat oleh Pelapor
                        </span>
                    </div>
                    @if ($complaint->publicResponses->isEmpty())
                        <p class="text-xs text-ink-faint italic">Belum ada respons publik.</p>
                    @else
                        <div class="space-y-3">
                            @foreach ($complaint->publicResponses as $response)
                                <div class="p-4 rounded-2xl bg-primary-50/60 border border-primary-100 space-y-1.5 rounded-tl-sm">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-semibold text-primary-800">{{ $response->author?->name ?? 'Tim Lapor Pak Wali' }}</span>
                                        <span class="text-ink-faint">{{ $response->created_at?->translatedFormat('d M Y, H:i') ?? '—' }} WITA</span>
                                    </div>
                                    <p class="text-sm text-ink-strong leading-relaxed whitespace-pre-line">{{ $response->body }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Add Public Response Form --}}
                    <form method="POST" action="{{ route('operator.complaint.add-note', $complaint) }}" class="pt-3 border-t border-surface-sunken">
                        @csrf
                        <input type="hidden" name="visibility" value="public_response">
                        <label for="note-public-body" class="block text-xs font-semibold text-ink mb-1.5">Tambah Respons Publik</label>
                        <textarea
                            id="note-public-body"
                            name="body"
                            rows="3"
                            placeholder="Tulis respons publik untuk pelapor di sini..."
                            class="w-full px-4 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 resize-none ui-animated"
                            required
                            maxlength="5000"
                        ></textarea>
                        <div class="flex justify-end mt-2">
                            <button type="submit"
                                class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold rounded-xl ui-animated shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Kirim Respons Publik
                            </button>
                        </div>
                    </form>
                </div>

            </div>{{-- end left column --}}

            {{-- ══ Right column (4/12) ══════════════════════════════════════════ --}}
            <div class="lg:col-span-4 flex flex-col gap-6 [&>*:last-child]:flex-1">

                {{-- Perbarui Status --}}
                <div class="bg-white rounded-2xl border border-line border-l-4 border-l-primary-600 p-5 shadow-sm space-y-4">
                    <div class="flex items-center gap-2.5">
                        <span class="h-5 w-1.5 rounded-full bg-primary-600" aria-hidden="true"></span>
                        <h2 class="text-sm font-bold text-ink uppercase tracking-wider flex items-center gap-2">
                            <svg class="w-4 h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Perbarui Status
                        </h2>
                    </div>

                    @if ($statusUpdateBlockedByCompletion)
                        {{-- PHASE N6A — F-1: the Operator cannot update the status while
                             the completion submission awaits Super Admin validation. --}}
                        <p class="text-xs text-accent-800 bg-accent-50 border border-accent-200 rounded-xl p-3">
                            Laporan sedang menunggu validasi penyelesaian oleh Super Admin.
                            Perbarui Status tidak tersedia sampai pengajuan dikembalikan.
                        </p>
                    @elseif (empty($allowedTransitions))
                        <p class="text-xs text-ink-faint italic p-3 bg-page rounded-xl border border-line">
                            Status "{{ $status->label() }}" tidak memiliki aksi lebih lanjut.
                        </p>
                    @else
                        <form method="POST" action="{{ route('operator.complaint.update-status', $complaint) }}"
                              x-data="{
                                  selectedStatus: '',
                                  get isRejected() { return this.selectedStatus === 'rejected'; },
                                  get needsPublicResponse() { return this.selectedStatus === 'rejected' || this.selectedStatus === 'resolved'; }
                              }">
                            @csrf
                            @method('PATCH')
                            <div class="space-y-3">
                                <div>
                                    <label for="status-select" class="block text-xs font-semibold text-ink-soft mb-1.5">Status Baru</label>
                                    <select id="status-select" name="status" required
                                        x-model="selectedStatus"
                                        class="w-full px-4 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated">
                                        <option value="" disabled selected>Pilih status...</option>
                                        @foreach ($allowedTransitions as $nextStatus)
                                            <option value="{{ $nextStatus->value }}">{{ $nextStatus->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Rejection Reason — required when rejected --}}
                                <div x-show="isRejected" x-cloak>
                                    <label for="rejection-reason" class="block text-xs font-semibold text-red-700 mb-1.5">
                                        Alasan Penolakan <span class="text-red-500">*</span>
                                    </label>
                                    <textarea
                                        id="rejection-reason"
                                        name="rejection_reason"
                                        :required="isRejected"
                                        rows="3"
                                        placeholder="Jelaskan alasan penolakan laporan ini..."
                                        class="w-full px-4 py-2.5 text-sm border border-red-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-red-400/40 focus:border-red-400 resize-none ui-animated"
                                        maxlength="1000"
                                    ></textarea>
                                    @error('rejection_reason')
                                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Public Response — required when rejected or resolved --}}
                                <div x-show="needsPublicResponse" x-cloak>
                                    <label for="status-public-response" class="block text-xs font-semibold text-ink mb-1.5">
                                        Respons Publik untuk Pelapor <span class="text-red-500">*</span>
                                        <span class="font-normal text-ink-faint">(terlihat oleh pelapor)</span>
                                    </label>
                                    <textarea
                                        id="status-public-response"
                                        name="public_response_body"
                                        :required="needsPublicResponse"
                                        rows="3"
                                        placeholder="Tulis tanggapan resmi untuk pelapor..."
                                        class="w-full px-4 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 resize-none ui-animated"
                                        maxlength="5000"
                                    ></textarea>
                                    @error('public_response_body')
                                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="status-note" class="block text-xs font-semibold text-ink-soft mb-1.5">
                                        Catatan Perubahan <span class="font-normal text-ink-faint">(opsional)</span>
                                    </label>
                                    <textarea
                                        id="status-note"
                                        name="note"
                                        rows="2"
                                        placeholder="Keterangan perubahan status..."
                                        class="w-full px-4 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 resize-none ui-animated"
                                        maxlength="1000"
                                    ></textarea>
                                </div>
                                <div class="flex justify-end pt-1">
                                    <button type="submit"
                                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-accent-700 hover:bg-accent-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        Simpan Status
                                    </button>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>

                {{-- PHASE 3 — Completion Validation Gate ─────────────────────────
                     The Operator submits the finished work for Super Admin
                     validation. The status stays `in_progress`; only the review
                     state moves to `pending_completion_validation`. A returned
                     completion becomes submittable again. This is a convenience
                     UI — the server enforces the gate (see submitCompletion()). --}}
                @if ($complaint->isPendingCompletionValidation())
                    <div class="bg-accent-50 rounded-2xl border border-accent-200 p-5 shadow-sm space-y-2">
                        <h2 class="text-sm font-bold text-accent-800 uppercase tracking-wider flex items-center gap-2">
                            <svg class="w-4 h-4 text-accent-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Menunggu Validasi Penyelesaian
                        </h2>
                        <p class="text-xs text-accent-800 leading-relaxed">
                            Penyelesaian laporan ini sedang menunggu validasi Super Admin.
                            @if ($complaint->completion_submitted_at)
                                Diajukan pada {{ $complaint->completion_submitted_at->translatedFormat('d M Y, H:i') }} WITA.
                            @endif
                        </p>
                    </div>
                @elseif ($complaint->isCompletionReturnedToOperator())
                    <div class="bg-orange-50 rounded-2xl border border-orange-200 p-5 shadow-sm space-y-2">
                        <h2 class="text-sm font-bold text-orange-800 uppercase tracking-wider flex items-center gap-2">
                            <svg class="w-4 h-4 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            Penyelesaian Dikembalikan
                        </h2>
                        <p class="text-xs text-orange-800 leading-relaxed">
                            Super Admin mengembalikan pengajuan penyelesaian untuk diperbaiki. Silakan ajukan kembali setelah perbaikan.
                        </p>
                        @if ($complaint->completion_return_reason)
                            <div class="p-3 rounded-xl bg-white border border-orange-200">
                                <p class="text-[11px] font-semibold text-orange-700 uppercase tracking-wider">Catatan dari Super Admin</p>
                                <p class="text-sm text-ink-body mt-1 whitespace-pre-line">{{ $complaint->completion_return_reason }}</p>
                            </div>
                        @endif
                    </div>
                @endif

                @if ($canSubmitCompletion)
                    <div class="bg-white rounded-2xl border border-line p-5 shadow-sm space-y-3">
                        <h2 class="text-sm font-bold text-ink uppercase tracking-wider flex items-center gap-2">
                            <svg class="w-4 h-4 text-accent-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Ajukan Penyelesaian
                        </h2>
                        <p class="text-xs text-ink-faint leading-relaxed">
                            Ajukan laporan ini sebagai selesai untuk divalidasi Super Admin.
                            Status laporan tetap <strong class="text-ink-body">Sedang Diproses</strong> sampai validasi disetujui.
                        </p>
                        <form method="POST" action="{{ route('operator.complaint.submit-completion', $complaint) }}">
                            @csrf
                            @method('PATCH')
                            <label for="completion-note" class="block text-xs font-semibold text-ink-soft mb-1.5">
                                Narasi Penyelesaian <span class="font-normal text-ink-faint">(opsional — internal)</span>
                            </label>
                            <textarea
                                id="completion-note"
                                name="completion_note"
                                rows="3"
                                placeholder="Jelaskan pekerjaan yang telah diselesaikan..."
                                class="w-full px-4 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-accent-700/30 focus:border-accent-700 resize-none ui-animated"
                                maxlength="5000"
                            ></textarea>
                            @error('completion')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                            <div class="flex justify-end mt-2">
                                <button type="submit"
                                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-accent-700 hover:bg-accent-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Ajukan Penyelesaian
                                </button>
                            </div>
                        </form>
                    </div>
                @endif

                {{-- Kategori & Tujuan — READ-ONLY (PHASE 2R) ─────────────────────
                     An Operator can NEVER change the category, the Dinas/Unit
                     destination, or the assigned Operator. These are decided by
                     Super Admin initial validation; the handling Operator is
                     resolved automatically from the Dinas/Unit. The values are
                     shown here as read-only information (no form, no dropdown). --}}
                <div class="bg-white rounded-2xl border border-line p-5 shadow-sm space-y-3">
                    <div class="flex items-center gap-2.5">
                        <span class="h-5 w-1.5 rounded-full bg-emerald-400" aria-hidden="true"></span>
                        <h2 class="text-sm font-bold text-ink uppercase tracking-wider flex items-center gap-2">
                            <svg class="w-4 h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                            Kategori &amp; Tujuan
                        </h2>
                        <span class="ml-auto inline-flex items-center gap-1 px-2.5 py-1 bg-surface-sunken text-ink-soft text-[10px] font-semibold rounded-full border border-line">
                            Hanya baca
                        </span>
                    </div>
                    <dl class="space-y-2 text-xs">
                        <div class="p-2.5 bg-page rounded-xl border border-line">
                            <dt class="font-semibold text-ink-faint">Kategori</dt>
                            <dd class="text-ink mt-0.5 font-medium">{{ $complaint->category?->name ?? '—' }}</dd>
                        </div>
                        <div class="p-2.5 bg-page rounded-xl border border-line">
                            <dt class="font-semibold text-ink-faint">Dinas/Unit Tujuan</dt>
                            <dd class="text-ink mt-0.5 font-medium">{{ $complaint->dinasUnit?->name ?? 'Belum ditentukan' }}</dd>
                        </div>
                        <div class="p-2.5 bg-page rounded-xl border border-line">
                            <dt class="font-semibold text-ink-faint">Operator Dinas/Unit</dt>
                            <dd class="text-ink mt-0.5 font-medium">{{ $complaint->resolvedOperator()?->name ?? 'Belum ada Operator' }}</dd>
                        </div>
                    </dl>
                    <p class="text-[11px] text-ink-faint leading-relaxed">
                        Kategori dan Dinas/Unit ditetapkan melalui validasi awal Super Admin
                        dan tidak dapat diubah oleh Operator.
                    </p>
                </div>

                {{-- PHASE 3 — Bagian 2 — Rating kepuasan + ulasan tertulis pelapor
                     (READ-ONLY). Compact single-row layout: star indicator + score +
                     written review on one slim flex line (no tall empty space). --}}
                @if ($complaint->rating)
                    <div class="bg-white rounded-2xl border border-line p-5 shadow-sm space-y-3">
                        <div class="flex items-center gap-2.5">
                            <span class="h-5 w-1.5 rounded-full bg-accent-400" aria-hidden="true"></span>
                            <h2 class="text-sm font-bold text-ink uppercase tracking-wider flex items-center gap-2">
                                <svg class="w-4 h-4 text-accent-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.539 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.539-1.118l1.518-4.674a1 1 0 00-.363-1.118L2.98 10.1c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                                Rating Kepuasan Pelapor
                            </h2>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                            <span class="inline-flex items-center gap-2">
                                <span class="text-accent-500 text-base tracking-wider leading-none" aria-hidden="true">{{ str_repeat('★', $complaint->rating->rating) }}{{ str_repeat('☆', 5 - $complaint->rating->rating) }}</span>
                                <span class="text-xs font-semibold text-ink">{{ $complaint->rating->rating }} dari 5 bintang</span>
                            </span>
                            <span class="hidden sm:inline-block h-4 w-px bg-line" aria-hidden="true"></span>
                            <span class="inline-flex items-baseline gap-1.5 min-w-0">
                                <span class="text-[11px] font-semibold text-ink-faint uppercase tracking-wider whitespace-nowrap">Ulasan Pelapor</span>
                                @if ($complaint->rating->hasReview())
                                    <span class="text-sm text-ink-body whitespace-pre-line">{{ $complaint->rating->review_text }}</span>
                                @else
                                    <span class="text-xs text-ink-faint italic">Tidak ada ulasan tertulis.</span>
                                @endif
                            </span>
                        </div>
                    </div>
                @endif

                {{-- Riwayat Status — audit timeline of every status transition. --}}
                <div class="bg-white rounded-2xl border border-line p-5 shadow-sm space-y-4">
                    <div class="flex items-center gap-2.5">
                        <span class="h-5 w-1.5 rounded-full bg-primary-600" aria-hidden="true"></span>
                        <h2 class="text-sm font-bold text-ink uppercase tracking-wider flex items-center gap-2">
                            <svg class="w-4 h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Riwayat Status
                        </h2>
                    </div>
                    @if ($complaint->statusHistories->isEmpty())
                        <p class="text-xs text-ink-faint italic">Belum ada riwayat.</p>
                    @else
                        <ol class="relative border-l-2 border-primary-200 ml-2.5 space-y-5 my-1">
                            @foreach ($complaint->statusHistories as $history)
                                @php
                                    $toLabel = \App\Enums\ComplaintStatus::tryFrom($history->to_status)?->label() ?? $history->to_status;
                                    $fromLabel = $history->from_status
                                        ? (\App\Enums\ComplaintStatus::tryFrom($history->from_status)?->label() ?? $history->from_status)
                                        : null;
                                @endphp
                                <li class="ml-5">
                                    <span class="absolute -left-[7px] mt-1 w-3 h-3 rounded-full bg-primary-600 ring-4 ring-white" aria-hidden="true"></span>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-xs font-bold text-ink">{{ $toLabel }}</span>
                                        @if ($fromLabel && $fromLabel !== $toLabel)
                                            <span class="text-[11px] text-ink-faint">
                                                dari <span class="font-medium text-ink-soft">{{ $fromLabel }}</span>
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2 flex-wrap mt-0.5">
                                        <time class="text-[11px] text-ink-faint">
                                            {{ $history->created_at?->translatedFormat('d M Y, H:i') ?? '—' }} WITA
                                        </time>
                                        @if ($history->changedBy)
                                            <span class="text-[11px] text-ink-faint">• oleh <span class="font-medium text-ink-soft">{{ $history->changedBy->name }}</span></span>
                                        @endif
                                    </div>
                                    @if ($history->note)
                                        <p class="text-xs text-ink-soft mt-1.5 bg-page p-2.5 rounded-lg border border-line">
                                            {{ $history->note }}
                                        </p>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>

            </div>{{-- end right column --}}

        </div>{{-- end grid --}}

        {{-- ═══════════════════════════════════════════════════════════════════
             Lokasi Laporan (FULL WIDTH) — diletakkan penuh di bawah grid agar kedua
             kolom di atasnya tetap seimbang (8:4) tanpa ruang kosong. SATU kartu
             terintegrasi: pratinjau administratif + peta interaktif + form Ubah
             Lokasi. Hanya SATU map viewer yang dirender (tanpa duplikasi canvas).

             administrative-location → kecamatan_id + kelurahan_id
             location-detail         → location_text (read-only here)
             location-map-slot       → peta + GPS + koordinat (Prompt 4/5)
             ═══════════════════════════════════════════════════════════════════ --}}
        <div class="location-section w-full bg-white rounded-2xl border border-line p-6 shadow-sm space-y-4">
            <div class="flex items-center gap-2.5">
                <span class="h-5 w-1.5 rounded-full bg-primary-600" aria-hidden="true"></span>
                <h2 class="text-sm font-bold text-ink uppercase tracking-wider flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Lokasi Laporan
                </h2>
            </div>

            {{-- Read view (NULL-safe for legacy complaints) --}}
            <dl class="administrative-location grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                <div class="p-2.5 bg-page rounded-xl border border-line">
                    <dt class="font-semibold text-ink-faint">Kecamatan</dt>
                    <dd class="text-ink mt-0.5">{{ $complaint->kecamatan?->name ?? '—' }}</dd>
                </div>
                <div class="p-2.5 bg-page rounded-xl border border-line">
                    <dt class="font-semibold text-ink-faint">Kelurahan</dt>
                    <dd class="text-ink mt-0.5">{{ $complaint->kelurahan?->name ?? '—' }}</dd>
                </div>
                <div class="p-2.5 bg-page rounded-xl border border-line location-detail">
                    <dt class="font-semibold text-ink-faint">Lokasi/Alamat Detail</dt>
                    <dd class="text-ink mt-0.5">{{ $complaint->location_text ?? '—' }}</dd>
                </div>
            </dl>

            {{-- Satu map viewer terintegrasi: pratinjau titik tersimpan + penyesuaian
                 koordinat. Legacy complaints tanpa koordinat menampilkan catatan. --}}
            @if (! $complaint->latitude || ! $complaint->longitude)
                <p class="text-xs text-ink-faint bg-page border border-line rounded-xl px-3.5 py-2.5">
                    Lokasi pada peta belum tersedia. Tandai titik pada peta di bawah untuk menambahkan koordinat.
                </p>
            @endif

            <form method="POST" action="{{ route('operator.complaint.update-location', $complaint) }}"
                  x-data="{
                      location: locationPicker({
                          kecamatan: {{ Js::from($kecamatanForJs) }},
                          kelurahan: {{ Js::from($kelurahanForJs) }},
                          boundaries: {{ Js::from($boundariesForJs) }},
                          selectedKecamatanId: '{{ old('kecamatan_id', $complaint->kecamatan_id ?? '') }}',
                          selectedKelurahanId: '{{ old('kelurahan_id', $complaint->kelurahan_id ?? '') }}'
                      })
                  }"
                  @location-point.window="location.suggestFromCoordinates($event.detail.lat, $event.detail.lng)">
                @csrf
                @method('PATCH')
                <p class="text-[11px] font-semibold text-ink-faint uppercase tracking-wider mb-2">Ubah Lokasi</p>
                <div class="space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="location-kecamatan-select" class="block text-xs font-semibold text-ink-soft mb-1.5">
                                Kecamatan <span class="text-red-500">*</span>
                            </label>
                            <select id="location-kecamatan-select" name="kecamatan_id" required
                                x-model="location.selectedKecamatanId"
                                @change="location.onKecamatanChange()"
                                :disabled="!location.hasKecamatan"
                                @if ($errors->has('kecamatan_id')) aria-invalid="true" aria-describedby="location-kecamatan-error" @endif
                                class="w-full px-4 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated disabled:bg-surface-sunken disabled:text-ink-faint">
                                <option value="">-- Pilih Kecamatan --</option>
                                @foreach ($kecamatan as $kec)
                                    <option value="{{ $kec->id }}" @selected((string) old('kecamatan_id', $complaint->kecamatan_id ?? '') === (string) $kec->id)>{{ $kec->name }}</option>
                                @endforeach
                            </select>
                            @error('kecamatan_id')
                                <p id="location-kecamatan-error" role="alert" class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="location-kelurahan-select" class="block text-xs font-semibold text-ink-soft mb-1.5">
                                Kelurahan <span class="text-red-500">*</span>
                            </label>
                            <select id="location-kelurahan-select" name="kelurahan_id" required
                                x-model="location.selectedKelurahanId"
                                :disabled="!location.selectedKecamatanId"
                                @if ($errors->has('kelurahan_id')) aria-invalid="true" aria-describedby="location-kelurahan-error" @endif
                                class="w-full px-4 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated disabled:bg-surface-sunken disabled:text-ink-faint">
                                <option value=""
                                    x-text="location.selectedKecamatanId ? '-- Pilih Kelurahan --' : 'Pilih Kecamatan terlebih dahulu'"></option>
                                <template x-for="kel in location.filteredKelurahan" :key="kel.id">
                                    <option :value="kel.id" x-text="kel.name"></option>
                                </template>
                            </select>
                            @error('kelurahan_id')
                                <p id="location-kelurahan-error" role="alert" class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    @if ($kecamatan->isEmpty())
                        <p class="text-xs text-accent-600 font-medium">Data Kecamatan belum tersedia.</p>
                    @endif
                    <p class="text-xs text-accent-600 font-medium"
                       x-show="location.selectedKecamatanId && !location.hasKelurahanForSelected" x-cloak>
                        Data Kelurahan untuk Kecamatan ini belum tersedia.
                    </p>

                    @if ($boundariesForJs['features'] !== [])
                        <div class="space-y-1" aria-live="polite">
                            <p class="text-xs font-medium text-green-700"
                               x-show="location.detectState === 'matched'" x-cloak>
                                Kecamatan &amp; Kelurahan terisi otomatis dari titik peta. Periksa kembali sebelum menyimpan.
                            </p>
                            <p class="text-xs font-medium text-accent-700"
                               x-show="location.detectState === 'outside'" x-cloak>
                                Titik peta berada di luar area yang dikenali. Silakan pilih Kecamatan &amp; Kelurahan secara manual.
                            </p>
                        </div>
                        <p class="text-[11px] text-ink-faint">
                            Deteksi wilayah memakai data batas indikatif
                            (<a href="https://github.com/cahyadsn/wilayah_boundaries" target="_blank" rel="noopener noreferrer" class="text-primary-600 hover:underline">cahyadsn/wilayah_boundaries</a>, MIT).
                        </p>
                    @endif

                    {{-- Single integrated map viewer (Prompt 4/5): serves as both the
                         preview of the stored pin and the coordinate adjuster. Prefilled
                         from the stored coordinates; a cleared point submits empty → server
                         sets NULL. Old input (after a failed validation redirect) wins when
                         valid. --}}
                    <x-location-map-slot
                        :latitude="old('latitude', $complaint->latitude)"
                        :longitude="old('longitude', $complaint->longitude)"
                        label="Peta Lokasi Laporan"
                        hint="Opsional — geser dan klik/tap peta untuk memindahkan titik, atau gunakan deteksi lokasi. Kosongkan titik untuk menghapus koordinat."
                    />

                    <p class="text-[11px] text-ink-faint">
                        Perubahan lokasi administratif dan titik peta tidak mengubah "Lokasi/Alamat Detail".
                    </p>

                    <div class="flex justify-end pt-1">
                        <button type="submit"
                            :disabled="!location.hasKecamatan"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm disabled:opacity-60 disabled:cursor-not-allowed">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Simpan Lokasi
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-layouts.dashboard>
