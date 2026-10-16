<?php

namespace App\Http\Requests;

use App\Models\Business;
use App\Rules\BookingAddress;
use App\Support\TimeZones;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('timezone'))) {
            $this->merge(['timezone' => TimeZones::current($this->input('timezone'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['required', 'string', new BookingAddress, Rule::unique(Business::class, 'slug')],
            'timezone' => ['required', 'timezone:all_with_bc'],
            'currency' => ['required', Rule::in(Business::CURRENCIES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'Another business already has that address. Try adding your area, like glow-studio-leeds.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'slug' => 'booking address',
        ];
    }
}
