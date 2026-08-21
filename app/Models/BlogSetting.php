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

        /** @var self $settings */
        $settings = self::query()
            ->where('site_section_id', $section->getKey())
            ->firstOrFail();

        return $settings;
    }

    /** @return BelongsTo<SiteSection, $this> */
    public function siteSection(): BelongsTo
    {
        return $this->belongsTo(SiteSection::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new LogicException('Blog settings cannot be deleted.');
        });
    }
}
