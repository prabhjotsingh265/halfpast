<?php

namespace App\Http\Requests;

use App\Enums\DepositType;
use App\Tenancy\CurrentBusiness;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Prices arrive as the owner typed them ("30" or "30.50") and are stored in
 * minor units. A fixed deposit is converted the same way.
 */
class ServiceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Unticking everyone sends no staff_ids at all.
        $this->mergeIfMissing(['staff_ids' => []]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:720', 'multiple_of:5'],
            'buffer_after_minutes' => ['required', 'integer', 'min:0', 'max:120', 'multiple_of:5'],
            'price' => ['required', 'numeric', 'min:0', 'max:1000000', 'decimal:0,2'],
            'deposit_type' => ['required', Rule::enum(DepositType::class)],
            'deposit_value' => [
                Rule::requiredIf(fn () => $this->input('deposit_type') !== DepositType::None->value),
                'nullable',
                'numeric',
                'min:0',
                'decimal:0,2',
            ],
            'is_active' => ['boolean'],
            'staff_ids' => ['array'],
            'staff_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('staff', 'id')->where('business_id', app(CurrentBusiness::class)->id()),
            ],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $type = $this->input('deposit_type');
                $value = (float) $this->input('deposit_value', 0);

                if ($type === DepositType::Percent->value && ($value < 1 || $value > 100)) {
                    $validator->errors()->add('deposit_value', 'A percentage deposit has to be between 1 and 100.');
                }

                if ($type === DepositType::Fixed->value && $value > (float) $this->input('price', 0)) {
                    $validator->errors()->add('deposit_value', "The deposit can't be more than the price.");
                }

                if ($type === DepositType::Fixed->value && $value <= 0) {
                    $validator->errors()->add('deposit_value', 'Enter the deposit amount.');
                }
            },
        ];
    }

    /**
     * The validated data, with money in minor units.
     *
     * @return array<string, mixed>
     */
    public function serviceAttributes(): array
    {
        $data = $this->validated();
        $type = DepositType::from((string) $data['deposit_type']);

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'duration_minutes' => (int) $data['duration_minutes'],
            'buffer_after_minutes' => (int) $data['buffer_after_minutes'],
            'price' => self::toMinorUnits($data['price']),
            'deposit_type' => $type,
            'deposit_value' => match ($type) {
                DepositType::None => 0,
                DepositType::Fixed => self::toMinorUnits($data['deposit_value'] ?? 0),
                DepositType::Percent => (int) $data['deposit_value'],
            },
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    /**
     * @return list<int>
     */
    public function staffIds(): array
    {
        return array_map('intval', array_values((array) $this->validated('staff_ids', [])));
    }

    private static function toMinorUnits(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
