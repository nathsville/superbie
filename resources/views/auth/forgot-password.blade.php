@php $title = 'Lupa Password — Lapor Pak Wali'; @endphp

<x-layouts.app :title="$title">
    <div class="min-h-screen flex flex-col justify-center items-center py-12 px-4 sm:px-6 lg:px-8 page-enter">
        <div class="max-w-md w-full">
            <div class="text-center mb-8">
                <a href="{{ route('home') }}" class="inline-block" aria-label="SuperBie - beranda">
                    <img src="/brand/superbie-logo.svg" alt="SuperBie" class="h-10 w-auto sm:h-11">
                </a>
                <h1 class="text-2xl font-bold text-ink">Reset Password</h1>
                <p class="text-sm text-ink-soft mt-1">Masukkan email terdaftar Anda untuk menerima tautan reset password</p>
            </div>

            <div class="bg-white rounded-2xl border border-line shadow-sm p-8 card-enter">
                @if (session('status'))
                    <div class="mb-5 p-4 rounded-xl bg-primary-50 border border-primary-200 text-sm text-primary-800">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('password.email') }}" class="space-y-5" novalidate>
                    @csrf
                    <div>
                        <label for="email" class="block text-sm font-semibold text-ink mb-1.5">
                            Alamat Email <span class="text-error">*</span>
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
                            class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('email') ? 'border-error' : 'border-line' }} text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-600/20 focus:border-primary-600 ui-animated"
                        >
                        @error('email')
                            <p class="mt-1.5 text-xs text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <button
                        type="submit"
                        class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold text-sm py-3 px-4 rounded-xl shadow-sm hover:shadow-md ui-animated active:scale-[0.98]"
                    >
                        Kirim Tautan Reset Password
                    </button>
                </form>

                <div class="mt-6 pt-6 border-t border-line text-center">
                    <a href="{{ route('login') }}" class="text-sm text-primary-600 hover:underline font-medium">
                        ← Kembali ke halaman login
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
