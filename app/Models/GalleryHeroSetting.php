<?php

namespace App\Models;

use App\Domain\Content\GalleryTemplate;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['configuration'])]
#[Guarded(['id', 'site_section_id'])]
final class GalleryHeroSetting extends Model
{
    protected $table = 'gallery_hero_settings';

    protected function casts(): array
    {
        return ['configuration' => 'array'];
    }

    /** @return BelongsTo<SiteSection, $this> */
    public function siteSection(): BelongsTo
    {
        return $this->belongsTo(SiteSection::class);
    }

    public static function forSection(SiteSection|int $section): self
    {
        /** @var SiteSection $page */
        $page = $section instanceof SiteSection ? $section : SiteSection::query()->findOrFail($section);
        if ($page->galleryTemplate() !== GalleryTemplate::HeroArtworks) {
            throw ValidationException::withMessages(['template' => 'This Gallery does not use Hero Artworks.']);
        }

        /** @var self $setting */
        $setting = self::query()->where('site_section_id', $page->getKey())->firstOrFail();

        return $setting;
    }

    /** @return array<string, mixed> */
    public function configuration(): array
    {
        $value = $this->getAttribute('configuration');

        return is_array($value) ? $value : [];
    }
}
