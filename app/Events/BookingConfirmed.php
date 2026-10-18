<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A booking became definite: no deposit was needed, the deposit was paid,
 * or the business added it by hand. The confirmation email (unless the
 * business chose not to send one) and the owner's live calendar listen for it.
 */
class BookingConfirmed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Booking $booking, public bool $tellCustomer = true) {}
}
