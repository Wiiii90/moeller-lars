<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['storage_key', 'original_filename', 'mime_type', 'byte_size', 'sha256', 'state', 'alt_text', 'copyright_notice', 'credit', 'width', 'height', 'metadata', 'focal_point_x', 'focal_point_y', 'legacy_id', 'legacy_source', 'legacy_path', 'legacy_filename', 'legacy_byte_size', 'migration_batch_id', 'migrated_at'])]
#[Guarded(['id'])]
class MediaAsset extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'focal_point_x' => 'decimal:4',
            'focal_point_y' => 'decimal:4',
            'migrated_at' => 'datetime',
        ];
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class);
    }

    public function cvEntries(): HasMany
    {
        return $this->hasMany(CvEntry::class, 'image_media_asset_id');
    }

    public function blogPosts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'cover_media_asset_id');
    }

    public function artworks(): BelongsToMany
    {
        return $this->belongsToMany(Artwork::class, 'artwork_media')
            ->withPivot(['role', 'position', 'alt_text_override'])
            ->withTimestamps();
    }

    public function exhibitionMedia(): HasMany
    {
        return $this->hasMany(ExhibitionMedia::class);
    }

    public function exhibitions(): BelongsToMany
    {
        return $this->belongsToMany(Exhibition::class, 'exhibition_media')
            ->withPivot(['role', 'position', 'alt_text_override'])
            ->withTimestamps();
    }
}
