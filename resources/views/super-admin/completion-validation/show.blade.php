@php
    $title = 'Validasi Penyelesaian ' . $complaint->reference_code . ' — Super Admin';
    $status = $complaint->status;
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';

    // Re-populate the return-reason textarea after a failed validation. `old()`
    // can hold a crafted ARRAY (e.g. `reason[]=x`), and echoing an array raises
    // "htmlspecialchars(): Argument #1 must be of type string, array given"
    // (HTTP 500) on the very page the redirect lands on. Coerce to a scalar
    // string here so malformed input degrades to an empty field, never a crash.
    $oldReason = old('reason', '');
    $oldReason = is_scalar($oldReason) ? (string) $oldReason : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Validasi Penyelesaian Laporan</x-slot:header>
    <x-slot:breadcrumb>Tinjau pengajuan penyelesaian Operator, lalu setujui atau kembalikan untuk diperbaiki</x-slot:breadcrumb>

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
                        @if ($complaint->completion_submitted_at)
                            <span>Diajukan penyelesaian: <strong class="text-ink-body">{{ $complaint->completion_submitted_at->translatedFormat('d F Y, H:i') }} WITA</strong></span>
                        @endif
                    </div>
                </div>
                <div class="shrink-0 flex items-center gap-2">
                    <div class="flex items-center gap-2 px-3.5 py-2 bg-page rounded-xl border border-line">
                        <svg class="w-4 h-4 text-ink-faint" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <span class="text-xs font-medium text-ink-soft">{{ $complaint->resolvedOperator()?->name ?? 'Belum ada Operator' }}</span>
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
                        <div class="rounded-xl bg-page border border-surface-sunken px-4 py-3">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-ink-faint">Kategori</span>
                            <p class="text-sm font-semibold text-ink mt-1">{{ $complaint->category?->name ?? '—' }}</p>
                        </div>
                        <div class="rounded-xl bg-page border border-surface-sunken px-4 py-3">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-ink-faint">Dinas/Unit Tujuan</span>
                            <p class="text-sm font-semibold text-ink mt-1">{{ $complaint->dinasUnit?->name ?? 'Belum ditentukan' }}</p>
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

                {{-- Operator's INTERNAL completion narrative (source of the public response) --}}
                <div class="bg-white rounded-2xl border border-emerald-200 p-6 shadow-sm space-y-5">
                    <div class="flex items-center gap-2.5">
                        <span class="h-5 w-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                        <h2 class="text-sm font-semibold text-emerald-800 tracking-wide">Narasi Penyelesaian Operator</h2>
                        <span class="ml-auto inline-flex items-center gap-1.5 px-2.5 py-1 bg-accent-50 text-accent-700 text-[10px] font-semibold uppercase tracking-wider rounded-full border border-accent-200">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            Internal
                        </span>
                    </div>
                    @if ($completionNarrative)
                        <div class="rounded-xl bg-emerald-50 border border-emerald-100 p-4 space-y-2">
                            <div class="flex items-center justify-between gap-3 flex-wrap">
                                <span class="inline-flex items-center gap-2 text-xs font-semibold text-emerald-800">
                                    <span class="w-6 h-6 rounded-full bg-emerald-600 text-white text-[10px] font-bold flex items-center justify-center shrink-0">{{ strtoupper(mb_substr($completionNarrative->author?->name ?? 'O', 0, 1)) }}</span>
                                    {{ $completionNarrative->author?->name ?? 'Operator' }}
                                </span>
                                <span class="text-[11px] text-emerald-600">{{ $completionNarrative->created_at?->translatedFormat('d M Y, H:i') ?? '—' }} WITA</span>
                            </div>
                            <p class="text-sm text-ink-body leading-relaxed whitespace-pre-line">{{ $completionNarrative->body }}</p>
                        </div>
                        <p class="text-[11px] text-ink-faint">
                            Narasi ini bersifat internal. Saat penyelesaian disetujui, narasi ini akan dipublikasikan
                            sebagai tanggapan resmi yang terlihat oleh pelapor.
                        </p>
                    @else
                        <div class="rounded-xl bg-red-50 border border-red-200 p-4 space-y-1">
                            <p class="text-xs font-semibold text-red-800">Narasi penyelesaian internal tidak tersedia.</p>
                            <p class="text-xs text-red-700 leading-relaxed">
                                Operator mengajukan penyelesaian tanpa narasi. Penyelesaian TIDAK dapat disetujui tanpa
                                tanggapan publik. Kembalikan pengajuan ini kepada Operator untuk melengkapi narasi penyelesaian.
                            </p>
                        </div>
                    @endif
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

                @if (! $actionable)
                    {{--
                        N6D-2: a stale, NON-ACTIONABLE row (status != in_progress)
                        — e.g. a frozen G7 direct resolve leaving
                        `resolved + pending_completion_validation`. Kept reachable
                        so the M5 deep-link never 404s, but read-only: no Approve /
                        Return action is offered, matching the server-side guards.
                    --}}
                    <div class="bg-white rounded-2xl border border-accent-200 border-l-4 border-l-accent-500 p-5 sm:p-6 shadow-sm space-y-4">
                        <div class="flex items-center gap-2.5">
                            <span class="h-5 w-1.5 rounded-full bg-accent-500" aria-hidden="true"></span>
                            <h2 class="text-sm font-semibold text-accent-800 tracking-wide">Tidak Lagi Dapat Divalidasi</h2>
                        </div>
                        <p class="text-xs text-accent-800 leading-relaxed">
                            Laporan ini <strong>tidak lagi</strong> berada dalam antrean validasi penyelesaian karena
                            statusnya bukan <strong class="text-ink-body">Sedang Diproses</strong> (mis. sudah
                            diselesaikan langsung oleh Super Admin melalui Perbarui Status). Persetujuan dan
                            pengembalian penyelesaian <strong>dinonaktifkan</strong> untuk baris ini. Tidak ada aksi yang tersedia.
                        </p>
                        <p class="text-[11px] text-ink-faint">
                            Halaman ini ditampilkan hanya sebagai rujukan; laporan dapat dipantau pada menu Semua Laporan.
                        </p>
                    </div>
                @else

                {{-- APPROVE --}}
                <div class="bg-white rounded-2xl border border-emerald-200 border-l-4 border-l-emerald-500 p-5 sm:p-6 shadow-sm space-y-4">
                    <div class="flex items-center gap-2.5">
                        <span class="h-5 w-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                        <h2 class="text-sm font-semibold text-emerald-800 tracking-wide">Setujui Penyelesaian</h2>
                    </div>
                    <p class="text-xs text-ink-faint leading-relaxed">
                        Menyetujui akan mengubah status laporan menjadi <strong class="text-ink-body">Selesai</strong>
                        dan mempublikasikan narasi penyelesaian sebagai tanggapan resmi kepada pelapor.
                    </p>

                    @if ($canApprove)
                        <form method="POST" action="{{ route('super-admin.completion-validation.approve', $complaint) }}"
                              x-data
                              @submit="if (! confirm('Setujui penyelesaian laporan ini? Status akan menjadi Selesai dan tanggapan resmi dipublikasikan ke pelapor.')) $event.preventDefault();">
                            @csrf
                            <button type="submit"
                                class="w-full inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl ui-animated shadow-sm">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Setujui &amp; Selesaikan
                            </button>
                        </form>
                    @else
                        <div class="p-3.5 rounded-xl bg-accent-50 border border-accent-200">
                            <p class="text-xs text-accent-800 leading-relaxed">
                                Persetujuan dinonaktifkan karena narasi penyelesaian internal tidak tersedia.
                                Kembalikan pengajuan kepada Operator untuk melengkapinya.
                            </p>
                        </div>
                    @endif
                </div>

                {{-- RETURN --}}
                <div class="bg-white rounded-2xl border border-orange-200 border-l-4 border-l-orange-500 p-5 sm:p-6 shadow-sm space-y-4">
                    <div class="flex items-center gap-2.5">
                        <span class="h-5 w-1.5 rounded-full bg-orange-500" aria-hidden="true"></span>
                        <h2 class="text-sm font-semibold text-orange-800 tracking-wide">Kembalikan ke Operator</h2>
                    </div>
                    <form method="POST" action="{{ route('super-admin.completion-validation.return', $complaint) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label for="reason" class="block text-xs font-semibold text-ink-soft mb-1.5">Alasan / Hal yang Harus Diperbaiki <span class="text-red-500">*</span></label>
                            <textarea id="reason" name="reason" rows="4" required minlength="10" maxlength="2000"
                                placeholder="Contoh: Narasi penyelesaian belum menjelaskan tindakan perbaikan. Mohon lengkapi bukti dan deskripsi pekerjaan."
                                class="w-full px-3.5 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated">{{ $oldReason }}</textarea>
                            <p class="text-[11px] text-ink-faint mt-1.5">Alasan ini bersifat internal dan hanya ditampilkan kepada Operator.</p>
                        </div>
                        <button type="submit"
                            class="w-full inline-flex items-center justify-center gap-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl ui-animated shadow-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                            Kembalikan ke Operator
                        </button>
                    </form>
                </div>

                @endif

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
