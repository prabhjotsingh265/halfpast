<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when a booking is cancelled, saying what happens to the deposit.
 */
class BookingCancelledMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Booking $booking)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $booking = $this->booking->loadMissing('business');
        $start = $booking->starts_at->setTimezone($booking->business->timezone);

        return new Envelope(
            from: new Address((string) config('mail.from.address'), $booking->business->name),
            subject: "Cancelled: {$booking->service_name} on {$start->format('D j M')} at {$start->format('g:i a')}",
        );
    }

    public function content(): Content
    {
        $booking = $this->booking->loadMissing(['business', 'staff']);
        $zone = $booking->business->timezone;

        return new Content(
            view: 'mail.booking-cancelled',
            text: 'mail.booking-cancelled-text',
            with: [
                'booking' => $booking,
                'business' => $booking->business,
                'start' => $booking->starts_at->setTimezone($zone),
                'byBusiness' => $booking->cancelled_by === 'business',
                'kept' => $booking->deposit - $booking->refund_due,
                'bookAgainUrl' => route('booking.show', ['business' => $booking->business->slug]),
                'money' => fn (int $minor) => Money::format($minor, $booking->currency),
            ],
        );
    }
}
