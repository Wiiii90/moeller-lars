<?php

use App\Domain\Artwork\ArtworkEditorialService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\AuditEvent;
use App\Models\CvEntry;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

function editorialCategory(string $state = 'published'): ArtworkCategory
{
    $category = new ArtworkCategory;
    $category->fill(['slug' => 'test-category-'.fake()->unique()->uuid(), 'name' => 'Test category', 'state' => $state, 'position' => 0]);
    $category->save();

    return $category;
}

function editorialArtwork(ArtworkCategory $category, array $attributes = []): Artwork
{
    $artwork = new Artwork;
    $artwork->fill(array_merge([
        'artwork_category_id' => $category->getKey(),
        'slug' => fake()->unique()->slug(),
        'title' => 'Test artwork',
        'state' => 'draft',
        'position' => 0,
        'date_precision' => 'unknown',
    ], $attributes));
    $artwork->save();

    return $artwork;
}

function editorialAsset(string $state = 'available'): MediaAsset
{
    $asset = new MediaAsset;
    $asset->fill([
        'storage_key' => 'originals/test-'.fake()->unique()->uuid.'.jpg',
        'original_filename' => 'test.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 3,
        'sha256' => str_repeat('a', 64),
        'state' => $state,
        'alt_text' => 'Test asset ALT',
        'width' => 1,
        'height' => 1,
    ]);
    $asset->save();

    return $asset;
}

function editorialPrimary(Artwork $artwork, MediaAsset $asset): ArtworkMedia
{
    $media = new ArtworkMedia;
    $media->fill([
        'artwork_id' => $artwork->getKey(),
        'media_asset_id' => $asset->getKey(),
        'role' => 'primary',
        'position' => 0,
    ]);
    $media->save();

    return $media;
}

function editorialThumbnail(MediaAsset $asset, string $state = 'available'): MediaVariant
{
    $variant = new MediaVariant;
    $variant->fill([
        'media_asset_id' => $asset->getKey(),
        'variant_kind' => 'thumbnail',
        'storage_key' => 'variants/test-'.fake()->unique()->uuid().'.webp',
        'mime_type' => 'image/webp',
        'byte_size' => 3,
        'sha256' => str_repeat('b', 64),
        'transform_profile' => 'public-thumb-v1',
        'state' => $state,
        'width' => 1,
        'height' => 1,
    ]);
    $variant->save();

    return $variant;
}

it('publishes only an artwork with a published category and one available primary media asset', function () {
    $category = editorialCategory();
    $artwork = editorialArtwork($category);
    $asset = editorialAsset();
    editorialPrimary($artwork, $asset);
    editorialThumbnail($asset);

    $published = app(ArtworkEditorialService::class)->publish($artwork);

    expect($published->state)->toBe('published')
        ->and($published->published_at)->not->toBeNull()
        ->and(AuditEvent::query()->where('action', 'artwork.published')->where('entity_id', (string) $artwork->getKey())->exists())->toBeTrue();
});

it('does not change published_at when publishing again', function () {
    $category = editorialCategory();
    $artwork = editorialArtwork($category, [
        'state' => 'published',
        'published_at' => now()->subDays(3),
    ]);
    $asset = editorialAsset();
    editorialPrimary($artwork, $asset);
    editorialThumbnail($asset);
    $publishedAt = $artwork->published_at;

    $published = app(ArtworkEditorialService::class)->publish($artwork);

    expect($published->published_at?->equalTo($publishedAt))->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'artwork.published')->where('entity_id', (string) $artwork->getKey())->count())->toBe(0);
});

it('rejects hidden categories, missing primaries, and unavailable assets', function (string $case) {
    $category = editorialCategory($case === 'hidden-category' ? 'hidden' : 'published');
    $artwork = editorialArtwork($category);

    if ($case !== 'missing-primary') {
        $asset = editorialAsset($case === 'unavailable-asset' ? 'quarantined' : 'available');
        editorialPrimary($artwork, $asset);
        editorialThumbnail($asset);
    }

    expect(fn () => app(ArtworkEditorialService::class)->publish($artwork))
        ->toThrow(ValidationException::class);
})->with(['hidden-category', 'missing-primary', 'unavailable-asset']);

it('rejects publication without canonical ALT or public thumbnail', function (string $case) {
    $category = editorialCategory();
    $artwork = editorialArtwork($category);
    $asset = editorialAsset();
    editorialPrimary($artwork, $asset);

    if ($case === 'missing-alt') {
        $asset->forceFill(['alt_text' => null])->save();
        editorialThumbnail($asset);
    } elseif ($case === 'missing-thumbnail') {
        // No derivative row on purpose.
    } else {
        editorialThumbnail($asset, 'deleted');
    }

    expect(fn () => app(ArtworkEditorialService::class)->publish($artwork))
        ->toThrow(ValidationException::class);
})->with(['missing-alt', 'missing-thumbnail', 'deleted-thumbnail']);

it('unpublishes without clearing published_at', function () {
    $category = editorialCategory();
    $artwork = editorialArtwork($category, [
        'state' => 'published',
        'published_at' => now()->subDay(),
    ]);
    $publishedAt = $artwork->published_at;

    $draft = app(ArtworkEditorialService::class)->unpublish($artwork);

    expect($draft->state)->toBe('draft')
        ->and($draft->published_at?->equalTo($publishedAt))->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'artwork.unpublished')->where('entity_id', (string) $artwork->getKey())->exists())->toBeTrue();
});

it('ingests and attaches one primary media record', function () {
    Storage::fake('local');
    config()->set('media.disk', 'local');

    $category = editorialCategory();
    $artwork = editorialArtwork($category);
    $file = UploadedFile::fake()->image('one.jpg', 1200, 800);

    $result = app(ArtworkEditorialService::class)->attachPrimaryMedia($artwork, $file);
    $usage = $result->artworkMedia()->firstOrFail();
    $asset = $usage->mediaAsset()->firstOrFail();

    expect($usage->role)->toBe('primary')
        ->and($usage->position)->toBe(0)
        ->and($asset->state)->toBe('available')
        ->and($asset->variants()->where('variant_kind', 'thumbnail')->exists())->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'media.ingested')->where('entity_id', (string) $asset->getKey())->exists())->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'artwork.primary_media_attached')->where('entity_id', (string) $artwork->getKey())->exists())->toBeTrue();
});

it('rejects a second primary before ingesting anything', function () {
    Storage::fake('local');
    config()->set('media.disk', 'local');

    $category = editorialCategory();
    $artwork = editorialArtwork($category);
    $asset = editorialAsset();
    editorialPrimary($artwork, $asset);

    expect(fn () => app(ArtworkEditorialService::class)->attachPrimaryMedia(
        $artwork,
        UploadedFile::fake()->image('second.jpg', 100, 100),
    ))->toThrow(ValidationException::class)
        ->and(MediaAsset::query()->count())->toBe(1);
});

it('replaces a draft primary while preserving the usage record and clearing its ALT override', function () {
    Storage::fake('local');
    config()->set('media.disk', 'local');

    $category = editorialCategory();
    $artwork = editorialArtwork($category);
    $oldAsset = editorialAsset();
    $oldAsset->forceFill(['storage_key' => 'originals/old.jpg'])->save();
    $oldVariant = editorialThumbnail($oldAsset);
    $oldVariant->forceFill(['storage_key' => 'variants/old.webp'])->save();
    Storage::disk('local')->put('originals/old.jpg', 'old');
    Storage::disk('local')->put('variants/old.webp', 'old-thumb');
    $usage = editorialPrimary($artwork, $oldAsset);
    $usage->forceFill(['alt_text_override' => 'Artwork specific'])->save();

    $result = app(ArtworkEditorialService::class)->replacePrimaryMedia(
        $artwork,
        UploadedFile::fake()->image('replacement.jpg', 900, 600),
    );
    $usage->refresh();
    $oldAsset->refresh();

    expect($result->state)->toBe('draft')
        ->and($usage->media_asset_id)->not->toBe($oldAsset->getKey())
        ->and($usage->alt_text_override)->toBeNull()
        ->and($oldAsset->state)->toBe('deleted')
        ->and($oldAsset->variants()->where('state', 'deleted')->count())->toBe(1)
        ->and(Storage::disk('local')->exists('originals/old.jpg'))->toBeFalse()
        ->and(Storage::disk('local')->exists('variants/old.webp'))->toBeFalse()
        ->and(AuditEvent::query()->where('action', 'artwork.primary_media_replaced')->where('entity_id', (string) $artwork->getKey())->exists())->toBeTrue();
});

it('replaces a published primary without changing publication state', function () {
    Storage::fake('local');
    config()->set('media.disk', 'local');

    $category = editorialCategory();
    $artwork = editorialArtwork($category, [
        'state' => 'published',
        'published_at' => now()->subDay(),
    ]);
    $publishedAt = $artwork->published_at;
    $oldAsset = editorialAsset();
    $oldAsset->forceFill(['storage_key' => 'originals/published-old.jpg'])->save();
    $oldVariant = editorialThumbnail($oldAsset);
    $oldVariant->forceFill(['storage_key' => 'variants/published-old.webp'])->save();
    Storage::disk('local')->put('originals/published-old.jpg', 'old');
    Storage::disk('local')->put('variants/published-old.webp', 'old-thumb');
    editorialPrimary($artwork, $oldAsset);

    $result = app(ArtworkEditorialService::class)->replacePrimaryMedia(
        $artwork,
        UploadedFile::fake()->image('published-replacement.jpg', 1200, 800),
    );

    expect($result->state)->toBe('published')
        ->and($result->published_at?->equalTo($publishedAt))->toBeTrue()
        ->and($result->artworkMedia()->where('role', 'primary')->count())->toBe(1)
        ->and($result->artworkMedia()->where('role', 'primary')->firstOrFail()->mediaAsset->state)->toBe('available');
});

it('keeps shared old media available after replacement', function () {
    Storage::fake('local');
    config()->set('media.disk', 'local');

    $category = editorialCategory();
    $artwork = editorialArtwork($category);
    $oldAsset = editorialAsset();
    $oldAsset->forceFill(['storage_key' => 'originals/shared.jpg'])->save();
    editorialPrimary($artwork, $oldAsset);

    $cv = new CvEntry;
    $cv->fill([
        'section' => 'Biography',
        'title' => 'Portrait',
        'state' => 'draft',
        'position' => 0,
        'date_precision' => 'unknown',
        'image_media_asset_id' => $oldAsset->getKey(),
    ]);
    $cv->save();

    app(ArtworkEditorialService::class)->replacePrimaryMedia(
        $artwork,
        UploadedFile::fake()->image('replacement.jpg', 900, 600),
    );
    $oldAsset->refresh();

    expect($oldAsset->state)->toBe('available')
        ->and(AuditEvent::query()->where('action', 'media.deleted')->where('entity_id', (string) $oldAsset->getKey())->exists())->toBeFalse();
});

it('rolls back replacement and removes new media when the replacement transaction fails', function () {
    Storage::fake('local');
    config()->set('media.disk', 'local');

    $category = editorialCategory();
    $artwork = editorialArtwork($category);
    $oldAsset = editorialAsset();
    $oldAsset->forceFill(['storage_key' => 'originals/rollback-old.jpg'])->save();
    editorialPrimary($artwork, $oldAsset);

    $replacementAuditTrigger = app('db')->unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION reject_replacement_audit()
        RETURNS trigger AS $$
        BEGIN
            IF NEW.action = 'artwork.primary_media_replaced' THEN
                RAISE EXCEPTION 'forced replacement audit failure';
            END IF;
            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;
        CREATE TRIGGER audit_replacement_failure
        BEFORE INSERT ON audit_events
        FOR EACH ROW EXECUTE FUNCTION reject_replacement_audit();
    SQL);
    expect($replacementAuditTrigger)->toBeTrue();

    try {
        app(ArtworkEditorialService::class)->replacePrimaryMedia(
            $artwork,
            UploadedFile::fake()->image('new.jpg', 800, 500),
        );
        $this->fail('Expected replacement to fail.');
    } catch (Throwable $exception) {
        expect($exception->getMessage())->toContain('forced replacement audit failure');
    }

    expect($artwork->artworkMedia()->where('role', 'primary')->firstOrFail()->media_asset_id)->toBe($oldAsset->getKey())
        ->and(MediaAsset::query()->count())->toBe(1)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('rolls back replacement when deleting the old asset audit fails', function () {
    Storage::fake('local');
    config()->set('media.disk', 'local');

    $category = editorialCategory();
    $artwork = editorialArtwork($category);
    $oldAsset = editorialAsset();
    $oldAsset->forceFill(['storage_key' => 'originals/delete-audit-old.jpg'])->save();
    editorialPrimary($artwork, $oldAsset);

    $mediaDeleteAuditTrigger = app('db')->unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION reject_media_delete_audit()
        RETURNS trigger AS $$
        BEGIN
            IF NEW.action = 'media.deleted' THEN
                RAISE EXCEPTION 'forced media deletion audit failure';
            END IF;
            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;
        CREATE TRIGGER audit_media_delete_failure
        BEFORE INSERT ON audit_events
        FOR EACH ROW EXECUTE FUNCTION reject_media_delete_audit();
    SQL);
    expect($mediaDeleteAuditTrigger)->toBeTrue();

    try {
        app(ArtworkEditorialService::class)->replacePrimaryMedia(
            $artwork,
            UploadedFile::fake()->image('new-after-delete-audit.jpg', 800, 500),
        );
        $this->fail('Expected replacement to fail.');
    } catch (Throwable $exception) {
        expect($exception->getMessage())->toContain('forced media deletion audit failure');
    }

    expect($artwork->artworkMedia()->where('role', 'primary')->firstOrFail()->media_asset_id)->toBe($oldAsset->getKey())
        ->and($oldAsset->fresh()->state)->toBe('available')
        ->and(MediaAsset::query()->count())->toBe(1);
});

it('cleans the newly ingested media if the artwork media insert fails', function () {
    Storage::fake('local');
    config()->set('media.disk', 'local');

    $category = editorialCategory();
    $artwork = editorialArtwork($category);

    ArtworkMedia::creating(function (): void {
        throw new RuntimeException('forced attach failure');
    });

    expect(fn () => app(ArtworkEditorialService::class)->attachPrimaryMedia(
        $artwork,
        UploadedFile::fake()->image('cleanup.jpg', 200, 100),
    ))->toThrow(RuntimeException::class, 'forced attach failure')
        ->and(MediaAsset::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('rolls back media attachment and cleanup when an audit insert fails', function () {
    Storage::fake('local');
    config()->set('media.disk', 'local');

    $category = editorialCategory();
    $artwork = editorialArtwork($category);

    $mediaAuditTrigger = app('db')->unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION reject_media_ingest_audit()
        RETURNS trigger AS $$
        BEGIN
            IF NEW.action = 'media.ingested' THEN
                RAISE EXCEPTION 'forced media audit failure';
            END IF;
            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;
        CREATE TRIGGER audit_media_ingest_failure
        BEFORE INSERT ON audit_events
        FOR EACH ROW EXECUTE FUNCTION reject_media_ingest_audit();
    SQL);
    expect($mediaAuditTrigger)->toBeTrue();

    expect(fn () => app(ArtworkEditorialService::class)->attachPrimaryMedia(
        $artwork,
        UploadedFile::fake()->image('audit.jpg', 200, 100),
    ))->toThrow(Throwable::class)
        ->and(MediaAsset::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('denies unauthenticated and non-admin publish and attach mutations', function () {
    $category = editorialCategory();
    $artwork = editorialArtwork($category);

    Auth::logout();

    expect(fn () => app(ArtworkEditorialService::class)->publish($artwork))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ArtworkEditorialService::class)->attachPrimaryMedia(
            $artwork,
            UploadedFile::fake()->image('denied.jpg', 100, 100),
        ))->toThrow(AuthorizationException::class);

    $this->actingAs(User::factory()->create(['is_admin' => false]), 'web');

    expect(fn () => app(ArtworkEditorialService::class)->publish($artwork))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ArtworkEditorialService::class)->attachPrimaryMedia(
            $artwork,
            UploadedFile::fake()->image('denied-non-admin.jpg', 100, 100),
        ))->toThrow(AuthorizationException::class);
});

it('denies unauthenticated and non-admin replacement without ingesting anything', function () {
    Storage::fake('local');
    config()->set('media.disk', 'local');

    $category = editorialCategory();
    $artwork = editorialArtwork($category);
    $asset = editorialAsset();
    editorialPrimary($artwork, $asset);

    Auth::logout();

    expect(fn () => app(ArtworkEditorialService::class)->replacePrimaryMedia(
        $artwork,
        UploadedFile::fake()->image('denied-replacement.jpg', 100, 100),
    ))->toThrow(AuthorizationException::class);

    expect(MediaAsset::query()->count())->toBe(1);

    $this->actingAs(User::factory()->create(['is_admin' => false]), 'web');

    expect(fn () => app(ArtworkEditorialService::class)->replacePrimaryMedia(
        $artwork,
        UploadedFile::fake()->image('denied-replacement-non-admin.jpg', 100, 100),
    ))->toThrow(AuthorizationException::class)
        ->and(MediaAsset::query()->count())->toBe(1);
});

it('rejects replacement without a primary before ingesting media', function () {
    Storage::fake('local');
    config()->set('media.disk', 'local');

    $category = editorialCategory();
    $artwork = editorialArtwork($category);

    expect(fn () => app(ArtworkEditorialService::class)->replacePrimaryMedia(
        $artwork,
        UploadedFile::fake()->image('missing-primary.jpg', 100, 100),
    ))->toThrow(ValidationException::class)
        ->and(MediaAsset::query()->count())->toBe(0);
});
