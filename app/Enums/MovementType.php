<?php

namespace App\Enums;

enum MovementType: string
{
    case Purchase = 'purchase';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Consumption = 'consumption';
    case Wastage = 'wastage';
    case Adjustment = 'adjustment';
    case ReturnStock = 'return';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Bought',
            self::TransferIn => 'Came in',
            self::TransferOut => 'Went out',
            self::Consumption => 'Used',
            self::Wastage => 'Thrown away',
            self::Adjustment => 'Corrected',
            self::ReturnStock => 'Sent back',
        };
    }

    /** Movements that add stock. Everything else removes it. */
    public function addsStock(): bool
    {
        return in_array($this, [self::Purchase, self::TransferIn, self::ReturnStock], true);
    }
}
