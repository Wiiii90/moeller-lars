<?php

use App\Domain\Media\MediaCapacityService;
use App\Filament\Support\AdminIcon;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('storage-page-test');
    config([
        'media.disk' => 'storage-page-test',
        'media.quota_bytes' => 1_000,
    ]);
    app(MediaCapacityService::class)->forgetCachedSnapshot();
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

it('presents upload capacity and distribution in the storage workspace', function (): void {
    Storage::disk(config('media.disk'))->put('originals/orphan.jpg', str_repeat('x', 100));
    app(MediaCapacityService::class)->refreshCachedSnapshot();

    $html = $this->get('/admin/storage')->assertOk()->getContent();

    expect($html)->toContain('Upload Media Files')
        ->and($html)->not->toContain('admin-storage__visual-main')
        ->and(strpos($html, 'admin-storage__upload admin-visual-stage__pane'))->toBeLessThan(strpos($html, 'admin-storage__capacity-group admin-visual-stage__pane'))
        ->and(strpos($html, 'admin-storage__capacity-group admin-visual-stage__pane'))->toBeLessThan(strpos($html, 'admin-storage__distribution admin-visual-stage__pane'))
        ->and(strpos($html, 'admin-storage__capacity-actions'))->toBeLessThan(strpos($html, 'admin-storage__distribution admin-visual-stage__pane'))
        ->and($html)->toContain('Refresh storage measurement')
        ->and($html)->toContain('Free storage now');
});

it('exposes the canonical storage actions', function (): void {
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
        ->and(AdminIcon::Refresh->value)->toBe('heroicon-o-arrow-path')
        ->and(AdminIcon::ReclaimStorage->value)->toBe('heroicon-o-archive-box-x-mark');

    $this->get('/admin/storage')
        ->assertOk()
        ->assertSee('Details')
        ->assertSee('Edit')
        ->assertSee('Download')
        ->assertSee('Delete');
});
