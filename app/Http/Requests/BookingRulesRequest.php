<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingRulesRequest extends FormRequest
{
    /** Slot spacings offered; each divides an hour evenly. */
    public const STEPS = [5, 10, 15, 20, 30, 60];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Long enough to pay with an OTP or a bank app, short enough not
            // to block a slot for long when someone walks away.
            'hold_minutes' => ['required', 'integer', 'between:5,30'],
            'min_notice_minutes' => ['required', 'integer', 'between:0,10080'],
            'booking_horizon_days' => ['required', 'integer', 'between:1,365'],
            'slot_step_minutes' => ['required', 'integer', Rule::in(self::STEPS)],
            'cancellation_window_hours' => ['required', 'integer', 'between:0,168'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hold_minutes.between' => 'Hold a time for between 5 and 30 minutes.',
            'min_notice_minutes.between' => 'Notice can be up to a week.',
            'booking_horizon_days.between' => 'Take bookings from 1 day up to a year ahead.',
            'cancellation_window_hours.between' => 'The cancellation window can be up to a week.',
        ];
    }
}
