<?php

namespace App\Http\Controllers;

use App\Enums\BusinessRole;
use App\Http\Middleware\SetCurrentBusiness;
use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * One click into a demo business, as its owner or as someone on its team.
 * Only while demo mode is on, and only into accounts marked as demo.
 */
class DemoLoginController extends Controller
{
    public function __invoke(Request $request, string $slug): RedirectResponse
    {
        abort_unless((bool) config('demo.enabled'), 404);

        $business = Business::where('slug', $slug)->where('is_demo', true)->firstOrFail();
        $role = $request->input('as') === BusinessRole::Staff->value ? BusinessRole::Staff : BusinessRole::Owner;

        $user = $business->members()
            ->where('users.is_demo', true)
            ->wherePivot('role', $role->value)
            ->first();

        abort_if($user === null, 404);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put(SetCurrentBusiness::SESSION_KEY, $business->id);

        return redirect()->route('dashboard');
    }
}
