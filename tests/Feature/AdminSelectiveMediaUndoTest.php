<?php

use App\Domain\Admin\AdminActionReceiptService;
use App\Domain\Admin\AdminUndoService;
use App\Domain\Artwork\ArtworkEditorialService;
use App\Models\AdminActionReceipt;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\AuditEvent;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function selectiveMediaUndoAsset(string $name): MediaAsset
{
    return MediaAsset::query()->create([
        'storage_key' => 'originals/media-undo-'.$name.'.jpg',
        'original_filename' => 'media-undo-'.$name.'.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 3,
        'sha256' => hash('sha256', 'media-undo-'.$name),
        'state' => 'available',
        'alt_text' => $name,
        'width' => 10,
        'height' => 10,
    ]);
}

function selectiveMediaUndoArtwork(): Artwork
{
    $category = ArtworkCategory::query()->create([
        'slug' => 'media-undo-gallery',
        'name' => 'Media Undo Gallery',
        'state' => 'published',
        'position' => 0,
    ]);

    return Artwork::query()->create([
        'artwork_category_id' => $category->getKey(),
        'slug' => 'media-undo-artwork',
        'title' => 'Media Undo Artwork',
        'state' => 'draft',
        'position' => 0,
        'date_precision' => 'unknown',
    ]);
}

it('undoes an additional media attachment by detaching only the relation', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');
    $artwork = selectiveMediaUndoArtwork();
    $asset = selectiveMediaUndoAsset('attach');
    $editorial = app(ArtworkEditorialService::class);

    $usage = $editorial->attachAdditionalMedia($artwork, $asset);
    /** @var AdminActionReceipt $receipt */
    $receipt = AdminActionReceipt::query()
        ->where('action_key', 'artwork.additional_media_attached')
        ->where('artwork_media_id', $usage->getKey())
        ->firstOrFail();

    expect($receipt->getAttribute('media_asset_id'))->toBe($asset->getKey())
        ->and($receipt->getAttribute('after_position'))->toBe(1)
        ->and($receipt->getAttribute('before_state'))->toBe('detached')
        ->and($receipt->getAttribute('after_state'))->toBe('attached');

    app(AdminUndoService::class)->undo((int) $receipt->getKey());

    expect(ArtworkMedia::query()->whereKey($usage->getKey())->exists())->toBeFalse()
        ->and($asset->fresh()->getAttribute('state'))->toBe('available')
        ->and($receipt->fresh()->getAttribute('undone_at'))->not->toBeNull()
        ->and(AuditEvent::query()->where('action', 'artwork.additional_media_detached')->count())->toBe(1)
        ->and(AdminActionReceipt::query()
            ->where('action_key', 'artwork.additional_media_detached')
            ->whereNull('undone_at')
            ->exists())->toBeTrue();
});

it('restores detached media to the exact local position using neighbor preconditions', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');
    $artwork = selectiveMediaUndoArtwork();
    $editorial = app(ArtworkEditorialService::class);
    $firstAsset = selectiveMediaUndoAsset('restore-first');
    $middleAsset = selectiveMediaUndoAsset('restore-middle');
    $lastAsset = selectiveMediaUndoAsset('restore-last');
    $first = $editorial->attachAdditionalMedia($artwork, $firstAsset);
    $middle = $editorial->attachAdditionalMedia($artwork, $middleAsset);
    $last = $editorial->attachAdditionalMedia($artwork, $lastAsset);

    $editorial->detachAdditionalMedia($artwork, $middle);
    /** @var AdminActionReceipt $receipt */
    $receipt = AdminActionReceipt::query()
        ->where('action_key', 'artwork.additional_media_detached')
        ->where('artwork_media_id', $middle->getKey())
        ->firstOrFail();

    expect($receipt->getAttribute('before_position'))->toBe(2)
        ->and($receipt->getAttribute('previous_artwork_media_id'))->toBe($first->getKey())
        ->and($receipt->getAttribute('next_artwork_media_id'))->toBe($last->getKey());

    app(AdminUndoService::class)->undo((int) $receipt->getKey());

    $assetOrder = ArtworkMedia::query()
        ->where('artwork_id', $artwork->getKey())
        ->where('role', 'additional')
        ->orderBy('position')
        ->pluck('media_asset_id')
        ->map(static fn (mixed $id): int => (int) $id)
        ->all();

    expect($assetOrder)->toBe([
        (int) $firstAsset->getKey(),
        (int) $middleAsset->getKey(),
        (int) $lastAsset->getKey(),
    ])
        ->and($receipt->fresh()->getAttribute('undone_at'))->not->toBeNull()
        ->and(AdminActionReceipt::query()
            ->where('action_key', 'artwork.additional_media_attached')
            ->where('media_asset_id', $middleAsset->getKey())
            ->whereNull('undone_at')
            ->exists())->toBeTrue();
});

it('rejects detached-media undo after its local neighbor gap changes', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');
    $artwork = selectiveMediaUndoArtwork();
    $editorial = app(ArtworkEditorialService::class);
    $first = $editorial->attachAdditionalMedia($artwork, selectiveMediaUndoAsset('gap-first'));
    $middle = $editorial->attachAdditionalMedia($artwork, selectiveMediaUndoAsset('gap-middle'));
    $next = $editorial->attachAdditionalMedia($artwork, selectiveMediaUndoAsset('gap-next'));
    $editorial->attachAdditionalMedia($artwork, selectiveMediaUndoAsset('gap-last'));

    $editorial->detachAdditionalMedia($artwork, $middle);
    /** @var AdminActionReceipt $receipt */
    $receipt = AdminActionReceipt::query()
        ->where('action_key', 'artwork.additional_media_detached')
        ->where('artwork_media_id', $middle->getKey())
        ->firstOrFail();
    /** @var AuditEvent $event */
    $event = AuditEvent::query()->findOrFail($receipt->getAttribute('audit_event_id'));

    $editorial->moveAdditionalMedia($artwork, $next, 'down');

    expect(app(AdminActionReceiptService::class)->availableForEvents(collect([$event]), $admin))->toBe([])
        ->and(fn () => app(AdminUndoService::class)->undo((int) $receipt->getKey()))
        ->toThrow(ValidationException::class)
        ->and(ArtworkMedia::query()
            ->where('artwork_id', $artwork->getKey())
            ->where('media_asset_id', $middle->getAttribute('media_asset_id'))
            ->exists())->toBeFalse()
        ->and($first->fresh()->getAttribute('position'))->toBe(1);
});

it('undoes an adjacent additional-media reorder and emits a reciprocal reorder receipt', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');
    $artwork = selectiveMediaUndoArtwork();
    $editorial = app(ArtworkEditorialService::class);
    $first = $editorial->attachAdditionalMedia($artwork, selectiveMediaUndoAsset('reorder-first'));
    $second = $editorial->attachAdditionalMedia($artwork, selectiveMediaUndoAsset('reorder-second'));
    $third = $editorial->attachAdditionalMedia($artwork, selectiveMediaUndoAsset('reorder-third'));

    $editorial->moveAdditionalMedia($artwork, $third, 'up');
    /** @var AdminActionReceipt $receipt */
    $receipt = AdminActionReceipt::query()
        ->where('action_key', 'artwork.additional_media_reordered')
        ->latest('id')
        ->firstOrFail();

    expect($third->fresh()->getAttribute('position'))->toBe(2)
        ->and($second->fresh()->getAttribute('position'))->toBe(3)
        ->and($receipt->getAttribute('artwork_media_id'))->toBe($third->getKey())
        ->and($receipt->getAttribute('neighbor_artwork_media_id'))->toBe($second->getKey())
        ->and($receipt->getAttribute('before_position'))->toBe(3)
        ->and($receipt->getAttribute('after_position'))->toBe(2)
        ->and($receipt->getAttribute('inverse_direction'))->toBe('down');

    app(AdminUndoService::class)->undo((int) $receipt->getKey());

    expect($first->fresh()->getAttribute('position'))->toBe(1)
        ->and($second->fresh()->getAttribute('position'))->toBe(2)
        ->and($third->fresh()->getAttribute('position'))->toBe(3)
        ->and($receipt->fresh()->getAttribute('undone_at'))->not->toBeNull()
        ->and(AdminActionReceipt::query()
            ->where('action_key', 'artwork.additional_media_reordered')
            ->whereNull('undone_at')
            ->where('artwork_media_id', $third->getKey())
            ->exists())->toBeTrue();
});

it('rejects an older reorder receipt after the same relation is moved again', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');
    $artwork = selectiveMediaUndoArtwork();
    $editorial = app(ArtworkEditorialService::class);
    $first = $editorial->attachAdditionalMedia($artwork, selectiveMediaUndoAsset('stale-first'));
    $editorial->attachAdditionalMedia($artwork, selectiveMediaUndoAsset('stale-second'));
    $third = $editorial->attachAdditionalMedia($artwork, selectiveMediaUndoAsset('stale-third'));

    $editorial->moveAdditionalMedia($artwork, $third, 'up');
    /** @var AdminActionReceipt $oldReceipt */
    $oldReceipt = AdminActionReceipt::query()
        ->where('action_key', 'artwork.additional_media_reordered')
        ->latest('id')
        ->firstOrFail();

    $editorial->moveAdditionalMedia($artwork, $third, 'up');

    expect(fn () => app(AdminUndoService::class)->undo((int) $oldReceipt->getKey()))
        ->toThrow(ValidationException::class)
        ->and($third->fresh()->getAttribute('position'))->toBe(1)
        ->and($first->fresh()->getAttribute('position'))->toBe(2);
});
