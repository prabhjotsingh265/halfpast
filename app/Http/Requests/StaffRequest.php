<?php

namespace App\Http\Requests;

use App\Actions\Staff\SaveStaffPhoto;
use App\Http\Controllers\StaffPhotoController;
use App\Models\WorkingHour;
use App\Tenancy\CurrentBusiness;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Hours arrive as "HH:MM" on a five-minute grid. A day can have several
 * stretches, but they can't overlap. "24:00" is allowed as an end, for
 * anyone who works until midnight.
 */
class StaffRequest extends FormRequest
{
    private const START = '/^([01]\d|2[0-3]):[0-5][05]$/';

    private const END = '/^(([01]\d|2[0-3]):[0-5][05]|24:00)$/';

    private const DAYS = [1 => 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing(['service_ids' => [], 'hours' => []]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'bio' => ['nullable', 'string', 'max:300'],
            'is_active' => ['boolean'],
            'service_ids' => ['array'],
            'service_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('services', 'id')->where('business_id', app(CurrentBusiness::class)->id()),
            ],
            'photo' => SaveStaffPhoto::RULES,
            'hours' => ['array', 'max:42'],
            'hours.*.weekday' => ['required', 'integer', 'between:1,7'],
            'hours.*.starts_at' => ['required', 'string', 'regex:'.self::START],
            'hours.*.ends_at' => ['required', 'string', 'regex:'.self::END],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hours.*.starts_at.regex' => 'Pick a start time on the five-minute mark.',
            'hours.*.ends_at.regex' => 'Pick an end time on the five-minute mark.',
            'service_ids.*.exists' => "One of those services isn't on your list any more.",
            ...StaffPhotoController::messages(),
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

                foreach ($this->hoursByDay() as $weekday => $stretches) {
                    $previousEnd = null;

                    foreach ($stretches as $index => [$start, $end]) {
                        if ($end <= $start) {
                            $validator->errors()->add("hours.{$index}.ends_at", self::DAYS[$weekday].' finishes before it starts.');
                        } elseif ($previousEnd !== null && $start < $previousEnd) {
                            $validator->errors()->add("hours.{$index}.starts_at", self::DAYS[$weekday].' has hours that overlap.');
                        }

                        $previousEnd = max($previousEnd ?? 0, $end);
                    }
                }
            },
        ];
    }

    /**
     * @return array{name: string, bio: string|null, is_active: bool}
     */
    public function staffAttributes(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'bio' => $this->validated('bio'),
            'is_active' => (bool) $this->validated('is_active', true),
        ];
    }

    /**
     * @return list<int>
     */
    public function serviceIds(): array
    {
        return array_map('intval', array_values((array) $this->validated('service_ids', [])));
    }

    /**
     * @return list<array{weekday: int, starts_at: string, ends_at: string}>
     */
    public function hours(): array
    {
        /** @var array<int, array{weekday: int|string, starts_at: string, ends_at: string}> $hours */
        $hours = (array) $this->validated('hours', []);

        return array_values(array_map(fn (array $hour) => [
            'weekday' => (int) $hour['weekday'],
            'starts_at' => $hour['starts_at'],
            'ends_at' => $hour['ends_at'],
        ], $hours));
    }

    /**
     * Each day's stretches as minutes since midnight, earliest first, keyed by
     * their position in the request so errors land on the right row.
     *
     * @return array<int, array<int, array{int, int}>>
     */
    private function hoursByDay(): array
    {
        $days = [];

        foreach ((array) $this->input('hours', []) as $index => $hour) {
            $days[(int) $hour['weekday']][(int) $index] = [
                WorkingHour::minutesOf((string) $hour['starts_at']),
                WorkingHour::minutesOf((string) $hour['ends_at']),
            ];
        }

        foreach ($days as &$stretches) {
            uasort($stretches, fn (array $a, array $b) => $a[0] <=> $b[0]);
        }

        ksort($days);

        return $days;
    }
}
