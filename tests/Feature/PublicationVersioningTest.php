<?php

use App\Domain\Publication\PublicationMediaCleanupService;
use App\Domain\Publication\PublicationService;
use App\Domain\Publication\PublicationVersionService;
use App\Models\AuditEvent;
use App\Models\MediaAsset;
use App\Models\PublicationCheckpoint;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function publicationVersionSettingId(): int
{
    return (int) DB::table('public_content_settings')->where('scope', 'general')->value('id');
}

function publicationVersionSetDisclaimer(?string $value): void
{
    DB::table('public_content_settings')
        ->where('id', publicationVersionSettingId())
        ->update(['legal_disclaimer' => $value]);
}

function publicationVersionDisclaimer(string $schema = 'public'): ?string
{
    $value = DB::table($schema.'.public_content_settings')
        ->where('id', publicationVersionSettingId())
        ->value('legal_disclaimer');

    return is_string($value) ? $value : null;
}

it('creates immutable SHA-256 commits with retained restorable snapshots', function (): void {
    $actor = User::factory()->admin()->create();
    $publication = app(PublicationService::class);

    publicationVersionSetDisclaimer('Version A');
    $checkpoint = $publication->commit($actor, 'Version A');

    expect($checkpoint)->toBeInstanceOf(PublicationCheckpoint::class)
        ->and((string) $checkpoint->getAttribute('hash'))->toMatch('/^[a-f0-9]{64}$/')
        ->and((string) $checkpoint->getAttribute('snapshot_hash'))->toMatch('/^[a-f0-9]{64}$/')
        ->and((string) $checkpoint->getAttribute('schema_hash'))->toMatch('/^[a-f0-9]{64}$/')
        ->and((bool) $checkpoint->getAttribute('snapshot_available'))->toBeTrue()
        ->and((string) $checkpoint->getAttribute('operation'))->toBe('commit')
        ->and(DB::table('publication_version_rows')->where('publication_checkpoint_id', $checkpoint->getKey())->count())->toBeGreaterThan(0)
        ->and(publicationVersionDisclaimer('committed'))->toBe('Version A')
        ->and(app(PublicationVersionService::class)->isRestorable($checkpoint))->toBeTrue();
});

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

it('keeps publication commit history permanent at the database boundary', function (): void {
    $checkpoint = PublicationCheckpoint::query()->latest('id')->firstOrFail();

    expect(fn () => DB::table('publication_checkpoints')->where('id', $checkpoint->getKey())->delete())
        ->toThrow(QueryException::class)
        ->and(PublicationCheckpoint::query()->whereKey($checkpoint->getKey())->exists())->toBeTrue();
});
