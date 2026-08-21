<?php

use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Models\MediaAsset;
use App\Models\PublicContentSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('stores safe typed social links in General settings', function (): void {
    $settings = PublicContentSetting::general();
    $settings->update([
        'social_links' => [
            ['platform' => 'instagram', 'url' => 'https://www.instagram.com/lars/', 'visible' => true],
            ['platform' => 'facebook', 'url' => 'https://www.facebook.com/lars.artist/', 'visible' => false],
        ],
    ]);

    expect($settings->fresh()->social_links)->toEqual([
        ['platform' => 'instagram', 'url' => 'https://www.instagram.com/lars/', 'visible' => true],
        ['platform' => 'facebook', 'url' => 'https://www.facebook.com/lars.artist/', 'visible' => false],
    ]);
});

it('rejects unsupported social platforms and unsafe social urls', function (): void {
    expect(fn () => PublicContentSetting::general()->update([
        'social_links' => [
            ['platform' => 'myspace', 'url' => 'https://example.test/profile', 'visible' => true],
        ],
    ]))->toThrow(ValidationException::class);

    expect(fn () => PublicContentSetting::general()->update([
        'social_links' => [
            ['platform' => 'instagram', 'url' => 'javascript:alert(1)', 'visible' => true],
        ],
    ]))->toThrow(ValidationException::class);
});

it('keeps favicon selection image-only at the model boundary', function (): void {
    $document = MediaAsset::create([
        'storage_key' => 'originals/statement.pdf',
        'original_filename' => 'statement.pdf',
        'mime_type' => 'application/pdf',
        'byte_size' => 100,
        'sha256' => hash('sha256', 'statement'),
        'state' => 'available',
    ]);

    expect(fn () => PublicContentSetting::general()->update([
        'favicon_media_asset_id' => $document->getKey(),
    ]))->toThrow(ValidationException::class);
});

it('keeps transport credentials and topology out of General', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $this->get(PublicContentSettingResource::getUrl('edit', ['record' => PublicContentSetting::general()]))
        ->assertSuccessful()
        ->assertSee('Private delivery recipient')
        ->assertDontSee('SMTP Host')
        ->assertDontSee('SMTP Username')
        ->assertDontSee('SMTP Password')
        ->assertDontSee('TLS secret');
});
