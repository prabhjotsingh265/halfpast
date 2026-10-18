<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where signing in (with or without a two-factor code) takes you: the page
 * you were heading for, or the dashboard.
 *
 * From the sign-in form that's a full page load, not an Inertia visit. The
 * page you were heading for may not be one of the app's own (the admin or
 * Horizon), and Inertia shows anything else in a pop-up over the form.
 */
class LoginResponse implements LoginResponseContract, TwoFactorLoginResponse
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false]);
        }

        $redirect = redirect()->intended(Fortify::redirects('login'));

        return $request->header('X-Inertia') ? Inertia::location($redirect->getTargetUrl()) : $redirect;
    }
}
