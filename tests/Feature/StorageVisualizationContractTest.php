<?php

use App\Domain\Media\MediaCapacityService;
use App\Filament\Support\AdminIcon;
use App\Models\MediaAsset;
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

it('renders capacity as exact overview plus magnified used composition without focusable svg slices', function (): void {
    Storage::disk(config('media.disk'))->put('originals/orphan.jpg', str_repeat('x', 100));
    app(MediaCapacityService::class)->refreshCachedSnapshot();

    $this->get('/admin/storage')
        ->assertOk()
        ->assertSee('admin-storage-capacity__lens', false)
        ->assertSee('admin-storage-capacity__overview-free', false)
        ->assertSee('admin-storage-capacity__detail-base', false)
        ->assertSee('admin-storage-capacity__segment--uncatalogued', false)
        ->assertSee('Used composition')
        ->assertDontSee('admin-storage-capacity__pie', false)
        ->assertDontSee('admin-storage__donut', false)
        ->assertDontSee('admin-storage__capacity-base', false);

    $capacityView = file_get_contents(resource_path('views/components/admin/storage-capacity-visual.blade.php'));

    expect($capacityView)
        ->not->toContain('role="button"')
        ->not->toContain('tabindex="0"')
        ->not->toContain('x-on:click')
        ->not->toContain('translate(var(--storage-slice-x');
});

it('orders the storage stage as upload capacity destinations without destination header actions or duplicate capacity copy', function (): void {
    Storage::disk(config('media.disk'))->put('originals/orphan.jpg', str_repeat('x', 100));
    app(MediaCapacityService::class)->refreshCachedSnapshot();

    $response = $this->get('/admin/storage')->assertOk();
    $html = $response->getContent();

    expect($html)->toContain('Upload Media Files')
        ->and(strpos($html, 'admin-storage__upload'))->toBeLessThan(strpos($html, 'admin-storage__capacity-group'))
        ->and(strpos($html, 'admin-storage__capacity-group'))->toBeLessThan(strpos($html, 'admin-storage__distribution'))
        ->and($html)->not->toContain('admin-storage__visual-actions')
        ->and($html)->not->toContain('admin-storage__capacity-copy')
        ->and($html)->not->toContain('Filter library')
        ->and($html)->toContain('Refresh storage measurement');
});

it('uses canonical icon-labelled actions and the shared data-table add row in storage tables', function (): void {
    MediaAsset::query()->create([
        'storage_key' => 'originals/action-test.jpg',
        'original_filename' => 'action-test.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 12,
        'sha256' => hash('sha256', 'action-test'),
        'state' => 'available',
        'alt_text' => 'Action test',
    ]);

    expect(AdminIcon::Details->value)->toBe('heroicon-o-information-circle')
        ->and(AdminIcon::Refresh->value)->toBe('heroicon-o-arrow-path');

    $this->get('/admin/storage')
        ->assertOk()
        ->assertSee('Details')
        ->assertSee('Edit')
        ->assertSee('Download')
        ->assertSee('Delete')
        ->assertSee('admin-add-row--data', false)
        ->assertDontSee('admin-add-row--compact', false);
});

it('reuses the shared storage capacity visual on the dashboard', function (): void {
    $dashboardView = file_get_contents(resource_path('views/filament/pages/dashboard.blade.php'));

    expect($dashboardView)
        ->toContain('<x-admin.storage-capacity-visual :capacity="$storage" compact />')
        ->not->toContain('admin-storage__donut')
        ->not->toContain('admin-storage__capacity-used');
});
