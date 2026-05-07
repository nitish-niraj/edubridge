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

        $jitsiDomain = trim((string) env('VITE_JITSI_DOMAIN', 'meet.jit.si'));
        $jitsiDomain = preg_replace('#^https?://#', '', $jitsiDomain);
        $jitsiDomain = rtrim($jitsiDomain, '/');
        $jitsiOrigin = $jitsiDomain !== '' ? 'https://' . $jitsiDomain : null;
        $jitsiWsOrigin = $jitsiDomain !== '' ? 'wss://' . $jitsiDomain : null;

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $cameraAllow = ['self'];
        if ($jitsiOrigin) {
            $cameraAllow[] = '"' . $jitsiOrigin . '"';
        }
        $permissionsPolicy = 'camera=(' . implode(' ', $cameraAllow) . '), microphone=(' . implode(' ', $cameraAllow) . '), geolocation=()';
        $response->headers->set('Permissions-Policy', $permissionsPolicy);

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $jitsiScriptSrc = $jitsiOrigin ? ' ' . $jitsiOrigin : '';
        $jitsiFrameSrc = $jitsiOrigin ? ' ' . $jitsiOrigin : '';
        $jitsiConnectSrc = $jitsiOrigin ? ' ' . $jitsiOrigin : '';
        if ($jitsiWsOrigin) {
            $jitsiConnectSrc .= ' ' . $jitsiWsOrigin;
        }

        $csp = "default-src 'self'; script-src 'self' 'unsafe-inline' https://checkout.phonepe.com https://mercury.phonepe.com https://mercury-t2.phonepe.com https://sdk.twilio.com https://browser.sentry-cdn.com https://www.googletagmanager.com{$jitsiScriptSrc}; frame-src 'self' https://mercury.phonepe.com https://mercury-t2.phonepe.com{$jitsiFrameSrc}; connect-src 'self' wss://*.pusher.com https://api.twilio.com https://www.google-analytics.com https://region1.google-analytics.com https://*.ingest.sentry.io{$jitsiConnectSrc}; media-src 'self' blob: mediastream:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net; font-src 'self' https://fonts.gstatic.com https://fonts.bunny.net; img-src 'self' data: blob: https:;";

        if (!app()->environment('local')) {
            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }
}
