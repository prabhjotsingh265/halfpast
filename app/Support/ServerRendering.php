<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Which pages are rendered on the server: the ones anyone can land on from a
 * search, a shared link or an email (the home page, the booking pages, the
 * privacy policy and terms), so they arrive ready to read before any
 * JavaScript runs. The dashboard is only reached signed in, and renders in
 * the browser as before.
 *
 * The server can't know a visitor's clock, language or time zone, so those
 * pages start from the business's zone, a fixed locale and, on the home
 * page, half past three, then catch up once they're running.
 */
class ServerRendering
{
    public const ROUTES = ['home', 'booking.show', 'booking.checkout', 'booking.manage', 'privacy', 'terms'];

    /**
     * True for a full page load of one of those pages. Inertia's own visits
     * between pages get JSON, which is never rendered on the server.
     */
    public static function appliesTo(Request $request): bool
    {
        return (bool) config('inertia.ssr.enabled')
            && ! $request->hasHeader('X-Inertia')
            && $request->routeIs(...self::ROUTES);
    }
}
