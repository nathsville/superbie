@php
    $title = 'Ubah Konfigurasi — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
    $inputBase = 'w-full px-4 py-2.5 rounded-xl border text-sm bg-white transition focus:outline-none focus:ring-2 focus:ring-primary-600/25 focus:border-primary-600 ui-animated';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Ubah Konfigurasi</x-slot:header>
    <x-slot:breadcrumb>Konfigurasi</x-slot:breadcrumb>

    <div class="w-full max-w-full card-enter">
        <form method="POST" action="{{ route('super-admin.config.update', $key) }}"
              class="w-full space-y-5 bg-white rounded-2xl border border-line p-6 shadow-sm">

            @csrf
            @method('PUT')

            {{-- Baris 1 (full width): Header informasi konfigurasi --}}
            <div class="rounded-2xl bg-page border border-line p-4 sm:p-5">
                <div class="flex items-start gap-3">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-primary-50 text-primary-600 shrink-0" aria-hidden="true">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </span>
                    <div class="min-w-0">
                        <span class="block text-base font-bold text-ink">{{ $setting['label'] }}</span>
                        <span class="mt-1 inline-flex font-mono text-[11px] text-ink-soft bg-white px-2 py-0.5 rounded-md border border-line">{{ $setting['key'] }}</span>
                        <p class="text-sm text-ink-faint mt-2 leading-relaxed">{{ $setting['description'] }}</p>
                    </div>
                </div>
            </div>

            {{-- Baris 2 (2 kolom): Input nilai | Kartu catatan default --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 items-stretch">
                {{-- Kolom kiri: input nilai --}}
                <div>
                    <label for="value" class="block text-sm font-semibold text-ink mb-1.5">
                        Nilai <span class="text-red-500">*</span>
                    </label>
                    <input id="value" name="value" type="number" min="1" step="1" required
                        value="{{ old('value', $setting['effective']) }}"
                        class="{{ $inputBase }} {{ $errors->has('value') ? 'border-red-500 ring-1 ring-red-500' : 'border-line' }}">
                    @error('value')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                    <p class="mt-1.5 text-[11px] text-ink-faint">Masukkan bilangan bulat positif. Nilai ini menimpa default aplikasi.</p>
                </div>

                {{-- Kolom kanan: kartu catatan / informasi default (tinggi seimbang) --}}
                <div class="h-full rounded-xl border border-dashed border-line-strong bg-page p-4 flex flex-col justify-center">
                    <div class="flex items-center gap-2 mb-2">
                        <svg class="w-4 h-4 text-ink-faint" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs font-semibold text-ink-soft uppercase tracking-wider">Catatan / Informasi Default</p>
                    </div>
                    <ul class="text-xs text-ink-faint leading-relaxed space-y-1.5">
                        <li>
                            Nilai default: <strong class="text-ink-soft">{{ $setting['default'] }}</strong>
                            (dari <span class="font-mono text-ink-soft">config/business_rules.php</span>).
                        </li>
                        @if ($setting['is_overridden'])
                            <li>Nilai aktif saat ini: <strong class="text-ink-soft">{{ $setting['effective'] }}</strong> (menimpa default).</li>
                        @else
                            <li>Belum ada nilai override; aplikasi memakai default.</li>
                        @endif
                    </ul>
                </div>
            </div>

            {{-- Action toolbar — bottom right --}}
            <div class="flex items-center justify-end gap-2 pt-4 border-t border-surface-sunken">
                <a href="{{ route('super-admin.config.index') }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-white hover:bg-surface-sunken text-ink-soft text-sm font-semibold rounded-xl border border-line ui-animated">
                    Batal
                </a>
                <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</x-layouts.dashboard>
