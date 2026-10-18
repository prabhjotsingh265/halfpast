<?php

namespace App\Support\Calendar;

use App\Actions\Booking\CancellationTerms;
use App\Actions\Booking\RecordVisit;
use App\Enums\BusinessRole;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Staff;
use App\Models\TimeOff;
use App\Models\User;

/**
 * The person looking at a calendar or the dashboard, and what they can do
 * there: the rules in BookingPolicy and TimeOffPolicy, worked out once per
 * page rather than once per booking. Owners can do anything; staff act on
 * their own diary.
 */
final class Viewer
{
    public readonly bool $isOwner;

    /** @var list<int> The team members this person is (usually one, for staff). */
    public readonly array $staffIds;

    public function __construct(public readonly Business $business, ?User $user)
    {
        $this->isOwner = $user !== null && $user->roleIn($business) === BusinessRole::Owner;
        $this->staffIds = $this->isOwner || $user === null
            ? []
            : array_values(Staff::where('user_id', $user->id)->pluck('id')->map(fn ($id) => (int) $id)->all());
    }

    public function owns(?int $staffId): bool
    {
        return $this->isOwner || ($staffId !== null && in_array($staffId, $this->staffIds, true));
    }

    public function canRemove(TimeOff $entry): bool
    {
        return $this->owns($entry->staff_id);
    }

    /**
     * A booking as the calendar and dashboard show it, with what this person
     * can do with it.
     *
     * @return array<string, mixed>
     */
    public function booking(Booking $booking): array
    {
        $paid = $booking->payments->first(fn (Payment $payment) => in_array($payment->status, [PaymentStatus::Captured, PaymentStatus::PartiallyRefunded], true));

        return [
            'id' => $booking->id,
            'code' => $booking->code,
            'reference' => $booking->displayCode(),
            'staff_id' => $booking->staff_id,
            'service_name' => $booking->service_name,
            'status' => $booking->status->value,
            'source' => $booking->source,
            'starts_at' => $booking->starts_at->utc()->toIso8601ZuluString(),
            'ends_at' => $booking->ends_at->utc()->toIso8601ZuluString(),
            'blocked_until' => $booking->blocked_until->utc()->toIso8601ZuluString(),
            'price' => $booking->price,
            'deposit' => $booking->deposit,
            'currency' => $booking->currency,
            'paid' => $paid === null ? null : ['amount' => $paid->amount, 'gateway' => $paid->gateway],
            'notes' => $booking->notes,
            'customer' => $booking->customer === null ? null : [
                'name' => $booking->customer->name,
                'email' => $booking->customer->email,
                'phone' => $booking->customer->phone,
            ],
            'can' => [
                'mark' => $this->owns($booking->staff_id) && RecordVisit::allowed($booking),
                'cancel' => $this->isOwner && CancellationTerms::for($booking, $this->business)->allowed,
            ],
        ];
    }
}
