<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\Complaint::observe(\App\Observers\ComplaintObserver::class);
        \App\Models\User::observe(\App\Observers\UserObserver::class);

        $this->configureRateLimiting();
    }

    /**
     * Configure the application's named rate limiters.
     *
     * `complaint-submission` — SECURITY / abuse protection for the authenticated
     * citizen complaint submission endpoint (Prompt 17). Values come from the
     * single source of truth `config/business_rules.php`:
     *   5 submissions / 10 minutes / authenticated user.
     *
     * Scope key = authenticated user id (never the client IP): several citizens
     * may share one NAT/public IP, so the limit must follow the account. This is
     * deliberately SEPARATE from the daily report business rule (which is checked
     * in StoreComplaintRequest); it never replaces it. A submission carrying any
     * number of attachments counts as exactly ONE hit because the limiter is
     * applied once per HTTP request at the route boundary, not per file.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('complaint-submission', function (Request $request) {
            $maxAttempts  = (int) config('business_rules.complaint_submission_rate_limit');
            $decayMinutes = (int) config('business_rules.complaint_submission_rate_window_minutes');

            return Limit::perMinutes($decayMinutes, $maxAttempts)
                ->by($request->user()?->getAuthIdentifier())
                ->response(function (Request $request, array $headers) {
                    $message = 'Anda telah mencapai batas pengiriman laporan sementara. '
                        . 'Silakan coba lagi beberapa saat lagi.';

                    if ($request->expectsJson()) {
                        return response()->json(['message' => $message], 429, $headers);
                    }

                    return response()->view('errors.429', ['message' => $message], 429, $headers);
                });
        });
    }
}
