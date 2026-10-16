@php
    $title = 'Audit & Keamanan — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
    $redact = fn ($meta) => app(\App\Services\AuditLogService::class)->redactMetadata($meta);
    $hasFilters = !empty(array_filter([
        $filters['search'] ?? null,
        $filters['action'] ?? null,
        $filters['subject'] ?? null,
        $filters['actor'] ?? null,
        $filters['date_from'] ?? null,
        $filters['date_to'] ?? null,
    ]));
    $inputBase = 'w-full px-3.5 py-2.5 text-sm border border-line rounded-xl bg-white transition focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Audit & Keamanan</x-slot:header>
    <x-slot:breadcrumb>Jejak audit aktivitas administratif (read-only)</x-slot:breadcrumb>

    <div class="w-full max-w-full space-y-6 card-enter">
        {{-- ── Toolbar & filter ─────────────────────────────────────────────── --}}
        <div class="w-full bg-white rounded-2xl border border-line shadow-sm p-4 sm:p-5">
            <div class="flex items-start justify-between flex-wrap gap-3 mb-4">
                <div>
                    <h2 class="text-sm font-semibold text-ink tracking-wide">Filter Log Audit</h2>
                    <p class="text-sm text-ink-faint mt-1">
                        Total: <strong>{{ $totalLogs }}</strong> catatan audit
                        @if ($hasFilters)
                            <span>• hasil terfilter</span>
                        @endif
                    </p>
                </div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[11px] font-semibold bg-primary-50 text-primary-700 border border-primary-100">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    Append-only • Tidak dapat diubah
                </span>
            </div>

            <h2 class="sr-only">Pencarian dan filter log audit</h2>
            <form method="GET" action="{{ route('super-admin.audit.index') }}" class="space-y-4"
                  role="search" aria-label="Pencarian dan filter log audit">
                {{-- Baris 1 --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div>
                        <label for="search" class="block text-xs font-semibold text-ink-soft mb-1.5">Cari</label>
                        <input id="search" name="search" type="text" value="{{ $filters['search'] ?? '' }}"
                            placeholder="Action, subject, actor, dll"
                            class="{{ $inputBase }}">
                    </div>
                    <div>
                        <label for="action" class="block text-xs font-semibold text-ink-soft mb-1.5">Action</label>
                        <select id="action" name="action" class="{{ $inputBase }}">
                            <option value="">Semua</option>
                            @foreach ($actionOptions as $action)
                                <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="subject" class="block text-xs font-semibold text-ink-soft mb-1.5">Subject</label>
                        <select id="subject" name="subject" class="{{ $inputBase }}">
                            <option value="">Semua</option>
                            @foreach ($subjectOptions as $subject)
                                <option value="{{ $subject }}" @selected(($filters['subject'] ?? '') === $subject)>{{ $subject }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="actor" class="block text-xs font-semibold text-ink-soft mb-1.5">Aktor</label>
                        <select id="actor" name="actor" class="{{ $inputBase }}">
                            <option value="">Semua</option>
                            @foreach ($actorOptions as $actor)
                                <option value="{{ $actor->id }}" @selected((string) ($filters['actor'] ?? '') === (string) $actor->id)>{{ $actor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Baris 2 --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end pt-4 border-t border-surface-sunken">
                    <div>
                        <label for="date_from" class="block text-xs font-semibold text-ink-soft mb-1.5">Dari</label>
                        <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}"
                            class="{{ $inputBase }}">
                    </div>
                    <div>
                        <label for="date_to" class="block text-xs font-semibold text-ink-soft mb-1.5">Sampai</label>
                        <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}"
                            class="{{ $inputBase }}">
                    </div>
                    <div class="lg:col-span-2 flex items-center justify-end gap-2">
                        <a href="{{ route('super-admin.audit.index') }}"
                           class="inline-flex items-center gap-2 px-4 py-2.5 bg-white hover:bg-surface-sunken text-ink-soft text-sm font-semibold rounded-xl border border-line ui-animated">
                            Reset
                        </a>
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                            Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- ── Table ────────────────────────────────────────────────────────── --}}
        <h2 class="sr-only">Daftar catatan audit</h2>
        <div class="w-full bg-white rounded-2xl border border-line shadow-sm overflow-hidden">
            @if ($logs->isEmpty())
                <div class="empty-state py-16 text-center">
                    <svg class="w-12 h-12 text-line-strong mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <p class="text-sm font-semibold text-ink-soft">Tidak ada catatan audit yang ditemukan.</p>
                    <p class="text-xs text-ink-faint mt-1">Sesuaikan kata kunci atau rentang tanggal pada filter.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <caption class="sr-only">Daftar catatan audit beserta waktu, aktor, action, subject, dan ringkasan metadata</caption>
                        <thead class="bg-page text-ink-soft text-xs uppercase tracking-wider">
                            <tr>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Waktu</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold min-w-[10rem]">Aktor</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold whitespace-nowrap">Action</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold min-w-[11rem]">Subject</th>
                                <th scope="col" class="text-left px-4 py-3.5 font-semibold min-w-[18rem]">Ringkasan</th>
                                <th scope="col" class="text-right px-4 py-3.5 font-semibold whitespace-nowrap">Detail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-sunken">
                            @foreach ($logs as $log)
                                @php $safe = $redact($log->metadata); @endphp
                                <tr class="hover:bg-page transition-colors">
                                    <td class="px-4 py-4 text-ink-faint whitespace-nowrap">
                                        {{ $log->created_at?->translatedFormat('d M Y H:i') ?? '—' }}
                                    </td>
                                    <td class="px-4 py-4 text-ink">
                                        <span class="font-medium">{{ $log->actor?->name ?? 'Sistem' }}</span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center rounded-md font-mono text-[11px] font-semibold bg-violet-50 text-violet-700 border border-violet-100 px-2 py-0.5">{{ $log->action }}</span>
                                    </td>
                                    <td class="px-4 py-4">
                                        @if ($log->subject_type)
                                            <span class="inline-flex items-center gap-1 rounded-md font-mono text-[11px] text-ink-soft bg-page border border-line px-2 py-0.5">
                                                {{ $log->subject_type }}
                                                @if ($log->subject_id)
                                                    <span class="text-ink-faint">#{{ $log->subject_id }}</span>
                                                @endif
                                            </span>
                                        @else
                                            <span class="text-xs text-ink-faint">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4">
                                        @if (empty($safe))
                                            <span class="text-xs text-ink-faint">—</span>
                                        @else
                                            <div class="flex flex-wrap gap-1">
                                                @foreach ($safe as $k => $v)
                                                    <span class="inline-flex items-center gap-1 rounded-md bg-page border border-line px-1.5 py-0.5 font-mono text-[11px] leading-relaxed">
                                                        <span class="text-ink-faint">{{ $k }}:</span>
                                                        <span class="text-ink-body break-all">{{ is_scalar($v) ? \Illuminate\Support\Str::limit((string) $v, 40) : json_encode($v) }}</span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-right whitespace-nowrap">
                                        <a href="{{ route('super-admin.audit.show', $log) }}"
                                           aria-label="Lihat detail catatan audit {{ $log->action }}" title="Lihat"
                                           class="inline-flex items-center gap-1 rounded-lg bg-primary-50 px-3 py-1.5 text-xs font-semibold text-primary-600 hover:bg-primary-100 ui-animated">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            Lihat
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($logs->hasPages())
                    <div class="px-5 sm:px-6 py-4 border-t border-line bg-surface">
                        {{ $logs->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-layouts.dashboard>
