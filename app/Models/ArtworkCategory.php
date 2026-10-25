<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['slug', 'name', 'show_on_home', 'description', 'landscape_detail_enabled', 'portrait_detail_enabled', 'artwork_page_surface_mode', 'edge_navigation_mode', 'artwork_divider', 'sort_mode', 'video_sound_enabled', 'show_artwork_information', 'show_media_credits', 'show_media_copyright', 'caption_layout', 'legacy_id', 'legacy_source', 'migration_batch_id', 'migrated_at'])]
#[Guarded(['id'])]
class ArtworkCategory extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'show_on_home' => 'boolean',
            'landscape_detail_enabled' => 'boolean',
            'portrait_detail_enabled' => 'boolean',
            'video_sound_enabled' => 'boolean',
            'show_artwork_information' => 'boolean',
            'show_media_credits' => 'boolean',
            'show_media_copyright' => 'boolean',
        ];
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
