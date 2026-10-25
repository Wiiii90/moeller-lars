<?php

use App\Domain\Media\MediaIngestService;
use App\Domain\Media\MediaVariantRegenerationService;
use App\Domain\Publication\PublicationService;
use App\Domain\Storage\SiteStorageReclaimService;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('media-variant-reclamation-test');
    config([
        'media.disk' => 'media-variant-reclamation-test',
        'media.quota_bytes' => 10_000_000_000,
    ]);
});

it('reclaims rebuildable generated variants and recreates them on the next admin request', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');

    $asset = app(MediaIngestService::class)->ingest(
        UploadedFile::fake()->image('rebuildable.jpg', 1200, 800),
    );
    $variant = $asset->variants()->sole();
    $originalKey = (string) $asset->getAttribute('storage_key');
    $variantKey = (string) $variant->getAttribute('storage_key');

    Storage::disk(config('media.disk'))->assertExists($originalKey);
    Storage::disk(config('media.disk'))->assertExists($variantKey);

    $result = app(SiteStorageReclaimService::class)->reclaim();

    expect($result['generated_files'])->toBeGreaterThanOrEqual(1)
        ->and($result['generated_bytes'])->toBeGreaterThan(0)
        ->and($variant->fresh())->not->toBeNull()
        ->and($variant->fresh()?->getAttribute('state'))->toBe('available');
    Storage::disk(config('media.disk'))->assertExists($originalKey);
    Storage::disk(config('media.disk'))->assertMissing($variantKey);

    Auth::guard('web')->logout();
    $this->get(route('admin.media.variant', $variant))->assertForbidden();
    Storage::disk(config('media.disk'))->assertMissing($variantKey);

    $this->actingAs($actor, 'web');
    $this->get(route('admin.media.variant', $variant))
        ->assertOk()
        ->assertHeader('ETag', '"'.$variant->getAttribute('sha256').'"');

    Storage::disk(config('media.disk'))->assertExists($variantKey);
});

it('keeps a generated file when the current transform cannot reproduce its recorded derivative', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');

    $asset = app(MediaIngestService::class)->ingest(
        UploadedFile::fake()->image('non-reproducible.jpg', 640, 480),
    );
    $variant = $asset->variants()->sole();
    $variantKey = (string) $variant->getAttribute('storage_key');

    $variant->forceFill(['sha256' => str_repeat('0', 64)])->save();

    $result = app(MediaVariantRegenerationService::class)->reclaimRebuildableFiles();

    expect($result)->toBe(['files' => 0, 'bytes' => 0]);
    Storage::disk(config('media.disk'))->assertExists($variantKey);
});

it('never regenerates an unreferenced committed variant for an unauthenticated public request', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');

    $asset = app(MediaIngestService::class)->ingest(
        UploadedFile::fake()->image('private-variant.jpg', 640, 480),
    );
    $variant = $asset->variants()->sole();
    $key = (string) $variant->getAttribute('storage_key');

    // Committed existence alone cannot grant public access.
    expect(app(PublicationService::class)->commit($actor))->not->toBeNull();
    Storage::disk(config('media.disk'))->delete($key);
    Storage::disk(config('media.disk'))->assertMissing($key);
    Auth::guard('web')->logout();

    $this->get(route('media.variant', $variant))->assertNotFound();
    Storage::disk(config('media.disk'))->assertMissing($key);
});

it('preserves variants when file deletion fails and reclaims them on retry', function (bool $throws): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $asset = app(MediaIngestService::class)->ingest(UploadedFile::fake()->image('retry-variant.jpg', 640, 480));
    $variant = $asset->variants()->sole();
    $disk = Storage::disk(config('media.disk'));
    $failingDisk = Mockery::mock($disk);
    $expectation = $failingDisk->shouldReceive('delete')->with($variant->storage_key)->once();
    if ($throws) {
        $expectation->andThrow(new RuntimeException('Filesystem unavailable'));
    } else {
        $expectation->andReturnFalse();
    }
    Storage::shouldReceive('disk')->andReturn($failingDisk);
    expect(app(MediaVariantRegenerationService::class)->reclaimRebuildableFiles())->toBe(['files' => 0, 'bytes' => 0]);
    expect($disk->exists($variant->storage_key))->toBeTrue()
        ->and($disk->exists($asset->storage_key))->toBeTrue()
        ->and($variant->fresh()->state)->toBe('available');
    $failingDisk->shouldReceive('delete')->with($variant->storage_key)->once()
        ->andReturnUsing(fn () => $disk->delete($variant->storage_key));
    expect(app(MediaVariantRegenerationService::class)->reclaimRebuildableFiles())
        ->toBe(['files' => 1, 'bytes' => (int) $variant->byte_size]);
    expect($disk->exists($variant->storage_key))->toBeFalse()
        ->and($disk->exists($asset->storage_key))->toBeTrue();
    expect(app(MediaVariantRegenerationService::class)->reclaimRebuildableFiles())->toBe(['files' => 0, 'bytes' => 0]);
})->with(['delete returns false' => false, 'delete throws' => true]);
