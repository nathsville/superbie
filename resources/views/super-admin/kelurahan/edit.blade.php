@php
    $title = 'Ubah Kelurahan — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Ubah Kelurahan</x-slot:header>
    <x-slot:breadcrumb>{{ $kelurahan->name }}</x-slot:breadcrumb>

    <div class="w-full max-w-full space-y-6 card-enter">
        @if (session('success'))
            <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->has('kelurahan'))
            <div class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.74 5a2 2 0 00-3.48 0L3.33 16a2 2 0 001.74 3z"/></svg>
                {{ $errors->first('kelurahan') }}
            </div>
        @endif

        {{-- Kartu form (full width) --}}
        @include('super-admin.kelurahan._form', ['kelurahan' => $kelurahan])

        {{-- Danger zone: hard delete (blocked if used by complaints) --}}
        <div class="w-full bg-red-50/60 rounded-2xl border border-red-200 p-5 sm:p-6 shadow-sm">
            <div class="flex items-center gap-2.5 mb-3">
                <span class="h-5 w-1.5 rounded-full bg-red-500" aria-hidden="true"></span>
                <h2 class="text-sm font-semibold text-red-700 tracking-wide">Hapus Kelurahan</h2>
            </div>
            @if ($kelurahan->complaints()->exists())
                <p class="text-sm text-ink-soft leading-relaxed">
                    Kelurahan ini sudah digunakan pada laporan dan <strong>tidak dapat dihapus</strong>.
                    Data historis laporan tetap menyimpan Kelurahan ini sebagai wilayah.
                </p>
            @else
                <p class="text-sm text-ink-soft leading-relaxed mb-4">Kelurahan ini belum pernah digunakan pada laporan. Penghapusan bersifat permanen.</p>
                <div class="flex items-center justify-end">
                    <form method="POST" action="{{ route('super-admin.kelurahan.destroy', $kelurahan) }}"
                          x-data
                          @submit="if (! confirm('Hapus Kelurahan ini secara permanen?')) $event.preventDefault();">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Hapus Permanen
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-layouts.dashboard>
