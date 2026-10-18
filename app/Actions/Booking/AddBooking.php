<?php

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Events\BookingConfirmed;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use App\Models\TimeOff;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A booking the business adds itself: someone phoned, or walked in. It's
 * confirmed straight away with no deposit, and the online rules (notice,
 * how far ahead) don't apply, so a walk-in can be fitted in now or a regular
 * booked months out. It can even go outside working hours. What it can't do
 * is overlap another booking (the database's overlap rule decides that), or
 * land on time off or a day the business is closed.
 */
class AddBooking
{
    public function __construct(private ClearLapsedHolds $clearLapsedHolds) {}

    /**
     * @throws ValidationException
     */
    public function handle(
        Business $business,
        Service $service,
        Staff $staff,
        CarbonImmutable $startsAt,
        ?Customer $customer = null,
        ?string $notes = null,
        bool $tellCustomer = true,
        ?CarbonImmutable $now = null,
    ): Booking {
        $now ??= CarbonImmutable::now();
        $zone = $business->timezone;
        // Stored as UTC: a local time saved as-is would be written as its
        // wall-clock reading and land hours out.
        $startsAt = $startsAt->utc();

        if (! $service->is_active || ! $staff->is_active || ! $service->staff()->whereKey($staff->id)->exists()) {
            throw ValidationException::withMessages(['staff' => "{$staff->name} doesn't do {$service->name}."]);
        }

        // Earlier today is fine (a walk-in written up afterwards); other days aren't.
        if ($startsAt->lessThan($now->setTimezone($zone)->startOfDay())) {
            throw ValidationException::withMessages(['date' => 'Pick today or a day after.']);
        }

        $endsAt = $startsAt->addMinutes($service->duration_minutes);
        $blockedUntil = $endsAt->addMinutes($service->buffer_after_minutes);

        $away = TimeOff::where('starts_at', '<', $endsAt->utc())
            ->where('ends_at', '>', $startsAt->utc())
            ->where(fn ($query) => $query->whereNull('staff_id')->orWhere('staff_id', $staff->id))
            ->orderByRaw('staff_id is not null')
            ->first();

        if ($away !== null) {
            throw ValidationException::withMessages(['time' => $away->staff_id === null
                ? "{$business->name} is closed then."
                : "{$staff->name} is off then."]);
        }

        try {
            $booking = DB::transaction(function () use ($business, $service, $staff, $startsAt, $endsAt, $blockedUntil, $customer, $notes, $now) {
                $this->clearLapsedHolds->handle($business->id, $staff->id, $startsAt, $blockedUntil, $now);

                $booking = new Booking([
                    'service_id' => $service->id,
                    'staff_id' => $staff->id,
                    'customer_id' => $customer?->id,
                    'service_name' => $service->name,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'blocked_until' => $blockedUntil,
                    'status' => BookingStatus::Confirmed,
                    'source' => 'manual',
                    'price' => $service->price,
                    'deposit' => 0,
                    'currency' => $business->currency,
                    'notes' => $notes,
                ]);
                $booking->business_id = $business->id;
                $booking->save();

                return $booking;
            });
        } catch (QueryException $e) {
            if ($e->getCode() !== Booking::OVERLAP_ERROR) {
                throw $e;
            }

            throw ValidationException::withMessages(['time' => "{$staff->name} already has a booking then, or tidy-up time after one."]);
        }

        BookingConfirmed::dispatch($booking, $tellCustomer);

        return $booking;
    }
}
