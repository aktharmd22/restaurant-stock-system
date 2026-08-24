<?php

namespace App\Enums;

enum RequestStatus: string
{
    case Draft = 'draft';
    case Waiting = 'waiting';
    case Approved = 'approved';
    case Partial = 'partial';
    case Rejected = 'rejected';
    case Sent = 'sent';
    case Received = 'received';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /**
     * The words people see. Kept in step with resources/js/Support/status.js -
     * if one changes, change the other.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Not sent',
            self::Waiting => 'Waiting',
            self::Approved => 'Approved',
            self::Partial => 'Less than asked',
            self::Rejected => 'Not approved',
            self::Sent => 'On the way',
            self::Received => 'Arrived',
            self::Closed => 'Done',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Waiting for the admin to look at it. */
    public function needsReview(): bool
    {
        return $this === self::Waiting;
    }

    /** Approved in some form and not yet dispatched. */
    public function awaitingDispatch(): bool
    {
        return in_array($this, [self::Approved, self::Partial], true);
    }

    /** Left the main store, not yet confirmed by the branch. */
    public function inTransit(): bool
    {
        return $this === self::Sent;
    }

    /**
     * Nothing more can happen to it. A finished request is never edited or
     * deleted - it is history.
     */
    public function isFinished(): bool
    {
        return in_array($this, [self::Received, self::Closed, self::Cancelled, self::Rejected], true);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this, [self::Draft, self::Waiting, self::Approved, self::Partial], true);
    }
}
