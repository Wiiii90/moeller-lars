<?php



require_once __DIR__.'/PublicationVersionFixtures.php';

it('keeps physical media while any retained publication version still references the asset', function (): void {
    config()->set('media.disk', 'publication-version-test');
    Storage::fake('publication-version-test');
    $actor = User::factory()->admin()->create();
    $publication = app(PublicationService::class);
    $key = 'media/versioned-file.jpg';

    $asset = MediaAsset::query()->create([
        'storage_key' => $key,
        'original_filename' => 'versioned-file.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'test'),
        'state' => 'available',
        'copyright_notice_mode' => MediaAsset::COPYRIGHT_INHERIT,
    ]);
    Storage::disk('publication-version-test')->put($key, 'test');
    $publication->commit($actor, 'Publish media');

    $asset->setAttribute('state', 'deleted');
    $asset->save();
    $publication->commit($actor, 'Delete media from live state');

    app(PublicationMediaCleanupService::class)->deleteNow([$key]);

    Storage::disk('publication-version-test')->assertExists($key);
    expect(DB::table('publication_media_cleanups')
        ->where('media_asset_id', $asset->getKey())
        ->where('storage_key', $key)
        ->exists())->toBeTrue();
});
