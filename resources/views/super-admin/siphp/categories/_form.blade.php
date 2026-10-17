@php
    $category = $category ?? null;
    $isEdit = $category !== null;
    $action = $isEdit ? route('super-admin.market-categories.update', $category) : route('super-admin.market-categories.store');
    $inputBase = 'w-full px-4 py-2.5 rounded-xl border text-sm bg-white transition focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated';
@endphp
<form method="POST" action="{{ $action }}" class="w-full space-y-5 bg-white rounded-2xl border border-line p-6 shadow-sm card-enter">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        {{-- Nama Kategori --}}
        <div>
            <label for="name" class="block text-sm font-semibold text-ink mb-1.5">Nama Kategori <span class="text-red-500">*</span></label>
            <input id="name" name="name" type="text" required maxlength="100"
                value="{{ old('name', $category->name ?? '') }}"
                placeholder="Contoh: Barang Pokok"
                class="{{ $inputBase }} {{ $errors->has('name') ? 'border-red-500 ring-1 ring-red-500' : 'border-line' }}">
            @error('name')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        {{-- Urutan --}}
        <div>
            <label for="sort_order" class="block text-sm font-semibold text-ink mb-1.5">Urutan Tampil <span class="text-xs font-normal text-ink-faint">(opsional)</span></label>
            <input id="sort_order" name="sort_order" type="number" min="0" max="65535" step="1"
                value="{{ old('sort_order', $category->sort_order ?? 0) }}"
                class="{{ $inputBase }} {{ $errors->has('sort_order') ? 'border-red-500 ring-1 ring-red-500' : 'border-line' }}">
            @error('sort_order')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
            <p class="mt-1.5 text-[11px] text-ink-faint">Angka kecil tampil lebih dahulu di halaman publik.</p>
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex items-center justify-between gap-2 pt-4 border-t border-surface-sunken">
        @if ($isEdit)
            {{-- Lives outside the main form (HTML forbids nesting); the button
                 references the hidden delete form via the `form` attribute. --}}
            <button type="submit" form="delete-category-{{ $category->id }}"
                onclick="return confirm({{ \Illuminate\Support\Js::from('Hapus kategori "' . $category->name . '"? Tindakan ini tidak dapat dibatalkan.') }})"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-red-50 hover:bg-red-100 text-red-700 text-sm font-semibold rounded-xl border border-red-200 ui-animated">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Hapus
            </button>
        @else
            <span></span>
        @endif

        <div class="flex items-center gap-2">
            <a href="{{ route('super-admin.market-categories.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-white hover:bg-surface-sunken text-ink-soft text-sm font-semibold rounded-xl border border-line ui-animated">
                Batal
            </a>
            <button type="submit"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Kategori' }}
            </button>
        </div>
    </div>
</form>

@if ($isEdit)
    {{-- Hidden delete form referenced by the button above (never nested). --}}
    <form id="delete-category-{{ $category->id }}" method="POST"
          action="{{ route('super-admin.market-categories.destroy', $category) }}" class="hidden">
        @csrf
        @method('DELETE')
    </form>
@endif
