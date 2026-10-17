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


it('recognizes rights-only Working changes in the canonical publication summary and records a checkpoint', function (): void {
    config()->set('media.rights_preparation_enabled', true);
    config()->set('media.rights_delivery_enabled', false);

    $artifact = MediaRightsArtifact::query()->create([
        'media_asset_id' => 917,
        'role' => 'original',
        'revision' => str_repeat('1', 64),
        'storage_key' => 'variants/rights/'.str_repeat('1', 64).'.jpg',
        'source_sha256' => str_repeat('2', 64),
        'sha256' => str_repeat('3', 64),
        'byte_size' => 172,
        'mime_type' => 'image/jpeg',
        'created_at' => now(),
    ]);
    app(MediaRightsPublicationSelections::class)->selectWorking($artifact, 'original', 917);

    $publication = app(\App\Domain\Publication\PublicationService::class);
    $summary = $publication->pendingSummary();
    expect(collect($summary['groups'])->firstWhere('entity', 'Image rights files')['count'] ?? null)->toBe(1)
        ->and($publication->hasPendingChanges())->toBeTrue()
        ->and(collect($publication->pendingDetails()['rows'])->firstWhere('table', 'media_rights_artifact_selections')['row_key'] ?? null)
        ->toBe('original:917');

    $actor = \App\Models\User::factory()->admin()->create();
    $checkpoint = $publication->commit($actor, 'Rights files updated');
    expect($checkpoint)->toBeInstanceOf(PublicationCheckpoint::class)
        ->and(app(MediaRightsPublicationSelections::class)->pendingCount($checkpoint))->toBe(0)
        ->and((int) DB::table('media_rights_artifact_selections')
            ->where('scope_key', 'checkpoint:'.$checkpoint->getKey())
            ->value('media_rights_artifact_id'))->toBe((int) $artifact->getKey());
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
