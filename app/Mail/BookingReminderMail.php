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
 * "See you tomorrow at 3:30 pm" (24 hours before) or "In 2 hours" (2 hours
 * before), in the business's own time.
 */
class BookingReminderMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  '24h'|'2h'|string  $kind
     */
    public function __construct(public Booking $booking, public string $kind)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $booking = $this->booking->loadMissing('business');
        $start = $booking->starts_at->setTimezone($booking->business->timezone);

        return new Envelope(
            from: new Address((string) config('mail.from.address'), $booking->business->name),
            subject: $this->kind === '2h'
                ? "In 2 hours: {$booking->service_name} at {$start->format('g:i a')}"
                : "Tomorrow: {$booking->service_name} at {$start->format('g:i a')}",
        );
    }

    public function content(): Content
    {
        $booking = $this->booking->loadMissing(['business', 'staff']);
        $business = $booking->business;
        $start = $booking->starts_at->setTimezone($business->timezone);
        // Only offer cancelling while it's still possible.
        $canCancel = $booking->starts_at->isFuture();

        return new Content(
            view: 'mail.booking-reminder',
            text: 'mail.booking-reminder-text',
            with: [
                'booking' => $booking,
                'business' => $business,
                'start' => $start,
                'end' => $booking->ends_at->setTimezone($business->timezone),
                'soon' => $this->kind === '2h',
                'zoneName' => str_replace('_', ' ', last(explode('/', $business->timezone))),
                'manageUrl' => $booking->manageUrl(),
                'canCancel' => $canCancel,
                'dueOnTheDay' => Money::format($booking->price - $booking->deposit, $booking->currency),
            ],
        );
    }
}
