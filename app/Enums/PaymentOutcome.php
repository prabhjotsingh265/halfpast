<?php

namespace App\Enums;

/**
 * What settling a payment decided once the money was there.
 */
enum PaymentOutcome: string
{
    /** The booking is the customer's; the money is taken. */
    case Confirmed = 'confirmed';

    /** Someone else booked the time first. The money goes back. */
    case SlotTaken = 'slot_taken';

    /** The booking was already paid for. This second payment goes back. */
    case Duplicate = 'duplicate';

    /** The gateway reported a different amount or currency. Goes back, and is reported. */
    case AmountMismatch = 'amount_mismatch';

    /** The booking can't be confirmed any more (cancelled, for instance). Goes back. */
    case NotBookable = 'not_bookable';

    /** The money couldn't be taken even after retrying, so the booking was cancelled. */
    case CaptureFailed = 'capture_failed';

    public function keepsMoney(): bool
    {
        return $this === self::Confirmed;
    }
}
