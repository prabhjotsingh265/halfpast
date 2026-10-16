<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Tenancy\CurrentBusiness;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On public pages the business comes from the address, /glow-studio, not
 * from who's logged in. An owner looking at someone else's booking page
 * sees that business, the same as any customer would.
 */
class UseBusinessFromAddress
{
    public function __construct(private readonly CurrentBusiness $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $slug = $route?->parameter('business');

        $business = $slug instanceof Business
            ? $slug
            : Business::where('slug', is_string($slug) ? $slug : '')->first();

        abort_if($business === null, 404);

        $this->current->set($business);
        // Saves route model binding looking the business up a second time.
        $route?->setParameter('business', $business);

        return $next($request);
    }
}
