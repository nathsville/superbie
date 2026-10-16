@props([
    'message' => 'Belum ada data.',
    'tone'    => 'neutral',
])

{{-- Honest empty state — never filled with dummy data.
     `tone="success"` renders the calm "all clear" variant used by the
     long-age Dinas/Unit card (✓ no long-running active reports). --}}
@if ($tone === 'success')
    <div class="empty-state">
        <p class="inline-flex items-center gap-2 text-sm font-medium text-emerald-700">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/>
            </svg>
            {{ $message }}
        </p>
    </div>
@else
    <div class="empty-state">
        <p class="text-sm text-ink-soft">{{ $message }}</p>
    </div>
@endif
