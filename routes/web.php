<?php

use App\Http\Controllers\LegacyRedirectController;
use App\Http\Controllers\PublicArtworkController;
use App\Http\Controllers\PublicMediaController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicArtworkController::class, 'home'])->name('home');

foreach (['paintings', 'prints', 'drawings', 'cyanotype', 'bichromate', 'litho', 'photo', 'ignis', 'other'] as $slug) {
    Route::get('/'.$slug, [PublicArtworkController::class, 'category'])->defaults('category', $slug)->name('artworks.'.$slug);
}

Route::get('/artworks/{slug}', [PublicArtworkController::class, 'show'])->name('artworks.show');
Route::get('/media/original/{mediaAsset}', [PublicMediaController::class, 'original'])->name('media.original');
Route::get('/media/variant/{mediaVariant}', [PublicMediaController::class, 'variant'])->name('media.variant');
Route::get('/index.php', LegacyRedirectController::class);
