@php
    $title = 'Tambah Pengguna — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Tambah Pengguna</x-slot:header>
    <x-slot:breadcrumb>Kelola Pengguna</x-slot:breadcrumb>

    <div class="max-w-2xl">
        @include('super-admin.users._form', ['user' => null])
    </div>
</x-layouts.dashboard>
