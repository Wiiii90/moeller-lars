<?php

use App\Domain\Publication\PublicationService;
use App\Domain\Publication\PublicationVersionService;
use App\Models\AuditEvent;
use App\Models\MediaAsset;
use App\Models\PublicationCheckpoint;
use App\Models\User;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/PublicationVersionFixtures.php';

it('resets the complete working publication state to the current live version without deleting Activity', function (): void {
    $actor = User::factory()->admin()->create();
    $versions = app(PublicationVersionService::class);
    $beforeEvents = AuditEvent::query()->count();
    $live = publicationVersionDisclaimer('committed');

    publicationVersionSetDisclaimer('Uncommitted edit');
    expect(publicationVersionDisclaimer())->toBe('Uncommitted edit');

    $changed = $versions->resetStagedChanges($actor);

    expect($changed)->toBeGreaterThan(0)
        ->and(publicationVersionDisclaimer())->toBe($live)
        ->and(app(PublicationService::class)->hasPendingChanges())->toBeFalse()
        ->and(AuditEvent::query()->count())->toBe($beforeEvents + 1)
        ->and(AuditEvent::query()->latest('id')->value('action'))->toBe('publication.stage_reset');
});


it('restores an old snapshot into working state and records the restore as a new commit when published', function (): void {
    $actor = User::factory()->admin()->create();
    $publication = app(PublicationService::class);
    $versions = app(PublicationVersionService::class);

    publicationVersionSetDisclaimer('Restore target');
    $target = $publication->commit($actor, 'Restore target');
    publicationVersionSetDisclaimer('Later live state');
    $later = $publication->commit($actor, 'Later live state');

    expect(publicationVersionDisclaimer('committed'))->toBe('Later live state');

    $versions->stageVersion($target, $actor);

    expect(publicationVersionDisclaimer())->toBe('Restore target')
        ->and(publicationVersionDisclaimer('committed'))->toBe('Later live state')
        ->and($publication->hasPendingChanges())->toBeTrue()
        ->and($versions->workingContext())->toBe([
            'operation' => 'restore',
            'source_publication_checkpoint_id' => (int) $target->getKey(),
        ]);

    $restored = $publication->commit($actor, 'Restore '.$target->shortHash());

    expect($restored)->toBeInstanceOf(PublicationCheckpoint::class)
        ->and((string) $restored->getAttribute('operation'))->toBe('restore')
        ->and((int) $restored->getAttribute('parent_publication_checkpoint_id'))->toBe((int) $later->getKey())
        ->and((int) $restored->getAttribute('source_publication_checkpoint_id'))->toBe((int) $target->getKey())
        ->and(publicationVersionDisclaimer('committed'))->toBe('Restore target')
        ->and($versions->workingContext())->toBe([
            'operation' => null,
            'source_publication_checkpoint_id' => null,
        ]);
});


it('reverts only the current LIVE commit by staging its parent and publishing a new revert commit', function (): void {
    $actor = User::factory()->admin()->create();
    $publication = app(PublicationService::class);
    $versions = app(PublicationVersionService::class);

    publicationVersionSetDisclaimer('Parent state');
    $parent = $publication->commit($actor, 'Parent state');
    publicationVersionSetDisclaimer('Current live state');
    $live = $publication->commit($actor, 'Current live state');

    $staged = $versions->stageRevertOfCurrent($actor);

    expect((int) $staged['reverted']->getKey())->toBe((int) $live->getKey())
        ->and((int) $staged['checkpoint']->getKey())->toBe((int) $parent->getKey())
        ->and(publicationVersionDisclaimer())->toBe('Parent state')
        ->and(publicationVersionDisclaimer('committed'))->toBe('Current live state')
        ->and($versions->workingContext())->toBe([
            'operation' => 'revert',
            'source_publication_checkpoint_id' => (int) $live->getKey(),
        ]);

    $revert = $publication->commit($actor, 'Revert '.$live->shortHash());

    expect($revert)->toBeInstanceOf(PublicationCheckpoint::class)
        ->and((string) $revert->getAttribute('operation'))->toBe('revert')
        ->and((int) $revert->getAttribute('parent_publication_checkpoint_id'))->toBe((int) $live->getKey())
        ->and((int) $revert->getAttribute('source_publication_checkpoint_id'))->toBe((int) $live->getKey())
        ->and(publicationVersionDisclaimer('committed'))->toBe('Parent state')
        ->and(PublicationCheckpoint::query()->whereKey($live->getKey())->exists())->toBeTrue();
});


it('restores media parents before artwork media when reverting the current LIVE commit', function (): void {
    $actor = User::factory()->admin()->create();
    $publication = app(PublicationService::class);
    $versions = app(PublicationVersionService::class);

    $categoryId = (int) DB::table('artwork_categories')->insertGetId([
        'slug' => 'revert-media-gallery',
        'name' => 'Revert media gallery',
        'state' => 'published',
        'position' => 0,
    ]);
    $artworkId = (int) DB::table('artworks')->insertGetId([
        'artwork_category_id' => $categoryId,
        'slug' => 'revert-media-artwork',
        'analytics_key' => '11111111-1111-4111-8111-111111111111',
        'title' => 'Revert media artwork',
        'state' => 'published',
        'position' => 0,
        'date_precision' => 'unknown',
    ]);
    $asset = MediaAsset::query()->create([
        'storage_key' => 'media/revert-parent.jpg',
        'original_filename' => 'revert-parent.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'revert-parent'),
        'state' => 'available',
        'copyright_notice_mode' => MediaAsset::COPYRIGHT_INHERIT,
    ]);
    $usageId = (int) DB::table('artwork_media')->insertGetId([
        'artwork_id' => $artworkId,
        'media_asset_id' => $asset->getKey(),
        'role' => 'primary',
        'position' => 0,
    ]);

    $parent = $publication->commit($actor, 'Parent with artwork media');

    DB::table('artwork_media')->where('id', $usageId)->delete();
    DB::table('media_assets')->where('id', $asset->getKey())->delete();
    $live = $publication->commit($actor, 'Current live without artwork media');

    expect(DB::table('committed.media_assets')->where('id', $asset->getKey())->exists())->toBeFalse()
        ->and(DB::table('committed.artwork_media')->where('id', $usageId)->exists())->toBeFalse();

    $staged = $versions->stageRevertOfCurrent($actor);

    expect((int) $staged['reverted']->getKey())->toBe((int) $live->getKey())
        ->and((int) $staged['checkpoint']->getKey())->toBe((int) $parent->getKey())
        ->and(DB::table('media_assets')->where('id', $asset->getKey())->exists())->toBeTrue()
        ->and(DB::table('artwork_media')->where('id', $usageId)->value('media_asset_id'))->toBe((int) $asset->getKey())
        ->and(DB::table('committed.media_assets')->where('id', $asset->getKey())->exists())->toBeFalse()
        ->and(DB::table('committed.artwork_media')->where('id', $usageId)->exists())->toBeFalse();

    $revert = $publication->commit($actor, 'Revert '.$live->shortHash());

    expect((string) $revert->getAttribute('operation'))->toBe('revert')
        ->and(DB::table('committed.media_assets')->where('id', $asset->getKey())->exists())->toBeTrue()
        ->and(DB::table('committed.artwork_media')->where('id', $usageId)->value('media_asset_id'))->toBe((int) $asset->getKey())
        ->and(PublicationCheckpoint::query()->whereKey($parent->getKey())->exists())->toBeTrue()
        ->and(PublicationCheckpoint::query()->whereKey($live->getKey())->exists())->toBeTrue();
});
