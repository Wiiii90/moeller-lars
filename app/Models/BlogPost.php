<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['slug', 'title', 'body', 'state', 'position', 'excerpt', 'cover_media_asset_id', 'published_at', 'scheduled_at', 'legacy_id', 'legacy_source', 'migration_batch_id', 'migrated_at'])]
#[Guarded(['id'])]
class BlogPost extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'migrated_at' => 'datetime',
        ];
    }

    public function coverMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_asset_id');
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        $now = now();

        return $query->where(function (Builder $visibility) use ($now): void {
            $visibility->where(function (Builder $published) use ($now): void {
                $published->where('state', 'published')->where('published_at', '<=', $now);
            })->orWhere(function (Builder $scheduled) use ($now): void {
                $scheduled->where('state', 'scheduled')->where('scheduled_at', '<=', $now);
            });
        });
    }
}
