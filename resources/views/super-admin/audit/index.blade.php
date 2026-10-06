@php
    $title = 'Audit & Keamanan — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
    $redact = fn ($meta) => app(\App\Services\AuditLogService::class)->redactMetadata($meta);
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Audit & Keamanan</x-slot:header>
    <x-slot:breadcrumb>Jejak audit aktivitas administratif (read-only)</x-slot:breadcrumb>

    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-[#64748B]">
            Total: <strong>{{ $totalLogs }}</strong> catatan audit
        </p>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            Append-only • Tidak dapat diubah
        </span>
    </div>

    {{-- Search / filter --}}
    <div class="bg-white rounded-2xl border border-[#E2E8F0] p-4 mb-6 card-enter">
        <form method="GET" action="{{ route('super-admin.audit.index') }}" class="grid grid-cols-1 sm:grid-cols-6 gap-3" role="search">
            <div class="sm:col-span-2">
                <label for="search" class="block text-xs font-semibold text-[#475569] mb-1">Cari</label>
                <input id="search" name="search" type="text" value="{{ $filters['search'] ?? '' }}"
                    placeholder="Action, subject, actor, atau subject ID"
                    class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB]">
            </div>
            <div>
                <label for="action" class="block text-xs font-semibold text-[#475569] mb-1">Action</label>
                <select id="action" name="action"
                    class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB]">
                    <option value="">Semua</option>
                    @foreach ($actionOptions as $action)
                        <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="subject" class="block text-xs font-semibold text-[#475569] mb-1">Subject</label>
                <select id="subject" name="subject"
                    class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB]">
                    <option value="">Semua</option>
                    @foreach ($subjectOptions as $subject)
                        <option value="{{ $subject }}" @selected(($filters['subject'] ?? '') === $subject)>{{ $subject }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="actor" class="block text-xs font-semibold text-[#475569] mb-1">Aktor</label>
                <select id="actor" name="actor"
                    class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB]">
                    <option value="">Semua</option>
                    @foreach ($actorOptions as $actor)
                        <option value="{{ $actor->id }}" @selected((string) ($filters['actor'] ?? '') === (string) $actor->id)>{{ $actor->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="date_from" class="block text-xs font-semibold text-[#475569] mb-1">Dari</label>
                <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}"
                    class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB]">
            </div>
            <div>
                <label for="date_to" class="block text-xs font-semibold text-[#475569] mb-1">Sampai</label>
                <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}"
                    class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2563EB]/30 focus:border-[#2563EB]">
            </div>
            <div class="sm:col-span-6 flex items-center gap-2">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-700 hover:bg-slate-800 text-white text-sm font-semibold rounded-xl ui-animated">
                    Filter
                </button>
                <a href="{{ route('super-admin.audit.index') }}" class="text-sm text-[#64748B] hover:underline">Reset</a>
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-2xl border border-[#E2E8F0] shadow-sm overflow-hidden card-enter">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#F8FAFC] text-[#475569] text-xs uppercase tracking-wider">
                    <tr>
                        <th class="text-left px-4 py-3">Waktu</th>
                        <th class="text-left px-4 py-3">Aktor</th>
                        <th class="text-left px-4 py-3">Action</th>
                        <th class="text-left px-4 py-3">Subject</th>
                        <th class="text-left px-4 py-3">Ringkasan</th>
                        <th class="text-right px-4 py-3">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E2E8F0]">
                    @forelse ($logs as $log)
                        @php $safe = $redact($log->metadata); @endphp
                        <tr class="hover:bg-[#F8FAFC]">
                            <td class="px-4 py-3 text-[#64748B] whitespace-nowrap">
                                {{ $log->created_at?->translatedFormat('d M Y H:i') ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-[#0F172A]">
                                {{ $log->actor?->name ?? 'Sistem' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-mono text-xs bg-slate-100 text-slate-700 px-2 py-0.5 rounded">{{ $log->action }}</span>
                            </td>
                            <td class="px-4 py-3 text-[#475569]">
                                @if ($log->subject_type)
                                    {{ $log->subject_type }}@if ($log->subject_id) <span class="text-[#94A3B8]">#{{ $log->subject_id }}</span>@endif
                                @else
                                    <span class="text-[#94A3B8]">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-[#475569] text-xs">
                                @if (empty($safe))
                                    <span class="text-[#94A3B8]">—</span>
                                @else
                                    @foreach ($safe as $k => $v)
                                        <span class="inline-block mr-2 whitespace-nowrap">
                                            <span class="text-[#94A3B8]">{{ $k }}:</span>
                                            {{ is_scalar($v) ? \Illuminate\Support\Str::limit((string) $v, 40) : json_encode($v) }}
                                        </span>
                                    @endforeach
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('super-admin.audit.show', $log) }}" class="text-[#2563EB] hover:underline font-semibold">Lihat</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-[#94A3B8] italic">Tidak ada catatan audit yang ditemukan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</x-layouts.dashboard>
