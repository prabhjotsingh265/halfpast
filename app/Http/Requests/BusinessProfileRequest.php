<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BusinessProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $trimmed = collect($this->only(['name', 'description', 'address', 'phone', 'website']))
            ->map(fn (mixed $value) => is_string($value) && trim($value) !== '' ? trim($value) : null);

        // People type "glowstudio.com"; the link needs the https:// in front.
        $website = $trimmed['website'] ?? null;
        if (is_string($website) && ! preg_match('#^https?://#i', $website)) {
            $trimmed['website'] = 'https://'.$website;
        }

        $this->merge($trimmed->all());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:300'],
            'address' => ['nullable', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^\+?[0-9()\-.\s]{6,}$/'],
            'website' => ['nullable', 'string', 'max:200', 'url:http,https'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Your business needs a name.',
            'description.max' => 'Keep it to 300 characters, about two sentences.',
            'phone.regex' => 'Use digits, spaces and + ( ) - only, like +44 20 7946 0123.',
            'website.url' => "That doesn't look like a web address, like glowstudio.com.",
        ];
    }
}
