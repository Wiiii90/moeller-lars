<?php

use App\Filament\Pages\HomePresentation;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Http\Controllers\AdminMediaController;
use App\Http\Controllers\PublicArtworkController;
use App\Http\Controllers\PublicContactController;
use App\Http\Controllers\PublicHomeController;
use App\Http\Controllers\PublicMediaController;
use App\Http\Controllers\PublicSeoController;
use App\Http\Controllers\PublicSiteSectionController;
use App\Http\Middleware\EnforceHomePresentationGate;
use App\Http\Middleware\ProtectArtistPreview;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicHomeController::class, 'show'])->name('home');
Route::post('/contact', [PublicContactController::class, 'submit'])
    ->middleware([EnforceHomePresentationGate::class, 'throttle:5,1'])
    ->name('contact.submit');
Route::get('/sitemap.xml', [PublicSeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/robots.txt', [PublicSeoController::class, 'robots'])->name('seo.robots');

Route::middleware(ProtectArtistPreview::class)
    ->prefix('preview')
    ->name('preview.')
    ->group(function (): void {
        Route::get('/', [PublicHomeController::class, 'show'])->name('home');
        Route::get('/artworks/{slug}', [PublicArtworkController::class, 'show'])->name('artworks.show');
        Route::get('/{section}/{slug}', [PublicSiteSectionController::class, 'journalEntry'])
            ->where(['section' => '[a-z0-9]+(?:-[a-z0-9]+)*', 'slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'])
            ->name('journal.show');
        Route::get('/{section}', [PublicSiteSectionController::class, 'show'])
            ->where('section', '[a-z0-9]+(?:-[a-z0-9]+)*')
            ->name('site.section');
    });

Route::get('/admin/home', fn () => redirect()->to(HomePresentation::getUrl(), 301));
Route::get('/admin/media-assets', fn () => redirect()->to(MediaAssetResource::getUrl('index'), 301));
Route::get('/admin/media-assets/{record}/edit', fn (string $record) => redirect()->to(MediaAssetResource::getUrl('edit', ['record' => $record]), 301))
    ->whereNumber('record');
Route::get('/admin/media-assets/{record}', fn (string $record) => redirect()->to(MediaAssetResource::getUrl('view', ['record' => $record]), 301))
    ->whereNumber('record');

Route::get('/admin/media-preview/original/{mediaAsset}', [AdminMediaController::class, 'original'])
    ->name('admin.media.original');
Route::get('/admin/media-preview/variant/{mediaVariant}', [AdminMediaController::class, 'variant'])
    ->name('admin.media.variant');
Route::get('/admin/media-download/original/{mediaAsset}', [AdminMediaController::class, 'download'])
    ->name('admin.media.download');
Route::get('/admin/media-download/selected', [AdminMediaController::class, 'downloadSelected'])
    ->name('admin.media.download-selected');

Route::get('/media/original/{mediaAsset}', [PublicMediaController::class, 'original'])->name('media.original');
Route::get('/media/variant/{mediaVariant}', [PublicMediaController::class, 'variant'])->name('media.variant');

Route::middleware(EnforceHomePresentationGate::class)->group(function (): void {
    Route::get('/artworks/{slug}', [PublicArtworkController::class, 'show'])->name('artworks.show');
    Route::get('/{section}/{slug}', [PublicSiteSectionController::class, 'journalEntry'])
        ->where(['section' => '[a-z0-9]+(?:-[a-z0-9]+)*', 'slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'])
        ->name('journal.show');
    Route::get('/{section}', [PublicSiteSectionController::class, 'show'])
        ->where('section', '[a-z0-9]+(?:-[a-z0-9]+)*')
        ->name('site.section');
});