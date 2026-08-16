<?php

use App\Domain\Media\MediaAssetEditorialService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\AuditEvent;
use App\Models\BlogPost;
use App\Models\CvEntry;
use App\Models\Exhibition;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function editorialMediaAdmin(): User
{
    return User::factory()->admin()->create();
}

function mediaEditorialAsset(string $state = 'available'): MediaAsset
{
    return MediaAsset::create([
        'storage_key' => 'originals/editorial-'.uniqid().'.jpg',
        'original_filename' => 'editorial.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'orig'),
        'state' => $state,
        'alt_text' => 'Default alt',
    ]);
}

it('requires an admin for media metadata mutation', function () {
    $asset = mediaEditorialAsset();
    expect(fn () => app(MediaAssetEditorialService::class)->updateMetadata($asset, ['alt_text' => 'Changed']))
        ->toThrow(AuthorizationException::class);
    $this->actingAs(User::factory()->create(), 'web');
    expect(fn () => app(MediaAssetEditorialService::class)->updateMetadata($asset, ['alt_text' => 'Changed']))
        ->toThrow(AuthorizationException::class);
    expect($asset->fresh()->alt_text)->toBe('Default alt')->and(AuditEvent::query()->count())->toBe(0);
});

it('updates plain media metadata and audits only real changes', function () {
    $admin = editorialMediaAdmin();
    $this->actingAs($admin, 'web');
    $asset = mediaEditorialAsset();
    app(MediaAssetEditorialService::class)->updateMetadata($asset, ['alt_text' => '  Updated  ', 'credit' => '  Credit  ', 'copyright_notice' => '  Copyright  ']);
    app(MediaAssetEditorialService::class)->updateMetadata($asset, ['alt_text' => 'Updated', 'credit' => 'Credit', 'copyright_notice' => 'Copyright']);

    expect($asset->fresh()->alt_text)->toBe('Updated')
        ->and($asset->fresh()->credit)->toBe('Credit')
        ->and($asset->fresh()->copyright_notice)->toBe('Copyright')
        ->and(AuditEvent::query()->where('action', 'media.metadata_updated')->where('admin_user_id', $admin->id)->count())->toBe(1);
});

it('rejects unknown and oversized media metadata and deleted assets', function () {
    $this->actingAs(editorialMediaAdmin(), 'web');
    $asset = mediaEditorialAsset();
    expect(fn () => app(MediaAssetEditorialService::class)->updateMetadata($asset, ['state' => 'deleted']))->toThrow(ValidationException::class);
    expect(fn () => app(MediaAssetEditorialService::class)->updateMetadata($asset, ['alt_text' => str_repeat('a', 501)]))->toThrow(ValidationException::class);
    $asset->update(['state' => 'deleted']);
    expect(fn () => app(MediaAssetEditorialService::class)->updateMetadata($asset, ['alt_text' => 'No']))->toThrow(ValidationException::class);
});

it('updates only the primary artwork ALT override and audits its asset', function () {
    $admin = editorialMediaAdmin();
    $this->actingAs($admin, 'web');
    $category = ArtworkCategory::create(['slug' => 'sculptures', 'name' => 'Sculptures', 'state' => 'published', 'position' => 0]);
    $artwork = Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'alt-editorial', 'title' => 'Artwork', 'state' => 'draft', 'position' => 0, 'date_precision' => 'unknown']);
    $asset = mediaEditorialAsset();
    $usage = ArtworkMedia::create(['artwork_id' => $artwork->id, 'media_asset_id' => $asset->id, 'role' => 'primary', 'position' => 0]);
    app(MediaAssetEditorialService::class)->updatePrimaryAltOverride($artwork, '  Usage alt  ');
    app(MediaAssetEditorialService::class)->updatePrimaryAltOverride($artwork, 'Usage alt');

    expect($usage->fresh()->alt_text_override)->toBe('Usage alt')
        ->and($asset->fresh()->alt_text)->toBe('Default alt')
        ->and(AuditEvent::query()->where('action', 'artwork.primary_media_alt_updated')->count())->toBe(1)
        ->and(AuditEvent::query()->where('action', 'artwork.primary_media_alt_updated')->first()->metadata)->toBe(['media_asset_id' => $asset->id]);
});

it('blocks primary ALT updates without a primary or with deleted media', function () {
    $this->actingAs(editorialMediaAdmin(), 'web');
    $category = ArtworkCategory::create(['slug' => 'sculptures', 'name' => 'Sculptures', 'state' => 'published', 'position' => 0]);
    $artwork = Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'no-alt-primary', 'title' => 'Artwork', 'state' => 'draft', 'position' => 0, 'date_precision' => 'unknown']);
    expect(fn () => app(MediaAssetEditorialService::class)->updatePrimaryAltOverride($artwork, 'Alt'))->toThrow(ValidationException::class);
    $asset = mediaEditorialAsset('deleted');
    ArtworkMedia::create(['artwork_id' => $artwork->id, 'media_asset_id' => $asset->id, 'role' => 'primary', 'position' => 0]);
    expect(fn () => app(MediaAssetEditorialService::class)->updatePrimaryAltOverride($artwork, 'Alt'))->toThrow(ValidationException::class);
});

it('logically deletes unreferenced media and leaves database rows', function () {
    Storage::fake(config('media.disk'));
    $admin = editorialMediaAdmin();
    $this->actingAs($admin, 'web');
    $asset = mediaEditorialAsset();
    $variant = new MediaVariant;
    $variant->fill(['media_asset_id' => $asset->id, 'variant_kind' => 'thumbnail', 'storage_key' => 'variants/editorial.webp', 'mime_type' => 'image/webp', 'byte_size' => 4, 'sha256' => hash('sha256', 'var'), 'transform_profile' => 'public-v1', 'state' => 'available', 'width' => 2, 'height' => 2]);
    $variant->save();
    Storage::disk(config('media.disk'))->put($asset->storage_key, 'orig');
    Storage::disk(config('media.disk'))->put($variant->storage_key, 'var');

    expect(app(MediaAssetEditorialService::class)->delete($asset))->toBeTrue()
        ->and($asset->fresh()->state)->toBe('deleted')
        ->and($variant->fresh()->state)->toBe('deleted')
        ->and(MediaAsset::query()->whereKey($asset->id)->exists())->toBeTrue()
        ->and(MediaVariant::query()->whereKey($variant->id)->exists())->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'media.deleted')->count())->toBe(1);
    Storage::disk(config('media.disk'))->assertMissing($asset->storage_key);
});

it('blocks every approved media reference', function (string $type) {
    $this->actingAs(editorialMediaAdmin(), 'web');
    $asset = mediaEditorialAsset();
    match ($type) {
        'artwork' => ArtworkMedia::create(['artwork_id' => Artwork::create(['artwork_category_id' => ArtworkCategory::create(['slug' => 'sculptures', 'name' => 'Sculptures', 'state' => 'published', 'position' => 0])->id, 'slug' => 'ref-'.uniqid(), 'title' => 'Ref', 'state' => 'draft', 'position' => 0, 'date_precision' => 'unknown'])->id, 'media_asset_id' => $asset->id, 'role' => 'primary', 'position' => 0]),
        'exhibition' => Exhibition::create(['slug' => 'ref-'.uniqid(), 'title' => 'Ref', 'state' => 'draft', 'position' => 0, 'hero_media_asset_id' => $asset->id]),
        'cv' => CvEntry::create(['section' => 'CV', 'title' => 'Ref', 'state' => 'draft', 'position' => 0, 'image_media_asset_id' => $asset->id]),
        default => BlogPost::create(['slug' => 'ref-'.uniqid(), 'title' => 'Ref', 'state' => 'draft', 'position' => 0, 'cover_media_asset_id' => $asset->id]),
    };
    expect(fn () => app(MediaAssetEditorialService::class)->delete($asset))->toThrow(ValidationException::class);
    expect($asset->fresh()->state)->toBe('available');
})->with(['artwork', 'exhibition', 'cv', 'blog']);

it('does not audit or mutate when deleting an already deleted asset', function () {
    Storage::fake(config('media.disk'));
    $this->actingAs(editorialMediaAdmin(), 'web');
    $asset = mediaEditorialAsset('deleted');
    Storage::disk(config('media.disk'))->put($asset->storage_key, 'orphan');
    expect(app(MediaAssetEditorialService::class)->delete($asset))->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'media.deleted')->count())->toBe(0);
});

it('rolls back logical media deletion when audit creation fails', function () {
    Storage::fake(config('media.disk'));
    $this->actingAs(editorialMediaAdmin(), 'web');
    $asset = mediaEditorialAsset();
    Storage::disk(config('media.disk'))->put($asset->storage_key, 'orig');
    AuditEvent::creating(fn (): never => throw new RuntimeException('audit failed'));

    try {
        expect(fn () => app(MediaAssetEditorialService::class)->delete($asset))
            ->toThrow(RuntimeException::class, 'audit failed');
    } finally {
        AuditEvent::flushEventListeners();
    }

    expect($asset->fresh()->state)->toBe('available')
        ->and(Storage::disk(config('media.disk'))->exists($asset->storage_key))->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'media.deleted')->count())->toBe(0);
});
