@php
    $title = 'Ubah Dinas/Unit — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Ubah Dinas/Unit</x-slot:header>
    <x-slot:breadcrumb>{{ $dinasUnit->name }}</x-slot:breadcrumb>

    @if (session('success'))
        <div class="mb-4 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert" x-data>
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->has('dinas_unit'))
        <div class="mb-4 flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.74 5a2 2 0 00-3.48 0L3.33 16a2 2 0 001.74 3z"/></svg>
            {{ $errors->first('dinas_unit') }}
        </div>
    @endif       

    <div class="max-w-2xl space-y-6">
        @include('super-admin.dinas._form', ['dinasUnit' => $dinasUnit])

        <div class="bg-white rounded-2xl border border-[#E2E8F0] p-6 shadow-sm">
            <h2 class="text-sm font-bold text-[#0F172A] uppercase tracking-wider mb-3">Kategori Terhubung</h2>
            @if ($dinasUnit->categories()->count() === 0)
                <p class="text-sm text-[#94A3B8] italic">Belum terhubung ke kategori mana pun.</p>
            @else
                <ul class="space-y-2">
                    @foreach ($dinasUnit->categories()->orderBy('name')->get() as $cat)
                        <li class="flex items-center justify-between text-sm">
                            <span class="text-[#0F172A]">{{ $cat->name }}</span>
                            <a href="{{ route('super-admin.categories.mapping.edit', $cat) }}"
                               class="text-[#2563EB] hover:underline text-xs font-semibold">Kelola Mapping</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Danger zone: hard delete (blocked if used by complaints) --}}
        <div class="bg-white rounded-2xl border border-red-200 p-6 shadow-sm">
            <h2 class="text-sm font-bold text-red-700 uppercase tracking-wider mb-3">Hapus Dinas/Unit</h2>
            @if ($dinasUnit->complaints()->exists())
                <p class="text-sm text-[#475569]">
                    Dinas/Unit ini sudah digunakan pada laporan dan <strong>tidak dapat dihapus</strong>.
                    Gunakan status <strong>Nonaktif</strong> untuk menghentikan penggunaannya pada penugasan baru.
                    Laporan lama tetap menyimpan Dinas/Unit ini sebagai tujuan.
                </p>
            @else
                <p class="text-sm text-[#475569] mb-4">Dinas/Unit ini belum pernah digunakan pada laporan. Penghapusan bersifat permanen.</p>
                <form method="POST" action="{{ route('super-admin.dinas.destroy', $dinasUnit) }}"
                      onsubmit="return confirm('Hapus Dinas/Unit ini secara permanen?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Hapus Permanen
                    </button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.dashboard>
