<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

it('uses a Pulse-specific CSP without weakening the public CSP', function (): void {
    $middleware = app(SecurityHeaders::class);

    $pulseResponse = $middleware->handle(
        Request::create('/pulse', 'GET'),
        static fn (): Response => new Response('ok'),
    );

    $pulseCsp = $pulseResponse->headers->get('Content-Security-Policy');

    expect($pulseCsp)
        ->toContain("script-src 'self' 'unsafe-inline' 'unsafe-eval'")
        ->toContain("style-src 'self' 'unsafe-inline' https://fonts.bunny.net")
        ->toContain("font-src 'self' data: https://fonts.bunny.net")
        ->toContain("connect-src 'self'")
        ->toContain("frame-ancestors 'none'");

    $publicRequest = Request::create('/', 'GET');
    $publicResponse = $middleware->handle(
        $publicRequest,
        static fn (): Response => new Response('ok'),
    );

    $publicCsp = $publicResponse->headers->get('Content-Security-Policy');
    $nonce = $publicRequest->attributes->get(SecurityHeaders::STYLE_NONCE_ATTRIBUTE);

    expect($nonce)->toBeString()->not->toBe('')
        ->and($publicCsp)
        ->toContain("script-src 'self'")
        ->toContain("style-src 'self' 'nonce-{$nonce}'")
        ->not->toContain("'unsafe-inline'")
        ->not->toContain("'unsafe-eval'");
});
