<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Demo logins are shared by everyone trying the app, so nobody can change
 * how they sign in (name, email, password, two-factor), delete them, or use
 * them to set up a business of their own. Everything inside the demo
 * businesses can still be changed; it's all rebuilt every night.
 */
class ProtectDemoAccounts
{
    public const LOCKED = [
        'profile.update',
        'profile.destroy',
        'user-password.update',
        'two-factor.enable',
        'two-factor.confirm',
        'two-factor.disable',
        'two-factor.regenerate-recovery-codes',
        'onboarding.store',
    ];

    public const MESSAGE = "Demo logins are shared, so their sign-in details can't be changed. Sign up to try this with your own account.";

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->is_demo || $request->isMethodSafe() || ! in_array($request->route()?->getName(), self::LOCKED, true)) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => self::MESSAGE], 403);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => self::MESSAGE]);

        return back();
    }
}
