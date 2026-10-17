@php
    $title = 'Tambah Toko Retail — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Tambah Toko Retail</x-slot:header>
    <x-slot:breadcrumb>Direktori Toko Retail (SIPHP)</x-slot:breadcrumb>

    <div class="w-full max-w-full">
        @include('super-admin.siphp.stores._form', ['store' => null])
    </div>
</x-layouts.dashboard>
