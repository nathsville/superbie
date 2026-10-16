@php
    $category = $category ?? null;
    $isEdit = $category !== null;
@endphp
@php
    $action = $isEdit ? route('super-admin.categories.update', $category) : route('super-admin.categories.store');
    $inputBase = 'w-full px-4 py-2.5 rounded-xl border text-sm bg-white transition focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated';
@endphp
<form method="POST" action="{{ $action }}" class="w-full space-y-5 bg-white rounded-2xl border border-line p-6 shadow-sm card-enter">
    @csrf
    @if ($isEdit)
        @method('PUT')
        {{-- `dinas_name` is retained historical data (never a routing source). The
             editable input was removed from the form; this hidden field preserves
             the stored value so an update never silently nulls it. --}}
        <input type="hidden" name="dinas_name" value="{{ old('dinas_name', $category->dinas_name ?? '') }}">
    @endif

    {{-- Baris 1 (full width): Nama Kategori --}}
    <div>
        <label for="name" class="block text-sm font-semibold text-ink mb-1.5">Nama Kategori <span class="text-red-500">*</span></label>
        <input id="name" name="name" type="text" required maxlength="100"
            value="{{ old('name', $category->name ?? '') }}"
            placeholder="Contoh: Infrastruktur Jalan"
            class="{{ $inputBase }} {{ $errors->has('name') ? 'border-red-500 ring-1 ring-red-500' : 'border-line' }}">
        @error('name')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    {{-- Baris 2 (full width): Deskripsi --}}
    <div>
        <label for="description" class="block text-sm font-semibold text-ink mb-1.5">Deskripsi <span class="text-xs font-normal text-ink-faint">(opsional)</span></label>
        <textarea id="description" name="description" rows="3" maxlength="500"
            placeholder="Penjelasan singkat cakupan kategori ini..."
            class="{{ $inputBase }} resize-none {{ $errors->has('description') ? 'border-red-500 ring-1 ring-red-500' : 'border-line' }}">{{ old('description', $category->description ?? '') }}</textarea>
        @error('description')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    {{-- Baris 3 (2 kolom): Urutan & Status --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <label for="sort_order" class="block text-sm font-semibold text-ink mb-1.5">Urutan</label>
            <input id="sort_order" name="sort_order" type="number" min="0" max="65535"
                value="{{ old('sort_order', $category->sort_order ?? 0) }}"
                class="{{ $inputBase }} {{ $errors->has('sort_order') ? 'border-red-500 ring-1 ring-red-500' : 'border-line' }}">
            @error('sort_order')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="is_active" class="block text-sm font-semibold text-ink mb-1.5">Status</label>
            <select id="is_active" name="is_active"
                class="{{ $inputBase }} {{ $errors->has('is_active') ? 'border-red-500 ring-1 ring-red-500' : 'border-line' }}">
                <option value="1" @selected((string) old('is_active', $category->is_active ?? true) === '1')>Aktif</option>
                <option value="0" @selected((string) old('is_active', $category->is_active ?? true) === '0')>Nonaktif</option>
            </select>
            <p class="mt-1.5 text-[11px] text-ink-faint">Kategori nonaktif tidak dapat dipilih untuk laporan baru; laporan lama tetap memakai kategori ini.</p>
            @error('is_active')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    {{-- Action toolbar — bottom right --}}
    <div class="flex items-center justify-end gap-2 pt-4 border-t border-surface-sunken">
        <a href="{{ route('super-admin.categories.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-white hover:bg-surface-sunken text-ink-soft text-sm font-semibold rounded-xl border border-line ui-animated">
            Batal
        </a>
        <button type="submit"
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Kategori' }}
        </button>
    </div>
</form>
