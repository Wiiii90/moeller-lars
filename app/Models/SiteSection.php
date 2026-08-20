<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
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

    public const TYPE_VITA = 'vita';

    public const TYPE_BLOG = 'blog';

    public const TYPE_EXHIBITIONS = 'exhibitions';

    public const TYPES = [
        self::TYPE_HOME,
        self::TYPE_GALLERY,
        self::TYPE_VITA,
        self::TYPE_BLOG,
        self::TYPE_EXHIBITIONS,
    ];

    public const SINGLETON_TYPES = [
        self::TYPE_HOME,
        self::TYPE_VITA,
        self::TYPE_BLOG,
        self::TYPE_EXHIBITIONS,
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

            if ($section->getAttribute('parent_id') !== null && $type !== self::TYPE_GALLERY) {
                throw ValidationException::withMessages(['parent_id' => 'Only Gallery sections may have a parent section.']);
            }

            if ((bool) $section->getAttribute('show_in_navigation')) {
                $label = $section->getAttribute('navigation_label');
                if (! is_string($label) || trim($label) === '') {
                    throw ValidationException::withMessages(['navigation_label' => 'A navigation label is required while this section is shown in navigation.']);
                }
            }
        });
    }

    /** @param Builder<self> $query */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('state', 'published');
    }

    /** @param Builder<self> $query */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /** @param Builder<self> $query */
    public function scopeVisibleInNavigation(Builder $query): Builder
    {
        return $query->published()->where('show_in_navigation', true);
    }

    public static function isPublished(string $type): bool
    {
        return static::query()->ofType($type)->published()->exists();
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

    public function publicPath(): string
    {
        return match ($this->getAttribute('type')) {
            self::TYPE_HOME => '/',
            self::TYPE_GALLERY => '/'.$this->getAttribute('slug'),
            self::TYPE_VITA => '/cv',
            self::TYPE_BLOG => '/blog',
            self::TYPE_EXHIBITIONS => '/exhibitions',
            default => throw new LogicException('Unsupported site section type.'),
        };
    }

    public function publicUrl(): string
    {
        return match ($this->getAttribute('type')) {
            self::TYPE_HOME => route('home'),
            self::TYPE_GALLERY => route('artworks.category', ['category' => $this->getAttribute('slug')]),
            self::TYPE_VITA => route('cv'),
            self::TYPE_BLOG => route('blog.index'),
            self::TYPE_EXHIBITIONS => route('exhibitions.index'),
            default => throw new LogicException('Unsupported site section type.'),
        };
    }

    public function isCurrentRequest(): bool
    {
        return match ($this->getAttribute('type')) {
            self::TYPE_HOME => request()->routeIs('home'),
            self::TYPE_GALLERY => request()->routeIs('artworks.category')
                && request()->route('category') === $this->getAttribute('slug'),
            self::TYPE_VITA => request()->routeIs('cv', 'contact'),
            self::TYPE_BLOG => request()->routeIs('blog.*'),
            self::TYPE_EXHIBITIONS => request()->routeIs('exhibitions.*'),
            default => false,
        };
    }
}
