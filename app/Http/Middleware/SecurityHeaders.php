<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headers that make the app harder to attack: a content security policy on
 * every page, no framing, no sniffing, and HTTPS remembered in production.
 *
 * The policy allows scripts from this site (inline ones only with this
 * page's nonce) and Razorpay's checkout, which runs in a pop-up on our
 * page. Stripe and PayPal take payment on their own sites, so they need
 * nothing here. The platform admin and its tools get every header but
 * the policy (`SecurityHeaders:without-csp`): their frameworks need
 * looser rules.
 */
class SecurityHeaders
{
    private const RAZORPAY = ['https://checkout.razorpay.com', 'https://api.razorpay.com', 'https://lumberjack.razorpay.com', 'https://cdn.razorpay.com'];

    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        Vite::useCspNonce();

        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self "https://checkout.razorpay.com" "https://api.razorpay.com")');

        if (app()->isProduction()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($mode !== 'without-csp' && config('security.csp') && str_contains((string) $headers->get('Content-Type'), 'text/html')) {
            $headers->set('Content-Security-Policy', $this->policy((string) Vite::cspNonce()));
        }

        return $response;
    }

    private function policy(string $nonce): string
    {
        $dev = Vite::isRunningHot() ? [rtrim((string) file_get_contents(Vite::hotFile()))] : [];
        $devSockets = array_map(fn (string $url) => preg_replace('#^http#', 'ws', $url), $dev);

        $reverb = config('security.reverb');
        $sockets = $reverb['host']
            ? [($reverb['scheme'] === 'https' ? 'wss' : 'ws').'://'.$reverb['host'].($reverb['port'] ? ':'.$reverb['port'] : '')]
            : [];

        $rules = [
            'default-src' => ["'self'"],
            // Razorpay's checkout also loads its fraud checks from its CDN.
            'script-src' => ["'self'", "'nonce-{$nonce}'", 'https://checkout.razorpay.com', 'https://cdn.razorpay.com', ...$dev],
            // Vue, the toaster and the progress bar set styles from script.
            'style-src' => ["'self'", "'unsafe-inline'", ...$dev],
            'img-src' => ["'self'", 'data:', 'blob:', 'https://cdn.razorpay.com'],
            'font-src' => ["'self'", 'data:', ...$dev],
            'connect-src' => ["'self'", ...$sockets, ...self::RAZORPAY, ...$dev, ...$devSockets],
            'frame-src' => ['https://checkout.razorpay.com', 'https://api.razorpay.com'],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
        ];

        return collect($rules)
            ->map(fn (array $sources, string $directive) => $directive.' '.implode(' ', array_unique($sources)))
            ->join('; ');
    }
}
