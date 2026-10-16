@php
    $title = 'Tambah Kelurahan — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Tambah Kelurahan</x-slot:header>
    <x-slot:breadcrumb>Master Kelurahan</x-slot:breadcrumb>

    <div class="w-full max-w-full">
        @include('super-admin.kelurahan._form', ['kelurahan' => null])
    </div>
</x-layouts.dashboard>
