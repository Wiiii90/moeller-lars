<?php

namespace App\Models;

use App\Domain\Media\MediaIngestService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['exhibition_id', 'media_asset_id', 'role', 'position', 'alt_text_override'])]
#[Guarded(['id'])]
class ExhibitionMedia extends Model
{
    protected $table = 'exhibition_media';

    public function exhibition(): BelongsTo
    {
        return $this->belongsTo(Exhibition::class);
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $usage): void {
            $assetId = $usage->getAttribute('media_asset_id');
            $asset = MediaAsset::query()->with('variants')->find($assetId);
            if (! $asset instanceof MediaAsset || $asset->getAttribute('state') !== 'available') {
                throw ValidationException::withMessages(['media_asset_id' => 'Exhibition media must reference an available media asset.']);
            }

            $override = $usage->getAttribute('alt_text_override');
            if ($override !== null && (! is_string($override) || trim($override) === '')) {
                throw ValidationException::withMessages(['alt_text_override' => 'ALT override must be non-empty text when provided.']);
            }

            $assetAlt = $asset->getAttribute('alt_text');
            if ($override === null && (! is_string($assetAlt) || trim($assetAlt) === '')) {
                throw ValidationException::withMessages(['media_asset_id' => 'Exhibition media requires canonical ALT text.']);
            }

            $thumbnailCount = $asset->variants
                ->filter(static fn (MediaVariant $variant): bool => $variant->getAttribute('variant_kind') === 'thumbnail'
                    && $variant->getAttribute('transform_profile') === MediaIngestService::TRANSFORM_PROFILE
                    && $variant->getAttribute('state') === 'available')
                ->count();

            if ($thumbnailCount !== 1) {
                throw ValidationException::withMessages(['media_asset_id' => 'Exhibition media requires exactly one available public thumbnail.']);
            }
        });
    }
}
