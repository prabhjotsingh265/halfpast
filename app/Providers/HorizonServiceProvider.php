<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

/**
 * The queue dashboard at /horizon. Platform admins only, in every
 * environment, so it behaves the same on a laptop as it does live.
 */
class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(fn ($request) => Gate::check('viewHorizon', [$request->user()]));
    }

    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user = null) => (bool) $user?->isPlatformAdmin());
    }
}
