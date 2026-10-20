<?php

use App\Domain\Publication\MediaRightsPublicationSelections;
use App\Models\MediaRightsArtifact;
use App\Models\PublicationCheckpoint;
use Illuminate\Support\Facades\DB;

it('captures immutable rights choices with checkpoints and restores only the working selection', function (): void {
    config()->set('media.rights_preparation_enabled', true);
    config()->set('media.rights_delivery_enabled', true);
    $selections = app(MediaRightsPublicationSelections::class);
    $artifact = MediaRightsArtifact::query()->create([
        'media_asset_id' => 901,
        'role' => 'original',
        'revision' => str_repeat('a', 64),
        'storage_key' => 'variants/rights/'.str_repeat('a', 64).'.jpg',
        'source_sha256' => str_repeat('b', 64),
        'sha256' => str_repeat('c', 64),
        'byte_size' => 512,
        'mime_type' => 'image/jpeg',
        'created_at' => now(),
    ]);
    $selections->selectWorking($artifact, 'original', 901);
    expect($selections->pendingCount(null))->toBe(1)
        ->and($selections->pendingDetails(null, 2))->toHaveCount(1);
    $checkpoint = new PublicationCheckpoint;
    $checkpoint->setRawAttributes(['id' => 409], true);

    $selections->captureCheckpoint($checkpoint);
    expect($selections->pendingCount($checkpoint))->toBe(0);
    expect(DB::table('media_rights_artifact_selections')
        ->where('scope_key', 'checkpoint:409')->value('media_rights_artifact_id'))
        ->toBe($artifact->getKey());

    $selections->restoreWorking(null);
    expect($selections->pendingCount($checkpoint))->toBe(1)
        ->and((string) $selections->pendingDetails($checkpoint, 1)[0]->change_kind)->toBe('removed');
    expect(DB::table('media_rights_artifact_selections')
        ->where('scope_key', 'working')->count())->toBe(0)
        ->and(DB::table('media_rights_artifact_selections')
            ->where('scope_key', 'checkpoint:409')->count())->toBe(1);

    $selections->restoreWorking($checkpoint);
    expect($selections->pendingCount($checkpoint))->toBe(0);
    expect(DB::table('media_rights_artifact_selections')
        ->where('scope_key', 'working')->value('media_rights_artifact_id'))
        ->toBe($artifact->getKey());

    expect(fn () => $selections->captureCheckpoint($checkpoint))
        ->toThrow(RuntimeException::class);
});


it('keeps legacy media selection database-free until rights-artifact delivery is enabled', function (): void {
    config()->set('media.rights_preparation_enabled', false);
    config()->set('media.rights_delivery_enabled', false);
    $selections = app(MediaRightsPublicationSelections::class);
    $asset = new \App\Models\MediaAsset;
    $asset->setRawAttributes(['id' => 901, 'mime_type' => 'image/jpeg'], true);
    $checkpoint = new PublicationCheckpoint;
    $checkpoint->setRawAttributes(['id' => 409], true);

    expect($selections->enabled())->toBeFalse()
        ->and($selections->pendingCount(null))->toBe(0)
        ->and($selections->pendingDetails(null, 3))->toBe([])
        ->and($selections->selectedForPublic($asset))->toBeNull()
        ->and($selections->selectedForWorking($asset))->toBeNull();
    $selections->captureCheckpoint($checkpoint);
    $selections->restoreWorking($checkpoint);
    expect(fn () => $selections->selectWorking(new MediaRightsArtifact, 'original', 901))
        ->toThrow(RuntimeException::class, 'not enabled');
});


it('allows offline preparation mode without enabling public artifact selection', function (): void {
    config()->set('media.rights_preparation_enabled', true);
    config()->set('media.rights_delivery_enabled', false);
    $selections = app(MediaRightsPublicationSelections::class);
    $asset = new \App\Models\MediaAsset;
    $asset->setRawAttributes(['id' => 901, 'mime_type' => 'image/jpeg'], true);
    expect($selections->enabled())->toBeTrue()
        ->and($selections->deliveryEnabled())->toBeFalse()
        ->and($selections->selectedForPublic($asset))->toBeNull();
});


it('publishes a complete rights-only revision through the canonical checkpoint', function (): void {
    \Illuminate\Support\Facades\Storage::fake('rights-publication');
    config()->set('media.disk', 'rights-publication');
    config()->set('media.rights_preparation_enabled', false);
    config()->set('media.rights_delivery_enabled', false);

    $originalBytes = 'original-source';
    $thumbnailBytes = 'thumbnail-source';
    $disk = \Illuminate\Support\Facades\Storage::disk('rights-publication');
    $disk->put('originals/rights-publication.jpg', $originalBytes);
    $disk->put('variants/rights-publication.webp', $thumbnailBytes);

    $asset = \App\Models\MediaAsset::query()->create([
        'storage_key' => 'originals/rights-publication.jpg',
        'original_filename' => 'rights-publication.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => strlen($originalBytes),
        'sha256' => hash('sha256', $originalBytes),
        'state' => 'available',
        'alt_text' => 'Rights publication image',
        'width' => 480,
        'height' => 720,
    ]);
    $thumbnail = \App\Models\MediaVariant::query()->create([
        'media_asset_id' => $asset->getKey(),
        'variant_kind' => 'thumbnail',
        'transform_profile' => 'public-v1',
        'state' => 'available',
        'storage_key' => 'variants/rights-publication.webp',
        'mime_type' => 'image/webp',
        'byte_size' => strlen($thumbnailBytes),
        'sha256' => hash('sha256', $thumbnailBytes),
        'width' => 480,
        'height' => 720,
    ]);

    $publication = app(\App\Domain\Publication\PublicationService::class);
    $actor = \App\Models\User::factory()->admin()->create();
    expect($publication->commit($actor, 'Media baseline'))->toBeInstanceOf(PublicationCheckpoint::class);

    config()->set('media.rights_preparation_enabled', true);
    $selections = app(MediaRightsPublicationSelections::class);
    $revisionService = app(\App\Domain\Media\MediaRightsArtifactRevision::class);

    foreach ([
        ['original', $asset, $originalBytes, 'image/jpeg', 'jpg'],
        ['thumbnail', $thumbnail, $thumbnailBytes, 'image/webp', 'webp'],
    ] as [$role, $source, $sourceBytes, $mime, $extension]) {
        $revision = $revisionService->forSource($asset, $source);
        $rightsBytes = 'rights:'.$role.':'.$sourceBytes;
        $key = 'variants/rights/'.$revision.'.'.$extension;
        $disk->put($key, $rightsBytes);
        $artifact = MediaRightsArtifact::query()->create([
            'media_asset_id' => $asset->getKey(),
            'role' => $role,
            'revision' => $revision,
            'storage_key' => $key,
            'source_sha256' => $source->getAttribute('sha256'),
            'sha256' => hash('sha256', $rightsBytes),
            'byte_size' => strlen($rightsBytes),
            'mime_type' => $mime,
            'created_at' => now(),
        ]);
        $selections->selectWorking($artifact, $role, (int) $source->getKey());
    }

    expect($selections->workingAssetReadiness($asset)['state'])->toBe('ready');
    $summary = $publication->pendingSummary();
    expect($summary['total'])->toBe(2)
        ->and(collect($summary['groups'])->firstWhere('entity', 'Image rights files')['count'] ?? null)->toBe(2);
    $changes = collect($publication->pendingDetails()['rows'])
        ->where('table', 'media_rights_artifact_selections')->pluck('row_key')->sort()->values()->all();
    expect($changes)->toBe(['original:'.$asset->getKey(), 'thumbnail:'.$thumbnail->getKey()]);

    $checkpoint = $publication->commit($actor, 'Publish image file rights');
    expect($checkpoint)->toBeInstanceOf(PublicationCheckpoint::class)
        ->and($selections->pendingCount($checkpoint))->toBe(0)
        ->and(DB::table('media_rights_artifact_selections')
            ->where('scope_key', 'checkpoint:'.$checkpoint->getKey())->count())->toBe(2);
});


it('rejects a stale rights-file selection after the asset copyright facts change', function (): void {
    config()->set('media.rights_preparation_enabled', true);
    config()->set('media.rights_delivery_enabled', false);

    $asset = new \App\Models\MediaAsset;
    $asset->setRawAttributes([
        'id' => 923,
        'mime_type' => 'image/jpeg',
        'sha256' => str_repeat('6', 64),
        'file_creator' => 'First photographer',
        'copyright_notice_mode' => \App\Models\MediaAsset::COPYRIGHT_OVERRIDE,
        'copyright_notice' => '© First photographer',
    ], true);

    $revision = app(\App\Domain\Media\MediaRightsArtifactRevision::class)->forSource($asset, $asset);
    $artifact = MediaRightsArtifact::query()->create([
        'media_asset_id' => 923,
        'role' => 'original',
        'revision' => $revision,
        'storage_key' => 'variants/rights/'.$revision.'.jpg',
        'source_sha256' => str_repeat('6', 64),
        'sha256' => str_repeat('7', 64),
        'byte_size' => 100,
        'mime_type' => 'image/jpeg',
        'created_at' => now(),
    ]);
    $selections = app(MediaRightsPublicationSelections::class);
    $selections->selectWorking($artifact, 'original', 923);
    expect($selections->selectedForWorking($asset)?->getKey())->toBe($artifact->getKey());

    $asset->setAttribute('file_creator', 'Different photographer');
    expect(fn () => $selections->selectedForWorking($asset))
        ->toThrow(RuntimeException::class, 'out of date');
});
