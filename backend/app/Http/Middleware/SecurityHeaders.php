<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ENF-04 / OWASP: baseline response hardening — MIME sniffing and framing
 * blocked, referrer minimised, strict CSP. HSTS is only advertised over
 * HTTPS in production so that plain-HTTP development stays usable.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set(
            'Content-Security-Policy',
            implode('; ', [
                "default-src 'self'",
                "base-uri 'self'",
                "frame-ancestors 'none'",
                "form-action 'self'",
                "img-src 'self' data: blob:",
                "font-src 'self' data:",
                "style-src 'self' 'unsafe-inline'",
                "script-src 'self'",
                "connect-src 'self'",
            ]),
        );

        if (config('app.env') === 'production' && $request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
