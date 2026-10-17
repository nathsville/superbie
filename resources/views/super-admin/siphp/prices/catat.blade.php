@php
    $title = 'Catat Harga — ' . $market->name . ' — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';

    // Old nested input (after a validation error) keyed by item id, so no
    // typed value is lost. Missing keys fall back to the stored row.
    $oldRows = old('rows');
    $oldRowsByItem = [];

    if (is_array($oldRows)) {
        foreach ($oldRows as $row) {
            if (isset($row['item_id'])) {
                $oldRowsByItem[(int) $row['item_id']] = $row['price'] ?? '';
            }
        }
    }
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Catat Harga — {{ $market->name }}</x-slot:header>
    <x-slot:breadcrumb>Harga Pasar (SIPHP)</x-slot:breadcrumb>

    <div class="w-full max-w-full space-y-6 card-enter">
        {{-- Flash --}}
        @if (session('success'))
            <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-xl card-enter" role="alert">
                <p class="font-semibold">Ada input yang perlu diperbaiki:</p>
                <ul class="list-disc list-inside mt-1 space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ── Pemilih tanggal ──────────────────────────────────────────────── --}}
        <div class="w-full bg-white rounded-2xl border border-line shadow-sm p-4 sm:p-5">
            <div class="flex items-start justify-between flex-wrap gap-3">
                <form method="GET" action="{{ route('super-admin.market-prices.catat', $market) }}"
                      class="flex items-end gap-3 flex-wrap" role="search" aria-label="Pilih tanggal berlaku">
                    <div>
                        <label for="date" class="block text-xs font-semibold text-ink-soft mb-1.5">Tanggal berlaku (WITA)</label>
                        <input id="date" name="date" type="date" value="{{ old('effective_date', $date) }}"
                            class="px-3.5 py-2.5 text-sm border border-line rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated">
                    </div>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                        Tampilkan
                    </button>
                </form>

                <a href="{{ route('super-admin.market-prices.index') }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-white hover:bg-surface-sunken text-ink-soft text-sm font-semibold rounded-xl border border-line ui-animated">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Kembali
                </a>
            </div>
        </div>

        @if ($items->isEmpty())
            <div class="w-full bg-white rounded-2xl border border-line shadow-sm empty-state py-16 text-center">
                <svg class="w-12 h-12 text-line-strong mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                <p class="text-sm font-semibold text-ink-soft">Belum ada barang aktif.</p>
                <p class="text-xs text-ink-faint mt-1">Tambahkan barang terlebih dahulu di menu Barang Pasar.</p>
                <div class="mt-4">
                    <a href="{{ route('super-admin.market-items.create') }}"
                       class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                        Tambah Barang
                    </a>
                </div>
            </div>
        @else
            {{-- ── Form input massal ────────────────────────────────────────── --}}
            <form method="POST" action="{{ route('super-admin.market-prices.store', $market) }}" class="w-full space-y-6">
                @csrf
                <input type="hidden" name="effective_date" value="{{ old('effective_date', $date) }}">

                @foreach ($categories as $category)
                    @php($group = $items->where('category_id', $category->id))
                    @if ($group->isNotEmpty())
                        <div class="w-full bg-white rounded-2xl border border-line shadow-sm overflow-hidden">
                            <div class="px-5 sm:px-6 py-4 border-b border-line bg-page">
                                <h3 class="text-sm font-semibold text-ink tracking-wide">{{ $category->name }}</h3>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <caption class="sr-only">Harga barang kategori {{ $category->name }}</caption>
                                    <thead class="bg-page text-ink-soft text-xs uppercase tracking-wider">
                                        <tr>
                                            <th scope="col" class="text-left px-4 py-3 font-semibold min-w-[14rem]">Barang</th>
                                            <th scope="col" class="text-left px-4 py-3 font-semibold whitespace-nowrap">Tersimpan</th>
                                            <th scope="col" class="text-left px-4 py-3 font-semibold whitespace-nowrap w-[12rem]">Harga (Rp)</th>
                                            <th scope="col" class="text-right px-4 py-3 font-semibold whitespace-nowrap">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-surface-sunken">
                                        @foreach ($group as $index => $item)
                                            @php($stored = $existing[$item->id] ?? null)
                                            <tr class="hover:bg-page transition-colors">
                                                <td class="px-4 py-3.5">
                                                    <span class="font-semibold text-ink">{{ $item->name }}</span>
                                                    @if ($item->unit)
                                                        <span class="text-xs text-ink-faint">({{ $item->unit }})</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3.5 whitespace-nowrap">
                                                    @if ($stored)
                                                        <span class="text-ink-soft font-medium">Rp {{ number_format((float) $stored->price, 0, ',', '.') }}</span>
                                                    @else
                                                        <span class="text-xs text-ink-faint">Belum ada</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3.5">
                                                    <input type="number" min="0" step="1" inputmode="numeric"
                                                        name="rows[{{ $index }}][item_id]" value="{{ $item->id }}" hidden>
                                                    <label class="sr-only" for="price-{{ $item->id }}">Harga {{ $item->name }}</label>
                                                    <input id="price-{{ $item->id }}" type="number" min="0" step="1" inputmode="numeric"
                                                        name="rows[{{ $index }}][price]"
                                                        value="{{ $oldRowsByItem[$item->id] ?? ($stored ? (int) $stored->price : '') }}"
                                                        placeholder="Kosongkan bila belum ada"
                                                        class="w-full px-3.5 py-2.5 text-sm border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated {{ $errors->has('rows.' . $index . '.price') ? 'border-red-500 ring-1 ring-red-500' : 'border-line' }}">
                                                    @error('rows.' . $index . '.price')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                                </td>
                                                <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                                    @if ($stored)
                                                        <button type="submit" form="delete-price-{{ $stored->id }}"
                                                            onclick="return confirm({{ \Illuminate\Support\Js::from('Hapus harga ' . $item->name . ' tanggal ' . $date . '?') }})"
                                                            aria-label="Hapus harga {{ $item->name }} tanggal {{ $date }}" title="Hapus baris harga"
                                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 ui-animated">
                                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                        </button>
                                                    @else
                                                        <span class="text-xs text-ink-faint">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                @endforeach

                <div class="w-full bg-white rounded-2xl border border-line shadow-sm p-5 flex items-center justify-between gap-3 flex-wrap">
                    <p class="text-xs text-ink-faint max-w-xl">
                        Isi hanya harga yang tersedia. Input kosong <strong>tidak</strong> disimpan dan tidak mengubah baris yang sudah ada.
                        Menyimpan ulang tanggal yang sama akan memperbarui nilainya (satu harga per pasar × barang × tanggal).
                    </p>
                    <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Simpan Harga
                    </button>
                </div>
            </form>

            {{-- Delete forms live OUTSIDE the bulk form (HTML nesting) and are
                 referenced by the row buttons via the `form` attribute. --}}
            @foreach ($existing as $stored)
                <form id="delete-price-{{ $stored->id }}" method="POST"
                      action="{{ route('super-admin.market-prices.destroy', $stored) }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @endforeach
        @endif
    </div>
</x-layouts.dashboard>
