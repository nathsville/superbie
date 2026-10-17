@php
    $store = $store ?? null;
    $isEdit = $store !== null;
    $action = $isEdit ? route('super-admin.market-stores.update', $store) : route('super-admin.market-stores.store');
    $inputBase = 'w-full px-4 py-2.5 rounded-xl border text-sm bg-white transition focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated';
    $maxKb = (int) config('siphp.store_photo.max_kilobytes');
@endphp
<form method="POST" action="{{ $action }}" enctype="multipart/form-data"
      class="w-full space-y-5 bg-white rounded-2xl border border-line p-6 shadow-sm card-enter">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        {{-- Nama Toko --}}
        <div>
            <label for="name" class="block text-sm font-semibold text-ink mb-1.5">Nama Toko <span class="text-red-500">*</span></label>
            <input id="name" name="name" type="text" required maxlength="150"
                value="{{ old('name', $store->name ?? '') }}"
                placeholder="Contoh: Toko Sinar Jaya"
                class="{{ $inputBase }} {{ $errors->has('name') ? 'border-red-500 ring-1 ring-red-500' : 'border-line' }}">
            @error('name')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        {{-- Jenis Toko --}}
        <div>
            <label for="type" class="block text-sm font-semibold text-ink mb-1.5">Jenis Toko <span class="text-xs font-normal text-ink-faint">(opsional)</span></label>
            <input id="type" name="type" type="text" maxlength="100"
                value="{{ old('type', $store->type ?? '') }}"
                placeholder="Contoh: Sembako / Kelontong"
                class="{{ $inputBase }} {{ $errors->has('type') ? 'border-red-500 ring-1 ring-red-500' : 'border-line' }}">
            @error('type')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        {{-- Pasar --}}
        <div>
            <label for="market_id" class="block text-sm font-semibold text-ink mb-1.5">Pasar <span class="text-xs font-normal text-ink-faint">(opsional)</span></label>
            <select id="market_id" name="market_id"
                class="{{ $inputBase }} {{ $errors->has('market_id') ? 'border-red-500 ring-1 ring-red-500' : 'border-line' }}">
                <option value="">-- Tidak terikat pasar --</option>
                @foreach ($markets as $market)
                    <option value="{{ $market->id }}" @selected((string) old('market_id', $store->market_id ?? '') === (string) $market->id)>{{ $market->name }}</option>
                @endforeach
            </select>
            @error('market_id')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
            <p class="mt-1.5 text-[11px] text-ink-faint">Toko boleh berdiri sendiri tanpa terikat pasar.</p>
        </div>

        {{-- Alamat --}}
        <div class="md:col-span-2">
            <label for="address" class="block text-sm font-semibold text-ink mb-1.5">Alamat <span class="text-xs font-normal text-ink-faint">(opsional)</span></label>
            <input id="address" name="address" type="text" maxlength="255"
                value="{{ old('address', $store->address ?? '') }}"
                placeholder="Contoh: Jl. Bau Massepe No. 12"
                class="{{ $inputBase }} {{ $errors->has('address') ? 'border-red-500 ring-1 ring-red-500' : 'border-line' }}">
            @error('address')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        {{-- Telepon --}}
        <div>
            <label for="phone" class="block text-sm font-semibold text-ink mb-1.5">Telepon <span class="text-xs font-normal text-ink-faint">(opsional)</span></label>
            <input id="phone" name="phone" type="text" maxlength="30"
                value="{{ old('phone', $store->phone ?? '') }}"
                placeholder="Contoh: 0812xxxxxxx"
                class="{{ $inputBase }} {{ $errors->has('phone') ? 'border-red-500 ring-1 ring-red-500' : 'border-line' }}">
            @error('phone')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    {{-- Jam Buka --}}
    <div>
        <label for="opening_hours" class="block text-sm font-semibold text-ink mb-1.5">Jam Buka <span class="text-xs font-normal text-ink-faint">(opsional)</span></label>
        <input id="opening_hours" name="opening_hours" type="text" maxlength="100"
            value="{{ old('opening_hours', $store->opening_hours ?? '') }}"
            placeholder="Contoh: 07.00 – 21.00 WITA"
            class="{{ $inputBase }} max-w-md {{ $errors->has('opening_hours') ? 'border-red-500 ring-1 ring-red-500' : 'border-line' }}">
        @error('opening_hours')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    {{-- Foto Toko --}}
    <div class="rounded-xl border border-dashed border-line-strong bg-page p-4 space-y-3">
        <div class="flex items-start gap-4 flex-wrap">
            @if ($isEdit && $store->photoUrl())
                <img src="{{ $store->photoUrl() }}" alt="Foto saat ini {{ $store->name }}"
                     class="w-20 h-20 rounded-xl object-cover border border-line shrink-0">
            @endif

            <div class="flex-1 min-w-[16rem]">
                <label for="photo" class="block text-sm font-semibold text-ink mb-1.5">Foto Toko <span class="text-xs font-normal text-ink-faint">(opsional)</span></label>
                <input id="photo" name="photo" type="file" accept="image/jpeg,image/png"
                    class="w-full text-sm text-ink-soft file:mr-3 file:px-4 file:py-2.5 file:rounded-xl file:border-0 file:bg-primary-50 file:text-primary-700 file:text-sm file:font-semibold hover:file:bg-primary-100 cursor-pointer">
                @error('photo')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                <p class="mt-1.5 text-[11px] text-ink-faint">
                    Format JPG/PNG, maksimal {{ number_format($maxKb / 1024, 1) }} MB. Foto tampil publik di halaman /siphp.
                </p>

                @if ($isEdit && $store->photo_path)
                    <label for="remove_photo" class="flex items-center gap-2 mt-3 cursor-pointer">
                        <input id="remove_photo" name="remove_photo" type="checkbox" value="1"
                            @checked(old('remove_photo'))
                            class="w-4 h-4 rounded border-line text-red-600 focus:ring-red-500/25">
                        <span class="text-xs text-ink-soft">Hapus foto saat ini (tanpa menggantinya)</span>
                    </label>
                @endif
            </div>
        </div>
    </div>

    {{-- Status aktif --}}
    <div class="rounded-xl border border-dashed border-line-strong bg-page p-4">
        <label for="is_active" class="flex items-start gap-3 cursor-pointer">
            <input id="is_active" name="is_active" type="checkbox" value="1"
                @checked(old('is_active', $store->is_active ?? true))
                class="mt-0.5 w-4 h-4 rounded border-line text-primary-600 focus:ring-primary-600/25">
            <span>
                <span class="block text-sm font-semibold text-ink">Tampilkan di halaman publik /siphp</span>
                <span class="block text-xs text-ink-faint mt-0.5">Toko nonaktif disembunyikan dari publik tanpa menghapus datanya.</span>
            </span>
        </label>
    </div>

    {{-- Actions --}}
    <div class="flex items-center justify-between gap-2 pt-4 border-t border-surface-sunken">
        @if ($isEdit)
            {{-- Lives outside the main form (HTML forbids nesting); the button
                 references the hidden delete form via the `form` attribute. --}}
            <button type="submit" form="delete-store-{{ $store->id }}"
                onclick="return confirm({{ \Illuminate\Support\Js::from('Hapus toko "' . $store->name . '"? Tindakan ini tidak dapat dibatalkan.') }})"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-red-50 hover:bg-red-100 text-red-700 text-sm font-semibold rounded-xl border border-red-200 ui-animated">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Hapus
            </button>
        @else
            <span></span>
        @endif

        <div class="flex items-center gap-2">
            <a href="{{ route('super-admin.market-stores.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-white hover:bg-surface-sunken text-ink-soft text-sm font-semibold rounded-xl border border-line ui-animated">
                Batal
            </a>
            <button type="submit"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Toko' }}
            </button>
        </div>
    </div>
</form>

@if ($isEdit)
    {{-- Hidden delete form referenced by the button above (never nested). --}}
    <form id="delete-store-{{ $store->id }}" method="POST"
          action="{{ route('super-admin.market-stores.destroy', $store) }}" class="hidden">
        @csrf
        @method('DELETE')
    </form>
@endif
