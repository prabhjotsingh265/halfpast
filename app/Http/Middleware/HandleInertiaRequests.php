<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Tenancy\CurrentBusiness;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'business' => fn () => $this->currentBusiness($request),
        ];
    }

    /**
     * The business being looked at: the one the dashboard is showing, or the
     * one whose booking page this is.
     *
     * @return array<string, mixed>|null
     */
    private function currentBusiness(Request $request): ?array
    {
        $business = app(CurrentBusiness::class)->get();
        $user = $request->user();

        // Customers on a booking page see the business too, with no role.
        if ($business === null) {
            return null;
        }

        return [
            'id' => $business->id,
            'name' => $business->name,
            'slug' => $business->slug,
            'timezone' => $business->timezone,
            'currency' => $business->currency,
            'role' => $user instanceof User ? $user->roleIn($business)?->value : null,
        ];
    }
}
