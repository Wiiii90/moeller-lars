<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeaders
{
    public const STYLE_NONCE_ATTRIBUTE = 'public_csp_style_nonce';

    public function handle(Request $request, Closure $next): Response
    {
        $pulsePath = trim((string) config('pulse.path', 'pulse'), '/');
        $isAdminRequest = $request->is('admin', 'admin/*');
        $isPulseRequest = $pulsePath !== '' && $request->is($pulsePath, $pulsePath.'/*');
        $isPublicRequest = $isAdminRequest === false && $isPulseRequest === false;
        $isArtistPreviewRequest = $request->is('preview', 'preview/*');
        $styleNonce = null;

        if ($isPublicRequest) {
            $styleNonce = base64_encode(random_bytes(18));
            $request->attributes->set(self::STYLE_NONCE_ATTRIBUTE, $styleNonce);
        }

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', $isArtistPreviewRequest ? 'SAMEORIGIN' : 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($isPulseRequest) {
            $response->headers->set('Content-Security-Policy', $this->pulseContentSecurityPolicy());
        } elseif ($isPublicRequest) {
            $response->headers->set('Content-Security-Policy', $this->publicContentSecurityPolicy($styleNonce, $isArtistPreviewRequest));
        }

        return $response;
    }

    private function pulseContentSecurityPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "img-src 'self' data:",
            "font-src 'self' data: https://fonts.bunny.net",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'none'",
            "form-action 'self'",
        ]).';';
    }

    private function publicContentSecurityPolicy(string $styleNonce, bool $allowSameOriginFraming): string
    {
        $scriptSources = ["'self'"];
        $styleSources = ["'self'", "'nonce-{$styleNonce}'"];
        $connectSources = ["'self'"];
        $imageSources = ["'self'", 'data:'];
        $frameSources = ["'self'", 'https://www.openstreetmap.org'];

        if ((bool) config('analytics.matomo.tracking_enabled')) {
            $baseUrl = config('analytics.matomo.base_url');
            if (is_string($baseUrl) === false || trim($baseUrl) === '') {
                throw new RuntimeException('Enabled Matomo tracking requires an explicit base URL for the public CSP.');
            }

            $parts = parse_url($baseUrl);
            if (is_array($parts) === false
                || ($parts['scheme'] ?? null) !== 'https'
                || is_string($parts['host'] ?? null) === false) {
                throw new RuntimeException('Enabled Matomo tracking requires a valid HTTPS base URL for the public CSP.');
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
            'style-src '.implode(' ', $styleSources),
            'img-src '.implode(' ', $imageSources),
            "font-src 'self' data:",
            'connect-src '.implode(' ', $connectSources),
            'frame-src '.implode(' ', $frameSources),
            "object-src 'none'",
            "base-uri 'self'",
            'frame-ancestors '.($allowSameOriginFraming ? "'self'" : "'none'"),
            "form-action 'self'",
        ]).';';
    }
}
