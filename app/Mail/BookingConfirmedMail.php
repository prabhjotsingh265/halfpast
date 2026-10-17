<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent once a booking is confirmed: the details in the business's own time,
 * a calendar file, and the link to view or cancel.
 */
class BookingConfirmedMail extends Mailable implements ShouldQueue
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
            subject: "Booked: {$booking->service_name} on {$start->format('D j M')} at {$start->format('g:i a')}",
        );
    }

    public function content(): Content
    {
        $booking = $this->booking->loadMissing(['business', 'staff', 'customer']);
        $zone = $booking->business->timezone;

        return new Content(
            view: 'mail.booking-confirmed',
            text: 'mail.booking-confirmed-text',
            with: [
                'booking' => $booking,
                'business' => $booking->business,
                'start' => $booking->starts_at->setTimezone($zone),
                'end' => $booking->ends_at->setTimezone($zone),
                'zoneName' => str_replace('_', ' ', last(explode('/', $zone))),
                'manageUrl' => $booking->manageUrl(),
                'money' => fn (int $minor) => Money::format($minor, $booking->currency),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->booking->calendarEvent()->render(), 'booking.ics')
                ->withMime('text/calendar; charset=utf-8; method=PUBLISH'),
        ];
    }
}
