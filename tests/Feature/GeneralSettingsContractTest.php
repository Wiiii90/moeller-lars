<?php

use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Models\MediaAsset;
use App\Models\PublicContentSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('preserves legacy Instagram data in the typed social-link contract', function () {
    $settings = PublicContentSetting::query()->sole();
    $settings->update([
        'instagram_handle' => 'legacy_artist',
        'show_instagram' => false,
        'social_links' => null,
    ]);

    expect($settings->refresh()->social_links)->toEqual([[
        'platform' => 'instagram',
        'url' => 'https://www.instagram.com/legacy_artist/',
        'visible' => false,
    ]]);
});

it('renders typed social links without exposing hidden links', function () {
    PublicContentSetting::query()->sole()->update([
        'contact_state' => 'enabled',
        'social_links' => [
            ['platform' => 'instagram', 'url' => 'https://www.instagram.com/lars/', 'visible' => true],
            ['platform' => 'facebook', 'url' => 'https://www.facebook.com/lars.artist/', 'visible' => true],
            ['platform' => 'youtube', 'url' => 'https://www.youtube.com/@hidden', 'visible' => false],
        ],
    ]);

    $this->get('/contact')
        ->assertSuccessful()
        ->assertSee('https://www.instagram.com/lars/', false)
        ->assertSee('https://www.facebook.com/lars.artist/', false)
        ->assertDontSee('https://www.youtube.com/@hidden', false);
});

it('rejects unsupported social platforms and unsafe social urls', function () {
    expect(fn () => PublicContentSetting::query()->sole()->update([
        'social_links' => [
            ['platform' => 'myspace', 'url' => 'https://example.test/profile', 'visible' => true],
        ],
    ]))->toThrow(ValidationException::class);

    expect(fn () => PublicContentSetting::query()->sole()->update([
        'social_links' => [
            ['platform' => 'instagram', 'url' => 'javascript:alert(1)', 'visible' => true],
        ],
    ]))->toThrow(ValidationException::class);
});

it('keeps favicon selection image-only at the model boundary', function () {
    $document = MediaAsset::create([
        'storage_key' => 'originals/statement.pdf',
        'original_filename' => 'statement.pdf',
        'mime_type' => 'application/pdf',
        'byte_size' => 100,
        'sha256' => hash('sha256', 'statement'),
        'state' => 'available',
    ]);

    expect(fn () => PublicContentSetting::query()->sole()->update([
        'favicon_media_asset_id' => $document->getKey(),
    ]))->toThrow(ValidationException::class);
});

it('does not expose smtp credentials or transport topology in General', function () {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $this->get(PublicContentSettingResource::getUrl('edit', ['record' => 1]))
        ->assertSuccessful()
        ->assertSee('Private delivery recipient')
        ->assertSee('SMTP credentials and mail-server configuration remain runtime/platform secrets')
        ->assertDontSee('SMTP Host')
        ->assertDontSee('SMTP Username')
        ->assertDontSee('SMTP Password')
        ->assertDontSee('DKIM')
        ->assertDontSee('TLS secret');
});
