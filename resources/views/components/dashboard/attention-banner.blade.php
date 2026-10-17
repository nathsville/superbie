@props([
    'belumDinasUnit' => 0,
    'laporanTertua'   => null,
    'variant'         => 'flat',
])

@php
    // Attention is derived ONLY from real data. No SLA, no anomaly score.
    //
    // PHASE 6C — Decision 3: the former "belum ditugaskan" (`assigned_to IS NULL`)
    // metric was REDEFINED to "Belum Ditentukan Dinas/Unit" (`dinas_unit_id IS
    // NULL`). The prop was renamed accordingly.
    //
    // Two surfaces, one component:
    //  - `flat` (default) — the quiet amber field (operator dashboard).
    //  - `bold`           — the saturated poster-yellow band (Super Admin).
    $total = (int) $belumDinasUnit + ($laporanTertua ? 1 : 0);

    $umur = $laporanTertua?->submitted_at
        ? (int) $laporanTertua->submitted_at->diffInDays(now())
        : null;
    $umurLabel = $umur === null ? '—' : ($umur === 0 ? 'Hari ini' : $umur.' hari');
@endphp

@if ($total > 0)
    @if ($variant === 'bold')
        {{-- Poster-yellow band: ink on flat #FFC14D, oversized readings, zero
             filler copy. No gradient, no nested boxes. --}}
        <section class="card-enter rounded-[1.5rem] bg-accent-500 px-6 py-6 sm:px-7 sm:py-7"
                 aria-label="Perlu perhatian">
            <h2 class="text-[17px] font-semibold text-ink">Perlu Perhatian</h2>

            <div class="mt-6 grid gap-x-6 gap-y-6 {{ (int) $belumDinasUnit > 0 && $laporanTertua ? 'grid-cols-2' : 'grid-cols-1' }}">
                @if ($belumDinasUnit > 0)
                    <div class="min-w-0">
                        <p class="text-[34px] font-semibold leading-none tracking-[-0.02em] text-ink tabular-nums sm:text-[40px]">{{ number_format($belumDinasUnit) }}</p>
                        <p class="mt-2.5 text-[13px] font-semibold leading-snug text-ink-body">Belum ditentukan Dinas/Unit</p>
                    </div>
                @endif

                @if ($laporanTertua)
                    <div class="min-w-0">
                        <p class="text-[34px] font-semibold leading-none tracking-[-0.02em] text-ink tabular-nums sm:text-[40px]">{{ $umurLabel }}</p>
                        <p class="mt-2.5 text-[13px] font-semibold leading-snug text-ink-body">Umur tertinggi</p>
                        <p class="mt-1 truncate text-xs text-ink-body">
                            {{ $laporanTertua->reference_code }} • {{ $laporanTertua->category?->name ?? 'Tanpa Kategori' }}
                        </p>
                    </div>
                @endif
            </div>
        </section>
    @else
        {{-- Flat amber field — the page's one warm surface. No gradient, no nested
             boxes: the reading rows carry the facts. --}}
        <section class="card-enter rounded-[1.5rem] bg-accent-100 px-5 py-5 sm:px-6 sm:py-6"
                 aria-label="Perlu perhatian">
            <h2 class="text-[17px] font-semibold text-ink">Perlu Perhatian</h2>
            <p class="mt-0.5 text-[13px] text-ink-soft">Berdasarkan data laporan aktual.</p>

            <div class="mt-5 grid grid-cols-1 gap-x-10 gap-y-5 sm:grid-cols-2">
                @if ($belumDinasUnit > 0)
                    <div class="min-w-0">
                        <p class="text-[13px] font-medium text-ink-soft">Laporan belum ditentukan Dinas/Unit</p>
                        <p class="mt-1 text-[26px] font-semibold leading-none tracking-[-0.01em] text-ink tabular-nums sm:text-[30px]">{{ $belumDinasUnit }}</p>
                        <p class="mt-1.5 text-[13px] text-ink-soft">Perlu penentuan tujuan Dinas/Unit</p>
                    </div>
                @endif

                @if ($laporanTertua)
                    <div class="min-w-0">
                        <p class="text-[13px] font-medium text-ink-soft">Umur laporan tertinggi</p>
                        <p class="mt-1 text-[26px] font-semibold leading-none tracking-[-0.01em] text-ink sm:text-[30px]">{{ $umurLabel }}</p>
                        <p class="mt-1.5 text-[13px] text-ink-soft truncate">
                            {{ $laporanTertua->reference_code }} • {{ $laporanTertua->category?->name ?? 'Tanpa Kategori' }}
                        </p>
                    </div>
                @endif
            </div>
        </section>
    @endif
@endif
