<?php

namespace App\Listeners;

use App\Events\BookingConfirmed;
use App\Mail\BookingConfirmedMail;
use Illuminate\Support\Facades\Mail;

class SendBookingConfirmation
{
    public function handle(BookingConfirmed $event): void
    {
        $customer = $event->booking->loadMissing('customer')->customer;

        // Walk-ins booked by hand may have no email address.
        if (! $event->tellCustomer || $customer?->email === null) {
            return;
        }

        Mail::to($customer->email, $customer->name)->send(new BookingConfirmedMail($event->booking));
    }
}
