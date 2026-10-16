<?php

namespace App\Models;

use App\Enums\BusinessRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A person's place in a business: who they are there and what they can do.
 *
 * @property int $id
 * @property int $business_id
 * @property int $user_id
 * @property BusinessRole $role
 */
class Membership extends Pivot
{
    protected $table = 'business_user';

    public $incrementing = true;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => BusinessRole::class,
        ];
    }
}
