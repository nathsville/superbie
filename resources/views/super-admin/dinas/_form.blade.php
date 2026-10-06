@php
    $dinasUnit = $dinasUnit ?? null;
    $isEdit = $dinasUnit !== null;
@endphp
@php
    $action = $isEdit ? route('super-admin.dinas.update', $dinasUnit) : route('super-admin.dinas.store');
@endphp
<form method="POST" action="{{ $action }}" class="space-y-5 bg-white rounded-2xl border border-[#E2E8F0] p-6 shadow-sm card-enter">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div>
        <label for="name" class="block text-sm font-semibold text-[#0F172A] mb-1.5">Nama Dinas/Unit <span class="text-red-500">*</span></label>
        <input id="name" name="name" type="text" required maxlength="150"
            value="{{ old('name', $dinasUnit->name ?? '') }}"
            class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('name') ? 'border-red-500 ring-1 ring-red-500' : 'border-[#E2E8F0]' }} text-sm focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB]">
        @error('name')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="code" class="block text-sm font-semibold text-[#0F172A] mb-1.5">Kode <span class="text-xs font-normal text-[#64748B]">(opsional)</span></label>
        <input id="code" name="code" type="text" maxlength="50"
            value="{{ old('code', $dinasUnit->code ?? '') }}"
            class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('code') ? 'border-red-500 ring-1 ring-red-500' : 'border-[#E2E8F0]' }} text-sm focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB]">
        @error('code')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="description" class="block text-sm font-semibold text-[#0F172A] mb-1.5">Deskripsi <span class="text-xs font-normal text-[#64748B]">(opsional)</span></label>
        <textarea id="description" name="description" rows="3" maxlength="500"
            class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('description') ? 'border-red-500 ring-1 ring-red-500' : 'border-[#E2E8F0]' }} text-sm focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB]">{{ old('description', $dinasUnit->description ?? '') }}</textarea>
        @error('description')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <label for="sort_order" class="block text-sm font-semibold text-[#0F172A] mb-1.5">Urutan</label>
            <input id="sort_order" name="sort_order" type="number" min="0" max="65535"
                value="{{ old('sort_order', $dinasUnit->sort_order ?? 0) }}"
                class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('sort_order') ? 'border-red-500 ring-1 ring-red-500' : 'border-[#E2E8F0]' }} text-sm focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB]">
            @error('sort_order')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="is_active" class="block text-sm font-semibold text-[#0F172A] mb-1.5">Status</label>
            <select id="is_active" name="is_active"
                class="w-full px-4 py-2.5 rounded-xl border border-[#E2E8F0] text-sm focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB]">
                <option value="1" @selected((string) old('is_active', $dinasUnit->is_active ?? true) === '1')>Aktif</option>
                <option value="0" @selected((string) old('is_active', $dinasUnit->is_active ?? true) === '0')>Nonaktif</option>
            </select>
            <p class="mt-1 text-[11px] text-[#94A3B8]">Dinas/Unit nonaktif tidak dapat dipilih sebagai tujuan penugasan baru, tetapi data historis tetap tampil.</p>
        </div>
    </div>

    <div class="flex items-center gap-3 pt-2">
        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
            {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Dinas/Unit' }}
        </button>
        <a href="{{ route('super-admin.dinas.index') }}" class="text-sm text-[#64748B] hover:underline">Batal</a>
    </div>
</form>
