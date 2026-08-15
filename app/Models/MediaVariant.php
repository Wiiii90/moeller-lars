<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['media_asset_id', 'variant_kind', 'storage_key', 'mime_type', 'byte_size', 'sha256', 'transform_profile', 'state', 'width', 'height'])]
#[Guarded(['id'])]
class MediaVariant extends Model
{
    use HasFactory;

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }
}
