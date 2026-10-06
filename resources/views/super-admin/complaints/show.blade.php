@php
    $title = 'Detail Laporan ' . $complaint->reference_code . ' — Super Admin';
    $status = $complaint->status;
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Detail Laporan</x-slot:header>
    <x-slot:breadcrumb>Semua Laporan — inspeksi read-only</x-slot:breadcrumb>

    <x-slot:headerActions>
        <a href="{{ route('super-admin.complaints.index') }}"
           class="inline-flex items-center gap-2 bg-white hover:bg-[#F1F5F9] text-[#475569] text-sm font-semibold px-4 py-2 rounded-xl border border-[#CBD5E1] ui-animated shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Kembali
        </a>
    </x-slot:headerActions>

    <div class="max-w-6xl mx-auto space-y-6 card-enter">

        {{-- Read-only banner --}}
        <div class="flex items-center gap-2 bg-slate-50 border border-slate-200 text-slate-600 text-xs px-4 py-2.5 rounded-xl">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            Halaman ini hanya untuk pemantauan. Super Admin tidak dapat mengubah status, kategori, tujuan, penugasan, catatan, atau lampiran laporan dari sini.
        </div>

        {{-- Status & Reference Banner --}}
        <div class="bg-white rounded-2xl border border-[#E2E8F0] p-5 shadow-sm">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3 flex-wrap">
                        <span class="text-xs font-semibold text-[#64748B] uppercase tracking-wider">Nomor Registrasi:</span>
                        <span class="font-mono text-sm font-bold text-[#0F172A] bg-[#F1F5F9] px-3 py-1 rounded-lg border border-[#E2E8F0]">{{ $complaint->reference_code }}</span>
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
                        @endif
                        &bull; Tujuan: <strong class="text-[#334155]">{{ $complaint->dinasUnit?->name ?? 'Belum ditentukan' }}</strong>
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Left column --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Complaint Detail --}}
                <div class="bg-white rounded-2xl border border-[#E2E8F0] p-6 shadow-sm space-y-4">
                    <h2 class="text-sm font-bold text-[#0F172A] uppercase tracking-wider">Rincian Pengaduan</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div class="p-3 bg-[#F8FAFC] rounded-xl border border-[#E2E8F0]">
                            <span class="font-semibold text-[#64748B]">Pelapor</span>
                            <p class="text-[#0F172A] mt-0.5 font-medium">{{ $complaint->reporter?->name ?? '—' }}</p>
                        </div>
                        <div class="p-3 bg-[#F8FAFC] rounded-xl border border-[#E2E8F0]">
                            <span class="font-semibold text-[#64748B]">Lokasi</span>
                            <p class="text-[#0F172A] mt-0.5">{{ $complaint->location_text ?? '—' }}</p>
                        </div>
                        <div class="p-3 bg-[#F8FAFC] rounded-xl border border-[#E2E8F0]">
                            <span class="font-semibold text-[#64748B]">Dibuat</span>
                            <p class="text-[#0F172A] mt-0.5">{{ $complaint->created_at?->translatedFormat('d M Y, H:i') ?? '—' }} WITA</p>
                        </div>
                        <div class="p-3 bg-[#F8FAFC] rounded-xl border border-[#E2E8F0]">
                            <span class="font-semibold text-[#64748B]">Diperbarui</span>
                            <p class="text-[#0F172A] mt-0.5">{{ $complaint->updated_at?->translatedFormat('d M Y, H:i') ?? '—' }} WITA</p>
                        </div>
                    </div>
                    <div class="text-sm text-[#334155] leading-relaxed whitespace-pre-line border-t border-[#F1F5F9] pt-4">
                        {{ $complaint->description }}
                    </div>
                </div>

                {{-- Attachments (reuses the existing authorized download route) --}}
                <div class="bg-white rounded-2xl border border-[#E2E8F0] p-6 shadow-sm space-y-4">
                    <h2 class="text-sm font-bold text-[#0F172A] uppercase tracking-wider">Lampiran Berkas ({{ $complaint->attachments->count() }})</h2>
                    @if ($complaint->attachments->isEmpty())
                        <p class="text-xs text-[#94A3B8] italic">Tidak ada lampiran.</p>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach ($complaint->attachments as $attachment)
                                <a href="{{ route('operator.complaint.attachment', [$complaint, $attachment]) }}"
                                   target="_blank" rel="noopener"
                                   class="flex items-center gap-3 p-3.5 rounded-xl border border-[#E2E8F0] hover:border-[#2563EB] hover:bg-blue-50/50 ui-animated group">
                                    <div class="w-9 h-9 rounded-lg bg-[#DBEAFE] text-[#2563EB] flex items-center justify-center shrink-0">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold text-[#0F172A] truncate group-hover:text-[#2563EB] ui-animated">{{ $attachment->original_name }}</p>
                                        <p class="text-[11px] text-[#64748B] mt-0.5">{{ $attachment->humanSize() }}</p>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Internal Notes (read-only) --}}
                <div class="bg-white rounded-2xl border border-amber-200 p-6 shadow-sm space-y-4">
                    <h2 class="text-sm font-bold text-amber-800 uppercase tracking-wider">Catatan Internal</h2>
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
                </div>

                {{-- Public Responses (read-only) --}}
                <div class="bg-white rounded-2xl border border-[#E2E8F0] p-6 shadow-sm space-y-4">
                    <h2 class="text-sm font-bold text-[#0F172A] uppercase tracking-wider">Respons Publik</h2>
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
                </div>

            </div>{{-- end left column --}}

            {{-- Right column --}}
            <div class="space-y-5">

                {{-- Historical routing + assignment (read-only) --}}
                <div class="bg-white rounded-2xl border border-[#E2E8F0] p-5 shadow-sm space-y-4">
                    <h2 class="text-sm font-bold text-[#0F172A] uppercase tracking-wider">Routing & Penugasan</h2>
                    <dl class="space-y-3 text-xs">
                        <div>
                            <dt class="font-semibold text-[#64748B]">Kategori</dt>
                            <dd class="text-[#0F172A] mt-0.5">{{ $complaint->category?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-[#64748B]">Dinas/Unit Tujuan (historis)</dt>
                            <dd class="text-[#0F172A] mt-0.5">
                                {{ $complaint->dinasUnit?->name ?? 'Belum ditentukan' }}
                                @if ($complaint->dinasUnit && ! $complaint->dinasUnit->is_active)
                                    <span class="ml-1 inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-500 border border-slate-200">nonaktif</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-[#64748B]">Operator Ditugaskan</dt>
                            <dd class="text-[#0F172A] mt-0.5">{{ $complaint->assignee?->name ?? 'Belum ditugaskan' }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- Status History timeline (read-only) --}}
                <div class="bg-white rounded-2xl border border-[#E2E8F0] p-5 shadow-sm space-y-4">
                    <h2 class="text-sm font-bold text-[#0F172A] uppercase tracking-wider">Riwayat Status</h2>
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
                                            <span class="text-[11px] text-[#64748B]">oleh {{ $history->changedBy->name }}</span>
                                        @endif
                                    </div>
                                    <time class="block text-[11px] text-[#64748B] mt-0.5">{{ $history->created_at?->translatedFormat('d M Y, H:i') ?? '—' }} WITA</time>
                                    @if ($history->note)
                                        <p class="text-xs text-[#475569] mt-1 bg-[#F8FAFC] p-2 rounded-lg border border-[#E2E8F0]">{{ $history->note }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>

                {{-- Audit context (read-only, redacted) --}}
                <div class="bg-white rounded-2xl border border-[#E2E8F0] p-5 shadow-sm space-y-4">
                    <h2 class="text-sm font-bold text-[#0F172A] uppercase tracking-wider">Jejak Audit Terkait</h2>
                    @if ($safeAuditLogs->isEmpty())
                        <p class="text-xs text-[#94A3B8] italic">Belum ada catatan audit untuk laporan ini.</p>
                    @else
                        <div class="space-y-3">
                            @foreach ($safeAuditLogs as $entry)
                                <div class="p-3 rounded-xl bg-[#F8FAFC] border border-[#E2E8F0] space-y-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-mono text-[11px] bg-slate-100 text-slate-700 px-2 py-0.5 rounded">{{ $entry['log']->action }}</span>
                                        <span class="text-[11px] text-[#64748B]">{{ $entry['log']->created_at?->translatedFormat('d M Y, H:i') ?? '—' }}</span>
                                    </div>
                                    <p class="text-[11px] text-[#64748B]">Oleh: {{ $entry['log']->actor?->name ?? 'Sistem' }}</p>
                                    @if (!empty($entry['metadata']))
                                        <p class="text-[11px] text-[#475569] break-words">
                                            @foreach ($entry['metadata'] as $k => $v)
                                                <span class="inline-block mr-2"><span class="text-[#94A3B8]">{{ $k }}:</span> {{ is_scalar($v) ? \Illuminate\Support\Str::limit((string) $v, 60) : json_encode($v) }}</span>
                                            @endforeach
                                        </p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>{{-- end right column --}}

        </div>{{-- end grid --}}
    </div>
</x-layouts.dashboard>
