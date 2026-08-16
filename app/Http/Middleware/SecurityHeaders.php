<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->is('admin', 'admin/*') === false) {
            $response->headers->set('Content-Security-Policy', $this->publicContentSecurityPolicy());
        }

        return $response;
    }

    private function publicContentSecurityPolicy(): string
    {
        $scriptSources = ["'self'"];
        $connectSources = ["'self'"];
        $imageSources = ["'self'", 'data:'];

        if ((bool) config('analytics.matomo.enabled')) {
            $baseUrl = config('analytics.matomo.base_url');
            if (is_string($baseUrl) === false || trim($baseUrl) === '') {
                throw new RuntimeException('Enabled Matomo analytics require an explicit base URL for the public CSP.');
            }

            $parts = parse_url($baseUrl);
            if (is_array($parts) === false
                || ($parts['scheme'] ?? null) !== 'https'
                || is_string($parts['host'] ?? null) === false) {
                throw new RuntimeException('Enabled Matomo analytics require a valid HTTPS base URL for the public CSP.');
            }

            $origin = 'https://'.$parts['host'];
            if (isset($parts['port'])) {
                $origin .= ':'.$parts['port'];
            }

            $scriptSources[] = $origin;
            $connectSources[] = $origin;
            $imageSources[] = $origin;
        }

        return implode('; ', [
            "default-src 'self'",
            'script-src '.implode(' ', $scriptSources),
            "style-src 'self'",
            'img-src '.implode(' ', $imageSources),
            "font-src 'self' data:",
            'connect-src '.implode(' ', $connectSources),
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'none'",
            "form-action 'self'",
        ]).';';
    }
}
