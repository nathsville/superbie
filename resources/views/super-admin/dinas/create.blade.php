@php
    $title = 'Tambah Dinas/Unit — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Tambah Dinas/Unit</x-slot:header>
    <x-slot:breadcrumb>Master Dinas/Unit</x-slot:breadcrumb>

    <div class="max-w-2xl">
        @include('super-admin.dinas._form', ['dinasUnit' => null])
    </div>
</x-layouts.dashboard>
