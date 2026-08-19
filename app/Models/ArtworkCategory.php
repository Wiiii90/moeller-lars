<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['parent_id', 'slug', 'name', 'state', 'position', 'show_in_navigation', 'show_on_home', 'description', 'legacy_id', 'legacy_source', 'migration_batch_id', 'migrated_at'])]
#[Guarded(['id'])]
class ArtworkCategory extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'position' => 'integer',
            'show_in_navigation' => 'boolean',
            'show_on_home' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function siteSection(): HasOne
    {
        return $this->hasOne(SiteSection::class, 'artwork_category_id');
    }

    public function artworks(): HasMany
    {
        return $this->hasMany(Artwork::class);
    }
}
