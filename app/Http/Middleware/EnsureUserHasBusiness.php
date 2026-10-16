<?php

namespace App\Http\Middleware;

use App\Tenancy\CurrentBusiness;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The dashboard only makes sense once there's a business to run, so anyone
 * without one is sent to set it up first.
 */
class EnsureUserHasBusiness
{
    public function __construct(private readonly CurrentBusiness $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->current->get() === null) {
            return redirect()->route('onboarding.create');
        }

        return $next($request);
    }
}
