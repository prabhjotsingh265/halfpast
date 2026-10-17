<?php

namespace App\Http\Requests;

use App\Tenancy\CurrentBusiness;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HoldTimeRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $businessId = app(CurrentBusiness::class)->id();

        return [
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('business_id', $businessId)->where('is_active', true)],
            'staff_id' => ['nullable', 'integer', Rule::exists('staff', 'id')->where('business_id', $businessId)->where('is_active', true)],
            // An instant, as the booking page sends it: "2026-11-03T10:30:00Z".
            'starts_at' => ['required', 'date'],
        ];
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->validated('starts_at'))->utc();
    }
}
