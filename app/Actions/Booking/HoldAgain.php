<?php

namespace App\Actions\Booking;

use App\Exceptions\TimeNotAvailable;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Service;
use App\Models\Staff;

/**
 * A fresh hold on a cleared booking's time, with the same person, carrying
 * the customer's details across. For a customer who took too long but whose
 * time nobody else has taken.
 */
class HoldAgain
{
    public function __construct(private HoldTime $hold) {}

    /**
     * @return Booking|null Null if the time has gone to someone else.
     */
    public function handle(Business $business, Booking $expired): ?Booking
    {
        $service = Service::find($expired->service_id);
        $staff = Staff::find($expired->staff_id);

        if ($service === null || $staff === null) {
            return null;
        }

        try {
            $booking = $this->hold->handle($business, $service, $staff, $expired->starts_at);
        } catch (TimeNotAvailable) {
            return null;
        }

        if ($expired->customer_id !== null) {
            $booking->update(['customer_id' => $expired->customer_id, 'notes' => $expired->notes]);
        }

        return $booking;
    }
}
