@php
    $title = 'Detail Laporan ' . $complaint->reference_code;
    $status = $complaint->status;
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @php $route = request()->routeIs('citizen.*') ? request()->route()->getName() : ''; @endphp
        @include('citizen.partials.nav', ['currentRoute' => $route])
    </x-slot:navigation>

    <x-slot:header>Detail Laporan</x-slot:header>
    <x-slot:breadcrumb>Lapor Pak Wali — Pantau status dan tindak lanjut laporan Anda</x-slot:breadcrumb>

    <div class="w-full max-w-full space-y-6 card-enter">
        {{-- ── Header laporan (full width) ──────────────────────────────────── --}}
        <div class="w-full bg-white rounded-2xl border border-line p-6 shadow-sm flex flex-col md:flex-row md:items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="flex items-center gap-2.5 flex-wrap">
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-faint uppercase tracking-wider">
                        <svg class="w-3.5 h-3.5 text-ink-faint" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5a2 2 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                        Nomor Registrasi
                    </span>
                    <span class="font-mono text-sm font-bold text-ink bg-surface-sunken px-3 py-1 rounded-lg border border-line">
                        {{ $complaint->reference_code }}
                    </span>
                    <span class="status-badge {{ $status->tailwindBadge() }}">
                        <span class="w-2 h-2 rounded-full bg-current" aria-hidden="true"></span>
                        {{ $status->label() }}
                    </span>
                </div>

                <h2 class="text-xl font-bold text-ink mt-3">{{ $complaint->title }}</h2>

                <p class="text-xs text-ink-faint mt-1.5">
                    Dikirim pada {{ $complaint->submitted_at?->translatedFormat('d F Y, H:i') ?? '—' }} WITA
                    @if ($complaint->category)
                        • Kategori: <strong class="text-ink-body">{{ $complaint->category->name }}</strong>@if ($complaint->category->dinas_name) ({{ $complaint->category->dinas_name }})@endif
                    @endif
                </p>
            </div>

            <div class="shrink-0">
                <a
                    href="{{ route('citizen.complaint.create') }}"
                    class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold px-4 py-2.5 rounded-xl ui-animated shadow-sm"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Buat Laporan Lain
                </a>
            </div>
        </div>

        {{-- PHASE 2 — Return-to-citizen banner. Shown ONLY while the complaint is
             returned to the reporter; the return reason is a reporter-visible
             workflow message (never an internal note). --}}
        @if ($complaint->isReturnedToCitizen())
            <div class="w-full bg-orange-50 border border-orange-200 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-start gap-4">
                <div class="w-10 h-10 rounded-xl bg-orange-100 text-orange-600 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <h2 class="text-sm font-bold text-orange-900">Laporan ini dikembalikan untuk diperbaiki</h2>
                    <p class="text-xs text-orange-800 mt-1">Perbaiki laporan sesuai catatan berikut, lalu kirim ulang untuk divalidasi kembali oleh Super Admin.</p>
                    @if ($complaint->review_return_reason)
                        <div class="mt-3 p-3 rounded-xl bg-white border border-orange-200">
                            <p class="text-[11px] font-semibold text-orange-700 uppercase tracking-wider">Catatan dari Super Admin</p>
                            <p class="text-sm text-ink-body mt-1 whitespace-pre-line">{{ $complaint->review_return_reason }}</p>
                        </div>
                    @endif
                </div>
                <a href="{{ route('citizen.complaint.edit', $complaint) }}"
                   class="shrink-0 inline-flex items-center justify-center gap-2 bg-orange-600 hover:bg-orange-700 text-white text-xs font-semibold px-4 py-2.5 rounded-xl ui-animated shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Perbaiki Laporan
                </a>
            </div>
        @endif

        {{-- ── Bento grid 8:4 ───────────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            {{-- ═══ KOLOM KIRI (8) — Rincian, Lampiran, Tanggapan, Rating ═════ --}}
            <div class="lg:col-span-8 space-y-6">
                {{-- Detail Content --}}
                <div class="w-full bg-white rounded-2xl border border-line p-6 shadow-sm space-y-5">
                    <h2 class="text-sm font-bold text-ink uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Rincian Pengaduan
                    </h2>

                    {{-- ═══════════════════════════════════════════════════════════════
                         LOKASI KEJADIAN — modular (NULL-safe for legacy complaints).

                         administrative-location → kecamatan_id + kelurahan_id
                         location-detail         → location_text
                         location-map-slot       → read-only map + pin (Prompt 4/5)
                         ═══════════════════════════════════════════════════════════════ --}}
                    <div class="location-section p-4 rounded-xl bg-page border border-line flex items-start gap-3">
                        <div class="w-9 h-9 rounded-lg bg-red-50 text-red-500 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0 space-y-3">
                            <span class="text-xs font-bold text-ink uppercase tracking-wider">Lokasi Kejadian</span>
                            <dl class="administrative-location grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="rounded-lg bg-white border border-line px-3 py-2">
                                    <dt class="text-[11px] font-semibold text-ink-faint uppercase tracking-wide">Kecamatan</dt>
                                    <dd class="text-sm text-ink mt-0.5 font-semibold">{{ $complaint->kecamatan?->name ?? '—' }}</dd>
                                </div>
                                <div class="rounded-lg bg-white border border-line px-3 py-2">
                                    <dt class="text-[11px] font-semibold text-ink-faint uppercase tracking-wide">Kelurahan</dt>
                                    <dd class="text-sm text-ink mt-0.5 font-semibold">{{ $complaint->kelurahan?->name ?? '—' }}</dd>
                                </div>
                            </dl>
                            <div class="location-detail">
                                <span class="text-[11px] font-semibold text-ink-faint uppercase tracking-wide">Lokasi/Alamat Detail</span>
                                <p class="text-sm text-ink-soft mt-0.5">{{ $complaint->location_text ?? '—' }}</p>
                            </div>
                            {{-- location-map-slot: read-only map (Prompt 4/5). Shows a marker only
                                 when a valid coordinate pair exists; legacy complaints show a
                                 "belum tersedia" note. Never calls the browser geolocation. --}}
                            <x-location-map-slot
                                readonly
                                :latitude="$complaint->latitude"
                                :longitude="$complaint->longitude"
                                label="Peta Lokasi Kejadian"
                            />
                        </div>
                    </div>

                    <div>
                        <span class="text-[11px] font-semibold text-ink-faint uppercase tracking-wide">Isi Pengaduan</span>
                        <div class="text-sm text-ink-body leading-relaxed whitespace-pre-line mt-1.5">
                            {{ $complaint->description }}
                        </div>
                    </div>
                </div>

                {{-- Attachments --}}
                <div class="w-full bg-white rounded-2xl border border-line p-6 shadow-sm space-y-4">
                    <h2 class="text-sm font-bold text-ink uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                        </svg>
                        Lampiran Berkas / Bukti ({{ $complaint->attachments->count() }})
                    </h2>

                    @if ($complaint->attachments->isEmpty())
                        <div class="p-4 rounded-xl bg-page border border-dashed border-line text-center py-6">
                            <div class="w-10 h-10 mx-auto mb-2 rounded-xl bg-primary-50 text-primary-600 flex items-center justify-center">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                </svg>
                            </div>
                            <p class="text-xs font-semibold text-ink">Tidak Ada Lampiran</p>
                            <p class="text-xs text-ink-faint mt-1">Tidak ada berkas lampiran yang diunggah untuk laporan ini.</p>
                        </div>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach ($complaint->attachments as $attachment)
                                <a
                                    href="{{ route('citizen.complaint.attachment', [$complaint, $attachment]) }}"
                                    target="_blank"
                                    class="flex items-center gap-3 p-3.5 rounded-xl border border-line hover:border-primary-600 hover:bg-primary-50/50 ui-animated group"
                                >
                                    <div class="w-9 h-9 rounded-lg bg-primary-100 text-primary-600 flex items-center justify-center shrink-0">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold text-ink truncate group-hover:text-primary-600 ui-animated">
                                            {{ $attachment->original_name }}
                                        </p>
                                        <p class="text-[11px] text-ink-faint mt-0.5">
                                            {{ $attachment->humanSize() }}
                                        </p>
                                    </div>
                                    <svg class="w-4 h-4 text-ink-faint group-hover:text-primary-600 group-hover:translate-x-0.5 ui-animated shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Official Responses from Staff --}}
                <div class="w-full bg-white rounded-2xl border border-line p-6 shadow-sm space-y-4">
                    <h2 class="text-sm font-bold text-ink uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                        </svg>
                        Tanggapan Resmi Petugas / Instansi
                    </h2>

                    @if ($complaint->publicResponses->isEmpty())
                        <div class="p-4 rounded-xl bg-page border border-line text-center py-6">
                            <div class="w-10 h-10 mx-auto mb-2 rounded-xl bg-primary-50 text-primary-600 flex items-center justify-center">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <p class="text-xs font-semibold text-ink">Belum Ada Tanggapan Resmi</p>
                            <p class="text-xs text-ink-faint mt-1 max-w-md mx-auto">
                                Laporan Anda sedang dalam tahap penelaahan oleh tim command center dan dinas terkait. Tanggapan resmi akan diperbarui secara transparan di sini.
                            </p>
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach ($complaint->publicResponses as $response)
                                <div class="relative p-4 pl-5 rounded-xl bg-primary-50 border border-primary-100 space-y-2">
                                    <span class="absolute left-0 top-3 bottom-3 w-1 rounded-full bg-primary-600" aria-hidden="true"></span>
                                    <div class="flex items-center justify-between gap-3 text-xs flex-wrap">
                                        <span class="font-bold text-primary-800 flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                            </svg>
                                            {{ $response->author?->name ?? 'Tim Lapor Pak Wali' }}
                                        </span>
                                        <span class="text-ink-faint text-[11px]">
                                            {{ $response->created_at?->translatedFormat('d M Y, H:i') ?? '—' }} WITA
                                        </span>
                                    </div>
                                    <p class="text-sm text-ink-strong leading-relaxed whitespace-pre-line">
                                        {{ $response->body }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- PHASE 3 — Bagian 2 — Rating Kepuasan Masyarakat (1–5).
                     Shown ONLY while the complaint is `resolved` (single source of
                     truth = ComplaintStatus). `closed` and every other status show
                     NO form. Once rated, the rating is IMMUTABLE — shown read-only,
                     never a second form. The form is a progressive enhancement: the
                     server re-validates ownership + status + duplicate server-side. --}}
                @php $existingRating = $complaint->rating; @endphp
                @if ($existingRating)
                    <div class="w-full bg-white rounded-2xl border border-line p-6 shadow-sm space-y-3" id="rating-section">
                        <h2 class="text-sm font-bold text-ink uppercase tracking-wider flex items-center gap-2">
                            <svg class="w-4 h-4 text-accent-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.539 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.539-1.118l1.518-4.674a1 1 0 00-.363-1.118L2.98 10.1c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                            </svg>
                            Rating Kepuasan
                        </h2>
                        <p class="text-sm text-ink" data-rating-value="{{ $existingRating->rating }}">
                            Rating Anda:
                            <span class="text-accent-500 text-base tracking-wider" aria-hidden="true">{{ str_repeat('★', $existingRating->rating) }}{{ str_repeat('☆', 5 - $existingRating->rating) }}</span>
                            <span class="sr-only">{{ $existingRating->rating }} dari 5 bintang</span>
                        </p>
                        @if ($existingRating->hasReview())
                            <div class="p-3 rounded-xl bg-page border border-line">
                                <p class="text-[11px] font-semibold text-ink-faint uppercase tracking-wider">Ulasan Anda</p>
                                <p class="text-sm text-ink-body mt-1 whitespace-pre-line">{{ $existingRating->review_text }}</p>
                            </div>
                        @endif
                        {{-- STAGE 3 — Official Super Admin reply (read-only). Shown ONLY
                             when a non-empty reply exists; the reporter never gets a
                             form to reply to Super Admin. --}}
                        @if ($existingRating->hasAdminResponse())
                            <div class="p-3 rounded-xl bg-primary-50 border border-primary-200">
                                <p class="text-[11px] font-semibold text-primary-700 uppercase tracking-wider">Balasan Resmi Super Admin</p>
                                <p class="text-sm text-ink-strong mt-1 whitespace-pre-line">{{ $existingRating->admin_response }}</p>
                            </div>
                        @endif
                        <p class="text-[11px] text-ink-faint">
                            Terima kasih atas penilaian Anda. Rating yang sudah dikirim tidak dapat diubah.
                        </p>
                    </div>
                @elseif ($complaint->canBeRated())
                    <div class="w-full bg-white rounded-2xl border border-line p-6 shadow-sm space-y-4" id="rating-section">
                        <h2 class="text-sm font-bold text-ink uppercase tracking-wider flex items-center gap-2">
                            <svg class="w-4 h-4 text-accent-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.539 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.539-1.118l1.518-4.674a1 1 0 00-.363-1.118L2.98 10.1c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                            </svg>
                            Berikan Rating Kepuasan
                        </h2>
                        <p class="text-xs text-ink-faint">
                            Laporan Anda telah selesai. Silakan berikan penilaian kepuasan Anda (1–5 bintang).
                        </p>

                        @if ($errors->has('rating'))
                            <div class="rounded-xl bg-red-50 border border-red-200 text-red-800 text-xs px-4 py-3" role="alert">
                                {{ $errors->first('rating') }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('citizen.rating.store', $complaint) }}" class="space-y-4">
                            @csrf
                            <fieldset>
                                <legend class="sr-only">Pilih rating kepuasan 1 sampai 5 bintang</legend>
                                <div class="flex flex-wrap items-center gap-2" role="radiogroup" aria-label="Rating kepuasan">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <label class="inline-flex items-center gap-1.5 cursor-pointer rounded-xl border border-line px-3 py-2 hover:border-accent-400 hover:bg-accent-50/50 ui-animated has-[:checked]:border-accent-400 has-[:checked]:bg-accent-50">
                                            <input
                                                type="radio"
                                                name="rating"
                                                value="{{ $i }}"
                                                class="sr-only"
                                                required
                                                aria-label="{{ $i }} bintang"
                                                {{ (string) old('rating') === (string) $i ? 'checked' : '' }}
                                            >
                                            <span class="text-lg text-accent-500" aria-hidden="true">{{ str_repeat('★', $i) }}</span>
                                            <span class="text-xs font-semibold text-ink-body">{{ $i }} bintang</span>
                                        </label>
                                    @endfor
                                </div>
                            </fieldset>

                            {{-- OPTIONAL written review ("ulasan tertulis"). It rides on the SAME
                                 immutable rating row. Blank is allowed. Stored as-is and rendered
                                 escaped; MODERATION is NOT implemented (prd.md §4 TODO). --}}
                            <div>
                                <label for="review-text" class="block text-xs font-semibold text-ink-soft mb-1">
                                    Ulasan Tertulis <span class="font-normal text-ink-faint">(opsional)</span>
                                </label>
                                <textarea
                                    id="review-text"
                                    name="review_text"
                                    rows="3"
                                    placeholder="Tulis ulasan Anda mengenai penanganan laporan ini (opsional)..."
                                    class="w-full px-3 py-2 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/30 focus:border-primary-600 resize-none ui-animated"
                                    maxlength="5000"
                                >{{ old('review_text') }}</textarea>
                                @error('review_text')
                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold px-5 py-2.5 rounded-xl ui-animated shadow-sm"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Kirim Rating
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            {{-- ═══ KOLOM KANAN (4) — Timeline & Bantuan Layanan ═════════════ --}}
            <div class="lg:col-span-4 space-y-6">
                {{-- Timeline Progres Laporan — truthful chronological projection of
                     the REAL recorded status events (`complaint_status_histories`),
                     ordered OLDEST → NEWEST so it reads from creation to the latest
                     event. Only the official status label + a STATIC, safe public
                     description + the timestamp are shown; the internal operator
                     `note` and the acting staff identity are NEVER rendered here. --}}
                @php $timeline = $complaint->timelineEntries(); @endphp
                <section
                    class="w-full bg-white rounded-2xl border border-line p-6 shadow-sm space-y-4"
                    aria-labelledby="timeline-heading"
                >
                    <h2 id="timeline-heading" class="text-sm font-bold text-ink uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Timeline Progres Laporan
                    </h2>

                    @if ($timeline->isEmpty())
                        <div class="p-4 rounded-xl bg-page border border-line text-center py-6">
                            <div class="w-10 h-10 mx-auto mb-2 rounded-xl bg-primary-50 text-primary-600 flex items-center justify-center">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <p class="text-xs font-semibold text-ink">Belum Ada Riwayat Progres</p>
                            <p class="text-xs text-ink-faint mt-1 max-w-md mx-auto">
                                Riwayat perkembangan laporan akan tampil di sini setelah laporan diproses lebih lanjut.
                            </p>
                        </div>
                    @else
                        <ol class="relative ml-2 my-2" aria-label="Riwayat perkembangan laporan dari yang terlama ke terbaru">
                            @foreach ($timeline as $index => $event)
                                @php $isLast = $index === $timeline->count() - 1; @endphp
                                <li class="ml-6 {{ $isLast ? '' : 'pb-6' }} relative">
                                    {{-- Connector line (drawn for every step except the last). --}}
                                    @unless ($isLast)
                                        <span class="absolute left-[-1.0rem] top-4 bottom-0 w-0.5 bg-primary-200" aria-hidden="true"></span>
                                    @endunless

                                    {{-- Marker. The current event is a filled ring; previous
                                         events are hollow dots. Status is conveyed by TEXT,
                                         not colour alone (see the badge below). --}}
                                    @if ($event['is_current'])
                                        <span class="absolute left-[-1.25rem] top-1 w-4 h-4 rounded-full bg-primary-600 ring-4 ring-primary-100" aria-hidden="true"></span>
                                    @else
                                        <span class="absolute left-[-1.25rem] top-1 w-4 h-4 rounded-full bg-white border-2 border-primary-300" aria-hidden="true"></span>
                                    @endif

                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-xs font-bold text-ink">{{ $event['label'] }}</span>

                                        @if ($event['is_current'])
                                            <span class="status-badge bg-primary-100 text-primary-700">Saat ini</span>
                                        @else
                                            <span class="status-badge bg-surface-sunken text-ink-soft">Sebelumnya</span>
                                        @endif

                                        @if ($event['is_initial'])
                                            <span class="status-badge bg-violet-100 text-violet-800">Awal</span>
                                        @endif

                                        @if ($event['is_completion'])
                                            <span class="status-badge bg-emerald-100 text-emerald-800">Penyelesaian</span>
                                        @endif
                                    </div>

                                    <time class="block text-[11px] text-ink-faint mt-1" datetime="{{ $event['occurred_at']?->toIso8601String() }}">
                                        @if ($event['occurred_at'])
                                            {{ $event['occurred_at']->translatedFormat('d M Y, H:i') }} WITA
                                        @else
                                            Waktu tidak tersedia
                                        @endif
                                    </time>

                                    @if ($event['description'])
                                        <p class="text-xs text-ink-soft mt-1.5 leading-relaxed">{{ $event['description'] }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ol>

                        <p class="text-[11px] text-ink-faint pt-1">
                            Urutan dari kejadian paling awal hingga paling baru. Keterangan bersifat umum; penjelasan resmi per laporan ditampilkan pada bagian "Tanggapan Resmi".
                        </p>
                    @endif
                </section>

                {{-- Assistance info card — dark accent surface for emphasis. --}}
                <div class="w-full bg-gradient-to-br from-ink to-ink-strong text-white rounded-2xl p-5 shadow-sm space-y-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-primary-500/20 text-primary-400 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-line">Bantuan Layanan</h3>
                    </div>
                    <p class="text-xs text-line-strong leading-relaxed">
                        Jika ada data tambahan yang perlu diklarifikasi terkait laporan ini, mohon simpan Nomor Registrasi Anda <strong class="text-white">{{ $complaint->reference_code }}</strong> untuk keperluan verifikasi.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-layouts.dashboard>
