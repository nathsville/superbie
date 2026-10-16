@php
    $kecamatan = $kecamatan ?? null;
    $isEdit = $kecamatan !== null;
@endphp
@php
    $action = $isEdit ? route('super-admin.kecamatan.update', $kecamatan) : route('super-admin.kecamatan.store');
    $inputBase = 'w-full px-4 py-2.5 rounded-xl border text-sm bg-white transition focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated';
@endphp
<form method="POST" action="{{ $action }}" class="w-full space-y-5 bg-white rounded-2xl border border-line p-6 shadow-sm card-enter">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    {{-- Baris 1 (2 kolom): Nama & Kode --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <label for="name" class="block text-sm font-semibold text-ink mb-1.5">Nama Kecamatan <span class="text-red-500">*</span></label>
            <input id="name" name="name" type="text" required maxlength="150"
                value="{{ old('name', $kecamatan->name ?? '') }}"
                placeholder="Contoh: Bacukiki Barat"
                aria-describedby="name-hint"
                class="{{ $inputBase }} {{ $errors->has('name') ? 'border-red-500 ring-1 ring-red-500' : 'border-line' }}">
            <p id="name-hint" class="mt-1.5 text-[11px] text-ink-faint">Gunakan nama resmi sesuai data wilayah, tanpa singkatan.</p>
            @error('name')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="code" class="block text-sm font-semibold text-ink mb-1.5">Kode <span class="text-xs font-normal text-ink-faint">(opsional, mis. kode wilayah BPS)</span></label>
            <input id="code" name="code" type="text" maxlength="50"
                value="{{ old('code', $kecamatan->code ?? '') }}"
                placeholder="Contoh: 73.72.01"
                aria-describedby="code-hint"
                class="{{ $inputBase }} font-mono {{ $errors->has('code') ? 'border-red-500 ring-1 ring-red-500' : 'border-line' }}">
            <p id="code-hint" class="mt-1.5 text-[11px] text-ink-faint">Opsional. Isi kode wilayah BPS dengan format <span class="font-mono">73.72.01</span>.</p>
            @error('code')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    {{-- Action toolbar — bottom right --}}
    <div class="flex items-center justify-end gap-2 pt-4 border-t border-surface-sunken">
        <a href="{{ route('super-admin.kecamatan.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-white hover:bg-surface-sunken text-ink-soft text-sm font-semibold rounded-xl border border-line ui-animated">
            Batal
        </a>
        <button type="submit"
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Kecamatan' }}
        </button>
    </div>
</form>
