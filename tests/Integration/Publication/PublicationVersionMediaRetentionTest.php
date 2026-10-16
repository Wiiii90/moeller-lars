<?php

use App\Domain\Media\MediaAssetEditorialService;
use App\Domain\Media\MediaIngestService;
use App\Domain\Media\MediaVariantRegenerationService;
use App\Domain\Publication\PublicationMediaCleanupService;
use App\Domain\Publication\PublicationService;
use App\Domain\Publication\PublicationVersionService;
use App\Domain\Storage\SiteStorageReclaimService;
use App\Filament\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Models\AdminNotification;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\AuditEvent;
use App\Models\MediaAsset;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

require_once __DIR__.'/PublicationVersionFixtures.php';

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    \Filament\Facades\Filament::setCurrentPanel('admin');
    \Filament\Facades\Filament::bootCurrentPanel();
    config([
        'media.disk' => 'publication-bytes',
        'media.quota_bytes' => 1_000_000_000,
        'filesystems.disks.publication-bytes' => [
            'driver' => 'local', 'root' => storage_path('framework/testing/publication-bytes/'.\Illuminate\Support\Str::uuid()), 'throw' => true,
        ],
    ]);
});

function publicationImageFixture(): array
{
    $asset = app(MediaIngestService::class)->ingest(UploadedFile::fake()->image('retained-original.jpg', 320, 200));
    $asset = app(MediaAssetEditorialService::class)->updateMetadata($asset, [
        'alt_text' => 'Synthetic shared artwork image', 'credit' => 'Recovery fixture',
        'file_creator' => 'Synthetic artist', 'file_rights_holder' => 'Synthetic rights holder',
        'copyright_notice_mode' => MediaAsset::COPYRIGHT_OVERRIDE, 'copyright_notice' => 'Synthetic copyright',
    ]);
    $gallery = ArtworkCategory::query()->create(['name' => 'Recovery gallery', 'slug' => 'recovery-gallery']);
    SiteSection::query()->create([
        'type' => SiteSection::TYPE_GALLERY, 'title' => 'Recovery gallery', 'slug' => 'recovery-gallery',
        'navigation_label' => 'Recovery gallery', 'state' => 'published', 'position' => 100,
        'artwork_category_id' => $gallery->id,
    ]);
    $artworks = collect([1, 2])->map(function ($number) use ($gallery, $asset) {
        $artwork = Artwork::query()->create([
            'artwork_category_id' => $gallery->id, 'title' => 'Recovery artwork '.$number,
            'slug' => 'recovery-artwork-'.$number, 'state' => 'published', 'position' => $number,
            'published_at' => now(),
        ]);
        ArtworkMedia::query()->create(['artwork_id' => $artwork->id, 'media_asset_id' => $asset->id, 'role' => 'primary', 'position' => 1]);
        return $artwork;
    });
    $variant = $asset->variants()->sole();
    $disk = Storage::disk(config('media.disk'));
    $bytes = [$asset->storage_key => $disk->get($asset->storage_key), $variant->storage_key => $disk->get($variant->storage_key)];
    expect(getimagesizefromstring($bytes[$asset->storage_key])[0])->toBe(320)
        ->and(getimagesizefromstring($bytes[$variant->storage_key])[0])->toBe(320);
    return compact('asset', 'variant', 'artworks', 'bytes', 'gallery');
}

function assertPublicationImageBytes(array $fixture): void
{
    $disk = Storage::disk(config('media.disk'));
    foreach ($fixture['bytes'] as $key => $expected) {
        expect(is_file($disk->path($key)))->toBeTrue()
            ->and(hash_file('sha256', $disk->path($key)))->toBe(hash('sha256', $expected))
            ->and($disk->get($key))->toBe($expected);
    }
    expect(hash_file('sha256', $disk->path($fixture['asset']->storage_key)))->toBe($fixture['asset']->sha256)
        ->and(hash_file('sha256', $disk->path($fixture['variant']->storage_key)))->toBe($fixture['variant']->sha256);
}

function assertPublicationImageReferences(array $fixture, string $schema): void
{
    $asset = $fixture['asset'];
    $assetFields = ['state', 'storage_key', 'sha256', 'byte_size', 'mime_type', 'original_filename', 'alt_text', 'credit', 'file_creator', 'file_rights_holder', 'copyright_notice_mode', 'copyright_notice'];
    $variantFields = ['state', 'storage_key', 'sha256', 'byte_size', 'mime_type', 'variant_kind', 'transform_profile', 'width', 'height'];
    expect(DB::table($schema.'.artwork_media')->where('media_asset_id', $asset->id)->orderBy('artwork_id')->pluck('artwork_id')->all())
        ->toBe($fixture['artworks']->pluck('id')->all())
        ->and(DB::table($schema.'.artwork_media')->where('media_asset_id', $asset->id)->where('role', 'primary')->where('position', 1)->count())->toBe(2)
        ->and(DB::table($schema.'.media_assets')->where('id', $asset->id)->first($assetFields))
        ->toEqual((object) $asset->only($assetFields))
        ->and(DB::table($schema.'.media_variants')->where('id', $fixture['variant']->id)->first($variantFields))
        ->toEqual((object) $fixture['variant']->only($variantFields))
        ->and(DB::table($schema.'.artworks')->whereIn('id', $fixture['artworks']->pluck('id'))->where('state', 'published')->where('artwork_category_id', $fixture['gallery']->id)->count())->toBe(2);
}

it('preserves original image bytes and shared references across deletion, historical recovery and publication', function (string $operation): void {
    $fixture = publicationImageFixture();
    $actor = auth()->user();
    $publication = app(PublicationService::class);
    $versions = app(PublicationVersionService::class);
    $cleanup = app(PublicationMediaCleanupService::class);
    $target = $publication->commit($actor, 'Shared original image');
    assertPublicationImageReferences($fixture, 'committed');
    $notifications = AdminNotification::query()->count();
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    $editor = Livewire::test(ListMediaAssets::class)->mountAction('delete', ['asset' => $fixture['asset']->id])->callMountedAction();
    expect(AdminNotification::query()->count())->toBe($notifications + 1)
        ->and(collect($editor->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1)
        ->and(ArtworkMedia::query()->where('media_asset_id', $fixture['asset']->id)->count())->toBe(0)
        ->and(Artwork::query()->whereIn('id', $fixture['artworks']->pluck('id'))->whereNull('artwork_category_id')->where('state', 'draft')->count())->toBe(2)
        ->and($fixture['asset']->fresh()->state)->toBe('deleted')
        ->and($fixture['variant']->fresh()->state)->toBe('deleted');
    assertPublicationImageReferences($fixture, 'committed');
    assertPublicationImageBytes($fixture);
    expect($cleanup->drain())->toBe(0)->and(DB::table('publication_media_cleanups')->count())->toBe(2);
    $live = $publication->commit($actor, 'Remove shared original from LIVE');
    expect(DB::table('committed.artwork_media')->where('media_asset_id', $fixture['asset']->id)->count())->toBe(0)
        ->and($versions->isRestorable($target->fresh()))->toBeTrue()
        ->and($cleanup->drain())->toBe(0);
    assertPublicationImageBytes($fixture);
    if ($operation === 'restore') {
        publicationVersionSetDisclaimer('Later editorial state');
        $live = $publication->commit($actor, 'Later commit without the image');
        $versions->stageVersion($target, $actor);
    } else {
        $staged = $versions->stageRevertOfCurrent($actor);
        expect($staged['checkpoint']->id)->toBe($target->id)->and($staged['reverted']->id)->toBe($live->id);
    }
    expect($versions->workingContext())->toBe([
        'operation' => $operation, 'source_publication_checkpoint_id' => $operation === 'restore' ? $target->id : $live->id,
    ]);
    assertPublicationImageReferences($fixture, 'public');
    assertPublicationImageBytes($fixture);
    expect(DB::table('committed.media_assets')->where('id', $fixture['asset']->id)->value('state'))->toBe('deleted')
        ->and(DB::table('committed.artwork_media')->where('media_asset_id', $fixture['asset']->id)->count())->toBe(0)
        ->and($versions->currentLiveCheckpoint()->id)->toBe($live->id);

    // Free storage may release only reproducible thumbnails, never the original.
    $result = app(SiteStorageReclaimService::class)->reclaim();
    $disk = Storage::disk(config('media.disk'));
    expect($result['removed_files'])->toBe(0)->and($result['generated_files'])->toBe(1)
        ->and(hash_file('sha256', $disk->path($fixture['asset']->storage_key)))->toBe($fixture['asset']->sha256)
        ->and($disk->exists($fixture['variant']->storage_key))->toBeFalse()
        ->and(app(MediaVariantRegenerationService::class)->ensureFile($fixture['variant']->fresh()))->toBeTrue();
    assertPublicationImageBytes($fixture);
    $published = $publication->commit($actor, 'Publish recovered media');
    expect($published->operation)->toBe($operation)
        ->and($published->parent_publication_checkpoint_id)->toBe($live->id)
        ->and($published->source_publication_checkpoint_id)->toBe($operation === 'restore' ? $target->id : $live->id);
    assertPublicationImageReferences($fixture, 'committed');
    assertPublicationImageBytes($fixture);
})->with(['restore', 'revert']);

it('releases original bytes only after the final recoverable source is explicitly relinquished', function (): void {
    $fixture = publicationImageFixture();
    $actor = auth()->user();
    $publication = app(PublicationService::class);
    $versions = app(PublicationVersionService::class);
    $target = $publication->commit($actor, 'Retained source');
    app(MediaAssetEditorialService::class)->delete($fixture['asset']);
    $publication->commit($actor, 'Deleted LIVE state');
    $versions->stageVersion($target, $actor);
    app(SiteStorageReclaimService::class)->reclaim();
    expect($versions->isRestorable($target->fresh()))->toBeTrue()
        ->and(app(MediaVariantRegenerationService::class)->ensureFile($fixture['variant']->fresh()))->toBeTrue();
    assertPublicationImageBytes($fixture);
    $versions->resetStagedChanges($actor);
    expect($versions->isRestorable($target->fresh()))->toBeTrue()
        ->and(app(PublicationMediaCleanupService::class)->drain())->toBe(0);
    assertPublicationImageBytes($fixture);
    $released = app(SiteStorageReclaimService::class)->reclaim();
    expect($released['removed_files'])->toBe(2)
        ->and($versions->isRestorable($target->fresh()))->toBeFalse()
        ->and(DB::table('publication_media_cleanups')->count())->toBe(0);
    foreach (array_keys($fixture['bytes']) as $key) expect(Storage::disk(config('media.disk'))->exists($key))->toBeFalse();
    $before = DB::table('media_assets')->orderBy('id')->get()->toJson();
    $audits = AuditEvent::query()->count();
    $notifications = AdminNotification::query()->count();
    expect(fn () => $versions->stageVersion($target->fresh(), $actor))->toThrow(ValidationException::class);
    expect(DB::table('media_assets')->orderBy('id')->get()->toJson())->toBe($before)
        ->and(ArtworkMedia::query()->where('media_asset_id', $fixture['asset']->id)->count())->toBe(0)
        ->and(AuditEvent::query()->count())->toBe($audits)
        ->and(AdminNotification::query()->count())->toBe($notifications);
    foreach (array_keys($fixture['bytes']) as $key) expect(Storage::disk(config('media.disk'))->exists($key))->toBeFalse();
});
