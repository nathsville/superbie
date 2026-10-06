<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt authentication with rate limiting.
     * Uses generic error messages to prevent user enumeration.
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('Email atau password salah.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * SECURITY / abuse protection (FINAL). Login policy is UNCHANGED (D-5):
     *   key = `email|ip`, threshold = 5 attempts, window = framework default
     *   decay (60 seconds), hit on failure / clear on success, `Lockout` event.
     *
     * Prompt 20 (F-18-16) — the ONLY change: the exceeded state is now an HTTP
     * 429 (Too Many Requests) instead of a 422 validation error, matching the
     * framework's own `throttle:` middleware semantics. The `Retry-After` value
     * is the native `RateLimiter::availableIn()` result (never hardcoded).
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw new ThrottleRequestsException(
            __('Terlalu banyak percobaan login. Coba lagi dalam :seconds detik.', [
                'seconds' => $seconds,
            ]),
            null,
            ['Retry-After' => $seconds],
        );
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')) . '|' . $this->ip());
    }
}
