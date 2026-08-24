<?php

namespace App\Enums;

enum RoleName: string
{
    case SuperAdmin = 'super_admin';
    case MainAdmin = 'main_admin';
    case BranchManager = 'branch_manager';
    case BranchStaff = 'branch_staff';

    /** Plain-English name shown to people. Never the raw slug. */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Owner',
            self::MainAdmin => 'Main store admin',
            self::BranchManager => 'Branch manager',
            self::BranchStaff => 'Branch staff',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
