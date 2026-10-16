@php
    $title = 'Detail Laporan ' . $complaint->reference_code . ' — Super Admin';
    $status = $complaint->status;
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';

    // Re-populate the reply textarea after a failed validation. `old()` can hold
    // a crafted ARRAY (e.g. `admin_response[]=x`), and echoing an array raises
    // "htmlspecialchars(): Argument #1 must be of type string, array given"
    // (HTTP 500) on the page the redirect lands on. Coerce to a scalar string so
    // malformed input degrades to an empty field, never a crash.
    $oldAdminResponse = old('admin_response', '');
    $oldAdminResponse = is_scalar($oldAdminResponse) ? (string) $oldAdminResponse : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Detail Laporan</x-slot:header>
    <x-slot:breadcrumb>Semua Laporan — inspeksi read-only</x-slot:breadcrumb>

    <div class="w-full max-w-full space-y-6 card-enter">

        {{-- Read-only banner --}}
        <div class="flex items-center gap-2.5 bg-page border border-line text-ink-soft text-xs px-4 py-3 rounded-xl">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            Halaman ini hanya untuk pemantauan. Super Admin tidak dapat mengubah status, kategori, tujuan, penugasan, catatan, atau lampiran laporan dari sini.
        </div>

        @if (session('success'))
            <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->has('admin_response'))
            <div class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ $errors->first('admin_response') }}
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
                    </div>
                    <h2 class="text-xl font-bold text-ink mt-3">{{ $complaint->title }}</h2>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ink-faint mt-2">
                        <span>Dikirim: {{ $complaint->submitted_at?->translatedFormat('d F Y, H:i') ?? '—' }} WITA</span>
                        @if ($complaint->category)
                            <span>Kategori: <strong class="text-ink-body">{{ $complaint->category->name }}</strong></span>
                        @endif
                        <span>Tujuan: <strong class="text-ink-body">{{ $complaint->dinasUnit?->name ?? 'Belum ditentukan' }}</strong></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Bento grid — 8:4 split (main content left, side info right) --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            {{-- ── Left column (main content, 8/12) ─────────────────────────── --}}
            <div class="lg:col-span-8 space-y-6">

                {{-- Complaint Detail --}}
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
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-ink-faint">Lokasi</span>
                            <p class="text-sm text-ink mt-1">{{ $complaint->location_text ?? '—' }}</p>
                        </div>
                        <div class="rounded-xl bg-page border border-surface-sunken px-4 py-3">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-ink-faint">Dibuat</span>
                            <p class="text-sm text-ink mt-1">{{ $complaint->created_at?->translatedFormat('d M Y, H:i') ?? '—' }} WITA</p>
                        </div>
                        <div class="rounded-xl bg-page border border-surface-sunken px-4 py-3">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-ink-faint">Diperbarui</span>
                            <p class="text-sm text-ink mt-1">{{ $complaint->updated_at?->translatedFormat('d M Y, H:i') ?? '—' }} WITA</p>
                        </div>
                    </div>

                    <div class="text-sm text-ink-body leading-relaxed whitespace-pre-line border-t border-surface-sunken pt-4">
                        {{ $complaint->description }}
                    </div>
                </div>

                {{-- Attachments (reuses the existing authorized download route) --}}
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

                {{-- Internal Notes (read-only) --}}
                <div class="bg-white rounded-2xl border border-line p-6 shadow-sm space-y-5">
                    <div class="flex items-center gap-2.5">
                        <span class="h-5 w-1.5 rounded-full bg-accent-400" aria-hidden="true"></span>
                        <h2 class="text-sm font-semibold text-ink tracking-wide">Catatan Internal</h2>
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
                </div>

                {{-- Public Responses (read-only) --}}
                <div class="bg-white rounded-2xl border border-line p-6 shadow-sm space-y-5">
                    <div class="flex items-center gap-2.5">
                        <span class="h-5 w-1.5 rounded-full bg-primary-600" aria-hidden="true"></span>
                        <h2 class="text-sm font-semibold text-ink tracking-wide">Respons Publik</h2>
                    </div>
                    @if ($complaint->publicResponses->isEmpty())
                        <p class="text-xs text-ink-faint italic">Belum ada respons publik.</p>
                    @else
                        <div class="space-y-3">
                            @foreach ($complaint->publicResponses as $response)
                                <div class="p-4 rounded-xl bg-primary-50/50 border border-primary-100 space-y-1.5">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-semibold text-primary-800">{{ $response->author?->name ?? 'Tim Lapor Pak Wali' }}</span>
                                        <span class="text-ink-faint">{{ $response->created_at?->translatedFormat('d M Y, H:i') ?? '—' }} WITA</span>
                                    </div>
                                    <p class="text-xs text-ink-strong leading-relaxed whitespace-pre-line">{{ $response->body }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Rating + optional written review (read-only) --}}
                <div class="bg-white rounded-2xl border border-line p-6 shadow-sm space-y-5">
                    <div class="flex items-center gap-2.5">
                        <span class="h-5 w-1.5 rounded-full bg-accent-400" aria-hidden="true"></span>
                        <h2 class="text-sm font-semibold text-ink tracking-wide">Rating Kepuasan Pelapor</h2>
                    </div>
                    @if (! $complaint->rating)
                        <p class="text-xs text-ink-faint italic">Pelapor belum memberikan rating.</p>
                    @else
                        <p class="text-sm text-ink">
                            <span class="text-accent-500 text-base tracking-wider" aria-hidden="true">{{ str_repeat('★', $complaint->rating->rating) }}{{ str_repeat('☆', 5 - $complaint->rating->rating) }}</span>
                            <span class="ml-1 text-xs text-ink-faint">{{ $complaint->rating->rating }} dari 5 bintang</span>
                        </p>
                        @if ($complaint->rating->hasReview())
                            <div class="p-4 rounded-xl bg-page border border-surface-sunken">
                                <p class="text-[11px] font-semibold text-ink-faint uppercase tracking-wider">Ulasan Pelapor</p>
                                <p class="text-sm text-ink-body mt-1 whitespace-pre-line">{{ $complaint->rating->review_text }}</p>
                            </div>

                            {{-- STAGE 3 — Official reply (create-once, immutable). --}}
                            @if ($complaint->rating->hasAdminResponse())
                                <div class="p-4 rounded-xl bg-primary-50 border border-primary-200">
                                    <p class="text-[11px] font-semibold text-primary-700 uppercase tracking-wider">Balasan Resmi Super Admin</p>
                                    <p class="text-sm text-ink-strong mt-1 whitespace-pre-line">{{ $complaint->rating->admin_response }}</p>
                                </div>
                                <p class="text-[11px] text-ink-faint">Balasan resmi bersifat permanen dan tidak dapat diubah.</p>
                            @else
                                <form method="POST" action="{{ route('super-admin.review-response.store', $complaint) }}" class="space-y-3">
                                    @csrf
                                    <div>
                                        <label for="admin_response" class="block text-[11px] font-semibold text-ink-faint uppercase tracking-wider mb-1">Balasan Resmi Super Admin</label>
                                        <textarea id="admin_response" name="admin_response" rows="4" required maxlength="5000"
                                            placeholder="Tulis balasan resmi atas ulasan masyarakat..."
                                            class="w-full px-3 py-2 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/30 focus:border-primary-600 resize-none">{{ $oldAdminResponse }}</textarea>
                                    </div>
                                    <button type="submit"
                                        class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold px-5 py-2.5 rounded-xl ui-animated shadow-sm">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                        Kirim Balasan Resmi
                                    </button>
                                </form>
                            @endif
                        @else
                            <p class="text-xs text-ink-faint italic">Pelapor tidak menyertakan ulasan tertulis. Tidak ada yang dapat dibalas.</p>
                        @endif
                    @endif
                </div>

            </div>{{-- end left column --}}

            {{-- ── Right column (side info, 4/12) ───────────────────────────── --}}
            <div class="lg:col-span-4 space-y-5">

                {{-- Historical routing + assignment (read-only) --}}
                <div class="bg-white rounded-2xl border border-line border-l-4 border-l-primary-600 p-5 shadow-sm space-y-4">
                    <div class="flex items-center gap-2.5">
                        <span class="h-5 w-1.5 rounded-full bg-primary-600" aria-hidden="true"></span>
                        <h2 class="text-sm font-semibold text-ink tracking-wide">Routing &amp; Penugasan</h2>
                    </div>
                    <dl class="space-y-3 text-xs">
                        <div>
                            <dt class="font-semibold text-ink-faint">Kategori</dt>
                            <dd class="text-ink mt-0.5">{{ $complaint->category?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-ink-faint">Dinas/Unit Tujuan (historis)</dt>
                            <dd class="text-ink mt-0.5">
                                {{ $complaint->dinasUnit?->name ?? 'Belum ditentukan' }}
                                @if ($complaint->dinasUnit && ! $complaint->dinasUnit->is_active)
                                    <span class="ml-1 inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold bg-surface-sunken text-ink-faint border border-line">nonaktif</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-ink-faint">Operator (dari Dinas/Unit)</dt>
                            <dd class="text-ink mt-0.5">{{ $complaint->dinasUnit?->operator?->name ?? 'Belum ada Operator' }}</dd>
                        </div>
                        @if ($complaint->assignee)
                            {{-- PHASE 6C — Decision 2: `assigned_to` is DEPRECATED.
                                 A historical assignment is shown ONLY as historical
                                 information (never as an active assignment control).
                                 New complaints never set it, so this block is normally
                                 absent. --}}
                            <div>
                                <dt class="font-semibold text-ink-faint">Penugasan Historis <span class="text-[10px] font-normal text-ink-faint">(legacy)</span></dt>
                                <dd class="text-ink-soft mt-0.5">{{ $complaint->assignee->name }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                {{-- Status History timeline (read-only) --}}
                <div class="bg-white rounded-2xl border border-line border-l-4 border-l-violet-400 p-5 shadow-sm space-y-4">
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

                {{-- Audit context (read-only, redacted) --}}
                <div class="bg-white rounded-2xl border border-line border-l-4 border-l-line-strong p-5 shadow-sm space-y-4">
                    <div class="flex items-center gap-2.5">
                        <span class="h-5 w-1.5 rounded-full bg-line-strong" aria-hidden="true"></span>
                        <h2 class="text-sm font-semibold text-ink tracking-wide">Jejak Audit Terkait</h2>
                    </div>
                    @if ($safeAuditLogs->isEmpty())
                        <p class="text-xs text-ink-faint italic">Belum ada catatan audit untuk laporan ini.</p>
                    @else
                        <div class="space-y-3">
                            @foreach ($safeAuditLogs as $entry)
                                <div class="p-3 rounded-xl bg-page border border-line space-y-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-mono text-[11px] bg-surface-sunken text-ink-body px-2 py-0.5 rounded">{{ $entry['log']->action }}</span>
                                        <span class="text-[11px] text-ink-faint">{{ $entry['log']->created_at?->translatedFormat('d M Y, H:i') ?? '—' }}</span>
                                    </div>
                                    <p class="text-[11px] text-ink-faint">Oleh: {{ $entry['log']->actor?->name ?? 'Sistem' }}</p>
                                    @if (!empty($entry['metadata']))
                                        <p class="text-[11px] text-ink-soft break-words">
                                            @foreach ($entry['metadata'] as $k => $v)
                                                <span class="inline-block mr-2"><span class="text-ink-faint">{{ $k }}:</span> {{ is_scalar($v) ? \Illuminate\Support\Str::limit((string) $v, 60) : json_encode($v) }}</span>
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
