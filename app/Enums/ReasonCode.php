<?php

namespace App\Enums;

/**
 * Why a line was cut or refused. Chosen from a short list, because free text
 * would mean the branch reads a different explanation every time - or none.
 */
enum ReasonCode: string
{
    case OutOfStock = 'out_of_stock';
    case TooMuchAsked = 'too_much_asked';
    case NotNeededToday = 'not_needed_today';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::OutOfStock => 'Out of stock',
            self::TooMuchAsked => 'Too much asked',
            self::NotNeededToday => 'Not needed today',
            self::Other => 'Other',
        };
    }

    /** Only "Other" opens a free-text box. */
    public function needsNote(): bool
    {
        return $this === self::Other;
    }

    /** @return array<int, array{value: string, label: string, needs_note: bool}> */
    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'needs_note' => $case->needsNote(),
        ], self::cases());
    }
}
