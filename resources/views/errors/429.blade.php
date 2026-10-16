<x-layouts.app title="Terlalu Banyak Permintaan">
    <div class="min-h-screen flex items-center justify-center px-6 py-16">
        <div class="w-full max-w-lg rounded-2xl bg-white border border-line shadow-sm p-8 text-center">
            <div class="mx-auto w-14 h-14 rounded-2xl bg-primary-600 flex items-center justify-center">
                <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>

            <h1 class="mt-5 text-xl font-bold text-ink">Terlalu Banyak Permintaan</h1>

            <p class="mt-3 text-sm text-ink-soft leading-relaxed" role="alert">
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
                class="mt-6 inline-flex items-center justify-center rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2"
            >
                Kembali
            </a>
        </div>
    </div>
</x-layouts.app>
