@php
    $title = 'Ubah Kategori Barang — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Ubah Kategori Barang</x-slot:header>
    <x-slot:breadcrumb>Kategori Barang Pasar — {{ $category->name }}</x-slot:breadcrumb>

    <div class="w-full max-w-full">
        @include('super-admin.siphp.categories._form', ['category' => $category])
    </div>
</x-layouts.dashboard>
