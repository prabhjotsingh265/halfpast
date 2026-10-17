<?php

namespace App\Mail;

use App\Actions\Booking\FindBookableTimes;
use App\Availability\Slot;
use App\Enums\PaymentOutcome;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when a customer's money goes back without them asking: someone else
 * booked the time first, they paid twice, or the deposit couldn't be taken.
 * Says plainly whether they were never charged or are being refunded.
 */
class PaymentReturnedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Payment $payment)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $booking = $this->booking();
        $business = $this->business();

        return new Envelope(
            from: new Address((string) config('mail.from.address'), $business->name),
            subject: match ($this->payment->outcome) {
                PaymentOutcome::SlotTaken => "We couldn't book your {$booking->service_name}",
                PaymentOutcome::Duplicate => "You paid twice for {$booking->service_name}: one payment is coming back",
                PaymentOutcome::CaptureFailed => "Your {$booking->service_name} booking didn't go through",
                default => "Your payment for {$booking->service_name} is coming back",
            },
        );
    }

    public function content(): Content
    {
        $booking = $this->booking();
        $business = $this->business();
        $start = $booking->starts_at->setTimezone($business->timezone);

        return new Content(
            view: 'mail.payment-returned',
            text: 'mail.payment-returned-text',
            with: [
                'booking' => $booking,
                'business' => $business,
                'start' => $start,
                'outcome' => $this->payment->outcome,
                // Authorised money is let go without ever being taken;
                // money already taken comes back as a refund.
                'refunded' => $this->payment->captured_at !== null,
                'amount' => Money::format($this->payment->amount, $this->payment->currency),
                'nearby' => $this->payment->outcome === PaymentOutcome::SlotTaken ? $this->nearbyTimes($booking, $business) : [],
                'bookAgainUrl' => route('booking.show', [
                    'business' => $business->slug,
                    'service' => $booking->service_id,
                    'date' => $start->toDateString(),
                ]),
                'manageUrl' => $this->payment->outcome === PaymentOutcome::Duplicate ? $booking->manageUrl() : null,
            ],
        );
    }

    /**
     * Up to three free times closest to the one they wanted, that day.
     *
     * @return array<int, string>
     */
    private function nearbyTimes(Booking $booking, Business $business): array
    {
        $service = Service::withoutGlobalScopes()->find($booking->service_id);

        if ($service === null) {
            return [];
        }

        $date = $booking->starts_at->setTimezone($business->timezone)->toDateString();
        $wanted = $booking->starts_at->getTimestamp();

        return collect(app(FindBookableTimes::class)->handle($business, $service, null, $date, $date))
            ->sortBy(fn (Slot $slot) => abs($slot->start->getTimestamp() - $wanted))
            ->take(3)
            ->sortBy(fn (Slot $slot) => $slot->start->getTimestamp())
            ->map(fn (Slot $slot) => CarbonImmutable::instance($slot->start)->setTimezone($business->timezone)->format('g:i a'))
            ->values()
            ->all();
    }

    private function booking(): Booking
    {
        return Booking::withoutGlobalScopes()->findOrFail($this->payment->booking_id);
    }

    private function business(): Business
    {
        return Business::findOrFail($this->payment->business_id);
    }
}
