<?php

use App\Http\Controllers\AdminMediaController;
use App\Http\Controllers\PublicArtworkController;
use App\Http\Controllers\PublicContactController;
use App\Http\Controllers\PublicHomeController;
use App\Http\Controllers\PublicMediaController;
use App\Http\Controllers\PublicSeoController;
use App\Http\Controllers\PublicSiteSectionController;
use App\Http\Middleware\EnforceHomePresentationGate;
use App\Http\Middleware\EnsureMediaVariantFile;
use App\Http\Middleware\ProtectArtistPreview;
use Illuminate\Support\Facades\Route;
use Livewire\Mechanisms\FrontendAssets\FrontendAssets;

Route::get('/', [PublicHomeController::class, 'show'])->name('home');
Route::post('/contact', [PublicContactController::class, 'submit'])
    ->middleware([EnforceHomePresentationGate::class, 'throttle:5,1'])
    ->name('contact.submit');
Route::get('/sitemap.xml', [PublicSeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/robots.txt', [PublicSeoController::class, 'robots'])->name('seo.robots');
Route::get('/.well-known/tdmrep.json', [PublicSeoController::class, 'tdmReservation'])->name('seo.tdm-reservation');

// The public Blade shell has a strict no-unsafe-eval CSP. Livewire's
// ordinary bundle remains unchanged for Filament; only this asset endpoint
// serves Livewire 4's CSP-safe frontend for public wire:navigate.
Route::get('/site-assets/livewire-csp.js', static function () {
    // Do not leak a request-local setting into a long-lived PHP worker or
    // the Admin panel's normal Alpine bundle.
    $previous = config('livewire.csp_safe', false);

    try {
        config()->set('livewire.csp_safe', true);

        return app(FrontendAssets::class)->returnJavaScriptAsFile();
    } finally {
        config()->set('livewire.csp_safe', $previous);
    }
})->name('public.livewire.csp');

Route::middleware(ProtectArtistPreview::class)
    ->prefix('preview')
    ->name('preview.')
    ->group(function (): void {
        Route::get('/', [PublicHomeController::class, 'show'])->name('home');
        Route::get('/media/original/{mediaAsset}', [AdminMediaController::class, 'original'])->name('media.original');
        Route::get('/media/variant/{mediaVariant}', [AdminMediaController::class, 'variant'])
            ->middleware(EnsureMediaVariantFile::class)
            ->name('media.variant');
        Route::get('/artworks/{slug}', [PublicArtworkController::class, 'show'])->name('artworks.show');
        Route::get('/{section}/{slug}', [PublicSiteSectionController::class, 'journalEntry'])
            ->where(['section' => '[a-z0-9]+(?:-[a-z0-9]+)*', 'slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'])
            ->name('journal.show');
        Route::get('/{section}', [PublicSiteSectionController::class, 'show'])
            ->where('section', '[a-z0-9]+(?:-[a-z0-9]+)*')
            ->name('site.section');
    });

Route::get('/admin/media-preview/original/{mediaAsset}', [AdminMediaController::class, 'original'])
    ->name('admin.media.original');
Route::get('/admin/media-preview/variant/{mediaVariant}', [AdminMediaController::class, 'variant'])
    ->middleware(EnsureMediaVariantFile::class)
    ->name('admin.media.variant');
Route::get('/admin/media-download/original/{mediaAsset}', [AdminMediaController::class, 'download'])
    ->name('admin.media.download');
Route::get('/admin/media-download/selected', [AdminMediaController::class, 'downloadSelected'])
    ->name('admin.media.download-selected');

Route::get('/media/original/{mediaAsset}', [PublicMediaController::class, 'original'])->name('media.original');
Route::get('/media/variant/{mediaVariant}', [PublicMediaController::class, 'variant'])
    ->middleware(EnsureMediaVariantFile::class)
    ->name('media.variant');

Route::middleware(EnforceHomePresentationGate::class)->group(function (): void {
    Route::get('/artworks/{slug}', [PublicArtworkController::class, 'show'])->name('artworks.show');
    Route::get('/{section}/{slug}', [PublicSiteSectionController::class, 'journalEntry'])
        ->where(['section' => '[a-z0-9]+(?:-[a-z0-9]+)*', 'slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'])
        ->name('journal.show');
    Route::get('/{section}', [PublicSiteSectionController::class, 'show'])
        ->where('section', '[a-z0-9]+(?:-[a-z0-9]+)*')
        ->name('site.section');
});
