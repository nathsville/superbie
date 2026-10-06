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

    <div class="max-w-3xl space-y-5">
        <div class="bg-white rounded-2xl border border-[#E2E8F0] p-6 shadow-sm card-enter">
            <div class="flex items-center justify-between mb-4">
                <span class="font-mono text-sm bg-slate-100 text-slate-700 px-3 py-1 rounded-lg">{{ $log->action }}</span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                    Append-only • Tidak dapat diubah
                </span>
            </div>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div>
                    <dt class="text-xs font-semibold text-[#94A3B8] uppercase tracking-wide mb-1">Waktu</dt>
                    <dd class="text-[#0F172A]">{{ $log->created_at?->translatedFormat('d M Y H:i:s') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-[#94A3B8] uppercase tracking-wide mb-1">Aktor</dt>
                    <dd class="text-[#0F172A]">
                        {{ $log->actor?->name ?? 'Sistem' }}
                        @if ($log->actor)
                            <span class="block text-xs text-[#64748B]">{{ $log->actor->email }}</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-[#94A3B8] uppercase tracking-wide mb-1">Subject</dt>
                    <dd class="text-[#0F172A]">
                        @if ($log->subject_type)
                            {{ $log->subject_type }}@if ($log->subject_id) <span class="text-[#64748B]">#{{ $log->subject_id }}</span>@endif
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-[#94A3B8] uppercase tracking-wide mb-1">IP Address</dt>
                    <dd class="text-[#0F172A] font-mono text-xs">{{ $log->ip_address ?? '—' }}</dd>
                </div>
            </dl>

            @if ($log->user_agent)
                <div class="mt-4">
                    <dt class="text-xs font-semibold text-[#94A3B8] uppercase tracking-wide mb-1">User Agent</dt>
                    <dd class="text-[#475569] text-xs break-all">{{ $log->user_agent }}</dd>
                </div>
            @endif
        </div>

        {{-- Metadata (redacted at presentation layer) --}}
        <div class="bg-white rounded-2xl border border-[#E2E8F0] p-6 shadow-sm card-enter">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-base font-semibold text-[#0F172A]">Metadata</h3>
                @if ($isRedacted)
                    <span class="text-[11px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full">
                        Beberapa nilai disembunyikan
                    </span>
                @endif
            </div>

            @if (empty($safeMetadata))
                <p class="text-sm text-[#94A3B8] italic">Tidak ada metadata.</p>
            @else
                <pre class="text-xs bg-[#F8FAFC] border border-[#E2E8F0] rounded-xl p-4 overflow-x-auto text-[#0F172A]">{{ json_encode($safeMetadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
            @endif
        </div>

        <a href="{{ route('super-admin.audit.index') }}" class="inline-flex items-center gap-2 text-sm text-[#64748B] hover:underline">← Kembali ke daftar audit</a>
    </div>
</x-layouts.dashboard>
