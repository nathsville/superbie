@props([
    'buckets' => [],
    'total'   => 0,
])

@php
    // Factual age distribution of ACTIVE complaints (bucketed from submitted_at
    // by the dashboard service). The oldest bucket (">7 hari") is the only one
    // flagged as needing attention. Nothing here is an SLA claim.
    $total = (int) $total;

    $max = 0;
    foreach ($buckets as $b) {
        $max = max($max, (int) ($b['count'] ?? 0));
    }
@endphp

@if (count($buckets) > 0 && $total > 0)
    <div class="w-full space-y-4">
        @foreach ($buckets as $bucket)
            @php
                $value  = (int) ($bucket['count'] ?? 0);
                $isOld  = $loop->last; // ">7 hari" — oldest bucket
                $barPct = $max > 0 ? round(($value / $max) * 100, 1) : 0;
                $share  = $total > 0 ? (int) round(($value / $total) * 100) : 0;
                $barCls = $isOld ? 'bg-rose-500' : 'bg-primary-600';
                $numCls = $isOld ? 'text-rose-600' : 'text-ink-soft';
            @endphp

            <div class="group relative">
                <div class="flex items-center justify-between gap-3 text-sm mb-1.5">
                    <span class="font-medium text-ink truncate">{{ $bucket['label'] }}</span>
                    <span class="flex items-center gap-2 shrink-0">
                        @if ($isOld && $value > 0)
                            <span class="aging-attention inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 border border-rose-200/60">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
                                </svg>
                                Perlu Perhatian
                            </span>
                        @endif
                        <span class="font-semibold tabular-nums {{ $numCls }}">{{ $value }}</span>
                    </span>
                </div>

                <div class="h-2.5 rounded-full bg-surface-sunken overflow-hidden">
                    <div class="h-full rounded-full {{ $barCls }} transition-all duration-500 ease-standard"
                         style="width: {{ $barPct }}%" role="presentation"></div>
                </div>

                {{-- Hover tooltip: share of total active reports --}}
                <div class="pointer-events-none absolute bottom-full left-0 mb-1.5 z-20 opacity-0 transition-opacity duration-150 motion-reduce:transition-none group-hover:opacity-100">
                    <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg bg-ink text-white text-[11px] font-semibold px-2.5 py-1.5 shadow-lg">
                        {{ $bucket['label'] }}: {{ $value }} laporan
                        <span class="text-white/60 font-normal">•</span>
                        {{ $share }}% dari aktif
                    </span>
                </div>
            </div>
        @endforeach
    </div>
@endif
