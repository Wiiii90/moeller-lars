<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['listing_title', 'listing_intro'])]
#[Guarded(['id', 'site_section_id'])]
final class JournalSetting extends Model
{
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
