<?php

use App\Models\CvEntry;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function publishProfileVita(): void
{
    testSingletonSection(SiteSection::TYPE_VITA, [
        'navigation_label' => 'CV',
        'state' => 'published',
        'show_in_navigation' => true,
    ]);

    CvEntry::create([
        'section' => 'Biography',
        'title' => 'Biography',
        'state' => 'published',
        'position' => 0,
        'date_precision' => 'year',
        'year_text' => '2026',
    ]);
}

it('controls public profile details independently from the contact form', function () {
    publishProfileVita();

    PublicContentSetting::query()->findOrFail(1)->update([
        'public_email' => 'artist@example.test',
        'show_public_email' => false,
        'instagram_handle' => 'artist_account',
        'show_instagram' => false,
        'contact_state' => 'enabled',
        'profile_text_blocks' => [
            ['title' => 'Studio visits', 'body' => "By appointment.\nHamburg"],
        ],
    ]);

    $this->get('/cv')
        ->assertSuccessful()
        ->assertDontSee('mailto:artist@example.test', false)
        ->assertDontSee('https://www.instagram.com/artist_account/', false)
        ->assertSee('Studio visits')
        ->assertSee('By appointment.')
        ->assertSee('<form class="contact-form"', false);
});

it('serves an assigned favicon through the controlled public media route', function () {
    publishProfileVita();

    $asset = MediaAsset::create([
        'storage_key' => 'originals/favicon.png',
        'original_filename' => 'favicon.png',
        'mime_type' => 'image/png',
        'byte_size' => 100,
        'sha256' => hash('sha256', 'favicon'),
        'state' => 'available',
        'alt_text' => 'Lars Möller site icon',
        'width' => 256,
        'height' => 256,
    ]);
    $variant = MediaVariant::create([
        'media_asset_id' => $asset->getKey(),
        'variant_kind' => 'thumbnail',
        'storage_key' => 'variants/favicon.webp',
        'mime_type' => 'image/webp',
        'byte_size' => 50,
        'sha256' => hash('sha256', 'favicon-variant'),
        'transform_profile' => 'public-v1',
        'state' => 'available',
        'width' => 256,
        'height' => 256,
    ]);

    PublicContentSetting::query()->findOrFail(1)->update([
        'favicon_media_asset_id' => $asset->getKey(),
    ]);
    Storage::fake(config('media.disk'));
    Storage::disk(config('media.disk'))->put('variants/favicon.webp', 'favicon');

    $this->get('/cv')
        ->assertSuccessful()
        ->assertSee('<link rel="icon"', false)
        ->assertSee(route('media.variant', $variant), false);

    $this->get(route('media.variant', $variant))->assertSuccessful();
});
