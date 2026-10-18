<?php

namespace App\Support\Calendar;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Mail\Money;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\BookingReminder;
use App\Models\Payment;
use App\Models\Refund;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Everything that happened to a booking, oldest first, in plain words: when
 * it was made and how, the deposit, confirmation, reminders, and how it
 * ended (and who did what). Built from the booking's events, payments,
 * refunds and reminders.
 */
final class BookingHistory
{
    private const GATEWAYS = [
        'stripe' => 'card',
        'razorpay' => 'Razorpay',
        'paypal' => 'PayPal',
        'test' => 'test payment',
    ];

    /**
     * @return list<array{at: string, text: string, tone: string}> Tone is "neutral", "good" or "bad".
     */
    public static function for(Booking $booking): array
    {
        $booking->loadMissing(['events.user', 'payments', 'reminders']);
        $money = fn (int $minor) => Money::format($minor, $booking->currency);
        $items = [];

        $add = function (?DateTimeInterface $at, string $text, string $tone = 'neutral', int $order = 5) use (&$items): void {
            if ($at !== null) {
                $items[] = ['at' => $at, 'text' => $text, 'tone' => $tone, 'order' => $order];
            }
        };

        // Bookings made before the history was kept have no "created" event.
        if (! $booking->events->contains('type', 'created')) {
            $add($booking->created_at, $booking->source === 'manual' ? 'Added by the business' : 'Booked online', order: 0);
        }

        foreach ($booking->events as $event) {
            /** @var BookingEvent $event */
            $by = $event->user?->name;

            match ($event->type) {
                'created' => $add($event->created_at, ($event->meta['source'] ?? $booking->source) === 'manual'
                    ? 'Added by '.($by ?? 'the business')
                    : 'Booked online', order: 0),
                'confirmed' => $add($event->created_at, 'Confirmed', 'good', 2),
                'completed' => $add($event->created_at, 'Marked completed'.($by ? " by {$by}" : ''), 'good', 4),
                'no_show' => $add($event->created_at, 'Marked a no-show'.($by ? " by {$by}" : ''), 'bad', 4),
                'cancelled' => $add($event->created_at, self::cancelled($event, $booking, $by), 'bad', 5),
                'expired' => $add($event->created_at, 'The hold ran out', order: 4),
                default => null,
            };
        }

        foreach ($booking->payments as $payment) {
            /** @var Payment $payment */
            $how = self::GATEWAYS[$payment->gateway] ?? $payment->gateway;

            if ($payment->captured_at !== null) {
                $add($payment->captured_at, "Deposit of {$money($payment->amount)} paid by {$how}", 'good', 1);
            } elseif ($payment->status === PaymentStatus::Authorized) {
                $add($payment->authorized_at, "Deposit of {$money($payment->amount)} approved by {$how}, being taken", order: 1);
            }

            if ($payment->status === PaymentStatus::Failed) {
                $add($payment->updated_at, "A {$how} payment didn't go through", 'bad', 6);
            }

            if ($payment->status === PaymentStatus::Released) {
                $add($payment->released_at, "A {$money($payment->amount)} payment was let go, so it wasn't charged", order: 6);
            }

            foreach (Refund::withoutGlobalScopes()->where('payment_id', $payment->id)->orderBy('id')->get() as $refund) {
                match ($refund->status) {
                    RefundStatus::Succeeded => $add($refund->updated_at, "{$money($refund->amount)} refunded", order: 7),
                    RefundStatus::Failed => $add($refund->updated_at, "A {$money($refund->amount)} refund didn't go through", 'bad', 7),
                    default => $add($refund->created_at, "{$money($refund->amount)} refund on its way", order: 7),
                };
            }
        }

        foreach ($booking->reminders as $reminder) {
            /** @var BookingReminder $reminder */
            $add($reminder->sent_at, ($reminder->kind === '24h' ? '24-hour' : '2-hour').' reminder sent', order: 3);
        }

        // Things in the same second go in the order they happen in: made,
        // paid, confirmed, reminded, marked or cancelled, refunded.
        usort($items, fn (array $a, array $b) => [$a['at'], $a['order']] <=> [$b['at'], $b['order']]);

        return array_map(fn (array $item) => [
            'at' => CarbonImmutable::instance($item['at'])->utc()->toIso8601ZuluString(),
            'text' => $item['text'],
            'tone' => $item['tone'],
        ], $items);
    }

    private static function cancelled(BookingEvent $event, Booking $booking, ?string $by): string
    {
        $text = match ($event->meta['by'] ?? $booking->cancelled_by) {
            'customer' => 'Cancelled by the customer',
            'system' => "Cancelled: the deposit couldn't be taken",
            default => 'Cancelled by '.($by ?? 'the business'),
        };

        $refundDue = (int) ($event->meta['refund_due'] ?? $booking->refund_due);

        return $booking->deposit > 0 && $refundDue === 0 && ($event->meta['by'] ?? null) !== 'system'
            ? "{$text}, keeping the deposit"
            : $text;
    }
}
