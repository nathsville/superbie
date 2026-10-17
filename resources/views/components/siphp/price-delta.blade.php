@props([
    'current',
    'previous' => null,
])

@php
    // Honest change indicator: it only ever compares two REAL recorded rows.
    // No previous recording → an em dash, never a fabricated "0".
    $currentValue = (float) $current;
    $previousValue = $previous !== null ? (float) $previous->price : null;
    $previousDate = $previous?->effective_date?->translatedFormat('d M Y');
@endphp

@if ($previousValue === null)
    <span class="text-ink-ghost" title="Belum ada pencatatan sebelumnya">—<span class="sr-only"> Belum ada pencatatan sebelumnya.</span></span>
@elseif ($currentValue > $previousValue)
    @php
        $diff = $currentValue - $previousValue;
        $percent = $previousValue > 0 ? $diff / $previousValue * 100 : null;
    @endphp
    <span class="font-medium text-warning" title="Naik dibanding pencatatan {{ $previousDate }}">↑ Rp {{ number_format($diff, 0, ',', '.') }}@if ($percent !== null) ({{ number_format($percent, 1, ',', '.') }}%)@endif<span class="sr-only"> dibanding pencatatan {{ $previousDate }}.</span></span>
@elseif ($currentValue < $previousValue)
    @php
        $diff = $previousValue - $currentValue;
        $percent = $previousValue > 0 ? $diff / $previousValue * 100 : null;
    @endphp
    <span class="font-medium text-success" title="Turun dibanding pencatatan {{ $previousDate }}">↓ Rp {{ number_format($diff, 0, ',', '.') }}@if ($percent !== null) ({{ number_format($percent, 1, ',', '.') }}%)@endif<span class="sr-only"> dibanding pencatatan {{ $previousDate }}.</span></span>
@else
    <span class="text-ink-faint" title="Sama dengan pencatatan {{ $previousDate }}">Tetap<span class="sr-only"> dibanding pencatatan {{ $previousDate }}.</span></span>
@endif
