<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['listing_title', 'listing_intro'])]
#[Guarded(['id', 'site_section_id'])]
class BlogSetting extends Model
{
    use HasFactory;

    protected $table = 'blog_settings';

    public static function forBlogSection(): self
    {
        $section = SiteSection::query()
            ->where('type', SiteSection::TYPE_BLOG)
            ->firstOrFail();

        return self::forSection($section);
    }

    public static function forSection(SiteSection|int $section): self
    {
        $sectionId = $section instanceof SiteSection ? (int) $section->getKey() : $section;

        /** @var self $settings */
        $settings = self::query()->where('site_section_id', $sectionId)->firstOrFail();

        return $settings;
    }

    /** @return BelongsTo<SiteSection, $this> */
    public function siteSection(): BelongsTo
    {
        return $this->belongsTo(SiteSection::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (self $settings): void {
            /** @var SiteSection|null $section */
            $section = $settings->siteSection()->first();
            if ($section === null
                || (string) $section->getAttribute('type') !== SiteSection::TYPE_JOURNAL
                || (string) $section->getAttribute('template') !== SiteSection::JOURNAL_TEMPLATE_BLOG) {
                throw new LogicException('Canonical legacy Blog settings cannot be deleted.');
            }
        });
    }
}
