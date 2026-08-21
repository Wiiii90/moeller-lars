<?php

use App\Filament\Pages\StorageCapacity;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
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
        ->and(StorageCapacity::getNavigationGroup())->toBe('Content')
        ->and(MediaAssetResource::getNavigationIcon())->toBe(Heroicon::OutlinedFolderOpen)
        ->and(StorageCapacity::getNavigationIcon())->toBe(Heroicon::OutlinedCircleStack);

    $this->get('/admin')
        ->assertSuccessful()
        ->assertSee('Website')
        ->assertSee('Content')
        ->assertSee('Insights')
        ->assertSee('Settings')
        ->assertDontSee('Library');
});

it('renders direct sign out and compact quick actions with the retained dashboard heading', function (): void {
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
        ]);
});
