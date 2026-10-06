@php
    $title = 'Ubah Konfigurasi — Super Admin';
    $currentRoute = request()->routeIs('super-admin.*') ? request()->route()->getName() : '';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @include('super-admin.partials.nav', ['currentRoute' => $currentRoute])
    </x-slot:navigation>

    <x-slot:header>Ubah Konfigurasi</x-slot:header>
    <x-slot:breadcrumb>Konfigurasi</x-slot:breadcrumb>

    <div class="max-w-2xl">
        <form method="POST" action="{{ route('super-admin.config.update', $key) }}"
              class="space-y-5 bg-white rounded-2xl border border-[#E2E8F0] p-6 shadow-sm card-enter">
            @csrf
            @method('PUT')

            <div>
                <span class="block text-sm font-semibold text-[#0F172A]">{{ $setting['label'] }}</span>
                <span class="block font-mono text-xs text-[#94A3B8] mt-0.5">{{ $setting['key'] }}</span>
                <span class="block text-xs text-[#64748B] mt-1">{{ $setting['description'] }}</span>
            </div>

            <div>
                <label for="value" class="block text-sm font-semibold text-[#0F172A] mb-1.5">
                    Nilai <span class="text-red-500">*</span>
                </label>
                <input id="value" name="value" type="number" min="1" step="1" required
                    value="{{ old('value', $setting['effective']) }}"
                    class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('value') ? 'border-red-500 ring-1 ring-red-500' : 'border-[#E2E8F0]' }} text-sm focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB]">
                @error('value')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="text-xs text-[#64748B] bg-[#F8FAFC] border border-[#E2E8F0] rounded-xl p-3 space-y-1">
                <p>Nilai default: <strong>{{ $setting['default'] }}</strong> (dari <span class="font-mono">config/business_rules.php</span>).</p>
                @if ($setting['is_overridden'])
                    <p>Nilai aktif saat ini: <strong>{{ $setting['effective'] }}</strong> (menimpa default).</p>
                @else
                    <p>Belum ada nilai override; aplikasi memakai default.</p>
                @endif
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-sm font-semibold rounded-xl ui-animated shadow-sm">
                    Simpan Perubahan
                </button>
                <a href="{{ route('super-admin.config.index') }}" class="text-sm text-[#64748B] hover:underline">Batal</a>
            </div>
        </form>
    </div>
</x-layouts.dashboard>
