@props([
    'belumDinasUnit' => 0,
    'laporanTertua'   => null,
])

@php
    // Attention is derived ONLY from real data. No SLA, no anomaly score.
    //
    // PHASE 6C — Decision 3: the former "belum ditugaskan" (`assigned_to IS NULL`)
    // metric was REDEFINED to "Belum Ditentukan Dinas/Unit" (`dinas_unit_id IS
    // NULL`). The prop was renamed accordingly.
    $total = (int) $belumDinasUnit + ($laporanTertua ? 1 : 0);
@endphp

@if ($total > 0)
    <section class="rounded-2xl border border-accent-200 bg-gradient-to-br from-accent-50 to-rose-50/60 p-5 sm:p-6 card-enter"
             aria-label="Perlu perhatian">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-accent-500 to-rose-500 text-white flex items-center justify-center shrink-0 shadow-sm">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <h2 class="text-base font-bold text-ink">Perlu Perhatian</h2>
                <p class="text-xs text-ink-soft mt-0.5">Berdasarkan data laporan aktual.</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
                    @if ($belumDinasUnit > 0)
                        <div class="flex items-center gap-3 rounded-xl bg-white border border-accent-200 px-4 py-3 shadow-sm">
                            <span class="w-2.5 h-2.5 rounded-full bg-accent-500 shrink-0"></span>
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-ink">{{ $belumDinasUnit }} laporan belum ditentukan Dinas/Unit</p>
                                <p class="text-[11px] text-ink-soft">Perlu penentuan tujuan Dinas/Unit</p>
                            </div>
                        </div>
                    @endif

                    @if ($laporanTertua)
                        @php
                            $umur = $laporanTertua->submitted_at
                                ? (int) $laporanTertua->submitted_at->diffInDays(now())
                                : null;
                        @endphp
                        <div class="flex items-center gap-3 rounded-xl bg-white border border-accent-200 px-4 py-3 shadow-sm">
                            <span class="w-2.5 h-2.5 rounded-full bg-rose-500 shrink-0"></span>
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-ink truncate">
                                    Umur tertinggi:
                                    {{ $umur === null ? '—' : ($umur === 0 ? 'hari ini' : $umur.' hari') }}
                                </p>
                                <p class="text-[11px] text-ink-soft truncate">
                                    {{ $laporanTertua->reference_code }} • {{ $laporanTertua->category?->name ?? 'Tanpa Kategori' }}
                                </p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endif
