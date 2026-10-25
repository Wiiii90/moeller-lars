<?php

use App\Domain\Media\PublicMedia;
use App\Http\Middleware\SecurityHeaders;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
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

it('emits TDM rights reservation for public HTML and media without affecting admin CSP', function (): void {
    $middleware = app(SecurityHeaders::class);
    foreach (['/', '/artworks/example', '/media/original/9', '/media/variant/9'] as $path) {
        $response = $middleware->handle(Request::create($path, 'GET'), static fn (): Response => new Response('ok'));
        expect($response->headers->get('tdm-reservation'))->toBe('1');
    }
    foreach (['/admin', '/pulse', '/preview/artworks/example'] as $path) {
        $response = $middleware->handle(Request::create($path, 'GET'), static fn (): Response => new Response('ok'));
        expect($response->headers->has('tdm-reservation'))->toBeFalse();
    }
});

it('serves the public CSP-safe Livewire script without changing the Admin runtime configuration', function (bool $debug): void {
    config()->set('app.debug', $debug);
    $before = config('livewire.csp_safe', false);

    $response = $this->get(route('public.livewire.csp'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('javascript')
        ->and(config('livewire.csp_safe', false))->toBe($before);
})->with([true, false]);

it('renders 50 percent Custom Page image size as CSP-authorized rules in Blade', function (): void {
    $asset = new MediaAsset;
    $asset->setRawAttributes(['id' => 140, 'state' => 'available'], true);
    $variant = new MediaVariant;
    $variant->setRawAttributes(['id' => 240], true);

    $media = Mockery::mock(PublicMedia::class);
    $media->shouldReceive('thumbnailVariantForAsset')->once()->with($asset)->andReturn($variant);
    $media->shouldReceive('variantUrl')->once()->with($variant)->andReturn('/media/variants/240');

    $blocks = [[
        'type' => 'image',
        'published' => true,
        'media_asset_id' => 140,
        'image_decorative' => true,
        'image_scale_percent' => 50,
    ]];
    $assets = collect([140 => $asset]);
    $label = 'Image scaling test';
    $isHome = false;
    $isPreview = false;
    request()->attributes->set(SecurityHeaders::STYLE_NONCE_ATTRIBUTE, 'image-size-test-nonce');

    $rendered = Blade::render(
        "@include('pages.partials.component-sequence') @stack('public-component-styles')",
        compact('blocks', 'assets', 'media', 'label', 'isHome', 'isPreview'),
    );

    expect($rendered)->toContain('id="custom-page-image-0"')
        ->toContain('<style nonce="image-size-test-nonce">')
        ->toContain('--custom-page-image-width: 14rem;')
        ->not->toContain('--custom-page-image-width-narrow: 10rem;')
        ->not->toContain('style="--custom-page-image-width');
});
