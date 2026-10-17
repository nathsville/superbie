@php
    $title = 'Tambah Pasar — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Tambah Pasar</x-slot:header>
    <x-slot:breadcrumb>Master Pasar (SIPHP)</x-slot:breadcrumb>

    <div class="w-full max-w-full">
        @include('super-admin.siphp.market._form', ['market' => null])
    </div>
</x-layouts.dashboard>
