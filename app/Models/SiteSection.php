<?php

namespace App\Models;

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteNodeType;
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
    'slug',
    'state',
    'position',
    'show_in_navigation',
    'parent_id',
    'artwork_category_id',
])]
#[Guarded(['id'])]
final class SiteSection extends Model
{
    /**
     * Persistence values retained for migrations and raw database boundaries.
     * SiteNodeType and JournalTemplate own application behavior.
     */
    public const TYPE_HOME = 'home';

    public const TYPE_GALLERY = 'gallery';

    public const TYPE_NAVIGATION_GROUP = 'navigation_group';

    public const TYPE_CUSTOM = 'custom';

    public const TYPE_JOURNAL = 'journal';

    public const JOURNAL_TEMPLATE_BLOG = 'blog';

    public const JOURNAL_TEMPLATE_EXHIBITIONS = 'exhibitions';

    public const UNIQUE_TYPES = [self::TYPE_HOME];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'show_in_navigation' => 'boolean',
            'parent_id' => 'integer',
            'artwork_category_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        self::saving(function (self $section): void {
            $nodeType = SiteNodeType::tryFrom((string) $section->getAttribute('type'));
            if ($nodeType === null) {
                throw ValidationException::withMessages(['type' => 'The site node type is invalid.']);
            }

            $template = $section->getAttribute('template');
            if ($nodeType === SiteNodeType::Journal) {
                if (! is_string($template) || JournalTemplate::tryFrom($template) === null) {
                    throw ValidationException::withMessages(['template' => 'A Journal page requires a supported template.']);
                }
            } elseif ($template !== null) {
                throw ValidationException::withMessages(['template' => 'Only Journal pages may select a template.']);
            }

            if ($nodeType === SiteNodeType::Gallery) {
                if (! is_numeric($section->getAttribute('artwork_category_id'))) {
                    throw ValidationException::withMessages(['artwork_category_id' => 'A Gallery site node must reference its Gallery record.']);
                }
            } elseif ($section->getAttribute('artwork_category_id') !== null) {
                throw ValidationException::withMessages(['artwork_category_id' => 'Only Gallery site nodes may reference a Gallery record.']);
            }

            $slug = $section->getAttribute('slug');
            if ($nodeType->requiresSlug()) {
                if (! is_string($slug) || trim($slug) === '') {
                    throw ValidationException::withMessages(['slug' => $nodeType->label().' requires a public URL slug.']);
                }
            } elseif ($slug !== null) {
                throw ValidationException::withMessages(['slug' => $nodeType->label().' does not own a public URL slug.']);
            }

            if ($nodeType === SiteNodeType::Home && (string) $section->getAttribute('state') !== 'published') {
                throw ValidationException::withMessages(['state' => 'Home is always published.']);
            }

            $parentId = $section->getAttribute('parent_id');
            if ($parentId !== null) {
                if (! $nodeType->canHaveParent()) {
                    throw ValidationException::withMessages(['parent_id' => $nodeType->label().' cannot be nested below another site node.']);
                }
                if ($section->exists && (int) $parentId === (int) $section->getKey()) {
                    throw ValidationException::withMessages(['parent_id' => 'A site node cannot be its own parent.']);
                }

                /** @var self|null $parent */
                $parent = self::query()->find($parentId);
                if (! $parent instanceof self) {
                    throw ValidationException::withMessages(['parent_id' => 'The selected parent site node does not exist.']);
                }

                $parentType = $parent->nodeType();
                if ($parent->getAttribute('parent_id') !== null || ! $nodeType->canBeChildOf($parentType)) {
                    throw ValidationException::withMessages(['parent_id' => 'The selected parent cannot contain this site node type.']);
                }
            }

            if ((bool) $section->getAttribute('show_in_navigation')) {
                $label = $section->getAttribute('navigation_label');
                if (! is_string($label) || trim($label) === '') {
                    throw ValidationException::withMessages(['navigation_label' => 'A navigation label is required while this site node is shown in navigation.']);
                }
            }

            if ($nodeType === SiteNodeType::CustomPage && (string) $section->getAttribute('state') === 'published' && $section->exists) {
                /** @var CustomPageSetting|null $settings */
                $settings = $section->customPageSetting()->first();
                if (! $settings instanceof CustomPageSetting) {
                    throw ValidationException::withMessages(['state' => 'A Custom Page needs its component configuration before it can be published.']);
                }
                $settings->assertReadyForPublic();
            }
        });

        self::deleting(function (self $section): void {
            if ($section->nodeType() === SiteNodeType::Home) {
                throw ValidationException::withMessages(['section' => 'Home cannot be deleted.']);
            }
        });
    }

    public static function isPublished(string $type): bool
    {
        return self::query()->where('type', $type)->where('state', 'published')->exists();
    }

    public function nodeType(): SiteNodeType
    {
        return SiteNodeType::from((string) $this->getAttribute('type'));
    }

    public function journalTemplate(): ?JournalTemplate
    {
        if ($this->nodeType() !== SiteNodeType::Journal) {
            return null;
        }

        return JournalTemplate::from((string) $this->getAttribute('template'));
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

    public function canContainChildren(): bool
    {
        return $this->nodeType()->canContainChildren();
    }
}
