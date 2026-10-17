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
    $checkpoint = new PublicationCheckpoint;
    $checkpoint->setRawAttributes(['id' => 409], true);

    $selections->captureCheckpoint($checkpoint);
    expect(DB::table('media_rights_artifact_selections')
        ->where('scope_key', 'checkpoint:409')->value('media_rights_artifact_id'))
        ->toBe($artifact->getKey());

    $selections->restoreWorking(null);
    expect(DB::table('media_rights_artifact_selections')
        ->where('scope_key', 'working')->count())->toBe(0)
        ->and(DB::table('media_rights_artifact_selections')
            ->where('scope_key', 'checkpoint:409')->count())->toBe(1);

    $selections->restoreWorking($checkpoint);
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
