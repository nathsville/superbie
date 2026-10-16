@props([
    'belumDinasUnit'  => 0,
    'longAge'         => 0,
])

@php
    // Attention Center (Prompt 3/4) — consolidates every "needs action" signal
    // into ONE read-only banner. It replaces the old standalone "Perlu Perhatian"
    // card AND the standalone "Laporan Belum Ditugaskan" KPI, so the dashboard
    // no longer shows two competing alert centres.
    //
    // PHASE 6C — Decision 3: the former "belum ditugaskan" signal
    // (`assigned_to IS NULL`) was REDEFINED to "Belum Ditentukan Dinas/Unit"
    // (`dinas_unit_id IS NULL`). Since `assigned_to` is deprecated and never
    // written by runtime, the two signals were semantically identical and are
    // now CONSOLIDATED into the single Dinas/Unit-based signal below.
    //
    // Every figure is a REAL active-report count supplied by the backend
    // (DashboardCacheService::attentionSummary() + aging buckets). Nothing here
    // is an SLA, a forecast, or a fabricated warning: tiles whose count is 0 are
    // never rendered as a warning — if every signal is 0 the banner shows the
    // honest "all clear" state instead.
    $signals = [
        [
            'count' => (int) $belumDinasUnit,
            'title' => 'Laporan belum ditentukan Dinas/Unit',
            'hint'  => 'Perlu penentuan tujuan',
            'tone'  => 'amber',
            'href'  => route('admin.complaints', ['assigned' => 'unassigned']),
        ],
        [
            'count' => (int) $longAge,
            'title' => 'Laporan aktif > 7 hari',
            'hint'  => 'Perlu pemantauan',
            'tone'  => 'rose',
            'href'  => null,
        ],
    ];

    // Only real, non-zero problems are surfaced.
    $active = array_values(array_filter($signals, fn ($s) => $s['count'] > 0));

    $tones = [
        'amber' => ['chip' => 'bg-accent-50 text-accent-700 border-accent-200/70', 'num' => 'text-accent-700'],
        'rose'  => ['chip' => 'bg-rose-50 text-rose-700 border-rose-200/70', 'num' => 'text-rose-700'],
    ];
@endphp

<section class="soft-card card-enter p-5 sm:p-6" aria-labelledby="attention-center-heading">
    <h2 id="attention-center-heading" class="text-base font-bold text-ink">Perlu Segera Ditindaklanjuti</h2>
    <p class="text-xs text-ink-faint mt-0.5">Berdasarkan data laporan aktual.</p>

    @if (count($active) > 0)
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-4">
            @foreach ($active as $signal)
                @php
                    $t = $tones[$signal['tone']] ?? $tones['amber'];
                    $tag = $signal['href'] ? 'a' : 'div';
                @endphp
                <{{ $tag }}
                    @if ($signal['href']) href="{{ $signal['href'] }}" @endif
                    class="rounded-2xl border {{ $t['chip'] }} px-4 py-3.5 flex items-start gap-3 {{ $signal['href'] ? 'ui-animated hover:shadow-[var(--shadow-soft-hover)]' : '' }}"
                    @if ($signal['href']) aria-label="{{ $signal['count'] }} {{ $signal['title'] }} — {{ $signal['hint'] }}" @endif
                >
                    {{-- The count is hidden from AT only when a link's aria-label
                         already announces it; on the informational tiles it stays
                         exposed so the number is never lost to screen readers. --}}
                    <span class="text-2xl font-extrabold tabular-nums leading-none {{ $t['num'] }} shrink-0"
                          @if ($signal['href']) aria-hidden="true" @endif>
                        {{ number_format($signal['count']) }}
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-ink">{{ $signal['title'] }}</span>
                        <span class="block text-[11px] text-ink-soft mt-0.5">{{ $signal['hint'] }}</span>
                    </span>
                </{{ $tag }}>
            @endforeach
        </div>

        <div class="mt-4">
            <a href="{{ route('admin.complaints') }}"
               class="inline-flex items-center gap-2 bg-primary-700 hover:bg-primary-600 text-white text-xs font-semibold px-4 py-2.5 rounded-full ui-animated">
                Buka Monitoring Laporan →
            </a>
        </div>
    @else
        <div class="mt-4 flex items-start gap-3 rounded-2xl bg-emerald-50 border border-emerald-200/70 px-4 py-3.5" role="status">
            <span class="w-9 h-9 rounded-full bg-emerald-500 text-white flex items-center justify-center shrink-0" aria-hidden="true">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/>
                </svg>
            </span>
            <div class="min-w-0">
                <p class="text-sm font-bold text-ink">Tidak ada masalah utama yang terdeteksi</p>
                <p class="text-[11px] text-ink-soft mt-0.5">Semua laporan aktif dalam kondisi monitoring normal.</p>
            </div>
        </div>
    @endif
</section>
