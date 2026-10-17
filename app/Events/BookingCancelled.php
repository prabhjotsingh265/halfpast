<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A confirmed booking was cancelled. The cancellation email listens for it,
 * and the payments code will refund `refund_due`.
 */
class BookingCancelled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Booking $booking) {}
}
