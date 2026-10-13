<?php

use App\Http\Middleware\SecurityHeaders;
use App\Models\User;
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

it('enforces non-frameable public pages and same-origin administrator Preview with fresh strict CSP nonces', function (): void {
    $public = $this->get('/')
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY');
    $publicCsp = (string) $public->headers->get('Content-Security-Policy');

    expect($publicCsp)
        ->toContain("frame-ancestors 'none'")
        ->not->toContain("'unsafe-inline'")
        ->not->toContain("'unsafe-eval'");

    $this->get('/preview')->assertNotFound();
    $this->actingAs(User::factory()->create(), 'web')
        ->get('/preview')
        ->assertNotFound();

    $admin = User::factory()->admin()->create();
    $preview = $this->actingAs($admin, 'web')
        ->get('/preview')
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    $previewCsp = (string) $preview->headers->get('Content-Security-Policy');

    expect($previewCsp)
        ->toContain("frame-ancestors 'self'")
        ->not->toContain("'unsafe-inline'")
        ->not->toContain("'unsafe-eval'");

    $publicNonceMatch = preg_match("/style-src 'self' 'nonce-([^']+)'/", $publicCsp, $publicNonce);
    $previewNonceMatch = preg_match("/style-src 'self' 'nonce-([^']+)'/", $previewCsp, $previewNonce);

    expect($publicNonceMatch)->toBe(1)
        ->and($previewNonceMatch)->toBe(1)
        ->and($previewNonce[1])->not->toBe($publicNonce[1])
        ->and($previewCsp)->toContain("'nonce-{$previewNonce[1]}'")
        ->and((string) $preview->getContent())->toContain('nonce="'.$previewNonce[1].'"');
});

it('serves the public CSP-safe Livewire script without changing the Admin runtime configuration', function (): void {
    $before = config('livewire.csp_safe', false);

    $response = $this->get(route('public.livewire.csp'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('javascript')
        ->and(config('livewire.csp_safe', false))->toBe($before);
});
