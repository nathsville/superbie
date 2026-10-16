@php
    $title = 'Tambah Kecamatan — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Tambah Kecamatan</x-slot:header>
    <x-slot:breadcrumb>Master Kecamatan</x-slot:breadcrumb>

    <div class="w-full max-w-full">
        @include('super-admin.kecamatan._form', ['kecamatan' => null])
    </div>
</x-layouts.dashboard>
