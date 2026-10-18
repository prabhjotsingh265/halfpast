<?php

namespace App\Actions\Booking;

use App\Models\Customer;
use Illuminate\Validation\ValidationException;

/**
 * The customer for a booking the business adds: one picked from those on
 * file, the one with the email given, or a new record. Nothing given means
 * a walk-in with no details, and no record at all.
 */
class FindOrAddCustomer
{
    /**
     * @throws ValidationException
     */
    public function handle(?int $id, ?string $name, ?string $email, ?string $phone): ?Customer
    {
        if ($id !== null) {
            return Customer::find($id) ?? throw ValidationException::withMessages(['customer_id' => "That customer couldn't be found."]);
        }

        $name = self::clean($name);
        $email = self::clean($email === null ? null : mb_strtolower($email));
        $phone = self::clean($phone);

        if ($name === null && $email === null && $phone === null) {
            return null;
        }

        if ($email === null) {
            return Customer::create(['name' => (string) $name, 'phone' => $phone]);
        }

        // One record per email address. Someone already on file keeps the
        // name they gave; a phone number is only filled in if missing.
        $customer = Customer::createOrFirst(['email' => $email], ['name' => (string) $name, 'phone' => $phone]);

        if ($customer->phone === null && $phone !== null) {
            $customer->update(['phone' => $phone]);
        }

        return $customer;
    }

    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
