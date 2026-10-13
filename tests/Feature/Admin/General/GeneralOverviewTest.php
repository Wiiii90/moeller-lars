<?php

use App\Filament\Pages\General;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Models\PublicContentSetting;
use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

require_once __DIR__.'/GeneralFixtures.php';

it('uses General as the canonical singleton route with no global save action', function (): void {
    $settings = PublicContentSetting::general();

    expect(parse_url(General::getUrl(), PHP_URL_PATH))->toBe('/admin/general')
        ->and(PublicContentSettingResource::getNavigationUrl())->toBe(General::getUrl());

    $this->get(General::getUrl())
        ->assertOk()
        ->assertSee('General');

    $legacyUrl = PublicContentSettingResource::getUrl('edit', ['record' => $settings]);
    $this->get($legacyUrl)->assertRedirect(General::getUrl());
});
