<?php

namespace App\Enums;

enum LineStatus: string
{
    case Waiting = 'waiting';
    case Approved = 'approved';
    case Reduced = 'reduced';
    case Rejected = 'rejected';
    case Sent = 'sent';
    case Received = 'received';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Waiting',
            self::Approved => 'Approved',
            self::Reduced => 'Less than asked',
            self::Rejected => 'Not approved',
            self::Sent => 'On the way',
            self::Received => 'Arrived',
        };
    }

    /** The pill colour key used by the frontend. */
    public function tone(): string
    {
        return match ($this) {
            self::Waiting => 'waiting',
            self::Approved => 'approved',
            self::Reduced => 'partial',
            self::Rejected => 'rejected',
            self::Sent => 'sent',
            self::Received => 'received',
        };
    }
}
