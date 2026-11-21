<?php

namespace App\Models;

use App\Domain\Content\GalleryTemplate;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SitePageHeading;
use App\Domain\Content\SiteSectionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'type',
    'template',
    'title',
    'navigation_label',
    'meta_description',
    'show_in_navigation',
    'public_site_gate',
    'slug',
    'state',
    'position',
    'parent_id',
    'artwork_category_id',
    'content_heading_enabled',
    'content_heading_override',
    'content_heading_alignment',
    'content_heading_divider',
])]
#[Guarded(['id'])]
final class SiteSection extends Model
{
    /**
     * Persistence values retained for migrations and raw database boundaries.
     * SiteSectionType, GalleryTemplate and JournalTemplate own application behavior.
     */
    public const TYPE_GALLERY = 'gallery';

    public const TYPE_NAVIGATION_GROUP = 'navigation_group';

    public const TYPE_CUSTOM = 'custom';

    public const TYPE_JOURNAL = 'journal';

    public const JOURNAL_TEMPLATE_BLOG = 'blog';

    public const JOURNAL_TEMPLATE_EXHIBITIONS = 'exhibitions';

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'show_in_navigation' => 'boolean',
            'is_home' => 'boolean',
            'public_site_gate' => 'boolean',
            'parent_id' => 'integer',
            'artwork_category_id' => 'integer',
            'content_heading_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        self::saving(function (self $section): void {
            $nodeType = SiteSectionType::tryFrom((string) $section->getAttribute('type'));
            if ($nodeType === null) {
                throw ValidationException::withMessages(['type' => 'The site node type is invalid.']);
            }

            if ($section->exists && $section->isDirty('is_home')) {
                throw ValidationException::withMessages(['is_home' => 'The system Home page identity cannot be changed.']);
            }

            if ($section->isHome() && $nodeType === SiteSectionType::NavigationNode) {
                throw ValidationException::withMessages(['type' => 'Home must render a public page.']);
            }

            $heading = SitePageHeading::validated([
                'content_heading_enabled' => (bool) $section->getAttribute('content_heading_enabled'),
                'content_heading_override' => $section->getAttribute('content_heading_override'),
                'content_heading_alignment' => $section->getAttribute('content_heading_alignment') ?: 'left',
                'content_heading_divider' => $section->getAttribute('content_heading_divider') ?: 'none',
            ]);
            if (trim((string) ($section->getAttribute('content_heading_override') ?? '')) !== ($heading['content_heading_override'] ?? '')) {
                $section->setAttribute('content_heading_override', $heading['content_heading_override']);
            }

            $template = $section->getAttribute('template');
            if ($nodeType === SiteSectionType::Journal) {
                if (! is_string($template) || JournalTemplate::tryFrom($template) === null) {
                    throw ValidationException::withMessages(['template' => 'A Journal page requires a supported template.']);
                }
            } elseif ($nodeType === SiteSectionType::Gallery) {
                if (! is_string($template) || GalleryTemplate::tryFrom($template) === null) {
                    throw ValidationException::withMessages(['template' => 'A Gallery page requires a supported template.']);
                }
            } elseif ($template !== null) {
                throw ValidationException::withMessages(['template' => 'Only Gallery and Journal pages may select a template.']);
            }

            if ($nodeType === SiteSectionType::Gallery) {
                if ($section->galleryTemplate() === GalleryTemplate::Standard
                    && ! is_numeric($section->getAttribute('artwork_category_id'))) {
                    throw ValidationException::withMessages(['artwork_category_id' => 'A Standard Gallery requires an Artwork collection.']);
                }
            } elseif ($section->getAttribute('artwork_category_id') !== null) {
                throw ValidationException::withMessages(['artwork_category_id' => 'Only Gallery site nodes may reference their Gallery record.']);
            }

            $slug = $section->getAttribute('slug');
            if ($section->isHome() && $slug !== null) {
                throw ValidationException::withMessages(['slug' => 'The Home page uses / and has no slug.']);
            }
            if ($nodeType->requiresSlug() && ! $section->isHome()) {
                if (! is_string($slug) || trim($slug) === '') {
                    throw ValidationException::withMessages(['slug' => $nodeType->label().' requires a public URL slug.']);
                }
            } elseif ($slug !== null) {
                throw ValidationException::withMessages(['slug' => 'Home and navigation-only pages do not own a public URL slug.']);
            }

            if ($section->isHome()) {
                if ((string) $section->getAttribute('state') !== 'published') {
                    throw ValidationException::withMessages(['state' => 'Home is always published.']);
                }
                if ($section->getAttribute('parent_id') !== null) {
                    throw ValidationException::withMessages(['parent_id' => 'Home is always a top-level page.']);
                }
            }

            $parentId = $section->getAttribute('parent_id');
            if ($parentId !== null) {
                if ($section->exists && (int) $parentId === (int) $section->getKey()) {
                    throw ValidationException::withMessages(['parent_id' => 'A site node cannot be its own parent.']);
                }

                /** @var self|null $parent */
                $parent = self::query()->find($parentId);
                if (! $parent instanceof self) {
                    throw ValidationException::withMessages(['parent_id' => 'The selected parent site node does not exist.']);
                }
                if (! $parent->canContainSitePages()) {
                    throw ValidationException::withMessages(['parent_id' => 'The parent must be a top-level page.']);
                }
                if ($section->exists && self::query()->where('parent_id', $section->getKey())->exists()) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'A page that already has child pages cannot itself become a child page.',
                    ]);
                }
            }

            // Publication controls general menu eligibility. Home additionally
            // owns its explicit navigation visibility without changing its route.
            // Published sections may use their title when no label is configured.
            if ((string) $section->getAttribute('state') === 'published') {
                $label = $section->getAttribute('navigation_label') ?: $section->getAttribute('title');
                if (! is_string($label) || trim($label) === '') {
                    throw ValidationException::withMessages(['navigation_label' => 'A published page requires a navigation label or title.']);
                }
            }

            if ($nodeType === SiteSectionType::CustomPage && (string) $section->getAttribute('state') === 'published' && $section->exists) {
                /** @var CustomPageSetting|null $settings */
                $settings = $section->customPageSetting()->first();
                if (! $settings instanceof CustomPageSetting) {
                    throw ValidationException::withMessages(['state' => 'A Custom Page needs its component configuration before it can be published.']);
                }
                $settings->assertReadyForPublic();
            }
        });

        self::deleting(function (self $section): void {
            if ($section->isHome()) {
                throw ValidationException::withMessages(['section' => 'Home cannot be deleted.']);
            }
        });
    }

    public static function isPublished(string $type): bool
    {
        return self::query()->where('type', $type)->where('state', 'published')->exists();
    }

    public function nodeType(): SiteSectionType
    {
        return SiteSectionType::from((string) $this->getAttribute('type'));
    }

    /** System page identity independent of the selected content type. */
    public function isHome(): bool
    {
        return (bool) $this->getAttribute('is_home');
    }

    /** Only ordinary top-level pages can receive one level of child pages. */
    public function canContainSitePages(): bool
    {
        return ! $this->isHome() && $this->getAttribute('parent_id') === null;
    }

    public function journalTemplate(): ?JournalTemplate
    {
        if ($this->nodeType() !== SiteSectionType::Journal) {
            return null;
        }

        return JournalTemplate::from((string) $this->getAttribute('template'));
    }

    public function galleryTemplate(): ?GalleryTemplate
    {
        if ($this->nodeType() !== SiteSectionType::Gallery) {
            return null;
        }

        return GalleryTemplate::from((string) $this->getAttribute('template'));
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function artworkCategory(): BelongsTo
    {
        return $this->belongsTo(ArtworkCategory::class);
    }

    public function customPageSetting(): HasOne
    {
        return $this->hasOne(CustomPageSetting::class);
    }

    public function journalSetting(): HasOne
    {
        return $this->hasOne(JournalSetting::class);
    }

    public function hasPublicPage(): bool
    {
        return $this->nodeType()->hasPublicPage();
    }

    /** Page metadata has a single owner, regardless of Gallery/Journal template. */
    public function metaDescriptionOr(string $fallback): string
    {
        $description = trim((string) ($this->getAttribute('meta_description') ?? ''));

        return $description !== '' ? $description : $fallback;
    }
}
