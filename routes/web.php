<?php

use App\Http\Controllers\AdminMediaController;
use App\Http\Controllers\PublicArtworkController;
use App\Http\Controllers\PublicBlogController;
use App\Http\Controllers\PublicContactController;
use App\Http\Controllers\PublicCvController;
use App\Http\Controllers\PublicExhibitionController;
use App\Http\Controllers\PublicMediaController;
use App\Http\Controllers\PublicSeoController;
use App\Http\Middleware\ProtectArtistPreview;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicArtworkController::class, 'home'])->name('home');
Route::get('/cv', [PublicCvController::class, 'show'])->name('cv');
Route::get('/exhibitions', [PublicExhibitionController::class, 'index'])->name('exhibitions.index');
Route::get('/contact', [PublicContactController::class, 'show'])->name('contact');
Route::post('/contact', [PublicContactController::class, 'submit'])
    ->middleware('throttle:5,1')
    ->name('contact.submit');
Route::get('/blog', [PublicBlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [PublicBlogController::class, 'show'])->name('blog.show');
Route::get('/sitemap.xml', [PublicSeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/robots.txt', [PublicSeoController::class, 'robots'])->name('seo.robots');

Route::middleware(ProtectArtistPreview::class)
    ->prefix('preview')
    ->name('preview.')
    ->group(function (): void {
        Route::get('/', [PublicArtworkController::class, 'home'])->name('home');
        Route::get('/cv', [PublicCvController::class, 'show'])->name('cv');
        Route::get('/exhibitions', [PublicExhibitionController::class, 'index'])->name('exhibitions.index');
        Route::get('/contact', [PublicContactController::class, 'show'])->name('contact');
        Route::get('/blog', [PublicBlogController::class, 'index'])->name('blog.index');
        Route::get('/blog/{slug}', [PublicBlogController::class, 'show'])->name('blog.show');
        Route::get('/artworks/{slug}', [PublicArtworkController::class, 'show'])->name('artworks.show');
        Route::get('/{category}', [PublicArtworkController::class, 'category'])
            ->where('category', '[a-z0-9]+(?:-[a-z0-9]+)*')
            ->name('artworks.category');
    });

Route::get('/admin/media-preview/original/{mediaAsset}', [AdminMediaController::class, 'original'])
    ->name('admin.media.original');
Route::get('/admin/media-preview/variant/{mediaVariant}', [AdminMediaController::class, 'variant'])
    ->name('admin.media.variant');
Route::get('/artworks/{slug}', [PublicArtworkController::class, 'show'])->name('artworks.show');
Route::get('/media/original/{mediaAsset}', [PublicMediaController::class, 'original'])->name('media.original');
Route::get('/media/variant/{mediaVariant}', [PublicMediaController::class, 'variant'])->name('media.variant');
Route::get('/{category}', [PublicArtworkController::class, 'category'])
    ->where('category', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('artworks.category');
