<?php

use App\Filament\Widgets\ArtistDashboard;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('keeps a cold Matomo report off the initial dashboard response', function (): void {
    Cache::flush();
    config([
        'analytics.matomo.reporting_enabled' => true,
        'analytics.matomo.tracking_enabled' => false,
        'analytics.matomo.base_url' => 'https://matomo.example.test',
        'analytics.matomo.site_id' => 1,
        'analytics.matomo.api_token' => 'test-token',
        'analytics.matomo.report_timeout_seconds' => 5,
        'analytics.matomo.report_cache_seconds' => 600,
        'analytics.matomo.report_stale_seconds' => 86400,
    ]);
    Http::fake();

    $this->get('/admin')
        ->assertSuccessful()
        ->assertSee('Loading visitor signals');

    Http::assertNothingSent();
});

it('distinguishes unavailable analytics from a measured zero after the deferred load', function (): void {
    config(['analytics.matomo.reporting_enabled' => false]);

    Livewire::test(ArtistDashboard::class)
        ->assertSet('analytics.loaded', false)
        ->call('loadAnalytics')
        ->assertSet('analytics.loaded', true)
        ->assertSet('analytics.status', 'disabled')
        ->assertSee('Visitor analytics is unavailable.')
        ->assertSee('Matomo reporting is disabled.');
});
