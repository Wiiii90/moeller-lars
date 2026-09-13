<?php

use App\Domain\Media\MediaCapacityService;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('storage-visualization-test');
    config([
        'media.disk' => 'storage-visualization-test',
        'media.quota_bytes' => 1_000,
    ]);
    app(MediaCapacityService::class)->forgetCachedSnapshot();
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

it('renders one full allowance donut and keeps remaining capacity non-interactive', function (): void {
    Storage::disk(config('media.disk'))->put('originals/orphan.jpg', str_repeat('x', 100));
    app(MediaCapacityService::class)->refreshCachedSnapshot();

    $this->get('/admin/storage')
        ->assertOk()
        ->assertSee('Total capacity')
        ->assertSee('admin-storage__capacity-base', false)
        ->assertSee('percent of storage allowance', false)
        ->assertDontSee('admin-storage__capacity-used', false)
        ->assertDontSee('admin-storage__capacity-marker', false);
});
