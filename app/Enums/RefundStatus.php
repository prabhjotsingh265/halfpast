<?php

namespace App\Enums;

enum RefundStatus: string
{
    case Pending = 'pending';

    case Succeeded = 'succeeded';

    /** Gave up after several tries; needs someone to look at it. */
    case Failed = 'failed';
}
