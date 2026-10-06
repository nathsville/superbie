@php
    $title = 'Profil Saya';
@endphp

<x-layouts.dashboard :title="$title">
    <x-slot:navigation>
        @php $route = request()->routeIs('citizen.*') ? request()->route()->getName() : ''; @endphp
        <div class="space-y-1">
            <a href="{{ route('citizen.dashboard') }}"
               class="nav-link {{ $route === 'citizen.dashboard' ? 'active' : '' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>
            <a href="{{ route('citizen.complaint.create') }}"
               class="nav-link {{ $route === 'citizen.complaint.create' ? 'active' : '' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Buat Laporan
            </a>
            <a href="{{ route('citizen.history') }}"
               class="nav-link {{ $route === 'citizen.history' ? 'active' : '' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Riwayat Laporan
            </a>
            <a href="{{ route('citizen.profile.edit') }}"
               class="nav-link {{ $route === 'citizen.profile.edit' ? 'active' : '' }}"
               aria-current="page">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                Profil Saya
            </a>
        </div>
    </x-slot:navigation>

    <x-slot:header>Profil Akun</x-slot:header>
    <x-slot:breadcrumb>Lapor Pak Wali — Kelola data diri dan keamanan akun Anda</x-slot:breadcrumb>

    <div class="max-w-3xl mx-auto space-y-6 card-enter">
        {{-- Profile Header Card --}}
        <div class="bg-white rounded-2xl border border-[#E2E8F0] p-6 shadow-sm flex items-center gap-5">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-[#2563EB] to-[#0F766E] flex items-center justify-center text-white text-2xl font-bold shrink-0 shadow-md">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-lg font-bold text-[#0F172A]">{{ $user->name }}</h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                        Masyarakat Terdaftar
                    </span>
                </div>
                <p class="text-xs text-[#64748B] mt-1">{{ $user->email }}</p>
                <p class="text-[11px] text-[#94A3B8] mt-0.5">
                    Bergabung sejak {{ $user->created_at?->translatedFormat('d F Y') ?? '—' }}
                </p>
            </div>
        </div>

        <form
            action="{{ route('citizen.profile.update') }}"
            method="POST"
            x-data="{ saving: false }"
            @submit="saving = true"
            class="bg-white rounded-2xl border border-[#E2E8F0] shadow-sm overflow-hidden"
        >
            @csrf
            @method('PATCH')

            <div class="p-6 sm:p-8 space-y-6">
                <div>
                    <h2 class="text-sm font-bold text-[#0F172A] uppercase tracking-wider mb-1">Informasi Dasar</h2>
                    <p class="text-xs text-[#64748B]">Perbarui nama dan kontak untuk mempermudah koordinasi tindak lanjut.</p>
                </div>

                {{-- Name --}}
                <div>
                    <label for="name" class="block text-sm font-semibold text-[#0F172A] mb-1.5">
                        Nama Lengkap <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="{{ old('name', $user->name) }}"
                        required
                        maxlength="255"
                        class="w-full rounded-xl border {{ $errors->has('name') ? 'border-red-500 ring-1 ring-red-500' : 'border-[#CBD5E1]' }} bg-white px-4 py-3 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#2563EB] focus:border-transparent transition-all"
                    >
                    @error('name')
                        <p class="text-xs text-red-600 mt-1.5 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Email (Read-only) --}}
                <div>
                    <label for="email" class="block text-sm font-semibold text-[#0F172A] mb-1.5">
                        Alamat Email <span class="text-xs font-normal text-[#64748B]">(Identitas Akun)</span>
                    </label>
                    <div class="relative">
                        <input
                            type="email"
                            id="email"
                            value="{{ $user->email }}"
                            disabled
                            class="w-full rounded-xl border border-[#E2E8F0] bg-[#F1F5F9] px-4 py-3 text-sm text-[#64748B] cursor-not-allowed select-none"
                        >
                        <div class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-emerald-600 text-xs font-semibold gap-1">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Terverifikasi
                        </div>
                    </div>
                    <p class="text-[11px] text-[#94A3B8] mt-1.5">Alamat email digunakan sebagai kredensial login utama dan tidak dapat diubah secara langsung.</p>
                </div>

                {{-- NIK (read-only, full display, immutable) --}}
                <div>
                    <label for="nik" class="block text-sm font-semibold text-[#0F172A] mb-1.5">
                        NIK <span class="text-xs font-normal text-[#64748B]">(Identitas Akun)</span>
                    </label>
                    <input
                        type="text"
                        id="nik"
                        value="{{ $user->nik }}"
                        disabled
                        class="w-full rounded-xl border border-[#E2E8F0] bg-[#F1F5F9] px-4 py-3 text-sm text-[#64748B] cursor-not-allowed select-none tracking-wider"
                    >
                    <p class="text-[11px] text-[#94A3B8] mt-1.5">NIK ditampilkan penuh dan tidak dapat diubah setelah akun dibuat.</p>
                </div>

                {{-- Phone (editable) --}}
                <div>
                    <label for="phone_number" class="block text-sm font-semibold text-[#0F172A] mb-1.5">
                        Nomor HP <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="phone_number"
                        name="phone_number"
                        value="{{ old('phone_number', $user->phone_number) }}"
                        required
                        inputmode="tel"
                        autocomplete="tel"
                        class="w-full rounded-xl border {{ $errors->has('phone_number') ? 'border-red-500 ring-1 ring-red-500' : 'border-[#CBD5E1]' }} bg-white px-4 py-3 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#2563EB] focus:border-transparent transition-all"
                    >
                    @error('phone_number')
                        <p class="text-xs text-red-600 mt-1.5 font-medium">{{ $message }}</p>
                    @enderror
                    <p class="text-[11px] text-[#94A3B8] mt-1.5">Format lokal (08xxxxxxxxxx) atau internasional (+62xxxxxxxxxx).</p>
                </div>

                {{-- Address (editable, free text) --}}
                <div>
                    <label for="address" class="block text-sm font-semibold text-[#0F172A] mb-1.5">
                        Alamat <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        id="address"
                        name="address"
                        required
                        rows="3"
                        autocomplete="street-address"
                        class="w-full rounded-xl border {{ $errors->has('address') ? 'border-red-500 ring-1 ring-red-500' : 'border-[#CBD5E1]' }} bg-white px-4 py-3 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#2563EB] focus:border-transparent transition-all"
                    >{{ old('address', $user->address) }}</textarea>
                    @error('address')
                        <p class="text-xs text-red-600 mt-1.5 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pt-6 border-t border-[#E2E8F0]">
                    <h2 class="text-sm font-bold text-[#0F172A] uppercase tracking-wider mb-1">Keamanan & Kata Sandi</h2>
                    <p class="text-xs text-[#64748B]">Kosongkan bagian ini jika Anda tidak ingin mengubah kata sandi.</p>
                </div>

                {{-- Current Password --}}
                <div>
                    <label for="current_password" class="block text-sm font-semibold text-[#0F172A] mb-1.5">
                        Kata Sandi Saat Ini
                    </label>
                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        placeholder="Masukkan kata sandi saat ini jika ingin mengubah"
                        class="w-full rounded-xl border {{ $errors->has('current_password') ? 'border-red-500 ring-1 ring-red-500' : 'border-[#CBD5E1]' }} bg-white px-4 py-3 text-sm text-[#0F172A] placeholder-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#2563EB] focus:border-transparent transition-all"
                    >
                    @error('current_password')
                        <p class="text-xs text-red-600 mt-1.5 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                {{-- New Password & Confirmation --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="password" class="block text-sm font-semibold text-[#0F172A] mb-1.5">
                            Kata Sandi Baru
                        </label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            minlength="8"
                            placeholder="Minimal 8 karakter"
                            class="w-full rounded-xl border {{ $errors->has('password') ? 'border-red-500 ring-1 ring-red-500' : 'border-[#CBD5E1]' }} bg-white px-4 py-3 text-sm text-[#0F172A] placeholder-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#2563EB] focus:border-transparent transition-all"
                        >
                        @error('password')
                            <p class="text-xs text-red-600 mt-1.5 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-semibold text-[#0F172A] mb-1.5">
                            Konfirmasi Kata Sandi Baru
                        </label>
                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            minlength="8"
                            placeholder="Ulangi kata sandi baru"
                            class="w-full rounded-xl border border-[#CBD5E1] bg-white px-4 py-3 text-sm text-[#0F172A] placeholder-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#2563EB] focus:border-transparent transition-all"
                        >
                    </div>
                </div>
            </div>

            {{-- Form Footer --}}
            <div class="px-6 sm:px-8 py-5 bg-[#F8FAFC] border-t border-[#E2E8F0] flex items-center justify-between">
                <a
                    href="{{ route('citizen.dashboard') }}"
                    class="text-sm font-semibold text-[#475569] hover:text-[#0F172A] px-4 py-2.5 rounded-xl border border-[#CBD5E1] bg-white hover:bg-[#F1F5F9] ui-animated"
                >
                    Kembali
                </a>

                <button
                    type="submit"
                    :disabled="saving"
                    class="inline-flex items-center gap-2 bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-sm font-semibold px-6 py-2.5 rounded-xl ui-animated shadow-sm hover:shadow active:scale-[0.98] disabled:opacity-60 disabled:cursor-not-allowed"
                >
                    <svg
                        x-show="saving"
                        class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span x-text="saving ? 'Menyimpan...' : 'Simpan Perubahan'">Simpan Perubahan</span>
                </button>
            </div>
        </form>
    </div>
</x-layouts.dashboard>
