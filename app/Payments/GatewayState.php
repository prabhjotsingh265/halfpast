<?php

namespace App\Payments;

enum GatewayState: string
{
    /** Not paid yet. */
    case Pending = 'pending';

    /** Money reserved, not taken. */
    case Authorized = 'authorized';

    /** Money taken. */
    case Captured = 'captured';

    /** Declined, abandoned or expired. */
    case Failed = 'failed';
}
