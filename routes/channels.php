<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
| Private channels for live updates. Each business has one for its
| calendar, open to the people who work there.
*/

Broadcast::channel('business.{businessId}.calendar', function (User $user, int $businessId): bool {
    return $user->businesses()->whereKey($businessId)->exists();
});
