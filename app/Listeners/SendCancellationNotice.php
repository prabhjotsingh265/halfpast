<?php

namespace App\Listeners;

use App\Events\BookingCancelled;
use App\Mail\BookingCancelledMail;
use Illuminate\Support\Facades\Mail;

class SendCancellationNotice
{
    public function handle(BookingCancelled $event): void
    {
        $customer = $event->booking->loadMissing('customer')->customer;

        if ($customer?->email === null) {
            return;
        }

        Mail::to($customer->email, $customer->name)->send(new BookingCancelledMail($event->booking));
    }
}
