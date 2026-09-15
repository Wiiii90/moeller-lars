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

it('keeps the storage stage in upload capacity destinations order without duplicate header actions', function (): void {
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

it('uses canonical icon-labelled actions in the storage table', function (): void {
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
        ->assertDontSee('admin-add-row--compact', false);
});

it('reuses the shared storage capacity component on the dashboard', function (): void {
    $dashboardView = file_get_contents(resource_path('views/filament/pages/dashboard.blade.php'));

    expect($dashboardView)
        ->toContain('<x-admin.storage-capacity-visual')
        ->toContain(':capacity="$storage"')
        ->toContain(':breakdown="$storage[\'breakdown\']"')
        ->toContain(':segments="$storage[\'segments\']"');
});
