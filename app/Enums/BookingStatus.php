<?php

namespace App\Enums;

enum BookingStatus: string
{
    /** Picked by a customer and kept for them while they finish booking. */
    case Held = 'held';

    case Confirmed = 'confirmed';

    /** A hold that ran out before the booking was finished. */
    case Expired = 'expired';

    case Cancelled = 'cancelled';

    case Completed = 'completed';

    case NoShow = 'no_show';

    /**
     * The statuses that take the time, matching the database's overlap rule.
     *
     * @return list<self>
     */
    public static function blocking(): array
    {
        return [self::Held, self::Confirmed];
    }
}
