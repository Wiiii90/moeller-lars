<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['section', 'title', 'state', 'position', 'date_precision', 'organisation', 'location', 'body', 'external_url', 'image_media_asset_id', 'year_text', 'starts_on', 'ends_on', 'legacy_id', 'legacy_source', 'migration_batch_id', 'migrated_at', 'published_at'])]
#[Guarded(['id'])]
class CvEntry extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'migrated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function imageMediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'image_media_asset_id');
    }
}
