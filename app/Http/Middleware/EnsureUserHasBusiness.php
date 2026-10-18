<?php

namespace App\Http\Middleware;

use App\Tenancy\CurrentBusiness;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The dashboard only makes sense once there's a business to run, so anyone
 * without one is sent to set it up first. A platform admin with no business
 * of their own goes to the admin instead.
 */
class EnsureUserHasBusiness
{
    public function __construct(private readonly CurrentBusiness $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->current->get() === null) {
            // The admin isn't part of the Inertia app, so it needs a full page load.
            return $request->user()?->is_admin
                ? Inertia::location(Filament::getPanel('admin')->getUrl() ?? '/admin')
                : redirect()->route('onboarding.create');
        }

        return $next($request);
    }
}
