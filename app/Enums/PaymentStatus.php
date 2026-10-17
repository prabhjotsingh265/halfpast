<?php

namespace App\Enums;

enum PaymentStatus: string
{
    /** Checkout started at the gateway; the customer hasn't paid yet. */
    case Pending = 'pending';

    /** The money is reserved on the customer's card or account, not taken. */
    case Authorized = 'authorized';

    /** The money has been taken. */
    case Captured = 'captured';

    /** An authorisation let go: the customer was never charged. */
    case Released = 'released';

    case Refunded = 'refunded';

    case PartiallyRefunded = 'partially_refunded';

    /** Declined, cancelled at the gateway, or the checkout ran out. */
    case Failed = 'failed';

    /** Whether the gateway has finished with it one way or another. */
    public function isSettled(): bool
    {
        return ! in_array($this, [self::Pending, self::Authorized], true);
    }
}
