<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Models\User;
use App\Tenancy\CurrentBusiness;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Works out which business a logged-in person is looking at: the one they
 * last switched to, or else the first one they joined.
 */
class SetCurrentBusiness
{
    public const SESSION_KEY = 'current_business_id';

    public function __construct(private readonly CurrentBusiness $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $business = $this->resolve($request, $user);
            $this->current->set($business);

            if ($business !== null) {
                $request->session()->put(self::SESSION_KEY, $business->id);
            }
        }

        return $next($request);
    }

    private function resolve(Request $request, User $user): ?Business
    {
        $chosen = $request->session()->get(self::SESSION_KEY);

        if (is_int($chosen)) {
            $business = $user->businesses()->whereKey($chosen)->first();

            if ($business !== null) {
                return $business;
            }
        }

        return $user->businesses()->orderBy('business_user.created_at')->first();
    }
}
