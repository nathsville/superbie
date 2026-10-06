<?php

namespace App\Enums;

enum UserRole: string
{
    case Masyarakat = 'masyarakat';
    case Operator   = 'operator';
    case Admin      = 'admin';
    case SuperAdmin = 'super_admin';

    public function label(): string
    {
        return match ($this) {
            self::Masyarakat => 'Masyarakat',
            self::Operator   => 'Operator',
            self::Admin      => 'Admin',
            self::SuperAdmin => 'Super Admin',
        };
    }

    /**
     * All ACTIVE role values.
     *
     * Source of truth for role validation (e.g. Super Admin User Management).
     * The historical `petugas` role is intentionally absent — it must never be
     * selectable or assignable to a new/updated account.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $role) => $role->value, self::cases());
    }

    /** @return list<string> */
    public static function staffValues(): array
    {
        return [
            self::Operator->value,
            self::Admin->value,
            self::SuperAdmin->value,
        ];
    }
}
