@php $title = 'Masuk — Lapor Pak Wali'; @endphp

<x-layouts.app :title="$title">
    <div class="min-h-screen flex flex-col justify-center items-center py-12 px-4 sm:px-6 lg:px-8 page-enter">
        <div class="max-w-md w-full">
            {{-- Logo --}}
            <div class="text-center mb-8">
                <a href="{{ route('home') }}" class="inline-block" aria-label="SuperBie - beranda">
                    <img src="/brand/superbie-logo.svg" alt="SuperBie" class="h-10 w-auto sm:h-11">
                </a>
                <h1 class="text-2xl font-bold text-ink">Masuk ke Akun Anda</h1>
                <p class="text-sm text-ink-soft mt-1">Lapor Pak Wali &middot; Kota Parepare</p>
            </div>

            {{-- Card --}}
            <div class="bg-white rounded-2xl border border-line shadow-sm p-8 card-enter">
                @if (session('status'))
                    <div class="mb-4 p-3 rounded-xl bg-primary-50 border border-primary-200 text-xs text-primary-800">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="space-y-5" novalidate>
                    @csrf

                    {{-- Email --}}
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
                            aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                            aria-describedby="{{ $errors->has('email') ? 'email-error' : '' }}"
                            placeholder="nama@email.com"
                            class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('email') ? 'border-error' : 'border-line' }} text-sm text-ink placeholder-ink-soft/50 focus:outline-none focus:ring-2 focus:ring-primary-600/20 focus:border-primary-600 ui-animated"
                        >
                        @error('email')
                            <p id="email-error" class="mt-1.5 text-xs text-error flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Password --}}
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="block text-sm font-semibold text-ink">
                                Password <span class="text-error">*</span>
                            </label>
                            <a href="{{ route('password.request') }}" class="text-xs text-primary-600 hover:underline">
                                Lupa password?
                            </a>
                        </div>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                            aria-describedby="{{ $errors->has('password') ? 'password-error' : '' }}"
                            placeholder="Masukkan password Anda"
                            class="w-full px-4 py-2.5 rounded-xl border {{ $errors->has('password') ? 'border-error' : 'border-line' }} text-sm text-ink placeholder-ink-soft/50 focus:outline-none focus:ring-2 focus:ring-primary-600/20 focus:border-primary-600 ui-animated"
                        >
                        @error('password')
                            <p id="password-error" class="mt-1.5 text-xs text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Remember me --}}
                    <div class="flex items-center">
                        <input
                            type="checkbox"
                            id="remember"
                            name="remember"
                            class="w-4 h-4 rounded text-primary-600 border-line focus:ring-primary-600"
                        >
                        <label for="remember" class="ml-2 text-sm text-ink-soft">
                            Ingat saya di perangkat ini
                        </label>
                    </div>

                    {{-- Submit button --}}
                    <button
                        type="submit"
                        class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold text-sm py-3 px-4 rounded-xl shadow-sm hover:shadow-md ui-animated active:scale-[0.98] flex items-center justify-center gap-2"
                    >
                        <span>Masuk ke Akun</span>
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </form>

                {{-- Register link --}}
                <div class="mt-6 pt-6 border-t border-line text-center">
                    <p class="text-sm text-ink-soft">
                        Belum memiliki akun?
                        <a href="{{ route('register') }}" class="font-semibold text-primary-600 hover:underline ml-1">
                            Daftar sekarang
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
