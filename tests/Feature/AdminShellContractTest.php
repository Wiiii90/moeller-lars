<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\StorageCapacity;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('uses the artist-facing shell navigation and public brand target', function (): void {
    $panel = Filament::getCurrentPanel();

    expect($panel)->not->toBeNull()
        ->and($panel?->getHomeUrl())->toBe(route('home'))
        ->and(MediaAssetResource::getNavigationGroup())->toBe('Content')
        ->and(StorageCapacity::getNavigationGroup())->toBe('Insights')
        ->and(MediaAssetResource::getNavigationIcon())->toBe(Heroicon::OutlinedFolderOpen)
        ->and(StorageCapacity::getNavigationIcon())->toBe(Heroicon::OutlinedCircleStack);

    $this->get('/admin')
        ->assertSuccessful()
        ->assertSee('Website')
        ->assertSee('Pages')
        ->assertSee('Home')
        ->assertSee('Vita')
        ->assertSee('EXHIBITIONS')
        ->assertSee('Blog')
        ->assertSee('Contact')
        ->assertSee('Content')
        ->assertSee('Insights')
        ->assertSee('Settings')
        ->assertSee('General')
        ->assertDontSee('Library');

    $this->get(PublicContentSettingResource::getNavigationUrl())
        ->assertSuccessful()
        ->assertSee('Site identity')
        ->assertSee('Contact delivery');
});

it('renders direct sign out and compact quick actions under one composed dashboard heading', function (): void {
    expect((new Dashboard)->getHeading())->toBeNull();

    $this->get('/admin')
        ->assertSuccessful()
        ->assertSee('<form class="artist-topbar-sign-out" method="POST"', false)
        ->assertSee('Sign out')
        ->assertSee('aria-label="Quick actions"', false)
        ->assertSeeInOrder([
            'Website at a glance',
            'Add artwork',
            'Add exhibition',
            'Add Vita / CV entry',
            'Add blog post',
            'Manage pages',
            'Open public site',
        ])
        ->assertSee('Contact form readiness')
        ->assertSee('Delivery recipient')
        ->assertSee('Mail transport');
});
