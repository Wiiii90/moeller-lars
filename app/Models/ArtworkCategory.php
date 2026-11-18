<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['slug', 'name', 'show_on_home', 'artwork_page_layout', 'edge_navigation_mode', 'artwork_divider', 'sort_mode', 'video_sound_enabled', 'show_artwork_information', 'show_media_credits', 'show_media_copyright', 'caption_layout', 'artwork_information', 'media_viewer_enabled', 'artwork_pages_enabled', 'viewer_zoom_enabled', 'viewer_navigation_enabled', 'legacy_id', 'legacy_source', 'migration_batch_id', 'migrated_at'])]
#[Guarded(['id'])]
class ArtworkCategory extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'show_on_home' => 'boolean',
            'video_sound_enabled' => 'boolean',
            'show_artwork_information' => 'boolean',
            'show_media_credits' => 'boolean',
            'show_media_copyright' => 'boolean',
            'artwork_information' => 'array',
            'media_viewer_enabled' => 'boolean',
            'artwork_pages_enabled' => 'boolean',
            'viewer_zoom_enabled' => 'boolean',
            'viewer_navigation_enabled' => 'boolean',
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
