<?php

use App\Filament\Pages\General;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Models\PublicContentSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('rejects General requests after administrative access is lost', function (bool $guest): void {
    $actor = Auth::user();
    $component = Livewire::test(General::class);
    if ($guest) { Auth::guard('web')->logout(); } else { $actor->forceFill(['is_admin' => false])->save(); }
    $before = PublicContentSetting::general()->public_email;
    $component->set('data.public_email', 'unauthorized@example.invalid')->assertForbidden();
    expect(PublicContentSetting::query()->findOrFail(1)->public_email)->toBe($before);
})->with(['guest' => true, 'revoked admin' => false]);

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
