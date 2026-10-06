@php
    $title = 'Mapping Kategori — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
    $mappedCount = count($mappedIds);
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Kelola Mapping Dinas/Unit</x-slot:header>
    <x-slot:breadcrumb>Kategori: {{ $category->name }}</x-slot:breadcrumb>

    @if (session('success'))
        <div class="mb-4 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.74 5a2 2 0 00-3.48 0L3.33 16a2 2 0 001.74 3z"/></svg>
            {{ $errors->first() }}
        </div>
    @endif

    <div class="max-w-3xl space-y-6">
        {{-- Category summary --}}
        <div class="bg-white rounded-2xl border border-[#E2E8F0] p-6 shadow-sm card-enter">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <h2 class="text-sm font-bold text-[#0F172A] uppercase tracking-wider mb-1">Kategori</h2>
                    <p class="text-sm text-[#64748B]">{{ $category->name }}</p>
                </div>
                <div class="flex items-center gap-2">
                    @if ($category->is_active)
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-green-50 text-green-700 border border-green-200">Aktif</span>
                    @else
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">Nonaktif</span>
                    @endif
                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">{{ $mappedCount }} ter-mapping</span>
                </div>
            </div>
            <p class="mt-3 text-[11px] text-[#94A3B8]">
                Mapping <strong>many-to-many</strong>: satu kategori dapat memiliki banyak Dinas/Unit, dan satu Dinas/Unit dapat menangani banyak kategori.
                Operator hanya dapat memilih tujuan dari Dinas/Unit yang <strong>ter-mapping</strong> ke kategori laporan dan <strong>aktif</strong>.
                Dinas/Unit nonaktif boleh tetap ter-mapping (tidak dihapus otomatis) — tidak tersedia untuk penugasan baru.
            </p>
        </div>

        <form method="POST" action="{{ route('super-admin.categories.mapping.update', $category) }}"
              class="bg-white rounded-2xl border border-[#E2E8F0] p-6 shadow-sm space-y-4 card-enter">
            @csrf
            @method('PUT')

            @if ($allDinasUnits->isEmpty())
                <p class="text-sm text-[#94A3B8] italic">
                    Belum ada Dinas/Unit. <a href="{{ route('super-admin.dinas.create') }}" class="text-[#2563EB] hover:underline">Tambah Dinas/Unit</a> terlebih dahulu.
                </p>
            @else
                <div class="space-y-2">
                    @foreach ($allDinasUnits as $unit)
                        @php $isMapped = in_array($unit->id, old('dinas_unit_ids', $mappedIds)); @endphp
                        <label class="flex items-center gap-3 px-4 py-3 border rounded-xl cursor-pointer ui-animated {{ $isMapped ? 'border-[#2563EB] bg-[#EFF6FF]' : 'border-[#E2E8F0] hover:bg-[#F8FAFC]' }}">
                            <input type="checkbox" name="dinas_unit_ids[]" value="{{ $unit->id }}"
                                @checked($isMapped)
                                class="rounded border-[#CBD5E1] text-[#2563EB] focus:ring-[#2563EB]">
                            <span class="flex-1 text-sm text-[#0F172A]">
                                {{ $unit->name }}
                                @if ($unit->code)<span class="text-xs text-[#94A3B8]">({{ $unit->code }})</span>@endif
                            </span>
                            @if ($isMapped)
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-100 text-blue-700 border border-blue-200">Mapped</span>
                            @endif
                            @if ($unit->is_active)
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-50 text-green-700 border border-green-200">Aktif</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">Nonaktif</span>
                            @endif
                        </label>
                    @endforeach
                </div>
                @error('dinas_unit_ids')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                @error('dinas_unit_ids.*')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                        Simpan Mapping
                    </button>
                    <a href="{{ route('super-admin.categories.index') }}" class="text-sm text-[#64748B] hover:underline">Kembali</a>
                </div>
            @endif
        </form>
    </div>
</x-layouts.dashboard>
