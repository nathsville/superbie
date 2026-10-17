@props([
    'log',
])

@php
    // Audit activity-feed row (timeline list — NOT a chart). Shows the real
    // actor name + role, the factual action string, and relative time.
    $actorName = $log->actor?->name ?? 'Sistem';
    $roleLabel = $log->actor?->role
        ? ucwords(str_replace('_', ' ', $log->actor->role))
        : null;
    $initial = mb_strtoupper(mb_substr($actorName, 0, 1));
@endphp

<div class="px-5 sm:px-6 py-3.5 table-row-hover">
    <div class="flex items-start gap-3">
        <span class="w-7 h-7 rounded-full bg-primary-50 text-primary-600 text-xs font-bold flex items-center justify-center shrink-0 mt-0.5"
              aria-hidden="true">{{ $initial }}</span>

        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="font-semibold text-ink text-sm truncate">{{ $actorName }}</span>
                @if ($roleLabel)
                    <span class="text-xs text-ink-faint">
                        {{ $roleLabel }}
                    </span>
                @endif
            </div>

            <div class="flex items-center gap-2 mt-1 flex-wrap">
                <span class="font-mono text-xs bg-surface-sunken text-ink-body px-2 py-0.5 rounded-[4px]">{{ $log->action }}</span>
                <span class="text-xs text-ink-soft">{{ $log->created_at?->diffForHumans() ?? '—' }}</span>
            </div>
        </div>
    </div>
</div>
