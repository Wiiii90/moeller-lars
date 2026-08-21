<?php

use App\Domain\Media\MediaCapacityService;
use App\Filament\Widgets\ArtistDashboard;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('renders the final at-a-glance dashboard without rebuilding the Pages or Gallery overview', function (): void {
    config(['analytics.matomo.reporting_enabled' => false]);
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ArtistDashboard::class)
        ->assertSee('Website at a glance')
        ->assertSee('Traffic & engagement')
        ->assertSee('Needs attention')
        ->assertSee('Recent activity')
        ->assertSee('Storage headroom')
        ->assertDontSee('Content overview');
});

it('keeps dashboard storage reads cache-only on a cache miss', function (): void {
    Storage::fake('dashboard-capacity');
    Cache::flush();
    config([
        'media.disk' => 'dashboard-capacity',
        'media.quota_bytes' => 100,
    ]);
    Storage::disk('dashboard-capacity')->put('originals/existing.jpg', str_repeat('a', 20));

    $capacity = app(MediaCapacityService::class);

    expect($capacity->cachedSnapshotIfAvailable())->toBeNull();
    expect($capacity->cachedSnapshot()['authoritative_bytes'])->toBe(20);
    expect($capacity->cachedSnapshotIfAvailable()['authoritative_bytes'])->toBe(20);
});
