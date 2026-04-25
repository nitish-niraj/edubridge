<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(self), geolocation=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $csp = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://checkout.phonepe.com https://browser.sentry-cdn.com https://www.googletagmanager.com; frame-src 'self' https://mercury.phonepe.com https://mercury-t2.phonepe.com; connect-src 'self' wss://*.pusher.com https://api.twilio.com https://www.google-analytics.com https://region1.google-analytics.com https://*.ingest.sentry.io; media-src 'self' blob:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net; font-src 'self' https://fonts.gstatic.com https://fonts.bunny.net; img-src 'self' data: blob: https:;";

        if (!app()->environment('local')) {
            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }
}
