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

it('serves the public CSP-safe Livewire script without changing the Admin runtime configuration', function (): void {
    $before = config('livewire.csp_safe', false);

    $response = $this->get(route('public.livewire.csp'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('javascript')
        ->and(config('livewire.csp_safe', false))->toBe($before);
});

it('renders authored image width through nonce-authorized CSS rather than blocked inline styles', function (): void {
    $source = file_get_contents(resource_path('views/pages/partials/component-sequence.blade.php'));
    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
    $styles = file_get_contents(resource_path('css/custom-pages.css'));

    expect($source)
        ->toContain("@push('public-component-styles')")
        ->toContain('SecurityHeaders::STYLE_NONCE_ATTRIBUTE')
        ->toContain('#custom-page-image-{{ $blockIndex }}')
        ->toContain('--custom-page-image-width: {{ 28 * $imageScale }}rem;')
        ->not->toContain('style="--custom-page-image-width')
        ->and($layout)->toContain("@stack('public-component-styles')")
        ->and($styles)
        ->toContain('var(--custom-page-image-width, 28rem)')
        ->not->toContain('var(--custom-page-image-width-narrow, 20rem)');
});


it('renders 50 percent Custom Page image size as CSP-authorized rules in Blade', function (): void {
    $asset = new \App\Models\MediaAsset;
    $asset->setRawAttributes(['id' => 140, 'state' => 'available'], true);
    $variant = new \App\Models\MediaVariant;
    $variant->setRawAttributes(['id' => 240], true);

    $media = \Mockery::mock(\App\Domain\Media\PublicMedia::class);
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

    $rendered = \Illuminate\Support\Facades\Blade::render(
        "@include('pages.partials.component-sequence') @stack('public-component-styles')",
        compact('blocks', 'assets', 'media', 'label', 'isHome', 'isPreview'),
    );

    expect($rendered)->toContain('id="custom-page-image-0"')
        ->toContain('<style nonce="image-size-test-nonce">')
        ->toContain('--custom-page-image-width: 14rem;')
        ->not->toContain('--custom-page-image-width-narrow: 10rem;')
        ->not->toContain('style="--custom-page-image-width');
});
