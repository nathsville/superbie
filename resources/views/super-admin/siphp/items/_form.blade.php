@php
    $item = $item ?? null;
    $isEdit = $item !== null;
    $action = $isEdit ? route('super-admin.market-items.update', $item) : route('super-admin.market-items.store');
    $inputBase = 'w-full px-4 py-2.5 rounded-xl border text-sm bg-white transition focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated';
@endphp
<form method="POST" action="{{ $action }}" class="w-full space-y-5 bg-white rounded-2xl border border-line p-6 shadow-sm card-enter">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        {{-- Kategori --}}
        <div>
            <label for="category_id" class="block text-sm font-semibold text-ink mb-1.5">Kategori Barang <span class="text-red-500">*</span></label>
            <select id="category_id" name="category_id" required
                class="{{ $inputBase }} {{ $errors->has('category_id') ? 'border-red-500 ring-1 ring-red-500' : 'border-line' }}">
                <option value="">-- Pilih Kategori --</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) old('category_id', $item->category_id ?? '') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            @error('category_id')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
            @if ($categories->isEmpty())
                <p class="mt-1.5 text-[11px] text-ink-faint">Belum ada kategori barang. Tambahkan kategori terlebih dahulu.</p>
            @endif
        </div>

        {{-- Nama Barang --}}
        <div>
            <label for="name" class="block text-sm font-semibold text-ink mb-1.5">Nama Barang <span class="text-red-500">*</span></label>
            <input id="name" name="name" type="text" required maxlength="150"
                value="{{ old('name', $item->name ?? '') }}"
                placeholder="Contoh: Beras"
                class="{{ $inputBase }} {{ $errors->has('name') ? 'border-red-500 ring-1 ring-red-500' : 'border-line' }}">
            @error('name')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        {{-- Satuan --}}
        <div>
            <label for="unit" class="block text-sm font-semibold text-ink mb-1.5">Satuan <span class="text-xs font-normal text-ink-faint">(opsional)</span></label>
            <input id="unit" name="unit" type="text" maxlength="50"
                value="{{ old('unit', $item->unit ?? '') }}"
                placeholder="Contoh: kg"
                class="{{ $inputBase }} {{ $errors->has('unit') ? 'border-red-500 ring-1 ring-red-500' : 'border-line' }}">
            @error('unit')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    {{-- Status aktif --}}
    <div class="rounded-xl border border-dashed border-line-strong bg-page p-4">
        <label for="is_active" class="flex items-start gap-3 cursor-pointer">
            <input id="is_active" name="is_active" type="checkbox" value="1"
                @checked(old('is_active', $item->is_active ?? true))
                class="mt-0.5 w-4 h-4 rounded border-line text-primary-600 focus:ring-primary-600/25">
            <span>
                <span class="block text-sm font-semibold text-ink">Barang aktif</span>
                <span class="block text-xs text-ink-faint mt-0.5">Barang nonaktif disembunyikan dari publik dan tidak muncul lagi untuk input harga baru. Riwayat harga yang sudah tercatat tidak pernah diubah.</span>
            </span>
        </label>
    </div>

    {{-- Actions --}}
    <div class="flex items-center justify-between gap-2 pt-4 border-t border-surface-sunken">
        @if ($isEdit)
            {{-- Lives outside the main form (HTML forbids nesting); the button
                 references the hidden delete form via the `form` attribute. --}}
            <button type="submit" form="delete-item-{{ $item->id }}"
                onclick="return confirm({{ \Illuminate\Support\Js::from('Hapus barang "' . $item->name . '"? Tindakan ini tidak dapat dibatalkan.') }})"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-red-50 hover:bg-red-100 text-red-700 text-sm font-semibold rounded-xl border border-red-200 ui-animated">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Hapus
            </button>
        @else
            <span></span>
        @endif

        <div class="flex items-center gap-2">
            <a href="{{ route('super-admin.market-items.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-white hover:bg-surface-sunken text-ink-soft text-sm font-semibold rounded-xl border border-line ui-animated">
                Batal
            </a>
            <button type="submit"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Barang' }}
            </button>
        </div>
    </div>
</form>

@if ($isEdit)
    {{-- Hidden delete form referenced by the button above (never nested). --}}
    <form id="delete-item-{{ $item->id }}" method="POST"
          action="{{ route('super-admin.market-items.destroy', $item) }}" class="hidden">
        @csrf
        @method('DELETE')
    </form>
@endif
