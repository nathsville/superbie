@php
    $title = 'Detail Laporan ' . $complaint->reference_code;
    $status = $complaint->status;
    $currentRoute = request()->routeIs('operator.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        <div class="space-y-1">
            <a href="{{ route('operator.dashboard') }}"
               class="nav-link {{ $currentRoute === 'operator.dashboard' ? 'active' : '' }}"
               @if($currentRoute === 'operator.dashboard') aria-current="page" @endif>
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>
            <a href="{{ route('operator.complaint.index') }}"
               class="nav-link {{ str_starts_with($currentRoute, 'operator.complaint') ? 'active' : '' }}"
               @if(str_starts_with($currentRoute, 'operator.complaint')) aria-current="page" @endif>
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Kelola Laporan
            </a>
        </div>
    </x-slot:navigation>

    <x-slot:header>Detail Laporan</x-slot:header>
    <x-slot:breadcrumb>Panel Operator — Tinjau dan Tindak Lanjut Laporan</x-slot:breadcrumb>

    <x-slot:headerActions>
        <a href="{{ route('operator.complaint.index') }}"
           class="inline-flex items-center gap-2 bg-white hover:bg-[#F1F5F9] text-[#475569] text-sm font-semibold px-4 py-2 rounded-xl border border-[#CBD5E1] ui-animated shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Kembali
        </a>
    </x-slot:headerActions>

    {{-- Flash messages --}}
    @if (session('success'))
        <div class="mb-4 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('success') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
            <p class="font-semibold mb-1">Terjadi kesalahan:</p>
            <ul class="list-disc list-inside space-y-0.5 text-xs">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="max-w-6xl mx-auto space-y-6 card-enter">

        {{-- Status & Reference Banner --}}
        <div class="bg-white rounded-2xl border border-[#E2E8F0] p-5 shadow-sm">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3 flex-wrap">
                        <span class="text-xs font-semibold text-[#64748B] uppercase tracking-wider">Nomor Registrasi:</span>
                        <span class="font-mono text-sm font-bold text-[#0F172A] bg-[#F1F5F9] px-3 py-1 rounded-lg border border-[#E2E8F0]">
                            {{ $complaint->reference_code }}
                        </span>
                        <span class="status-badge {{ $status->tailwindBadge() }}">
                            <span class="w-2 h-2 rounded-full bg-current" aria-hidden="true"></span>
                            {{ $status->label() }}
                        </span>
                    </div>
                    <h1 class="text-xl font-bold text-[#0F172A] mt-2.5">{{ $complaint->title }}</h1>
                    <p class="text-xs text-[#64748B] mt-1">
                        Dikirim: {{ $complaint->submitted_at?->translatedFormat('d F Y, H:i') ?? '—' }} WITA
                        @if ($complaint->category)
                            &bull; Kategori: <strong class="text-[#334155]">{{ $complaint->category->name }}</strong>
                            @if ($complaint->category->dinas_name)
                                ({{ $complaint->category->dinas_name }})
                            @endif
                        @endif
                        &bull; Tujuan: <strong class="text-[#334155]">{{ $complaint->dinasUnit?->name ?? 'Belum ditentukan' }}</strong>
                    </p>
                </div>
                <div class="shrink-0 flex items-center gap-2">
                    {{-- Operator info badge --}}
                    <div class="flex items-center gap-2 px-3 py-2 bg-[#F8FAFC] rounded-xl border border-[#E2E8F0]">
                        <svg class="w-4 h-4 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <span class="text-xs text-[#475569]">
                            {{ $complaint->assignee?->name ?? 'Belum ditugaskan' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Left column: main complaint info + actions --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Complaint Detail --}}
                <div class="bg-white rounded-2xl border border-[#E2E8F0] p-6 shadow-sm space-y-4">
                    <h2 class="text-sm font-bold text-[#0F172A] uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#2563EB]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Rincian Pengaduan
                    </h2>
                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div class="p-3 bg-[#F8FAFC] rounded-xl border border-[#E2E8F0]">
                            <span class="font-semibold text-[#64748B]">Pelapor</span>
                            <p class="text-[#0F172A] mt-0.5 font-medium">{{ $complaint->reporter?->name ?? '—' }}</p>
                        </div>
                        <div class="p-3 bg-[#F8FAFC] rounded-xl border border-[#E2E8F0]">
                            <span class="font-semibold text-[#64748B]">Lokasi</span>
                            <p class="text-[#0F172A] mt-0.5">{{ $complaint->location_text ?? '—' }}</p>
                        </div>
                    </div>
                    <div class="text-sm text-[#334155] leading-relaxed whitespace-pre-line border-t border-[#F1F5F9] pt-4">
                        {{ $complaint->description }}
                    </div>
                </div>

                {{-- Attachments --}}
                <div class="bg-white rounded-2xl border border-[#E2E8F0] p-6 shadow-sm space-y-4">
                    <h2 class="text-sm font-bold text-[#0F172A] uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#2563EB]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                        Lampiran Berkas ({{ $complaint->attachments->count() }})
                    </h2>
                    @if ($complaint->attachments->isEmpty())
                        <p class="text-xs text-[#94A3B8] italic">Tidak ada lampiran.</p>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach ($complaint->attachments as $attachment)
                                <a href="{{ route('operator.complaint.attachment', [$complaint, $attachment]) }}"
                                   target="_blank"
                                   class="flex items-center gap-3 p-3.5 rounded-xl border border-[#E2E8F0] hover:border-[#2563EB] hover:bg-blue-50/50 ui-animated group">
                                    <div class="w-9 h-9 rounded-lg bg-[#DBEAFE] text-[#2563EB] flex items-center justify-center shrink-0">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold text-[#0F172A] truncate group-hover:text-[#2563EB] ui-animated">{{ $attachment->original_name }}</p>
                                        <p class="text-[11px] text-[#64748B] mt-0.5">{{ $attachment->humanSize() }}</p>
                                    </div>
                                    <svg class="w-4 h-4 text-[#94A3B8] group-hover:text-[#2563EB] group-hover:translate-x-0.5 ui-animated shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Internal Notes (Operator/Staff only — NEVER shown to Citizen) --}}
                <div class="bg-white rounded-2xl border border-amber-200 p-6 shadow-sm space-y-4">
                    <h2 class="text-sm font-bold text-amber-800 uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Catatan Internal
                        <span class="ml-auto inline-flex items-center gap-1 px-2 py-0.5 bg-amber-100 text-amber-700 text-[10px] font-semibold rounded-full border border-amber-200">
                            <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                            Tidak terlihat oleh pelapor
                        </span>
                    </h2>
                    @if ($complaint->internalNotes->isEmpty())
                        <p class="text-xs text-[#94A3B8] italic">Belum ada catatan internal.</p>
                    @else
                        <div class="space-y-3">
                            @foreach ($complaint->internalNotes as $note)
                                <div class="p-4 rounded-xl bg-amber-50 border border-amber-100 space-y-1.5">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-semibold text-amber-800">{{ $note->author?->name ?? 'Sistem' }}</span>
                                        <span class="text-amber-600">{{ $note->created_at?->translatedFormat('d M Y, H:i') ?? '—' }} WITA</span>
                                    </div>
                                    <p class="text-xs text-[#334155] leading-relaxed whitespace-pre-line">{{ $note->body }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Add Internal Note Form --}}
                    <form method="POST" action="{{ route('operator.complaint.add-note', $complaint) }}" class="pt-2 border-t border-amber-100">
                        @csrf
                        <input type="hidden" name="visibility" value="internal">
                        <label for="note-internal-body" class="block text-xs font-semibold text-amber-800 mb-1.5">Tambah Catatan Internal</label>
                        <textarea
                            id="note-internal-body"
                            name="body"
                            rows="3"
                            placeholder="Tulis catatan internal di sini..."
                            class="w-full px-3 py-2 text-sm border border-amber-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-400/40 focus:border-amber-400 resize-none ui-animated"
                            required
                            maxlength="5000"
                        ></textarea>
                        <div class="flex justify-end mt-2">
                            <button type="submit"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold rounded-xl ui-animated shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Simpan Catatan Internal
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Public Responses --}}
                <div class="bg-white rounded-2xl border border-[#E2E8F0] p-6 shadow-sm space-y-4">
                    <h2 class="text-sm font-bold text-[#0F172A] uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#2563EB]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                        Respons Publik
                        <span class="ml-auto inline-flex items-center gap-1 px-2 py-0.5 bg-blue-50 text-blue-700 text-[10px] font-semibold rounded-full border border-blue-200">
                            <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            Terlihat oleh pelapor
                        </span>
                    </h2>
                    @if ($complaint->publicResponses->isEmpty())
                        <p class="text-xs text-[#94A3B8] italic">Belum ada respons publik.</p>
                    @else
                        <div class="space-y-3">
                            @foreach ($complaint->publicResponses as $response)
                                <div class="p-4 rounded-xl bg-blue-50/50 border border-blue-100 space-y-1.5">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-semibold text-[#1E40AF]">{{ $response->author?->name ?? 'Tim Lapor Pak Wali' }}</span>
                                        <span class="text-[#64748B]">{{ $response->created_at?->translatedFormat('d M Y, H:i') ?? '—' }} WITA</span>
                                    </div>
                                    <p class="text-xs text-[#1E293B] leading-relaxed whitespace-pre-line">{{ $response->body }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Add Public Response Form --}}
                    <form method="POST" action="{{ route('operator.complaint.add-note', $complaint) }}" class="pt-2 border-t border-[#F1F5F9]">
                        @csrf
                        <input type="hidden" name="visibility" value="public_response">
                        <label for="note-public-body" class="block text-xs font-semibold text-[#0F172A] mb-1.5">Tambah Respons Publik</label>
                        <textarea
                            id="note-public-body"
                            name="body"
                            rows="3"
                            placeholder="Tulis respons publik untuk pelapor di sini..."
                            class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB] resize-none ui-animated"
                            required
                            maxlength="5000"
                        ></textarea>
                        <div class="flex justify-end mt-2">
                            <button type="submit"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-xs font-semibold rounded-xl ui-animated shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Kirim Respons Publik
                            </button>
                        </div>
                    </form>
                </div>

            </div>{{-- end left column --}}

            {{-- Right column: actions sidebar --}}
            <div class="space-y-5">

                {{-- Update Status --}}
                <div class="bg-white rounded-2xl border border-[#E2E8F0] p-5 shadow-sm space-y-4">
                    <h2 class="text-sm font-bold text-[#0F172A] uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#2563EB]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Perbarui Status
                    </h2>

                    @if (empty($allowedTransitions))
                        <p class="text-xs text-[#94A3B8] italic p-3 bg-[#F8FAFC] rounded-xl border border-[#E2E8F0]">
                            Status "{{ $status->label() }}" tidak memiliki transisi lebih lanjut.
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
                                    <label for="status-select" class="block text-xs font-semibold text-[#475569] mb-1">Status Baru</label>
                                    <select id="status-select" name="status" required
                                        x-model="selectedStatus"
                                        class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB] ui-animated">
                                        <option value="" disabled selected>Pilih status...</option>
                                        @foreach ($allowedTransitions as $nextStatus)
                                            <option value="{{ $nextStatus->value }}">{{ $nextStatus->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Rejection Reason — required when rejected --}}
                                <div x-show="isRejected" x-cloak>
                                    <label for="rejection-reason" class="block text-xs font-semibold text-red-700 mb-1">
                                        Alasan Penolakan <span class="text-red-500">*</span>
                                    </label>
                                    <textarea
                                        id="rejection-reason"
                                        name="rejection_reason"
                                        :required="isRejected"
                                        rows="3"
                                        placeholder="Jelaskan alasan penolakan laporan ini..."
                                        class="w-full px-3 py-2 text-sm border border-red-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-red-400/40 focus:border-red-400 resize-none ui-animated"
                                        maxlength="1000"
                                    ></textarea>
                                    @error('rejection_reason')
                                        <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Public Response — required when rejected or resolved --}}
                                <div x-show="needsPublicResponse" x-cloak>
                                    <label for="status-public-response" class="block text-xs font-semibold text-[#0F172A] mb-1">
                                        Respons Publik untuk Pelapor <span class="text-red-500">*</span>
                                        <span class="font-normal text-[#64748B]">(terlihat oleh pelapor)</span>
                                    </label>
                                    <textarea
                                        id="status-public-response"
                                        name="public_response_body"
                                        :required="needsPublicResponse"
                                        rows="3"
                                        placeholder="Tulis tanggapan resmi untuk pelapor..."
                                        class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB] resize-none ui-animated"
                                        maxlength="5000"
                                    ></textarea>
                                    @error('public_response_body')
                                        <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="status-note" class="block text-xs font-semibold text-[#475569] mb-1">
                                        Catatan Perubahan <span class="font-normal text-[#94A3B8]">(opsional)</span>
                                    </label>
                                    <textarea
                                        id="status-note"
                                        name="note"
                                        rows="2"
                                        placeholder="Keterangan perubahan status..."
                                        class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB] resize-none ui-animated"
                                        maxlength="1000"
                                    ></textarea>
                                </div>
                                <button type="submit"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-[#0F766E] hover:bg-[#0D6B63] text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Simpan Status
                                </button>
                            </div>
                        </form>
                    @endif
                </div>

                {{-- Update Category --}}
                <div class="bg-white rounded-2xl border border-[#E2E8F0] p-5 shadow-sm space-y-4">
                    <h2 class="text-sm font-bold text-[#0F172A] uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#2563EB]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                        Ubah Kategori
                    </h2>
                    <form method="POST" action="{{ route('operator.complaint.update-category', $complaint) }}">
                        @csrf
                        @method('PATCH')
                        <div class="space-y-3">
                            <div>
                                <label for="category-select" class="block text-xs font-semibold text-[#475569] mb-1">Kategori</label>
                                <select id="category-select" name="category_id" required
                                    class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB] ui-animated">
                                    <option value="" disabled>Pilih kategori...</option>
                                    @foreach ($categories as $cat)
                                        <option value="{{ $cat->id }}" @selected($cat->id === $complaint->category_id)>
                                            {{ $cat->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Simpan Kategori
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Tujuan Dinas/Unit (actual destination, Prompt 5C) --}}
                <div class="bg-white rounded-2xl border border-[#E2E8F0] p-5 shadow-sm space-y-4">
                    <h2 class="text-sm font-bold text-[#0F172A] uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#2563EB]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        Tujuan Dinas/Unit
                    </h2>
                    <p class="text-xs text-[#64748B]">
                        Saat ini: <strong class="text-[#334155]">{{ $complaint->dinasUnit?->name ?? 'Belum ditentukan' }}</strong>
                    </p>
                    @if ($mappedDinasUnits->isEmpty())
                        <p class="text-xs text-[#94A3B8] italic">
                            Tidak ada Dinas/Unit aktif yang terhubung dengan kategori laporan ini.
                            @if ($complaint->dinas_unit_id)
                                Tujuan historis tetap tersimpan.
                            @endif
                        </p>
                    @else
                        <form method="POST" action="{{ route('operator.complaint.update-destination', $complaint) }}">
                            @csrf
                            @method('PATCH')
                            <div class="space-y-3">
                                <div>
                                    <label for="destination-select" class="block text-xs font-semibold text-[#475569] mb-1">Dinas/Unit Tujuan</label>
                                    <select id="destination-select" name="dinas_unit_id"
                                        class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB] ui-animated">
                                        <option value="">— Hapus tujuan —</option>
                                        @foreach ($mappedDinasUnits as $unit)
                                            <option value="{{ $unit->id }}" @selected($unit->id === $complaint->dinas_unit_id)>
                                                {{ $unit->name }}@unless($unit->is_active) (nonaktif)@endunless
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-700 hover:bg-slate-800 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Simpan Tujuan
                                </button>
                            </div>
                        </form>
                    @endif
                    @error('dinas_unit_id')
                        <p class="text-xs text-red-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Assignment --}}
                <div class="bg-white rounded-2xl border border-[#E2E8F0] p-5 shadow-sm space-y-4">
                    <h2 class="text-sm font-bold text-[#0F172A] uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#2563EB]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Tugaskan Operator
                    </h2>
                    <form method="POST" action="{{ route('operator.complaint.assign', $complaint) }}">
                        @csrf
                        @method('PATCH')
                        <div class="space-y-3">
                            <div>
                                <label for="assign-select" class="block text-xs font-semibold text-[#475569] mb-1">Operator</label>
                                <select id="assign-select" name="assigned_to"
                                    class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB] ui-animated">
                                    <option value="">— Hapus penugasan —</option>
                                    @foreach ($operators as $op)
                                        <option value="{{ $op->id }}" @selected($op->id === $complaint->assigned_to)>
                                            {{ $op->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-700 hover:bg-slate-800 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Simpan Penugasan
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Status History --}}
                <div class="bg-white rounded-2xl border border-[#E2E8F0] p-5 shadow-sm space-y-4">
                    <h2 class="text-sm font-bold text-[#0F172A] uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#2563EB]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Riwayat Status
                    </h2>
                    @if ($complaint->statusHistories->isEmpty())
                        <p class="text-xs text-[#94A3B8] italic">Belum ada riwayat.</p>
                    @else
                        <ol class="relative border-l-2 border-blue-200 ml-2 space-y-4 my-1">
                            @foreach ($complaint->statusHistories as $history)
                                <li class="ml-4">
                                    <span class="absolute -left-1.5 mt-1.5 w-3 h-3 rounded-full bg-[#2563EB] ring-4 ring-white"></span>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-xs font-bold text-[#0F172A]">
                                            {{ \App\Enums\ComplaintStatus::tryFrom($history->to_status)?->label() ?? $history->to_status }}
                                        </span>
                                        @if ($history->changedBy)
                                            <span class="text-[11px] text-[#64748B]">by {{ $history->changedBy->name }}</span>
                                        @endif
                                    </div>
                                    <time class="block text-[11px] text-[#64748B] mt-0.5">
                                        {{ $history->created_at?->translatedFormat('d M Y, H:i') ?? '—' }} WITA
                                    </time>
                                    @if ($history->note)
                                        <p class="text-xs text-[#475569] mt-1 bg-[#F8FAFC] p-2 rounded-lg border border-[#E2E8F0]">
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
    </div>
</x-layouts.dashboard>
