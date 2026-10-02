@php $title = 'Lupa Password — Lapor Pak Wali'; @endphp

<x-layouts.app :title="$title">
    <div class="min-h-screen flex flex-col justify-center items-center py-12 px-4 sm:px-6 lg:px-8 page-enter">
        <div class="max-w-md w-full">
            <div class="text-center mb-8">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-[#2563EB] to-[#0F766E] flex items-center justify-center shadow-md">
                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                </a>
                <h1 class="text-2xl font-bold text-[#0F172A]">Reset Password</h1>
                <p class="text-sm text-[#475569] mt-1">Masukkan email terdaftar Anda untuk menerima tautan reset password</p>
            </div>

            <div class="bg-white rounded-2xl border border-[#E2E8F0] shadow-sm p-8 card-enter">
                @if (session('status'))
                    <div class="mb-5 p-4 rounded-xl bg-blue-50 border border-blue-200 text-sm text-blue-800">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('password.email') }}" class="space-y-5" novalidate>
                    @csrf
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
                            autofocus
                            placeholder="nama@email.com"
                            class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('email') ? 'border-[#B91C1C]' : 'border-[#E2E8F0]' }} text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB] ui-animated"
                        >
                        @error('email')
                            <p class="mt-1.5 text-xs text-[#B91C1C]">{{ $message }}</p>
                        @enderror
                    </div>

                    <button
                        type="submit"
                        class="w-full bg-[#2563EB] hover:bg-[#1D4ED8] text-white font-semibold text-sm py-3 px-4 rounded-xl shadow-sm hover:shadow-md ui-animated active:scale-[0.98]"
                    >
                        Kirim Tautan Reset Password
                    </button>
                </form>

                <div class="mt-6 pt-6 border-t border-[#E2E8F0] text-center">
                    <a href="{{ route('login') }}" class="text-sm text-[#2563EB] hover:underline font-medium">
                        ← Kembali ke halaman login
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
