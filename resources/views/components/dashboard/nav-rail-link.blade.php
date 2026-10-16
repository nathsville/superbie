{{--
    Icon-rail navigation link (Prompt 27 — dashboard shell).

    Visual truth only: a circular icon target on a narrow floating rail with a
    filled rounded-square active state (cloned from the supplied reference).
    Colors stay the repository's blue/teal brand tokens.

    Responsive behaviour:
      - Desktop (lg+): icon-only square on the collapsed rail; the label
        collapses to assistive-tech-only text (`.rail-label`) so it stays
        announced and still matched by server-side HTML assertions
        (e.g. `assertSee('Semua Laporan')`). When the rail is expanded (hover or
        pinned) the label becomes visible text next to the icon.
      - Mobile drawer: the label renders as visible text next to the icon.

    `title` exposes the label as a native tooltip on the rail. `$attributes`
    is merged so callers keep `href`, `aria-current` and any `data-*` hooks.
    Progressive navigation still targets these `<a>` elements (same-origin GET
    only) and swaps `#primary-navigation` on navigate.
--}}
@props([
    'href'     => '#',
    'label'    => '',
    'active'   => false,
    'disabled' => false,
])

@php
    $labelClass = 'nav-rail-label rail-label';
@endphp

@if ($disabled)
    <span
        {{ $attributes->merge([
            'class' => 'nav-rail-link is-disabled',
            'title' => $label.' — belum tersedia pada baseline ini',
        ]) }}
        aria-disabled="true"
        role="link"
    >
        <span class="nav-rail-icon" aria-hidden="true">{{ $icon ?? '' }}</span>
        <span class="{{ $labelClass }}">{{ $label }}</span>
    </span>
@else
    <a
        href="{{ $href }}"
        {{ $attributes->merge([
            'class' => 'nav-rail-link'.($active ? ' is-active' : ''),
            'title' => $label,
        ]) }}
        @if ($active) aria-current="page" @endif
    >
        <span class="nav-rail-icon" aria-hidden="true">{{ $icon ?? '' }}</span>
        <span class="{{ $labelClass }}">{{ $label }}</span>
    </a>
@endif
