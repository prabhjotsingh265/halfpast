<?php

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Business;
use Carbon\CarbonImmutable;

/**
 * What happens if a customer cancels now. Earlier than the business's
 * cancellation window (24 hours by default) the deposit comes back in full;
 * inside it, the deposit is kept. Shown before they confirm, and applied
 * when they do.
 */
final readonly class CancellationTerms
{
    public function __construct(
        public bool $allowed,
        /** Minor units going back to the customer. */
        public int $refund,
        /** Minor units the business keeps. */
        public int $kept,
        /** When the free cancellation period ends. */
        public CarbonImmutable $freeUntil,
    ) {}

    public static function for(Booking $booking, Business $business, ?CarbonImmutable $now = null): self
    {
        $now ??= CarbonImmutable::now();
        $freeUntil = $booking->starts_at->subHours($business->cancellation_window_hours);

        $allowed = $booking->status === BookingStatus::Confirmed && $booking->starts_at->isAfter($now);
        $inTime = $now->lessThanOrEqualTo($freeUntil);

        return new self(
            allowed: $allowed,
            refund: $allowed && $inTime ? $booking->deposit : 0,
            kept: $allowed && ! $inTime ? $booking->deposit : 0,
            freeUntil: $freeUntil,
        );
    }
}
