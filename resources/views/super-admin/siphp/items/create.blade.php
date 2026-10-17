@php
    $title = 'Tambah Barang Pasar — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Tambah Barang Pasar</x-slot:header>
    <x-slot:breadcrumb>Barang Pasar (SIPHP)</x-slot:breadcrumb>

    <div class="w-full max-w-full">
        @include('super-admin.siphp.items._form', ['item' => null])
    </div>
</x-layouts.dashboard>
