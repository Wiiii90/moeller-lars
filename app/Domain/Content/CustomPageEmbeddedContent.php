<?php

namespace App\Domain\Content;

use App\Domain\Artwork\GalleryPresentation;
use App\Domain\Artwork\PublicArtworkQuery;
use App\Domain\Media\PublicMedia;
use App\Models\ArtworkCategory;
use App\Models\BlogPost;
use App\Models\Exhibition;
use App\Models\GalleryHeroSetting;
use App\Models\SiteSection;
use App\Routing\SiteNodeRoute;
use Illuminate\Validation\ValidationException;

/**
 * Shared source resolution for locally configured Custom Page templates.
 * The component document owns placement and source selection; existing
 * galleries and Journal entries retain their canonical content owners.
 */
final class CustomPageEmbeddedContent
{
    public function __construct(
        private readonly PublicArtworkQuery $artworks,
        private readonly GalleryHeroResolver $heroes,
        private readonly PublicMedia $media,
        private readonly SitePreviewContext $preview,
        private readonly SiteNodeRoute $routes,
    ) {}

    /** @param array<string,mixed> $block */
    public function assertValid(array $block, bool $public): void
    {
        $type = $block['type'] ?? null;
        if ($type === 'gallery') {
            $id = $block['gallery_id'] ?? null;
            if ($id === null && ! $public) {
                return;
            }
            if (! is_int($id) || $id < 1) {
                throw ValidationException::withMessages(['gallery_id' => 'Choose a Gallery.']);
            }
            $gallery = ArtworkCategory::query()->with('siteSection')->find($id);
            if (! $gallery instanceof ArtworkCategory || ! $gallery->siteSection instanceof SiteSection
                || $gallery->siteSection->galleryTemplate() !== GalleryTemplate::Standard) {
                throw ValidationException::withMessages(['gallery_id' => 'Choose an existing Standard Gallery.']);
            }
            if ($public && (string) $gallery->siteSection->getAttribute('state') !== 'published') {
                throw ValidationException::withMessages(['gallery_id' => 'Publish the source Gallery before publishing this component.']);
            }

            return;
        }

        $field = match ($type) {
            'hero_artworks' => 'gallery_ids',
            'exhibitions' => 'exhibition_ids',
            'blog' => 'post_ids',
            default => null,
        };
        if ($field === null) {
            return;
        }
        $ids = $block[$field] ?? [];
        if (! is_array($ids) || ! array_is_list($ids)
            || count($ids) > 100
            || count($ids) !== count(array_unique($ids, SORT_REGULAR))
            || array_filter($ids, static fn (mixed $id): bool => ! is_int($id) || $id < 1) !== []) {
            throw ValidationException::withMessages([$field => 'Choose valid, unique source records.']);
        }
        if ($ids === [] && ! $public) {
            return;
        }
        if ($ids === []) {
            throw ValidationException::withMessages([$field => 'Select at least one source.']);
        }
        $query = match ($type) {
            'hero_artworks' => ArtworkCategory::query()->whereHas('siteSection', static fn ($q) => $q->where('type', 'gallery')->where('template', GalleryTemplate::Standard->value)),
            'exhibitions' => Exhibition::query(),
            'blog' => BlogPost::query(),
        };
        if ((clone $query)->whereKey($ids)->count() !== count($ids)) {
            throw ValidationException::withMessages([$field => 'One or more selected sources no longer exist.']);
        }
        if ($type === 'hero_artworks') {
            $rule = $block['source_rule'] ?? 'newest_artwork';
            if (! is_string($rule) || ! array_key_exists($rule, GalleryHeroConfigurationService::SOURCE_RULES)) {
                throw ValidationException::withMessages(['source_rule' => 'Choose a supported Hero Artwork order.']);
            }
        }
        if ($public) {
            $active = match ($type) {
                'hero_artworks' => (clone $query)->whereHas('siteSection', static fn ($q) => $q->where('state', 'published')),
                'exhibitions' => (clone $query)->where('state', 'published')
                    ->whereHas('siteSection', static fn ($q) => $q->where('state', 'published')),
                'blog' => (clone $query)->publiclyVisible()
                    ->whereHas('siteSection', static fn ($q) => $q->where('state', 'published')),
            };
            if ($active->whereKey($ids)->count() !== count($ids)) {
                throw ValidationException::withMessages([$field => 'Publish all selected sources before publishing this component.']);
            }
        }
    }

    /**
     * Resolve one authored component into the existing Public template data.
     * Return null for an unconfigured draft; never create hidden SiteSections.
     *
     * @param array<string,mixed> $block
     * @return array<string,mixed>|null
     */
    public function presentation(array $block): ?array
    {
        $type = $block['type'] ?? null;
        $public = ! $this->preview->active();
        $this->assertValid($block, $public);
        if ($type === 'gallery') {
            $galleryId = $block['gallery_id'] ?? null;
            if (! is_int($galleryId)) {
                return null;
            }
            $category = ArtworkCategory::query()->with('siteSection')->findOrFail($galleryId);
            $section = $category->siteSection;
            $batch = $this->artworks->categoryBatch((string) $category->getAttribute('slug'), $section);
            $url = $this->preview->url($this->routes->url($section));

            return [
                'type' => $type,
                'category' => $category,
                'section' => $section,
                'artworks' => $batch['artworks'],
                'outline' => $batch['outline'],
                'hasMore' => $batch['hasMore'],
                'page' => 1,
                'divider' => GalleryPresentation::divider($category),
                'galleryOffsets' => GalleryPresentation::indexCardOffsets($batch['outline'], $category),
                'galleryGaps' => GalleryPresentation::indexCardGapSteps($batch['outline'], $category),
                'galleryUrl' => $url,
                'media' => $this->media,
            ];
        }
        if ($type === 'hero_artworks') {
            if (($block['gallery_ids'] ?? []) === []) {
                return null;
            }
            $settings = new GalleryHeroSetting([
                'configuration' => [
                    'enabled_gallery_ids' => $block['gallery_ids'],
                    'group_source' => 'automatic',
                    'display_strategy' => 'ordered',
                    'source_rule' => $block['source_rule'] ?? 'newest_artwork',
                    'selection_mode' => 'per_gallery',
                    'group_size' => 1,
                ],
            ]);
            $resolved = $this->heroes->resolve($settings);

            return [
                'type' => $type,
                'heroCandidate' => $resolved['current'],
                'heroGroup' => $resolved['group'],
                'media' => $this->media,
                'showDetails' => true,
                'showGalleryLink' => false,
                'showNavigation' => false,
                'allowVideoSound' => false,
                'enableViewer' => true,
                'showMediaCredits' => false,
                'showMediaCopyright' => false,
                'captionLayout' => GalleryPresentation::CAPTION_RIGHTS_LEFT,
            ];
        }
        if ($type === 'exhibitions') {
            $ids = $block['exhibition_ids'] ?? [];
            if ($ids === []) {
                return null;
            }
            $query = Exhibition::query()->whereKey($ids)->with('mediaUsages.mediaAsset.variants');
            if ($public) {
                $query->where('state', 'published');
            } else {
                $query->where('state', '<>', 'archived');
            }
            $items = $query->get()->sortBy(static fn (Exhibition $item): int => array_search((int) $item->getKey(), $ids, true))->values();

            return ['type' => $type, 'exhibitions' => $items];
        }
        if ($type === 'blog') {
            $ids = $block['post_ids'] ?? [];
            if ($ids === []) {
                return null;
            }
            $query = BlogPost::query()->whereKey($ids)->with('siteSection', 'mediaUsages.mediaAsset.variants');
            if ($public) {
                $query->publiclyVisible();
            } else {
                $query->where('state', '<>', 'archived');
            }
            $items = $query->get()->sortBy(static fn (BlogPost $item): int => array_search((int) $item->getKey(), $ids, true))->values();

            return ['type' => $type, 'posts' => $items];
        }

        return null;
    }

    /** @param list<array<string,mixed>> $blocks
     * @return array<int,array<string,mixed>>
     */
    public function presentations(array $blocks): array
    {
        $resolved = [];
        $visit = function (array $nodes) use (&$visit, &$resolved): void {
            foreach ($nodes as $node) {
                if (! is_array($node) || (! $this->preview->active() && ($node['published'] ?? true) !== true)) {
                    continue;
                }
                if (($node['type'] ?? null) === 'layout') {
                    foreach ($node['areas'] ?? [] as $area) {
                        $visit($area['blocks'] ?? []);
                    }

                    continue;
                }
                if (! in_array($node['type'] ?? null, ['gallery', 'hero_artworks', 'exhibitions', 'blog'], true)) {
                    continue;
                }
                $presentation = $this->presentation($node);
                if ($presentation !== null) {
                    $resolved[(int) $node['_root_index']] = $presentation;
                }
            }
        };
        $visit(\\App\\Models\\CustomPageSetting::layoutProjection($blocks));

        return $resolved;
    }
}
