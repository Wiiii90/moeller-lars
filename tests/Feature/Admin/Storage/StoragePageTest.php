<?php

use App\Domain\Media\MediaCapacityService;
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
        ->toContain('Refresh storage measurement')
        ->toContain('Free storage');
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

    $this->get('/admin/storage')
        ->assertOk()
        ->assertSee('Details')
        ->assertSee('Edit')
        ->assertSee('Download')
        ->assertSee('Delete');
});
