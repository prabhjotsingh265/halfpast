<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A booking the business adds by hand. The customer is someone already on
 * file, new details, or nobody at all (a walk-in who'd rather not say).
 */
class NewBookingRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $newCustomer = ! $this->filled('customer_id');

        return [
            'service' => ['required', 'integer'],
            'staff' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'customer_id' => ['nullable', 'integer'],
            // A name is needed to go with an email or phone number.
            'name' => ['nullable', 'string', 'max:120', Rule::requiredIf($newCustomer && ($this->filled('email') || $this->filled('phone')))],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{6,30}$/'],
            'notes' => ['nullable', 'string', 'max:500'],
            'tell_customer' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Add their name to go with the contact details.',
            'phone.regex' => 'Use digits, spaces and + ( ) - only.',
            'time.required' => 'Pick a time.',
        ];
    }
}
