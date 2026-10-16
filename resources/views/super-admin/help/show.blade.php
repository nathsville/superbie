@php
    $title = 'Tiket Bantuan ' . $ticket->reference_code . ' — Super Admin';
    $status = $ticket->status;
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
    $allowedTransitions = $status->allowedTransitions();
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Detail Tiket Bantuan</x-slot:header>
    <x-slot:breadcrumb>Penanganan tiket bantuan (Super Admin)</x-slot:breadcrumb>

    <div class="w-full max-w-full space-y-6 card-enter">
        @if (session('success'))
            <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-xl" role="alert">
                <ul class="list-disc list-inside space-y-0.5 text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ── Header tiket ─────────────────────────────────────────────────── --}}
        <div class="w-full bg-white rounded-2xl border border-line p-5 sm:p-6 shadow-sm">
            <div class="flex items-start gap-4 flex-wrap">
                <div class="w-12 h-12 rounded-2xl bg-primary-50 text-primary-600 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-3 flex-wrap">
                        <span class="font-mono text-sm font-bold text-ink bg-surface-sunken px-3 py-1 rounded-lg border border-line">{{ $ticket->reference_code }}</span>
                        <span class="status-badge {{ $status->tailwindBadge() }}">
                            <span class="w-2 h-2 rounded-full bg-current" aria-hidden="true"></span>
                            {{ $status->label() }}
                        </span>
                    </div>
                    <h1 class="text-xl font-bold text-ink mt-3">{{ $ticket->subject }}</h1>
                </div>
            </div>

            <dl class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-5">
                <div class="rounded-xl bg-page border border-surface-sunken px-4 py-3">
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-ink-faint">Pemilik</dt>
                    <dd class="text-sm font-semibold text-ink mt-1">{{ $ticket->user?->name ?? '—' }}</dd>
                </div>
                <div class="rounded-xl bg-page border border-surface-sunken px-4 py-3">
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-ink-faint">Kategori</dt>
                    <dd class="text-sm font-semibold text-ink mt-1">{{ $ticket->category->label() }}</dd>
                </div>
                <div class="rounded-xl bg-page border border-surface-sunken px-4 py-3">
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-ink-faint">Dibuat</dt>
                    <dd class="text-sm text-ink mt-1">{{ $ticket->created_at?->translatedFormat('d F Y, H:i') ?? '—' }} WITA</dd>
                </div>
            </dl>
        </div>

        {{-- ── Bento grid — 8:4 split (conversation left, actions right) ─────── --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            {{-- ── Left column (conversation + reply, 8/12) ──────────────────── --}}
            <div class="lg:col-span-8 space-y-6">

                {{-- Conversation --}}
                <div class="bg-white rounded-2xl border border-line p-6 shadow-sm space-y-5">
                    <div class="flex items-center gap-2.5">
                        <span class="h-5 w-1.5 rounded-full bg-primary-600" aria-hidden="true"></span>
                        <h2 class="text-sm font-semibold text-ink tracking-wide">Riwayat Percakapan</h2>
                        <span class="ml-auto text-[11px] font-semibold text-ink-faint bg-page border border-line px-2.5 py-0.5 rounded-full">{{ $ticket->messages->count() }} pesan</span>
                    </div>
                    <div class="space-y-5">
                        @forelse ($ticket->messages as $message)
                            @php $fromStaff = $message->isFromStaff(); @endphp
                            <div class="flex gap-3 {{ $fromStaff ? 'flex-row-reverse' : '' }}">
                                <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 {{ $fromStaff ? 'bg-primary-100 text-primary-600' : 'bg-surface-sunken text-ink-soft' }}">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                </div>
                                <div class="flex-1 min-w-0 max-w-2xl">
                                    <div class="flex items-center gap-2 flex-wrap {{ $fromStaff ? 'justify-end' : '' }}">
                                        <span class="text-xs font-bold {{ $fromStaff ? 'text-primary-800' : 'text-ink-body' }}">
                                            {{ $fromStaff ? 'Super Admin' : ($message->user?->name ?? 'Masyarakat') }}
                                        </span>
                                        <span class="text-[11px] text-ink-faint">{{ $message->created_at?->translatedFormat('d M Y, H:i') ?? '—' }} WITA</span>
                                    </div>
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

                {{-- Official reply --}}
                @if ($ticket->allowsStaffReply())
                    <div class="bg-white rounded-2xl border border-line border-l-4 border-l-primary-600 p-6 shadow-sm space-y-4">
                        <div class="flex items-center gap-2.5">
                            <span class="h-5 w-1.5 rounded-full bg-primary-600" aria-hidden="true"></span>
                            <h2 class="text-sm font-semibold text-ink tracking-wide">Balasan Resmi</h2>
                        </div>
                        <form method="POST" action="{{ route('super-admin.help.reply', $ticket) }}" class="space-y-3">
                            @csrf
                            <textarea name="body" rows="4" required maxlength="5000"
                                placeholder="Tulis balasan resmi untuk masyarakat..."
                                class="w-full px-3.5 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 resize-none ui-animated">{{ old('body') }}</textarea>
                            <div class="flex items-center justify-between gap-3 flex-wrap">
                                <p class="text-[11px] text-ink-faint">Balasan akan dikirim sebagai tanggapan resmi Super Admin.</p>
                                <button type="submit" class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold px-5 py-2.5 rounded-xl ui-animated shadow-sm">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                    Kirim Balasan
                                </button>
                            </div>
                        </form>
                    </div>
                @else
                    <div class="bg-page border border-line rounded-2xl p-5 text-center">
                        <p class="text-xs font-semibold text-ink-soft">Tiket berstatus {{ $status->label() }} — tidak dapat dibalas pada status ini.</p>
                    </div>
                @endif
            </div>

            {{-- ── Right column (status + related complaint, 4/12) ───────────── --}}
            <div class="lg:col-span-4 space-y-5">

                {{-- Ubah Status --}}
                <div class="bg-white rounded-2xl border border-line border-l-4 border-l-violet-400 p-5 sm:p-6 shadow-sm space-y-4">
                    <div class="flex items-center gap-2.5">
                        <span class="h-5 w-1.5 rounded-full bg-violet-400" aria-hidden="true"></span>
                        <h2 class="text-sm font-semibold text-ink tracking-wide">Ubah Status</h2>
                    </div>
                    @if (empty($allowedTransitions))
                        <p class="text-xs text-ink-faint">Tiket sudah {{ $status->label() }} dan tidak memiliki transisi lanjutan.</p>
                    @else
                        <form method="POST" action="{{ route('super-admin.help.status', $ticket) }}" class="space-y-3">
                            @csrf
                            @method('PATCH')
                            <div>
                                <label for="status" class="block text-xs font-semibold text-ink-soft mb-1.5">Status Baru</label>
                                <select id="status" name="status" required
                                    class="w-full px-3.5 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated">
                                    <option value="">— Pilih status baru —</option>
                                    @foreach ($allowedTransitions as $next)
                                        <option value="{{ $next->value }}" @selected(old('status') === $next->value)>{{ $next->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl ui-animated shadow-sm">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Simpan Status
                            </button>
                        </form>
                    @endif
                </div>

                {{-- Laporan Terkait --}}
                <div class="bg-white rounded-2xl border border-line border-l-4 border-l-emerald-400 p-5 sm:p-6 shadow-sm space-y-4">
                    <div class="flex items-center gap-2.5">
                        <span class="h-5 w-1.5 rounded-full bg-emerald-400" aria-hidden="true"></span>
                        <h2 class="text-sm font-semibold text-ink tracking-wide">Laporan Terkait</h2>
                    </div>
                    @if ($ticket->relatedComplaint)
                        {{-- Only public-safe reference fields — NEVER internal notes. --}}
                        <a href="{{ route('super-admin.complaints.show', $ticket->relatedComplaint) }}"
                           class="block rounded-xl border border-line hover:border-primary-600 hover:bg-primary-50/50 p-4 space-y-2 ui-animated group">
                            <span class="inline-flex items-center gap-1.5 font-mono text-xs font-semibold text-primary-700 bg-primary-50 border border-primary-100 px-2.5 py-1 rounded-lg">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                {{ $ticket->relatedComplaint->reference_code }}
                            </span>
                            <p class="text-sm font-semibold text-ink line-clamp-2">{{ $ticket->relatedComplaint->title }}</p>
                            <p class="text-xs text-ink-faint">Kategori: {{ $ticket->relatedComplaint->category?->name ?? '—' }}</p>
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 group-hover:text-primary-700 ui-animated">
                                Lihat laporan
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </span>
                        </a>
                    @else
                        <p class="text-xs text-ink-faint">Tidak ada laporan terkait.</p>
                    @endif
                </div>
            </div>

        </div>{{-- end grid --}}
    </div>
</x-layouts.dashboard>
