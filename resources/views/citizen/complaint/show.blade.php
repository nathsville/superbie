@php
    $title = 'Detail Laporan ' . $complaint->reference_code;
    $status = $complaint->status;
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @php $route = request()->routeIs('citizen.*') ? request()->route()->getName() : ''; @endphp
        <div class="space-y-1">
            <a href="{{ route('citizen.dashboard') }}"
               class="nav-link {{ $route === 'citizen.dashboard' ? 'active' : '' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>
            <a href="{{ route('citizen.complaint.create') }}"
               class="nav-link {{ $route === 'citizen.complaint.create' ? 'active' : '' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Buat Laporan
            </a>
            <a href="{{ route('citizen.history') }}"
               class="nav-link {{ $route === 'citizen.history' ? 'active' : '' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Riwayat Laporan
            </a>
            <a href="{{ route('citizen.profile.edit') }}"
               class="nav-link {{ $route === 'citizen.profile.edit' ? 'active' : '' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                Profil Saya
            </a>
        </div>
    </x-slot:navigation>

    <x-slot:header>Detail Laporan</x-slot:header>
    <x-slot:breadcrumb>Lapor Pak Wali — Pantau status dan tindak lanjut laporan Anda</x-slot:breadcrumb>

    <x-slot:headerActions>
        <a
            href="{{ route('citizen.history') }}"
            class="inline-flex items-center gap-2 bg-white hover:bg-[#F1F5F9] text-[#475569] text-sm font-semibold px-4 py-2 rounded-xl border border-[#CBD5E1] ui-animated shadow-sm"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Kembali ke Riwayat
        </a>
    </x-slot:headerActions>

    <div class="max-w-5xl mx-auto space-y-6 card-enter">
        {{-- Status & Reference Banner --}}
        <div class="bg-white rounded-2xl border border-[#E2E8F0] p-6 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="text-xs font-semibold text-[#64748B] uppercase tracking-wider">Nomor Registrasi:</span>
                    <span class="font-mono text-base font-bold text-[#0F172A] bg-[#F1F5F9] px-3 py-1 rounded-lg border border-[#E2E8F0]">
                        {{ $complaint->reference_code }}
                    </span>
                    <span class="status-badge {{ $status->tailwindBadge() }}">
                        <span class="w-2 h-2 rounded-full bg-current" aria-hidden="true"></span>
                        {{ $status->label() }}
                    </span>
                </div>
                <h1 class="text-xl font-bold text-[#0F172A] mt-2.5">{{ $complaint->title }}</h1>
                <p class="text-xs text-[#64748B] mt-1">
                    Dikirim pada {{ $complaint->submitted_at?->translatedFormat('d F Y, H:i') ?? '—' }} WITA
                    @if ($complaint->category)
                        • Kategori: <strong class="text-[#334155]">{{ $complaint->category->name }}</strong> ({{ $complaint->category->dinas_name }})
                    @endif
                </p>
            </div>

            <div class="shrink-0 flex items-center gap-2">
                <a
                    href="{{ route('citizen.complaint.create') }}"
                    class="inline-flex items-center gap-2 bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-xs font-semibold px-4 py-2 rounded-xl ui-animated shadow-sm"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Buat Laporan Lain
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Main Column: Detail & Attachments & Official Responses --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Detail Content --}}
                <div class="bg-white rounded-2xl border border-[#E2E8F0] p-6 shadow-sm space-y-4">
                    <h2 class="text-sm font-bold text-[#0F172A] uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#2563EB]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Rincian Pengaduan
                    </h2>

                    @if ($complaint->location_text)
                        <div class="p-3.5 rounded-xl bg-[#F8FAFC] border border-[#E2E8F0] flex items-start gap-3 text-xs">
                            <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <div>
                                <span class="font-semibold text-[#0F172A]">Lokasi Kejadian:</span>
                                <p class="text-[#475569] mt-0.5">{{ $complaint->location_text }}</p>
                            </div>
                        </div>
                    @endif

                    <div class="text-sm text-[#334155] leading-relaxed whitespace-pre-line pt-2">
                        {{ $complaint->description }}
                    </div>
                </div>

                {{-- Attachments --}}
                <div class="bg-white rounded-2xl border border-[#E2E8F0] p-6 shadow-sm space-y-4">
                    <h2 class="text-sm font-bold text-[#0F172A] uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#2563EB]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                        </svg>
                        Lampiran Berkas / Bukti ({{ $complaint->attachments->count() }})
                    </h2>

                    @if ($complaint->attachments->isEmpty())
                        <p class="text-xs text-[#64748B] italic">Tidak ada berkas lampiran yang diunggah untuk laporan ini.</p>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach ($complaint->attachments as $attachment)
                                <a
                                    href="{{ route('citizen.complaint.attachment', [$complaint, $attachment]) }}"
                                    target="_blank"
                                    class="flex items-center gap-3 p-3.5 rounded-xl border border-[#E2E8F0] hover:border-[#2563EB] hover:bg-blue-50/50 ui-animated group"
                                >
                                    <div class="w-9 h-9 rounded-lg bg-[#DBEAFE] text-[#2563EB] flex items-center justify-center shrink-0">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold text-[#0F172A] truncate group-hover:text-[#2563EB] ui-animated">
                                            {{ $attachment->original_name }}
                                        </p>
                                        <p class="text-[11px] text-[#64748B] mt-0.5">
                                            {{ $attachment->humanSize() }}
                                        </p>
                                    </div>
                                    <svg class="w-4 h-4 text-[#94A3B8] group-hover:text-[#2563EB] group-hover:translate-x-0.5 ui-animated shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Official Responses from Staff --}}
                <div class="bg-white rounded-2xl border border-[#E2E8F0] p-6 shadow-sm space-y-4">
                    <h2 class="text-sm font-bold text-[#0F172A] uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#2563EB]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                        </svg>
                        Tanggapan Resmi Petugas / Instansi
                    </h2>

                    @if ($complaint->publicResponses->isEmpty())
                        <div class="p-4 rounded-xl bg-[#F8FAFC] border border-[#E2E8F0] text-center py-6">
                            <div class="w-10 h-10 mx-auto mb-2 rounded-xl bg-blue-50 text-[#2563EB] flex items-center justify-center">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <p class="text-xs font-semibold text-[#0F172A]">Belum Ada Tanggapan Resmi</p>
                            <p class="text-xs text-[#64748B] mt-1 max-w-md mx-auto">
                                Laporan Anda sedang dalam tahap penelaahan oleh tim command center dan dinas terkait. Tanggapan resmi akan diperbarui secara transparan di sini.
                            </p>
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach ($complaint->publicResponses as $response)
                                <div class="p-4 rounded-xl bg-blue-50/50 border border-blue-100 space-y-2">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-bold text-[#1E40AF] flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                            </svg>
                                            {{ $response->author?->name ?? 'Tim Command Center Lapor Pak Wali' }}
                                        </span>
                                        <span class="text-[#64748B] text-[11px]">
                                            {{ $response->created_at?->translatedFormat('d M Y, H:i') ?? '—' }} WITA
                                        </span>
                                    </div>
                                    <p class="text-xs text-[#1E293B] leading-relaxed whitespace-pre-line">
                                        {{ $response->body }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Sidebar Column: Status Timeline & Metadata --}}
            <div class="space-y-6">
                {{-- Status Timeline --}}
                <div class="bg-white rounded-2xl border border-[#E2E8F0] p-6 shadow-sm space-y-4">
                    <h2 class="text-sm font-bold text-[#0F172A] uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#2563EB]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Riwayat Perkembangan
                    </h2>

                    @if ($complaint->statusHistories->isEmpty())
                        <p class="text-xs text-[#64748B] italic">Belum ada riwayat perkembangan.</p>
                    @else
                        <ol class="relative border-l-2 border-blue-200 ml-2 space-y-5 my-2">
                            @foreach ($complaint->statusHistories as $history)
                                <li class="ml-4">
                                    <span class="absolute -left-1.5 mt-1.5 w-3 h-3 rounded-full bg-[#2563EB] ring-4 ring-white"></span>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-xs font-bold text-[#0F172A]">
                                            {{ \App\Enums\ComplaintStatus::tryFrom($history->to_status)?->label() ?? $history->to_status }}
                                        </span>
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

                {{-- Assistance info card --}}
                <div class="bg-gradient-to-br from-[#0F172A] to-[#1E293B] text-white rounded-2xl p-5 shadow-sm space-y-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-blue-500/20 text-blue-400 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-200">Bantuan Layanan</h3>
                    </div>
                    <p class="text-xs text-slate-300 leading-relaxed">
                        Jika ada data tambahan yang perlu diklarifikasi terkait laporan ini, mohon simpan Nomor Registrasi Anda <strong>{{ $complaint->reference_code }}</strong> untuk keperluan verifikasi.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-layouts.dashboard>
