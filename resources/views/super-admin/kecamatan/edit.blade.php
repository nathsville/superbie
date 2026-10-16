@php
    $title = 'Ubah Kecamatan — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Ubah Kecamatan</x-slot:header>
    <x-slot:breadcrumb>{{ $kecamatan->name }}</x-slot:breadcrumb>

    <div class="w-full max-w-full space-y-6 card-enter">
        @if (session('success'))
            <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->has('kecamatan'))
            <div class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.74 5a2 2 0 00-3.48 0L3.33 16a2 2 0 001.74 3z"/></svg>
                {{ $errors->first('kecamatan') }}
            </div>
        @endif

        {{-- Kartu form (full width) --}}
        @include('super-admin.kecamatan._form', ['kecamatan' => $kecamatan])

        {{-- Kartu "Kelurahan Terdaftar" (full width) --}}
        @php $kelurahanList = $kecamatan->kelurahan()->orderBy('name')->get(); @endphp
        <div class="w-full bg-white rounded-2xl border border-line p-5 sm:p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
                <div class="flex items-center gap-2.5">
                    <span class="h-5 w-1.5 rounded-full bg-primary-600" aria-hidden="true"></span>
                    <h2 class="text-sm font-semibold text-ink tracking-wide">Kelurahan Terdaftar</h2>
                    <span class="text-[11px] font-semibold text-ink-faint bg-page border border-line px-2.5 py-0.5 rounded-full">{{ $kelurahanList->count() }}</span>
                </div>
                <a href="{{ route('super-admin.kelurahan.index') }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-surface-sunken text-primary-600 text-xs font-semibold rounded-xl border border-line ui-animated">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    Kelola Kelurahan
                </a>
            </div>

            @if ($kelurahanList->isEmpty())
                <p class="text-sm text-ink-faint italic">Belum ada Kelurahan pada Kecamatan ini.</p>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
                    @foreach ($kelurahanList as $kel)
                        <div class="flex items-center gap-2 rounded-xl bg-page border border-line px-3 py-2.5">
                            <svg class="w-3.5 h-3.5 shrink-0 text-ink-faint" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span class="text-sm text-ink truncate">{{ $kel->name }}</span>
                            @if ($kel->code)
                                <span class="ml-auto shrink-0 font-mono text-[10px] text-ink-faint bg-white border border-line px-1.5 py-0.5 rounded-md">{{ $kel->code }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Danger zone: hard delete (blocked if it still owns Kelurahan or is used by complaints) --}}
        <div class="w-full bg-red-50/60 rounded-2xl border border-red-200 p-5 sm:p-6 shadow-sm">
            <div class="flex items-center gap-2.5 mb-3">
                <span class="h-5 w-1.5 rounded-full bg-red-500" aria-hidden="true"></span>
                <h2 class="text-sm font-semibold text-red-700 tracking-wide">Hapus Kecamatan</h2>
            </div>
            @if ($kecamatan->kelurahan()->exists())
                <p class="text-sm text-ink-soft leading-relaxed">
                    Kecamatan ini masih memiliki Kelurahan dan <strong>tidak dapat dihapus</strong>.
                    Hapus atau pindahkan seluruh Kelurahan di bawahnya terlebih dahulu.
                </p>
            @elseif ($kecamatan->complaints()->exists())
                <p class="text-sm text-ink-soft leading-relaxed">
                    Kecamatan ini sudah digunakan pada laporan dan <strong>tidak dapat dihapus</strong>.
                    Data historis laporan tetap menyimpan Kecamatan ini sebagai wilayah.
                </p>
            @else
                <p class="text-sm text-ink-soft leading-relaxed mb-4">Kecamatan ini belum pernah digunakan pada laporan. Penghapusan bersifat permanen.</p>
                <div class="flex items-center justify-end">
                    <form method="POST" action="{{ route('super-admin.kecamatan.destroy', $kecamatan) }}"
                          x-data
                          @submit="if (! confirm('Hapus Kecamatan ini secara permanen?')) $event.preventDefault();">
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
