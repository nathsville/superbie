<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * AuditLogService — read-side queries for the Super Admin Audit & Security
 * surface (Prompt 10).
 *
 * SCOPE / CONSTRAINTS:
 *   - READ-ONLY. This service only ever SELECTs. Audit records are append-only
 *     and are never updated, deleted, normalized, or rewritten by this module.
 *   - No new schema. It uses ONLY the columns that actually exist on
 *     `audit_logs`: id, actor_id, action, subject_type, subject_id, metadata,
 *     ip_address, user_agent, created_at.
 *   - No invented security engine (no risk score, severity, threat level).
 *   - Filter option lists are derived from ACTUAL data (DISTINCT), never from a
 *     hardcoded/fabricated list.
 */
class AuditLogService
{
    /** Placeholder shown in place of a redacted sensitive value. */
    public const REDACTED = '[REDACTED]';

    /**
     * Key fragments that mark a metadata value as sensitive. Matching is
     * case-insensitive and substring-based so that variants such as
     * `remember_token`, `password_confirmation`, or `reset_token` are covered.
     *
     * Redaction happens at the PRESENTATION layer only — stored audit data is
     * never altered (Prompt 10 §12–§13).
     *
     * @var list<string>
     */
    private const SENSITIVE_KEY_FRAGMENTS = [
        'password',
        'token',
        'secret',
        'api_key',
        'apikey',
        'access_key',
        'private_key',
        'encryption_key',
        'credential',
        'authorization',
        'otp',
        'session_id',
    ];

    /**
     * Server-side paginated + filtered audit list.
     *
     * @param  array{search?:?string,action?:?string,subject?:?string,actor?:?int|string,date_from?:?string,date_to?:?string}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = AuditLog::query()
            ->with('actor')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', '%' . $search . '%')
                  ->orWhere('subject_type', 'like', '%' . $search . '%')
                  ->orWhereHas('actor', function ($a) use ($search) {
                      $a->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                  });

                if (ctype_digit($search)) {
                    $q->orWhere('subject_id', (int) $search);
                }
            });
        }

        $action = (string) ($filters['action'] ?? '');
        if ($action !== '') {
            $query->where('action', $action);
        }

        $subject = (string) ($filters['subject'] ?? '');
        if ($subject !== '') {
            $query->where('subject_type', $subject);
        }

        $actor = $filters['actor'] ?? null;
        if ($actor !== null && $actor !== '') {
            $query->where('actor_id', (int) $actor);
        }

        $from = $this->validDate($filters['date_from'] ?? null);
        if ($from !== null) {
            $query->whereDate('created_at', '>=', $from);
        }

        $to = $this->validDate($filters['date_to'] ?? null);
        if ($to !== null) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query->paginate(30)->withQueryString();
    }

    /**
     * Accept only well-formed Y-m-d dates; anything else is ignored so that
     * malformed input can never reach the query builder.
     */
    private function validDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return ($date && $date->format('Y-m-d') === $value) ? $value : null;
    }

    /**
     * Filter option lists, derived from ACTUAL audit data.
     *
     * @return array{actions: list<string>, subjects: list<string>, actors: \Illuminate\Support\Collection<int, User>}
     */
    public function filterOptions(): array
    {
        $actions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->all();

        $subjects = AuditLog::query()
            ->whereNotNull('subject_type')
            ->select('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->all();

        $actors = User::query()
            ->whereIn('id', function ($sub) {
                $sub->select('actor_id')
                    ->from('audit_logs')
                    ->whereNotNull('actor_id');
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        return [
            'actions'  => $actions,
            'subjects' => $subjects,
            'actors'   => $actors,
        ];
    }

    /**
     * Return a copy of the metadata with sensitive values masked.
     *
     * This is a pure presentation transform: the original array and the stored
     * audit row are left untouched.
     *
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>
     */
    public function redactMetadata(?array $metadata): array
    {
        if (empty($metadata)) {
            return [];
        }

        $safe = [];

        foreach ($metadata as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $safe[$key] = self::REDACTED;
                continue;
            }

            $safe[$key] = is_array($value) ? $this->redactMetadata($value) : $value;
        }

        return $safe;
    }

    private function isSensitiveKey(string $key): bool
    {
        $needle = strtolower($key);

        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (str_contains($needle, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
