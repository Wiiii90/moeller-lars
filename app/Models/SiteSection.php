<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use LogicException;

#[Fillable([
    'type',
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
    public const TYPE_HOME = 'home';
    public const TYPE_GALLERY = 'gallery';
    public const TYPE_NAVIGATION_GROUP = 'navigation_group';
    public const TYPE_VITA = 'vita';
    public const TYPE_BLOG = 'blog';
    public const TYPE_EXHIBITIONS = 'exhibitions';
    public const TYPE_CONTACT = 'contact';

    public const TYPES = [
        self::TYPE_HOME,
        self::TYPE_GALLERY,
        self::TYPE_NAVIGATION_GROUP,
        self::TYPE_VITA,
        self::TYPE_BLOG,
        self::TYPE_EXHIBITIONS,
        self::TYPE_CONTACT,
    ];

    /** Section types whose data model permits exactly one row per type. */
    public const UNIQUE_TYPES = [
        self::TYPE_HOME,
        self::TYPE_VITA,
        self::TYPE_BLOG,
        self::TYPE_EXHIBITIONS,
        self::TYPE_CONTACT,
    ];

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
            $type = (string) $section->getAttribute('type');
            if (! in_array($type, self::TYPES, true)) {
                throw ValidationException::withMessages(['type' => 'The site section type is invalid.']);
            }

            if ($type === self::TYPE_GALLERY) {
                if ($section->getAttribute('artwork_category_id') === null) {
                    throw ValidationException::withMessages(['artwork_category_id' => 'A Gallery section must reference an artwork category.']);
                }
            } elseif ($section->getAttribute('artwork_category_id') !== null) {
                throw ValidationException::withMessages(['artwork_category_id' => 'Only Gallery sections may reference artwork categories.']);
            }

            if ($type === self::TYPE_NAVIGATION_GROUP && $section->getAttribute('slug') !== null) {
                throw ValidationException::withMessages(['slug' => 'Navigation groups do not own a public URL slug.']);
            }

            $parentId = $section->getAttribute('parent_id');
            if ($parentId !== null) {
                if ($type === self::TYPE_HOME) {
                    throw ValidationException::withMessages(['parent_id' => 'Home cannot be nested below another section.']);
                }
                if ($type === self::TYPE_NAVIGATION_GROUP) {
                    throw ValidationException::withMessages(['parent_id' => 'Navigation groups must remain top-level submenu parents.']);
                }
                if ($section->exists && (int) $parentId === (int) $section->getKey()) {
                    throw ValidationException::withMessages(['parent_id' => 'A site section cannot be its own parent.']);
                }

                /** @var self|null $parent */
                $parent = self::query()->find($parentId);
                if ($parent !== null) {
                    if (! $parent->canContainChildren() || $parent->getAttribute('parent_id') !== null) {
                        throw ValidationException::withMessages(['parent_id' => 'The selected parent cannot contain submenu sections.']);
                    }
                    if ((string) $parent->getAttribute('type') === self::TYPE_GALLERY && $type !== self::TYPE_GALLERY) {
                        throw ValidationException::withMessages(['parent_id' => 'Gallery parents may only contain Gallery sections.']);
                    }
                }
            }

            if ((bool) $section->getAttribute('show_in_navigation')) {
                $label = $section->getAttribute('navigation_label');
                if (! is_string($label) || trim($label) === '') {
                    throw ValidationException::withMessages(['navigation_label' => 'A navigation label is required while this section is shown in navigation.']);
                }
            }
        });
    }

    public static function isPublished(string $type): bool
    {
        return self::query()->where('type', $type)->where('state', 'published')->exists();
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

    public function hasPublicPage(): bool
    {
        return (string) $this->getAttribute('type') !== self::TYPE_NAVIGATION_GROUP;
    }

    public function canContainChildren(): bool
    {
        return in_array((string) $this->getAttribute('type'), [self::TYPE_GALLERY, self::TYPE_NAVIGATION_GROUP], true);
    }

    public function publicPath(): ?string
    {
        return match ($this->getAttribute('type')) {
            self::TYPE_HOME => '/',
            self::TYPE_GALLERY => '/'.$this->getAttribute('slug'),
            self::TYPE_NAVIGATION_GROUP => null,
            self::TYPE_VITA => '/cv',
            self::TYPE_BLOG => '/blog',
            self::TYPE_EXHIBITIONS => '/exhibitions',
            self::TYPE_CONTACT => '/contact',
            default => throw new LogicException('Unsupported site section type.'),
        };
    }

    public function publicUrl(): ?string
    {
        return match ($this->getAttribute('type')) {
            self::TYPE_HOME => route('home'),
            self::TYPE_GALLERY => route('artworks.category', ['category' => $this->getAttribute('slug')]),
            self::TYPE_NAVIGATION_GROUP => null,
            self::TYPE_VITA => route('cv'),
            self::TYPE_BLOG => route('blog.index'),
            self::TYPE_EXHIBITIONS => route('exhibitions.index'),
            self::TYPE_CONTACT => route('contact'),
            default => throw new LogicException('Unsupported site section type.'),
        };
    }

    public function isCurrentRequest(): bool
    {
        return match ($this->getAttribute('type')) {
            self::TYPE_HOME => request()->routeIs('home', 'preview.home'),
            self::TYPE_GALLERY => request()->routeIs('artworks.category', 'preview.artworks.category')
                && request()->route('category') === $this->getAttribute('slug'),
            self::TYPE_NAVIGATION_GROUP => false,
            self::TYPE_VITA => request()->routeIs('cv', 'preview.cv', 'contact', 'preview.contact'),
            self::TYPE_BLOG => request()->routeIs('blog.*', 'preview.blog.*'),
            self::TYPE_EXHIBITIONS => request()->routeIs('exhibitions.*', 'preview.exhibitions.*'),
            self::TYPE_CONTACT => request()->routeIs('contact', 'preview.contact'),
            default => false,
        };
    }
}
