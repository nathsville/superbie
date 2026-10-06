<x-layouts.app title="Terlalu Banyak Permintaan">
    <div class="min-h-screen flex items-center justify-center px-6 py-16">
        <div class="w-full max-w-lg rounded-2xl bg-white border border-[#E2E8F0] shadow-sm p-8 text-center">
            <div class="mx-auto w-14 h-14 rounded-2xl bg-gradient-to-br from-[#2563EB] to-[#0F766E] flex items-center justify-center">
                <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>

            <h1 class="mt-5 text-xl font-bold text-[#0F172A]">Terlalu Banyak Permintaan</h1>

            <p class="mt-3 text-sm text-[#475569] leading-relaxed" role="alert">
                @php
                    // Message resolution:
                    //  - `$message` is passed explicitly by the complaint limiter's
                    //    custom response callback (AppServiceProvider).
                    //  - otherwise, an HttpException (e.g. the login limiter's
                    //    ThrottleRequestsException) is rendered by the framework
                    //    handler, which passes `$exception` but NOT `$message`.
                    $rateLimitMessage = $message
                        ?? (isset($exception) && $exception->getMessage() !== ''
                            ? $exception->getMessage()
                            : null);
                    $rateLimitMessage = $rateLimitMessage
                        ?: 'Terlalu banyak permintaan. Silakan coba lagi beberapa saat lagi.';
                @endphp
                {{ $rateLimitMessage }}
            </p>

            <a
                href="{{ url()->previous() }}"
                class="mt-6 inline-flex items-center justify-center rounded-xl bg-[#2563EB] px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#1D4ED8] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB] focus-visible:ring-offset-2"
            >
                Kembali
            </a>
        </div>
    </div>
</x-layouts.app>
