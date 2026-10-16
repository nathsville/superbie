@php
    $title = 'Mapping Kategori — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';

    // Repopulate on a failed validation; `old()` may hold a crafted non-array,
    // so guard before use and normalise every id to int for `.includes()`.
    $selectedIds = old('dinas_unit_ids', $mappedIds);
    if (! is_array($selectedIds)) {
        $selectedIds = $mappedIds;
    }
    $selectedIds = array_values(array_map('intval', $selectedIds));
    $mappedCount = count($selectedIds);
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Kelola Mapping Dinas/Unit</x-slot:header>
    <x-slot:breadcrumb>Kategori: {{ $category->name }}</x-slot:breadcrumb>

    <div class="w-full max-w-full space-y-6 card-enter"
         x-data="{ selected: {{ Js::from($selectedIds) }} }">
        @if (session('success'))
            <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.74 5a2 2 0 00-3.48 0L3.33 16a2 2 0 001.74 3z"/></svg>
                {{ $errors->first() }}
            </div>
        @endif

        {{-- ── Kartu informasi kategori ─────────────────────────────────────── --}}
        <div class="w-full bg-white rounded-2xl border border-line p-5 sm:p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div class="min-w-0">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-ink-faint">Kategori</span>
                    <h2 class="text-lg font-bold text-ink mt-1">{{ $category->name }}</h2>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    @if ($category->is_active)
                        <span class="status-badge border bg-green-50 text-green-700 border-green-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                            Aktif
                        </span>
                    @else
                        <span class="status-badge border bg-surface-sunken text-ink-soft border-line">
                            <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                            Nonaktif
                        </span>
                    @endif
                    <span class="status-badge border bg-primary-50 text-primary-700 border-primary-200">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <span x-text="selected.length">{{ $mappedCount }}</span> ter-mapping
                    </span>
                </div>
            </div>
            <p class="mt-4 text-xs text-ink-faint leading-relaxed">
                Mapping <strong class="text-ink-body">many-to-many</strong>: satu kategori dapat memiliki banyak Dinas/Unit, dan satu Dinas/Unit dapat menangani banyak kategori.
                Operator hanya dapat memilih tujuan dari Dinas/Unit yang <strong class="text-ink-body">ter-mapping</strong> ke kategori laporan dan <strong class="text-ink-body">aktif</strong>.
                Dinas/Unit nonaktif boleh tetap ter-mapping (tidak dihapus otomatis) — tidak tersedia untuk penugasan baru.
            </p>
        </div>

        {{-- ── Kartu daftar mapping ─────────────────────────────────────────── --}}
        <h2 class="sr-only">Daftar Dinas/Unit untuk di-mapping</h2>
        <form method="POST" action="{{ route('super-admin.categories.mapping.update', $category) }}"
              class="w-full bg-white rounded-2xl border border-line shadow-sm overflow-hidden">
            @csrf
            @method('PUT')

            <div class="p-5 sm:p-6">
                <div class="flex items-center gap-2.5 mb-4">
                    <span class="h-5 w-1.5 rounded-full bg-primary-600" aria-hidden="true"></span>
                    <h2 class="text-sm font-semibold text-ink tracking-wide">Pilih Dinas/Unit</h2>
                    <span class="ml-auto text-[11px] font-semibold text-ink-faint bg-page border border-line px-2.5 py-0.5 rounded-full">{{ $allDinasUnits->count() }} tersedia</span>
                </div>

                @if ($allDinasUnits->isEmpty())
                    <p class="text-sm text-ink-faint italic">
                        Belum ada Dinas/Unit. <a href="{{ route('super-admin.dinas.create') }}" class="text-primary-600 hover:underline">Tambah Dinas/Unit</a> terlebih dahulu.
                    </p>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach ($allDinasUnits as $unit)
                            @php $isMapped = in_array($unit->id, $selectedIds, true); @endphp
                            <label class="flex items-center gap-3 px-4 py-3.5 border rounded-xl cursor-pointer ui-animated"
                                   :class="selected.includes({{ $unit->id }})
                                       ? 'border-primary-600 bg-primary-50 ring-1 ring-primary-600/30'
                                       : 'border-line hover:bg-page'">
                                <input type="checkbox" name="dinas_unit_ids[]" value="{{ $unit->id }}"
                                    @checked($isMapped)
                                    @change="$event.target.checked
                                        ? (selected.includes({{ $unit->id }}) || selected.push({{ $unit->id }}))
                                        : selected = selected.filter(i => i !== {{ $unit->id }})"
                                    class="w-4 h-4 rounded border-line-strong text-primary-600 focus:ring-primary-600">
                                <span class="flex-1 min-w-0">
                                    <span class="block text-sm font-semibold text-ink truncate">{{ $unit->name }}</span>
                                    @if ($unit->code)
                                        <span class="block text-[11px] text-ink-faint font-mono mt-0.5">{{ $unit->code }}</span>
                                    @endif
                                </span>
                                <span class="flex items-center gap-1.5 shrink-0">
                                    @if ($unit->is_active)
                                        <span class="status-badge border bg-green-50 text-green-700 border-green-200 text-[10px]">Aktif</span>
                                    @else
                                        <span class="status-badge border bg-surface-sunken text-ink-soft border-line text-[10px]">Nonaktif</span>
                                    @endif
                                    <span x-show="selected.includes({{ $unit->id }})" x-cloak
                                          class="status-badge border bg-primary-100 text-primary-700 border-primary-200 text-[10px]">Mapped</span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    @error('dinas_unit_ids')<p class="text-xs text-red-600 mt-3">{{ $message }}</p>@enderror
                    @error('dinas_unit_ids.*')<p class="text-xs text-red-600 mt-3">{{ $message }}</p>@enderror
                @endif
            </div>

            @if ($allDinasUnits->isNotEmpty())
                {{-- Sticky action bar: stays reachable while scrolling a long list. --}}
                <div class="sticky bottom-0 px-5 sm:px-6 py-4 bg-white/95 backdrop-blur border-t border-line flex items-center justify-between gap-3 flex-wrap">
                    <p class="text-xs text-ink-faint">
                        <span class="font-semibold text-ink" x-text="selected.length">{{ $mappedCount }}</span> Dinas/Unit dipilih
                    </p>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('super-admin.categories.index') }}"
                           class="inline-flex items-center gap-2 px-4 py-2.5 bg-white hover:bg-surface-sunken text-ink-soft text-sm font-semibold rounded-xl border border-line ui-animated">
                            Kembali
                        </a>
                        <button type="submit"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Simpan Mapping
                        </button>
                    </div>
                </div>
            @endif
        </form>
    </div>
</x-layouts.dashboard>
