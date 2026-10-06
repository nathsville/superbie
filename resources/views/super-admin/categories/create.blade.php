@php
    $title = 'Tambah Kategori — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Tambah Kategori</x-slot:header>
    <x-slot:breadcrumb>Master Kategori</x-slot:breadcrumb>

    <div class="max-w-2xl">
        @include('super-admin.categories._form', ['category' => null])
    </div>
</x-layouts.dashboard>
