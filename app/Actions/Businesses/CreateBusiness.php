<?php

namespace App\Actions\Businesses;

use App\Actions\Staff\SaveWeeklyHours;
use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateBusiness
{
    public function __construct(private SaveWeeklyHours $saveHours) {}

    /**
     * Create a business with the person setting it up as its owner. Most
     * businesses start as one person, so the owner is also the first person
     * customers can book, on office hours until they change them.
     *
     * @param  array{name: string, slug: string, timezone: string, currency: string}  $details
     */
    public function handle(User $owner, array $details): Business
    {
        return DB::transaction(function () use ($owner, $details) {
            $business = Business::create($details);
            $business->addMember($owner, BusinessRole::Owner);

            $staff = $business->staff()->create(['name' => $owner->name, 'user_id' => $owner->id]);
            $this->saveHours->handle($staff, SaveWeeklyHours::OFFICE_HOURS);

            return $business;
        });
    }
}
