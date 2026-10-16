<?php

use App\Domain\Media\MediaIptcXmpWriter;
use App\Domain\Media\MediaRightsArtifactRevision;
use App\Domain\Media\MediaRightsProjection;
use App\Domain\Media\PublicMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;

function revisionAsset(string $creator): MediaAsset
{
    $asset = new MediaAsset;
    $asset->setRawAttributes([
        'id' => 91,
        'mime_type' => 'image/jpeg',
        'sha256' => str_repeat('a', 64),
        'file_creator' => $creator,
        'file_rights_holder' => 'Agency',
        'copyright_notice_mode' => MediaAsset::COPYRIGHT_OVERRIDE,
        'copyright_notice' => '© Agency',
    ], true);

    return $asset;
}

it('changes public image artifact revision when rights, source or mining authorization change', function (): void {
    $revisions = new MediaRightsArtifactRevision(new MediaIptcXmpWriter(new MediaRightsProjection));
    $asset = revisionAsset('Original photographer');
    $first = $revisions->forSource($asset, $asset);
    expect($first)->toHaveLength(64)
        ->and($revisions->forSource($asset, $asset))->toBe($first)
        ->and($revisions->forSource($asset, $asset, true))->not->toBe($first);

    $asset->setAttribute('file_creator', 'New photographer');
    $second = $revisions->forSource($asset, $asset);
    expect($second)->not->toBe($first);

    $asset->setAttribute('file_creator', 'Original photographer');
    $asset->setAttribute('sha256', str_repeat('b', 64));
    expect($revisions->forSource($asset, $asset))->not->toBe($first);
});

it('separates thumbnail rights revisions from full resolution and refuses another asset variant', function (): void {
    $revisions = new MediaRightsArtifactRevision(new MediaIptcXmpWriter(new MediaRightsProjection));
    $asset = revisionAsset('Photographer');
    $variant = new MediaVariant;
    $variant->setRawAttributes([
        'media_asset_id' => 91,
        'sha256' => str_repeat('c', 64),
        'mime_type' => 'image/webp',
        'variant_kind' => PublicMedia::THUMBNAIL_KIND,
        'transform_profile' => PublicMedia::PUBLIC_TRANSFORM_PROFILE,
    ], true);

    expect($revisions->forSource($asset, $variant))
        ->not->toBe($revisions->forSource($asset, $asset));

    $variant->setAttribute('media_asset_id', 92);
    expect(fn () => $revisions->forSource($asset, $variant))
        ->toThrow(InvalidArgumentException::class);
});
