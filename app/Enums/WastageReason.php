<?php

namespace App\Enums;

enum WastageReason: string
{
    case Spoiled = 'spoiled';
    case Expired = 'expired';
    case Damaged = 'damaged';
    case Spilled = 'spilled';
    case OverPrepared = 'over_prepared';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Spoiled => 'Went bad',
            self::Expired => 'Out of date',
            self::Damaged => 'Damaged',
            self::Spilled => 'Spilled',
            self::OverPrepared => 'Made too much',
            self::Other => 'Other',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }
}
