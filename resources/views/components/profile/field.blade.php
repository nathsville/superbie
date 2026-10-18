{{--
    Profile data field.

    Renders a muted field label above a bold value (view mode). When `name` is
    given the field is editable: the value becomes an input (or textarea) that
    is revealed while the surrounding form is in edit mode (`editing`), sharing
    the same Alpine scope. Read-only fields (no `name`) always show the value.

    Progressive enhancement: the value is toggled with `x-show` (NO `x-cloak`),
    so without JavaScript the server-rendered value is visible and the page
    degrades to read-only. The editable control carries `x-cloak` so it never
    flashes before Alpine initialises. `prefill` can be disabled so a sensitive
    field (e.g. a password) is never repopulated from `old()`.
--}}
@props([
    'label',
    'value'       => null,
    'name'        => null,
    'type'        => 'text',
    'textarea'    => false,
    'placeholder' => '',
    'hint'        => null,
    'span'        => null,
    'prefill'     => true,
])

@php
    $id       = $name ? 'field-'.$name : null;
    $display  = ($value === null || $value === '') ? '—' : $value;
    $spanCls  = match ((int) $span) {
        2 => 'sm:col-span-2',
        3 => 'sm:col-span-3',
        default => '',
    };
    $border   = ($name && $errors->has($name)) ? 'border-red-500 ring-1 ring-red-500' : 'border-line-strong';
    $inputCls = 'mt-1 w-full rounded-xl border bg-white px-4 py-2.5 text-sm text-ink placeholder-ink-ghost focus:outline-none focus:ring-2 focus:ring-primary-600 focus:border-transparent ui-animated';
    $fill     = ($name && $prefill) ? old($name, $value) : '';
@endphp

<div class="min-w-0 {{ $spanCls }}">
    <label @if ($id) for="{{ $id }}" @endif class="block text-xs font-medium text-ink-faint">{{ $label }}</label>

    @if ($name)
        <p x-show="!editing" class="mt-1 text-sm font-semibold text-ink break-words">{{ $display }}</p>

        @if ($textarea)
            <textarea
                x-cloak
                x-show="editing"
                id="{{ $id }}"
                name="{{ $name }}"
                rows="3"
                placeholder="{{ $placeholder }}"
                class="{{ $inputCls }} {{ $border }}"
            >{{ $fill }}</textarea>
        @else
            <input
                x-cloak
                x-show="editing"
                type="{{ $type }}"
                id="{{ $id }}"
                name="{{ $name }}"
                value="{{ $fill }}"
                placeholder="{{ $placeholder }}"
                class="{{ $inputCls }} {{ $border }}"
            >
        @endif

        @error($name)
            <p class="text-xs text-red-600 mt-1.5 font-medium">{{ $message }}</p>
        @enderror
    @else
        <p class="mt-1 text-sm font-semibold text-ink break-words">{{ $display }}</p>
    @endif

    @if ($hint)
        <p class="text-[11px] text-ink-soft mt-1.5">{{ $hint }}</p>
    @endif
</div>
