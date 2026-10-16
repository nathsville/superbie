@php
    $title = 'Tiket Bantuan ' . $ticket->reference_code;
    $status = $ticket->status;
    $currentRoute = request()->routeIs('citizen.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('citizen.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Detail Tiket Bantuan</x-slot:header>
    <x-slot:breadcrumb>Panel Masyarakat — Percakapan dengan Super Admin</x-slot:breadcrumb>

    <div class="w-full max-w-full space-y-6 card-enter">
        @if (session('success'))
            <div class="w-full flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('success') }}
            </div>
        @endif

        {{-- ── Header tiket (full width) ─────────────────────────────────────── --}}
        <div class="w-full bg-white rounded-2xl border border-line p-6 sm:p-7 shadow-sm">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-2xl bg-primary-50 text-primary-600 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-3 flex-wrap">
                        <span class="text-xs font-semibold text-ink-faint uppercase tracking-wider">Nomor Tiket</span>
                        <span class="font-mono text-sm font-bold text-ink bg-surface-sunken px-3 py-1 rounded-lg border border-line">{{ $ticket->reference_code }}</span>
                        <span class="status-badge {{ $status->tailwindBadge() }}">
                            <span class="w-2 h-2 rounded-full bg-current" aria-hidden="true"></span>
                            {{ $status->label() }}
                        </span>
                    </div>
                    <h2 class="text-xl font-bold text-ink mt-3">{{ $ticket->subject }}</h2>
                    <p class="text-xs text-ink-faint mt-2">
                        Kategori: <strong class="text-ink-body">{{ $ticket->category->label() }}</strong>
                        • Dibuat {{ $ticket->created_at?->translatedFormat('d F Y, H:i') ?? '—' }} WITA
                        @if ($ticket->resolved_at)
                            • Selesai {{ $ticket->resolved_at->translatedFormat('d F Y, H:i') }} WITA
                        @endif
                    </p>
                </div>
            </div>

            @if ($ticket->relatedComplaint)
                <div class="mt-4 inline-flex items-center gap-2 text-xs bg-page border border-line px-3 py-2 rounded-xl">
                    <span class="font-semibold text-ink-faint">Laporan terkait:</span>
                    <a href="{{ route('citizen.complaint.show', $ticket->relatedComplaint) }}" class="font-mono font-semibold text-primary-600 hover:underline">
                        {{ $ticket->relatedComplaint->reference_code }}
                    </a>
                    <span class="text-ink-faint">— {{ \Illuminate\Support\Str::limit($ticket->relatedComplaint->title, 40) }}</span>
                </div>
            @endif
        </div>

        {{-- ── Riwayat Percakapan (full width) ───────────────────────────────── --}}
        <div class="w-full bg-white rounded-2xl border border-line p-6 sm:p-7 shadow-sm space-y-5">
            <div class="flex items-center gap-2.5">
                <span class="h-5 w-1.5 rounded-full bg-primary-600" aria-hidden="true"></span>
                <h2 class="text-sm font-bold text-ink uppercase tracking-wider">Riwayat Percakapan</h2>
                <span class="ml-auto text-[11px] font-semibold text-ink-faint bg-page border border-line px-2.5 py-0.5 rounded-full">{{ $ticket->messages->count() }} pesan</span>
            </div>

            <div class="space-y-5">
                @forelse ($ticket->messages as $message)
                    @php $fromStaff = $message->isFromStaff(); @endphp
                    <div class="flex gap-3 {{ $fromStaff ? '' : 'flex-row-reverse' }}">
                        {{-- Avatar pengirim --}}
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 {{ $fromStaff ? 'bg-primary-100 text-primary-600' : 'bg-surface-sunken text-ink-soft' }}">
                            @if ($fromStaff)
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            @else
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            @endif
                        </div>

                        <div class="flex-1 min-w-0 max-w-2xl">
                            {{-- Pengirim + stempel waktu --}}
                            <div class="flex items-center gap-2 flex-wrap {{ $fromStaff ? '' : 'justify-end' }}">
                                <span class="text-xs font-bold {{ $fromStaff ? 'text-primary-800' : 'text-ink-body' }}">
                                    {{ $fromStaff ? 'Super Admin' : 'Anda' }}
                                </span>
                                @if ($fromStaff)
                                    <span class="text-[10px] font-semibold text-primary-800 bg-primary-50 border border-primary-100 px-2 py-0.5 rounded-full">Admin</span>
                                @else
                                    <span class="text-[10px] font-semibold text-ink-soft bg-surface-sunken border border-line px-2 py-0.5 rounded-full">Masyarakat</span>
                                @endif
                                <span class="inline-flex items-center gap-1 text-[11px] text-ink-faint">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    {{ $message->created_at?->translatedFormat('d M Y, H:i') ?? '—' }} WITA
                                </span>
                            </div>

                            {{-- Bubble chat --}}
                            <div class="mt-1.5 p-3.5 rounded-2xl text-sm text-ink-strong leading-relaxed whitespace-pre-line {{ $fromStaff ? 'bg-primary-50/60 border border-primary-100 rounded-tr-sm' : 'bg-page border border-line rounded-tl-sm' }}">
                                {{ $message->body }}
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-ink-faint italic">Belum ada pesan pada tiket ini.</p>
                @endforelse
            </div>
        </div>

        {{-- ── Kirim Pesan (full width) ──────────────────────────────────────── --}}
        @if ($ticket->allowsCitizenReply())
            <div class="w-full bg-white rounded-2xl border border-line border-l-4 border-l-primary-600 p-6 sm:p-7 shadow-sm space-y-4">
                <div class="flex items-center gap-2.5">
                    <span class="h-5 w-1.5 rounded-full bg-primary-600" aria-hidden="true"></span>
                    <h2 class="text-sm font-bold text-ink uppercase tracking-wider">Kirim Pesan</h2>
                </div>

                @if ($errors->has('body'))
                    <div class="rounded-xl bg-red-50 border border-red-200 text-red-800 text-xs px-4 py-3" role="alert">{{ $errors->first('body') }}</div>
                @endif

                <form method="POST" action="{{ route('citizen.help.reply', $ticket) }}" class="space-y-4">
                    @csrf
                    <textarea name="body" rows="4" required maxlength="5000"
                        placeholder="Tulis pesan Anda..."
                        class="w-full px-4 py-3 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 resize-none ui-animated">{{ old('body') }}</textarea>

                    <div class="flex items-center justify-end gap-3">
                        <button type="submit" class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold px-5 py-2.5 rounded-xl ui-animated shadow-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                            Kirim Pesan
                        </button>
                    </div>
                </form>
            </div>
        @elseif ($ticket->allowsCitizenReopen())
            <div class="w-full bg-white rounded-2xl border border-line border-l-4 border-l-accent-400 p-6 sm:p-7 shadow-sm space-y-4">
                <div class="flex items-center gap-2.5">
                    <span class="h-5 w-1.5 rounded-full bg-accent-400" aria-hidden="true"></span>
                    <h2 class="text-sm font-bold text-ink uppercase tracking-wider">Masalah Berlanjut?</h2>
                </div>
                <p class="text-xs text-ink-faint">Tiket ini sudah selesai. Jika kendala masih berlanjut, buka kembali tiket ini dengan menjelaskan alasannya.</p>

                @if ($errors->has('body'))
                    <div class="rounded-xl bg-red-50 border border-red-200 text-red-800 text-xs px-4 py-3" role="alert">{{ $errors->first('body') }}</div>
                @endif

                <form method="POST" action="{{ route('citizen.help.reopen', $ticket) }}" class="space-y-4">
                    @csrf
                    <textarea name="body" rows="4" required maxlength="5000"
                        placeholder="Jelaskan mengapa tiket perlu dibuka kembali..."
                        class="w-full px-4 py-3 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 resize-none ui-animated">{{ old('body') }}</textarea>

                    <div class="flex items-center justify-end gap-3">
                        <button type="submit" class="inline-flex items-center gap-2 bg-accent-600 hover:bg-accent-700 text-white text-sm font-semibold px-5 py-2.5 rounded-xl ui-animated shadow-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Buka Kembali Tiket
                        </button>
                    </div>
                </form>
            </div>
        @else
            <div class="w-full bg-page border border-line rounded-2xl p-6 text-center">
                <p class="text-xs font-semibold text-ink-soft">Tiket ini sudah ditutup dan tidak dapat menerima pesan baru.</p>
                <p class="text-xs text-ink-faint mt-1">Jika Anda memerlukan bantuan lain, silakan buat tiket baru.</p>
            </div>
        @endif

        {{-- ── Bottom navigation (di luar kartu) ────────────────────────────── --}}
        <div class="w-full flex items-center">
            <a href="{{ route('citizen.help.index') }}"
               class="inline-flex items-center gap-2 bg-white hover:bg-surface-sunken text-ink-soft text-sm font-semibold px-4 py-2.5 rounded-xl border border-line-strong ui-animated">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Kembali ke Pusat Bantuan
            </a>
        </div>
    </div>
</x-layouts.dashboard>
