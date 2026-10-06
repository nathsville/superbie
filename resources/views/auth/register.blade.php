@php $title = 'Daftar Akun — Lapor Pak Wali'; @endphp

<x-layouts.app :title="$title">
    <div class="min-h-screen flex flex-col justify-center items-center py-12 px-4 sm:px-6 lg:px-8 page-enter">
        <div class="max-w-md w-full">
            {{-- Logo --}}
            <div class="text-center mb-8">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-[#2563EB] to-[#0F766E] flex items-center justify-center shadow-md">
                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                </a>
                <h1 class="text-2xl font-bold text-[#0F172A]">Daftar Akun Masyarakat</h1>
                <p class="text-sm text-[#475569] mt-1">Daftar untuk mulai menyampaikan aspirasi dan pengaduan</p>
            </div>

            {{-- Card --}}
            <div class="bg-white rounded-2xl border border-[#E2E8F0] shadow-sm p-8 card-enter">
                <form method="POST" action="{{ route('register.store') }}" class="space-y-5" novalidate>
                    @csrf

                    {{-- Name --}}
                    <div>
                        <label for="name" class="block text-sm font-semibold text-[#0F172A] mb-1.5">
                            Nama Lengkap <span class="text-[#B91C1C]">*</span>
                        </label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="{{ old('name') }}"
                            required
                            autocomplete="name"
                            autofocus
                            aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                            placeholder="Nama sesuai KTP"
                            class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('name') ? 'border-[#B91C1C]' : 'border-[#E2E8F0]' }} text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB] ui-animated"
                        >
                        @error('name')
                            <p class="mt-1.5 text-xs text-[#B91C1C]">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Email --}}
                    <div>
                        <label for="email" class="block text-sm font-semibold text-[#0F172A] mb-1.5">
                            Alamat Email <span class="text-[#B91C1C]">*</span>
                        </label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            autocomplete="email"
                            aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                            placeholder="nama@email.com"
                            class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('email') ? 'border-[#B91C1C]' : 'border-[#E2E8F0]' }} text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB] ui-animated"
                        >
                        @error('email')
                            <p class="mt-1.5 text-xs text-[#B91C1C]">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- NIK --}}
                    <div>
                        <label for="nik" class="block text-sm font-semibold text-[#0F172A] mb-1.5">
                            NIK <span class="text-[#B91C1C]">*</span>
                        </label>
                        <input
                            type="text"
                            id="nik"
                            name="nik"
                            value="{{ old('nik') }}"
                            required
                            inputmode="numeric"
                            maxlength="16"
                            autocomplete="off"
                            aria-invalid="{{ $errors->has('nik') ? 'true' : 'false' }}"
                            placeholder="16 digit sesuai KTP"
                            class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('nik') ? 'border-[#B91C1C]' : 'border-[#E2E8F0]' }} text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB] ui-animated"
                        >
                        @error('nik')
                            <p class="mt-1.5 text-xs text-[#B91C1C]">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-[11px] text-[#94A3B8]">NIK tidak dapat diubah setelah akun dibuat.</p>
                    </div>

                    {{-- Phone --}}
                    <div>
                        <label for="phone_number" class="block text-sm font-semibold text-[#0F172A] mb-1.5">
                            Nomor HP <span class="text-[#B91C1C]">*</span>
                        </label>
                        <input
                            type="text"
                            id="phone_number"
                            name="phone_number"
                            value="{{ old('phone_number') }}"
                            required
                            inputmode="tel"
                            autocomplete="tel"
                            aria-invalid="{{ $errors->has('phone_number') ? 'true' : 'false' }}"
                            placeholder="08xxxxxxxxxx atau +62xxxxxxxxxx"
                            class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('phone_number') ? 'border-[#B91C1C]' : 'border-[#E2E8F0]' }} text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB] ui-animated"
                        >
                        @error('phone_number')
                            <p class="mt-1.5 text-xs text-[#B91C1C]">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Address --}}
                    <div>
                        <label for="address" class="block text-sm font-semibold text-[#0F172A] mb-1.5">
                            Alamat <span class="text-[#B91C1C]">*</span>
                        </label>
                        <textarea
                            id="address"
                            name="address"
                            required
                            rows="3"
                            autocomplete="street-address"
                            aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}"
                            placeholder="Alamat lengkap tempat tinggal"
                            class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('address') ? 'border-[#B91C1C]' : 'border-[#E2E8F0]' }} text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB] ui-animated"
                        >{{ old('address') }}</textarea>
                        @error('address')
                            <p class="mt-1.5 text-xs text-[#B91C1C]">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Password --}}
                    <div>
                        <label for="password" class="block text-sm font-semibold text-[#0F172A] mb-1.5">
                            Password <span class="text-[#B91C1C]">*</span>
                        </label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            autocomplete="new-password"
                            aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                            placeholder="Minimal 8 karakter"
                            class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('password') ? 'border-[#B91C1C]' : 'border-[#E2E8F0]' }} text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB] ui-animated"
                        >
                        @error('password')
                            <p class="mt-1.5 text-xs text-[#B91C1C]">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Password Confirmation --}}
                    <div>
                        <label for="password_confirmation" class="block text-sm font-semibold text-[#0F172A] mb-1.5">
                            Konfirmasi Password <span class="text-[#B91C1C]">*</span>
                        </label>
                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            required
                            autocomplete="new-password"
                            placeholder="Ulangi password"
                            class="w-full px-4 py-2.5 rounded-xl border border-[#E2E8F0] text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB] ui-animated"
                        >
                    </div>

                    {{-- Submit button --}}
                    <button
                        type="submit"
                        class="w-full bg-[#2563EB] hover:bg-[#1D4ED8] text-white font-semibold text-sm py-3 px-4 rounded-xl shadow-sm hover:shadow-md ui-animated active:scale-[0.98] flex items-center justify-center gap-2"
                    >
                        <span>Daftar Akun</span>
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </form>

                {{-- Login link --}}
                <div class="mt-6 pt-6 border-t border-[#E2E8F0] text-center">
                    <p class="text-sm text-[#475569]">
                        Sudah memiliki akun?
                        <a href="{{ route('login') }}" class="font-semibold text-[#2563EB] hover:underline ml-1">
                            Masuk di sini
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
