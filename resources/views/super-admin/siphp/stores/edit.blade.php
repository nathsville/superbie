@php
    $title = 'Ubah Toko Retail — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Ubah Toko Retail</x-slot:header>
    <x-slot:breadcrumb>Direktori Toko Retail (SIPHP) — {{ $store->name }}</x-slot:breadcrumb>

    <div class="w-full max-w-full">
        @include('super-admin.siphp.stores._form', ['store' => $store])
    </div>
</x-layouts.dashboard>
