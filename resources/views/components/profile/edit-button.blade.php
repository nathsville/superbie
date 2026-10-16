{{--
    Card-header "Edit" action (orange, pencil icon).

    Switches the surrounding profile form into edit mode by setting the shared
    Alpine `editing` flag. Hidden once editing so the value inputs are the focus.
--}}
@props(['label' => 'Edit'])

<button
    type="button"
    @click="editing = true"
    x-show="!editing"
    class="inline-flex items-center gap-1.5 shrink-0 text-sm font-semibold text-white bg-accent-500 hover:bg-accent-600 px-3.5 py-2 rounded-xl ui-animated shadow-sm"
>
    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
    </svg>
    {{ $label }}
</button>
