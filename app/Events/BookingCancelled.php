<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A confirmed booking was cancelled. The cancellation email listens for it
 * (unless the business chose not to tell the customer), and the payments
 * code refunds `refund_due`.
 */
class BookingCancelled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Booking $booking, public bool $tellCustomer = true) {}
}
