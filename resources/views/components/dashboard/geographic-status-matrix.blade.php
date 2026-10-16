@props([
    'rows' => [],
])

@php
    use App\Enums\ComplaintStatus;

    // Kelurahan × Status matrix (Prompt 6/5 §I). Columns iterate the official
    // ComplaintStatus enum — the single source of truth — so all seven statuses
    // are always present and consistently ordered. Every value is a real count
    // (a printed 0 is a real zero, never a hidden column). No query runs here.
    //
    // LC-2C: this is a HISTORICAL per-status count, so the 3 legacy statuses
    // (under_review, waiting_for_information, closed) are retained for factual
    // history but are explicitly marked "historis" — they are NOT part of the
    // active workflow and must never be read as live stages.
    $columns = ComplaintStatus::cases();

    $legacyValues = array_map(fn (ComplaintStatus $s) => $s->value, ComplaintStatus::legacyCases());

    // Short headers keep the matrix compact on narrow screens; the full official
    // label is exposed via title / aria-label so nothing depends on hover.
    $shortLabels = [
        'submitted'               => 'Diajukan',
        'under_review'            => 'Ditinjau',
        'in_progress'             => 'Diproses',
        'waiting_for_information' => 'Menunggu Info',
        'resolved'                => 'Selesai',
        'rejected'                => 'Ditolak',
        'closed'                  => 'Ditutup',
    ];

    // Highest single cell drives a light scan-aid tint (numbers stay printed, so
    // reading never depends on hover or colour alone).
    $max = 0;
    foreach ($rows as $r) {
        foreach ($columns as $c) {
            $max = max($max, (int) ($r[$c->value] ?? 0));
        }
    }
@endphp

@if (count($rows) > 0)
    <div class="w-full">
        {{-- Legend: colour name comes from the enum; label is always visible. --}}
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 mb-4 text-[11px] text-ink-soft">
            @foreach ($columns as $status)
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-sm" style="background: {{ match ($status->color()) {
                        'blue' => '#013FF6', 'yellow' => '#FFC14D', 'indigo' => '#7C3AED',
                        'orange' => '#F97316', 'green' => '#22C55E', 'red' => '#EF4444',
                        default => '#94A3B8',
                    } }}"></span>
                    {{ $status->label() }}@if (in_array($status->value, $legacyValues, true))<span class="text-line-strong">(historis)</span>@endif
                </span>
            @endforeach
        </div>

        {{-- Local horizontal scroll ONLY on this visual (never page-wide). --}}
        <div class="overflow-x-auto -mx-1 px-1">
            <table class="w-full border-separate border-spacing-1 text-xs">
                <thead>
                    <tr>
                        <th class="text-left font-medium text-ink-faint pl-1 pr-2 whitespace-nowrap">Kelurahan</th>
                        @foreach ($columns as $col)
                            @php $isLegacyCol = in_array($col->value, $legacyValues, true); @endphp
                            <th class="font-medium text-ink-faint text-center px-1 whitespace-nowrap"
                                title="{{ $col->label() }}{{ $isLegacyCol ? ' (historis)' : '' }}">
                                {{ $shortLabels[$col->value] ?? $col->label() }}@if ($isLegacyCol)<span class="block text-[9px] font-normal text-line-strong">historis</span>@endif
                            </th>
                        @endforeach
                        <th class="font-medium text-ink-faint text-center px-1 whitespace-nowrap">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="text-left pl-1 pr-2 max-w-[11rem]"
                                title="{{ $row['name'] }} — {{ $row['kecamatan'] }}">
                                <span class="block font-medium text-ink truncate">{{ $row['name'] }}</span>
                                <span class="block text-[10px] text-ink-faint truncate">{{ $row['kecamatan'] }}</span>
                            </td>
                            @foreach ($columns as $col)
                                @php
                                    $value = (int) ($row[$col->value] ?? 0);
                                    // Light tint scale 0.10 → 0.55; zero stays neutral.
                                    $alpha = ($value > 0 && $max > 0)
                                        ? round(0.10 + 0.45 * ($value / $max), 3)
                                        : 0;
                                    $bg = $value > 0 ? 'rgba(37, 99, 235, '.$alpha.')' : '#F8FAFC';
                                    $fg = $alpha >= 0.42 ? '#FFFFFF' : ($value > 0 ? '#011C70' : '#CBD5E1');
                                @endphp
                                <td class="p-0">
                                    <div class="h-9 min-w-[2.75rem] rounded-md flex items-center justify-center font-semibold tabular-nums"
                                         style="background: {{ $bg }}; color: {{ $fg }};"
                                         role="img"
                                         aria-label="{{ $row['name'] }} ({{ $row['kecamatan'] }}) — {{ $col->label() }}: {{ $value }} laporan">
                                        {{ $value }}
                                    </div>
                                </td>
                            @endforeach
                            <td class="p-0">
                                <div class="h-9 min-w-[2.75rem] rounded-md flex items-center justify-center font-bold tabular-nums bg-surface-sunken text-ink"
                                     role="img"
                                     aria-label="{{ $row['name'] }} — Total: {{ $row['total'] }} laporan">
                                    {{ $row['total'] }}
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="text-[11px] text-ink-faint mt-3">
            Angka menunjukkan jumlah laporan per status pada setiap kelurahan.
        </p>
    </div>
@endif
