<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Which pages are rendered on the server: the booking pages, which anyone can
 * land on from a search, a shared link or an email, so they arrive ready to
 * read before any JavaScript runs. The dashboard is only reached signed in,
 * and renders in the browser as before.
 *
 * The home page isn't here yet: its hero follows the visitor's own clock,
 * which the server can't know. It joins when the redesign builds that part
 * to start from a neutral hour and catch up once the page is running.
 */
class ServerRendering
{
    public const ROUTES = ['booking.show', 'booking.checkout', 'booking.manage'];

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
