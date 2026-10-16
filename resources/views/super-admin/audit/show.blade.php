@php
    $title = 'Detail Audit — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Detail Audit</x-slot:header>
    <x-slot:breadcrumb>Audit & Keamanan</x-slot:breadcrumb>

    <div class="w-full max-w-full space-y-5 card-enter">
        {{-- ── Header informasi audit ───────────────────────────────────────── --}}
        <div class="w-full bg-white rounded-2xl border border-line p-5 sm:p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3 flex-wrap mb-5">
                <span class="inline-flex items-center rounded-lg font-mono text-sm font-semibold bg-violet-50 text-violet-700 border border-violet-100 px-3 py-1">{{ $log->action }}</span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full font-mono text-[11px] font-semibold bg-primary-50 text-primary-700 border border-primary-100">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    Append-only • Tidak dapat diubah
                </span>
            </div>

            {{-- Grid 2 kolom: (Waktu, Subject) | (Aktor, IP Address) --}}
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5 text-sm">
                {{-- Kolom kiri --}}
                <div class="space-y-5">
                    <div>
                        <dt class="text-xs font-semibold text-ink-faint uppercase tracking-wide mb-1">Waktu</dt>
                        <dd class="text-ink font-medium">{{ $log->created_at?->translatedFormat('d M Y H:i:s') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-ink-faint uppercase tracking-wide mb-1">Subject</dt>
                        <dd>
                            @if ($log->subject_type)
                                <span class="inline-flex items-center gap-1 rounded-md font-mono text-xs text-ink-soft bg-page border border-line px-2 py-0.5">
                                    {{ $log->subject_type }}
                                    @if ($log->subject_id)
                                        <span class="text-ink-faint">#{{ $log->subject_id }}</span>
                                    @endif
                                </span>
                            @else
                                <span class="text-xs text-ink-faint">—</span>
                            @endif
                        </dd>
                    </div>
                </div>

                {{-- Kolom kanan --}}
                <div class="space-y-5">
                    <div>
                        <dt class="text-xs font-semibold text-ink-faint uppercase tracking-wide mb-1">Aktor</dt>
                        <dd class="text-ink font-medium">
                            {{ $log->actor?->name ?? 'Sistem' }}
                            @if ($log->actor)
                                <span class="block text-xs font-normal text-ink-faint mt-0.5">{{ $log->actor->email }}</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-ink-faint uppercase tracking-wide mb-1">IP Address</dt>
                        <dd>
                            @if ($log->ip_address)
                                <span class="inline-flex items-center rounded-md font-mono text-xs text-ink-soft bg-page border border-line px-2 py-0.5">{{ $log->ip_address }}</span>
                            @else
                                <span class="text-xs text-ink-faint">—</span>
                            @endif
                        </dd>
                    </div>
                </div>
            </dl>

            {{-- Baris bawah (full width): User Agent --}}
            <div class="mt-5 pt-5 border-t border-surface-sunken">
                <dl>
                    <dt class="text-xs font-semibold text-ink-faint uppercase tracking-wide mb-1">User Agent</dt>
                    <dd class="font-mono text-xs text-ink-soft break-all leading-relaxed">{{ $log->user_agent ?? '—' }}</dd>
                </dl>
            </div>
        </div>

        {{-- ── Metadata (redacted at presentation layer) ────────────────────── --}}
        <div class="w-full bg-white rounded-2xl border border-line p-5 sm:p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
                <div class="flex items-center gap-2.5">
                    <span class="h-5 w-1.5 rounded-full bg-primary-600" aria-hidden="true"></span>
                    <h2 class="text-sm font-semibold text-ink tracking-wide">Metadata</h2>
                </div>
                @if ($isRedacted)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-accent-50 text-accent-700 border border-accent-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                        Beberapa nilai disembunyikan
                    </span>
                @endif
            </div>

            @if (empty($safeMetadata))
                <p class="text-sm text-ink-faint italic">Tidak ada metadata.</p>
            @else
                <pre class="text-xs font-mono leading-relaxed bg-ink text-surface-sunken rounded-xl p-4 overflow-x-auto"><code>{{ json_encode($safeMetadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</code></pre>
            @endif
        </div>

        {{-- ── Bottom navigation ────────────────────────────────────────────── --}}
        <div class="w-full flex items-center">
            <a href="{{ route('super-admin.audit.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-white hover:bg-surface-sunken text-ink-soft text-sm font-semibold rounded-xl border border-line ui-animated">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Kembali ke daftar audit
            </a>
        </div>
    </div>
</x-layouts.dashboard>
