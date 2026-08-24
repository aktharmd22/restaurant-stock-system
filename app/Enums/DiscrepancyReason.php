<?php

namespace App\Enums;

/**
 * Why less arrived than was sent. Required whenever the branch confirms a
 * short delivery, so nobody gets blamed later without a record.
 */
enum DiscrepancyReason: string
{
    case Damaged = 'damaged';
    case Missing = 'missing';
    case WrongItem = 'wrong_item';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Damaged => 'Damaged',
            self::Missing => 'Missing',
            self::WrongItem => 'Wrong item',
            self::Expired => 'Expired',
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
