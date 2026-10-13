<?php

use App\Domain\Publication\PublicationService;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('denies public delivery of an unreferenced original even after publication, while the admin can access it', function (): void {
    Storage::fake('public-media-access-test');
    config(['media.disk' => 'public-media-access-test']);

    $contents = 'synthetic-media-content';
    $storageKey = 'originals/not-public.jpg';
    Storage::disk(config('media.disk'))->put($storageKey, $contents);

    $asset = MediaAsset::query()->create([
        'storage_key' => $storageKey,
        'original_filename' => 'not-public.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => strlen($contents),
        'sha256' => hash('sha256', $contents),
        'state' => 'available',
        'alt_text' => 'Private media fixture',
    ]);

    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');

    expect(app(PublicationService::class)->commit($actor))->not->toBeNull();

    $this->get(route('media.original', ['mediaAsset' => $asset->getKey()]))
        ->assertNotFound();

    $this->get(route('admin.media.original', ['mediaAsset' => $asset->getKey()]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Sendfile', Storage::disk(config('media.disk'))->path($storageKey));
});
