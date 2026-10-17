<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A booking became definite: no deposit was needed, or the deposit was paid.
 * The confirmation email and the owner's live calendar listen for it.
 */
class BookingConfirmed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Booking $booking) {}
}
