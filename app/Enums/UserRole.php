<?php

namespace App\Enums;

enum UserRole: string
{
    case Masyarakat = 'masyarakat';
    case Petugas    = 'petugas';
    case Operator   = 'operator';
    case Admin      = 'admin';
    case SuperAdmin = 'super_admin';

    public function label(): string
    {
        return match ($this) {
            self::Masyarakat => 'Masyarakat',
            self::Petugas    => 'Petugas',
            self::Operator   => 'Operator',
            self::Admin      => 'Admin',
            self::SuperAdmin => 'Super Admin',
        };
    }

    /** @return list<string> */
    public static function staffValues(): array
    {
        return [
            self::Petugas->value,
            self::Operator->value,
            self::Admin->value,
            self::SuperAdmin->value,
        ];
    }
}
