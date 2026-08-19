<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable(['artwork_category_id', 'slug', 'title', 'medium', 'dimensions', 'description', 'state', 'position', 'legacy_date_raw', 'work_date', 'work_year', 'featured_on_home', 'date_precision', 'legacy_id', 'legacy_source', 'migration_batch_id', 'migrated_at', 'published_at'])]
#[Guarded(['id'])]
class Artwork extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'work_year' => 'integer',
            'featured_on_home' => 'boolean',
            'migrated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $artwork): void {
            if (blank($artwork->getAttribute('analytics_key'))) {
                $artwork->setAttribute('analytics_key', (string) Str::uuid());
            }
        });

        static::saving(function (self $artwork): void {
            $workDate = $artwork->getAttribute('work_date');
            if ($workDate !== null) {
                $artwork->setAttribute('work_year', (int) $workDate->format('Y'));

                return;
            }

            $workYear = $artwork->getAttribute('work_year');
            if ($workYear !== null && ((int) $workYear < 1000 || (int) $workYear > 9999)) {
                throw new LogicException('Artwork work year must be a four-digit year.');
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ArtworkCategory::class, 'artwork_category_id');
    }

    public function artworkMedia(): HasMany
    {
        return $this->hasMany(ArtworkMedia::class);
    }

    public function mediaAssets(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'artwork_media')
            ->withPivot(['role', 'position', 'alt_text_override'])
            ->withTimestamps();
    }
}
