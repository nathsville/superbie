@php
    $category = $category ?? null;
    $isEdit = $category !== null;
@endphp
@php
    $action = $isEdit ? route('super-admin.categories.update', $category) : route('super-admin.categories.store');
@endphp
<form method="POST" action="{{ $action }}" class="space-y-5 bg-white rounded-2xl border border-[#E2E8F0] p-6 shadow-sm card-enter">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div>
        <label for="name" class="block text-sm font-semibold text-[#0F172A] mb-1.5">Nama Kategori <span class="text-red-500">*</span></label>
        <input id="name" name="name" type="text" required maxlength="100"
            value="{{ old('name', $category->name ?? '') }}"
            class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('name') ? 'border-red-500 ring-1 ring-red-500' : 'border-[#E2E8F0]' }} text-sm focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB]">
        @error('name')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="description" class="block text-sm font-semibold text-[#0F172A] mb-1.5">Deskripsi <span class="text-xs font-normal text-[#64748B]">(opsional)</span></label>
        <textarea id="description" name="description" rows="3" maxlength="500"
            class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('description') ? 'border-red-500 ring-1 ring-red-500' : 'border-[#E2E8F0]' }} text-sm focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB]">{{ old('description', $category->description ?? '') }}</textarea>
        @error('description')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="dinas_name" class="block text-sm font-semibold text-[#0F172A] mb-1.5">Label Instansi <span class="text-xs font-normal text-[#64748B]">(opsional, legacy)</span></label>
        <input id="dinas_name" name="dinas_name" type="text" maxlength="150"
            value="{{ old('dinas_name', $category->dinas_name ?? '') }}"
            class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('dinas_name') ? 'border-red-500 ring-1 ring-red-500' : 'border-[#E2E8F0]' }} text-sm focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB]">
        @error('dinas_name')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        <p class="mt-1 text-[11px] text-[#94A3B8]">Label tampilan lama. <strong>Tidak digunakan untuk routing</strong> — tujuan Dinas/Unit ditentukan oleh <a href="{{ $isEdit ? route('super-admin.categories.mapping.edit', $category) : '#' }}" class="text-[#2563EB] hover:underline">mapping Kategori ↔ Dinas/Unit</a>.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <label for="sort_order" class="block text-sm font-semibold text-[#0F172A] mb-1.5">Urutan</label>
            <input id="sort_order" name="sort_order" type="number" min="0" max="65535"
                value="{{ old('sort_order', $category->sort_order ?? 0) }}"
                class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('sort_order') ? 'border-red-500 ring-1 ring-red-500' : 'border-[#E2E8F0]' }} text-sm focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB]">
            @error('sort_order')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="is_active" class="block text-sm font-semibold text-[#0F172A] mb-1.5">Status</label>
            <select id="is_active" name="is_active"
                class="w-full px-4 py-2.5 rounded-xl border border-[#E2E8F0] text-sm focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB]">
                <option value="1" @selected((string) old('is_active', $category->is_active ?? true) === '1')>Aktif</option>
                <option value="0" @selected((string) old('is_active', $category->is_active ?? true) === '0')>Nonaktif</option>
            </select>
            <p class="mt-1 text-[11px] text-[#94A3B8]">Kategori nonaktif tidak dapat dipilih untuk laporan baru; laporan lama tetap memakai kategori ini.</p>
        </div>
    </div>

    <div class="flex items-center gap-3 pt-2">
        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
            {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Kategori' }}
        </button>
        <a href="{{ route('super-admin.categories.index') }}" class="text-sm text-[#64748B] hover:underline">Batal</a>
    </div>
</form>
