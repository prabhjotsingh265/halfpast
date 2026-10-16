<?php

namespace App\Actions\Businesses;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateBusiness
{
    /**
     * Create a business with the person setting it up as its owner.
     *
     * @param  array{name: string, slug: string, timezone: string, currency: string}  $details
     */
    public function handle(User $owner, array $details): Business
    {
        return DB::transaction(function () use ($owner, $details) {
            $business = Business::create($details);
            $business->addMember($owner, BusinessRole::Owner);

            return $business;
        });
    }
}
