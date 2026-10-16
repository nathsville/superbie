<?php

namespace App\Support;

/**
 * Defensive coercion for raw user input (query string, form payload).
 *
 * A crafted request can turn any parameter into an ARRAY (e.g. `?search[]=x`,
 * `?status[]=a`, `?name[]=x`). Casting that array inline — `(string) $v`,
 * `trim((string) $v)`, `filter_var($v, FILTER_VALIDATE_INT)` — raises
 * "Array to string conversion" (a 500 under Laravel's error handler).
 *
 * Every helper here degrades a non-scalar to a safe value (null) so the caller
 * can simply ignore malformed input and keep serving HTTP 200.
 */
final class QueryInput
{
    /**
     * Scalar value as a string, or null when the value is not scalar (array/object).
     */
    public static function string(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * Trimmed string, or null when the value is not scalar.
     * An empty scalar ("", "0" → "0") is preserved; only non-scalars become null.
     */
    public static function trimmed(mixed $value): ?string
    {
        $string = self::string($value);

        return $string === null ? null : trim($string);
    }

    /**
     * Integer value, or null when the value is not a well-formed integer.
     * Non-scalars (arrays) are rejected by FILTER_VALIDATE_INT without warning.
     */
    public static function int(mixed $value): ?int
    {
        if (! is_scalar($value)) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_INT) === false ? null : (int) $value;
    }
}
