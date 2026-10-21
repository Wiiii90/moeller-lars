<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['site_section_id', 'slug', 'title', 'body', 'state', 'position', 'excerpt', 'gallery_presentation', 'image_viewer_enabled', 'published_at', 'scheduled_at', 'legacy_id', 'legacy_source', 'migration_batch_id', 'migrated_at'])]
#[Guarded(['id'])]
class BlogPost extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'image_viewer_enabled' => 'boolean',
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'migrated_at' => 'datetime',
        ];
    }

    public function galleryPresentation(): string
    {
        $value = (string) ($this->getAttribute('gallery_presentation') ?? 'sequence');

        return in_array($value, ['sequence', 'grid', 'mosaic', 'slideshow'], true) ? $value : 'sequence';
    }

    public function siteSection(): BelongsTo
    {
        return $this->belongsTo(SiteSection::class);
    }

    public function mediaUsages(): HasMany
    {
        return $this->hasMany(JournalEntryMedia::class)
            ->orderBy('role')
            ->orderBy('position')
            ->orderBy('id');
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
