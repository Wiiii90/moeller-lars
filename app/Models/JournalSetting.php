<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['listing_title', 'listing_intro', 'default_gallery_presentation', 'default_settings_enabled', 'default_image_viewer_enabled', 'default_video_controls_enabled'])]
#[Guarded(['id', 'site_section_id'])]
final class JournalSetting extends Model
{
    protected function casts(): array
    {
        return [
            'default_image_viewer_enabled' => 'boolean',
            'default_settings_enabled' => 'boolean',
            'default_video_controls_enabled' => 'boolean',
        ];
    }

    /** @return BelongsTo<SiteSection, $this> */
    public function siteSection(): BelongsTo
    {
        return $this->belongsTo(SiteSection::class);
    }

    public static function forSection(SiteSection|int $section): self
    {
        $sectionId = $section instanceof SiteSection ? (int) $section->getKey() : $section;

        /** @var self $settings */
        $settings = self::query()->where('site_section_id', $sectionId)->firstOrFail();

        return $settings;
    }
}
