@props([
    'rows' => [],
])

@php
    use App\Enums\ComplaintStatus;

    // Columns = the ACTIVE lifecycle statuses (submitted, in_progress) plus
    // "Selesai" for context. LC-2C: legacy statuses (under_review,
    // waiting_for_information, closed) are history-only and are deliberately NOT
    // presented here as workflow stages — mirrors the service's categoryStatusMatrix.
    $columns = array_merge(
        ComplaintStatus::activeCases(),
        [ComplaintStatus::Resolved],
    );

    // Short column headers so the matrix stays compact on narrow screens.
    $shortLabels = [
        'submitted'   => 'Diajukan',
        'in_progress' => 'Diproses',
        'resolved'    => 'Selesai',
    ];

    // Highest single cell drives the heat intensity scale.
    $max = 0;
    foreach ($rows as $r) {
        foreach ($columns as $c) {
            $max = max($max, (int) ($r['counts'][$c->value] ?? 0));
        }
    }
@endphp

@if (count($rows) > 0)
    <div class="w-full">
        <style>
            .hm-cell { position: relative; }
            .hm-tip {
                position: absolute;
                bottom: calc(100% + 6px);
                left: 50%;
                transform: translateX(-50%);
                white-space: nowrap;
                background: #0F172A;
                color: #FFFFFF;
                font-size: 11px;
                font-weight: 600;
                line-height: 1;
                padding: 6px 9px;
                border-radius: 8px;
                opacity: 0;
                pointer-events: none;
                z-index: 40;
                transition: opacity 120ms ease;
            }
            .hm-cell:hover .hm-tip { opacity: 1; }
            @media (prefers-reduced-motion: reduce) {
                .hm-tip { transition: none; }
            }
        </style>

        <div class="overflow-x-auto -mx-1 px-1">
            <table class="w-full border-separate border-spacing-1 text-xs">
                <thead>
                    <tr>
                        <th class="text-left font-medium text-ink-faint pl-1 pr-2 whitespace-nowrap">Kategori</th>
                        @foreach ($columns as $col)
                            <th class="font-medium text-ink-faint text-center px-1 whitespace-nowrap">
                                {{ $shortLabels[$col->value] ?? $col->label() }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="text-left font-medium text-ink pl-1 pr-2 max-w-[10rem] truncate"
                                title="{{ $row['name'] }}">
                                {{ $row['name'] }}
                            </td>
                            @foreach ($columns as $col)
                                @php
                                    $value = (int) ($row['counts'][$col->value] ?? 0);
                                    // 0 → neutral; otherwise scale opacity 0.14 → 0.92.
                                    $alpha = ($value > 0 && $max > 0)
                                        ? round(0.14 + 0.78 * ($value / $max), 3)
                                        : 0;
                                    $bg    = $value > 0 ? 'rgba(234, 88, 12, '.$alpha.')' : '#F8FAFC';
                                    $fg    = $alpha >= 0.55 ? '#FFFFFF' : ($value > 0 ? '#7C2D12' : '#CBD5E1');
                                @endphp
                                <td class="hm-cell p-0">
                                    <div class="h-9 rounded-md flex items-center justify-center font-bold tabular-nums cursor-default"
                                         style="background: {{ $bg }}; color: {{ $fg }};"
                                         role="img"
                                         aria-label="{{ $row['name'] }} — {{ $col->label() }}: {{ $value }} laporan">
                                        {{ $value > 0 ? $value : '' }}
                                        <span class="hm-tip" aria-hidden="true">
                                            {{ $row['name'] }} - {{ $col->label() }}: {{ $value }} Laporan
                                        </span>
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Intensity legend --}}
        <div class="flex items-center gap-2 mt-3 text-[11px] text-ink-faint">
            <span>Rendah</span>
            <span class="flex items-center gap-1">
                <span class="w-4 h-3 rounded-sm" style="background: #F8FAFC; border: 1px solid #E2E8F0"></span>
                <span class="w-4 h-3 rounded-sm" style="background: rgba(234,88,12,0.14)"></span>
                <span class="w-4 h-3 rounded-sm" style="background: rgba(234,88,12,0.45)"></span>
                <span class="w-4 h-3 rounded-sm" style="background: rgba(234,88,12,0.70)"></span>
                <span class="w-4 h-3 rounded-sm" style="background: rgba(234,88,12,0.92)"></span>
            </span>
            <span>Tinggi (penumpukan)</span>
        </div>
    </div>
@endif
