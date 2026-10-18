<?php

use App\Http\Middleware\EnsureUserHasBusiness;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ProtectDemoAccounts;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetCurrentBusiness;
use App\Http\Middleware\UseBusinessFromAddress;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Payment gateways can't send a CSRF token; their signatures are checked instead.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        // Route models like {service} must load inside the current business,
        // so the business is worked out before bindings are resolved.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: SetCurrentBusiness::class);
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: UseBusinessFromAddress::class);

        $middleware->alias([
            'has-business' => EnsureUserHasBusiness::class,
            'business-from-address' => UseBusinessFromAddress::class,
        ]);

        $middleware->web(append: [
            SecurityHeaders::class,
            ProtectDemoAccounts::class,
            HandleAppearance::class,
            SetCurrentBusiness::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // The platform's own tools (Horizon, Pulse): sessions and CSRF from `web`, but
        // none of the app's middleware. They run inline scripts the content
        // security policy would block, so they get every header but that one.
        $middleware->group('tools', [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            PreventRequestForgery::class,
            SubstituteBindings::class,
            SecurityHeaders::class.':without-csp',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
