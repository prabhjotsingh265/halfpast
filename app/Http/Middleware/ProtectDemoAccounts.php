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
 * them to set up a business of their own. And because the demo businesses'
 * booking pages are public, nobody can put pictures on them or rewrite what
 * they say about themselves. Everything else inside them can be changed;
 * it's all rebuilt every night.
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

    /** What the public sees on a demo business's booking page. */
    public const PUBLIC = [
        'business.profile.update',
        'business.logo.store',
        'business.logo.destroy',
        'staff.photo.store',
        'staff.photo.destroy',
    ];

    public const PUBLIC_MESSAGE = "The demo's booking page is public, so its pictures and profile can't be changed. Sign up to try this with your own business.";

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->is_demo || $request->isMethodSafe()) {
            return $next($request);
        }

        $route = $request->route()?->getName();

        if (in_array($route, self::LOCKED, true)) {
            return $this->refuse($request, self::MESSAGE);
        }

        if (in_array($route, self::PUBLIC, true)) {
            return $this->refuse($request, self::PUBLIC_MESSAGE);
        }

        // A photo sent along with another form, like a new team member's, is dropped.
        $request->files->replace([]);

        return $next($request);
    }

    private function refuse(Request $request, string $message): Response
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $message], 403);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return back();
    }
}
