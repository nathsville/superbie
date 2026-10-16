@php
    $kelurahan = $kelurahan ?? null;
    $isEdit = $kelurahan !== null;
@endphp
@php
    $action = $isEdit ? route('super-admin.kelurahan.update', $kelurahan) : route('super-admin.kelurahan.store');
    $inputBase = 'w-full px-4 py-2.5 rounded-xl border text-sm bg-white transition focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated';
@endphp
<form method="POST" action="{{ $action }}" class="w-full space-y-5 bg-white rounded-2xl border border-line p-6 shadow-sm card-enter">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    {{-- Grid 2 kolom: baris 1 = Kecamatan | Nama, baris 2 = Kode | Catatan --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 items-stretch">
        {{-- Baris 1, Kolom 1: Kecamatan Induk --}}
        <div>
            <label for="kecamatan_id" class="block text-sm font-semibold text-ink mb-1.5">Kecamatan Induk <span class="text-red-500">*</span></label>
            <select id="kecamatan_id" name="kecamatan_id" required
                class="{{ $inputBase }} {{ $errors->has('kecamatan_id') ? 'border-red-500 ring-1 ring-red-500' : 'border-line' }}">
                <option value="">-- Pilih Kecamatan --</option>
                @foreach ($kecamatanList as $kec)
                    <option value="{{ $kec->id }}" @selected((string) old('kecamatan_id', $kelurahan->kecamatan_id ?? '') === (string) $kec->id)>{{ $kec->name }}</option>
                @endforeach
            </select>
            @error('kecamatan_id')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
            @if ($kecamatanList->isEmpty())
                <p class="mt-1.5 text-[11px] text-ink-faint">Belum ada Kecamatan. Tambahkan Kecamatan terlebih dahulu.</p>
            @endif
        </div>

        {{-- Baris 1, Kolom 2: Nama Kelurahan --}}
        <div>
            <label for="name" class="block text-sm font-semibold text-ink mb-1.5">Nama Kelurahan <span class="text-red-500">*</span></label>
            <input id="name" name="name" type="text" required maxlength="150"
                value="{{ old('name', $kelurahan->name ?? '') }}"
                placeholder="Contoh: Labukkang"
                class="{{ $inputBase }} {{ $errors->has('name') ? 'border-red-500 ring-1 ring-red-500' : 'border-line' }}">
            @error('name')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        {{-- Baris 2, Kolom 1: Kode Kelurahan --}}
        <div>
            <label for="code" class="block text-sm font-semibold text-ink mb-1.5">Kode Kelurahan <span class="text-xs font-normal text-ink-faint">(opsional, mis. kode wilayah BPS)</span></label>
            <input id="code" name="code" type="text" maxlength="50"
                value="{{ old('code', $kelurahan->code ?? '') }}"
                placeholder="Contoh: 73.72.03.1007"
                class="{{ $inputBase }} font-mono {{ $errors->has('code') ? 'border-red-500 ring-1 ring-red-500' : 'border-line' }}">
            @error('code')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        {{-- Baris 2, Kolom 2: Kartu Catatan / Petunjuk (tinggi seimbang) --}}
        <div class="h-full rounded-xl border border-dashed border-line-strong bg-page p-4 flex flex-col justify-center">
            <div class="flex items-center gap-2 mb-1.5">
                <svg class="w-4 h-4 text-ink-faint" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-xs font-semibold text-ink-soft uppercase tracking-wider">Catatan / Petunjuk</p>
            </div>
            <p class="text-xs text-ink-faint leading-relaxed">
                Pastikan <strong class="text-ink-soft">Kecamatan induk</strong> dan <strong class="text-ink-soft">nama Kelurahan</strong>
                sudah sesuai dengan data wilayah resmi. Kode Kelurahan bersifat opsional dan mengikuti format kode wilayah BPS.
            </p>
        </div>
    </div>

    {{-- Action toolbar — bottom right --}}
    <div class="flex items-center justify-end gap-2 pt-4 border-t border-surface-sunken">
        <a href="{{ route('super-admin.kelurahan.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-white hover:bg-surface-sunken text-ink-soft text-sm font-semibold rounded-xl border border-line ui-animated">
            Batal
        </a>
        <button type="submit"
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Kelurahan' }}
        </button>
    </div>
</form>
