<?php

namespace App\Http\Requests;

use App\Tenancy\CurrentBusiness;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Time off is entered the way people think about it, as dates and clock
 * times where the business is, and stored as the real moments in UTC. A
 * whole day runs from midnight to the next midnight, so a day that's 23 or
 * 25 hours long because the clocks change is still exactly that day.
 */
class TimeOffRequest extends FormRequest
{
    private const TIME = '/^([01]\d|2[0-3]):[0-5]\d$/';

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'staff_id' => [
                'nullable',
                'integer',
                Rule::exists('staff', 'id')->where('business_id', app(CurrentBusiness::class)->id()),
            ],
            'all_day' => ['boolean'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'starts_time' => ['exclude_if:all_day,true', 'required', 'regex:'.self::TIME],
            'ends_time' => ['exclude_if:all_day,true', 'required', 'regex:'.self::TIME],
            'reason' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ends_on.after_or_equal' => "The last day can't be before the first.",
            'staff_id.exists' => "That person isn't on the team any more.",
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                [$start, $end] = $this->period();

                if ($end->lessThanOrEqualTo($start)) {
                    $validator->errors()->add('ends_time', 'The time off has to end after it starts.');
                } elseif ($start->diffInDays($end) > 366) {
                    $validator->errors()->add('ends_on', 'Time off can be up to a year at a time.');
                }
            },
        ];
    }

    /**
     * When it starts and ends, in UTC.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public function period(): array
    {
        $zone = app(CurrentBusiness::class)->require()->timezone;
        $startsOn = (string) $this->input('starts_on');
        $endsOn = (string) $this->input('ends_on');

        if ($this->boolean('all_day')) {
            return [
                CarbonImmutable::parse($startsOn, $zone)->startOfDay()->utc(),
                CarbonImmutable::parse($endsOn, $zone)->addDay()->startOfDay()->utc(),
            ];
        }

        // A time that doesn't exist because the clocks went forward moves on
        // to the first moment that does, the way PHP reads it.
        return [
            CarbonImmutable::parse($startsOn.' '.$this->input('starts_time'), $zone)->utc(),
            CarbonImmutable::parse($endsOn.' '.$this->input('ends_time'), $zone)->utc(),
        ];
    }
}
