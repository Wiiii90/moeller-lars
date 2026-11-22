<?php

namespace App\Domain\Content;

use App\Domain\Artwork\GalleryPresentation;
use App\Domain\Artwork\PublicArtworkQuery;
use App\Domain\Media\PublicMedia;
use App\Models\BlogPost;
use App\Models\Exhibition;
use App\Models\SiteSection;
use App\Routing\SiteNodeRoute;
use Illuminate\Validation\ValidationException;

/**
 * Resolves whole existing Gallery/Journal page templates embedded in a Custom
 * Page. Component placement and optional presentation belong to the consumer;
 * actual entries and settings retain their single canonical Page owner.
 */
final class CustomPageEmbeddedContent
{
    public function __construct(
        private readonly PublicArtworkQuery $artworks,
        private readonly PublicMedia $media,
        private readonly SitePreviewContext $preview,
        private readonly SiteNodeRoute $routes,
        private readonly GalleryHeroPresentationResolver $heroPresentation,
    ) {}

    /** @return array<int,string> */
    public function sourceOptions(string $type): array
    {
        [$pageType, $template] = self::template($type);

        return SiteSection::query()
            ->where('type', $pageType)
            ->where('template', $template)
            ->orderBy('title')->pluck('title', 'id')->all();
    }

    /** @return array{string,string} */
    private static function template(string $type): array
    {
        return match ($type) {
            'gallery' => ['gallery', GalleryTemplate::Standard->value],
            'hero_artworks' => ['gallery', GalleryTemplate::HeroArtworks->value],
            'exhibitions' => ['journal', JournalTemplate::Exhibitions->value],
            'blog' => ['journal', JournalTemplate::Blog->value],
            default => throw new \InvalidArgumentException('Unsupported embedded page template.'),
        };
    }

    /** @param array<string,mixed> $block */
    public function assertValid(array $block, bool $public): void
    {
        $type = (string) ($block['type'] ?? '');
        [$pageType, $template] = self::template($type);
        if ($type === 'gallery' &&
            ! in_array($block['gallery_presentation'] ?? 'sequence', ['sequence', 'two_columns'], true)) {
            throw ValidationException::withMessages(['gallery_presentation' => 'Choose a supported Gallery presentation.']);
        }

        $id = $block['source_page_id'] ?? null;
        if ($id === null && ! $public) {
            return;
        }
        if (! is_int($id) || $id < 1) {
            throw ValidationException::withMessages(['source_page_id' => 'Choose a complete source page.']);
        }
        $source = SiteSection::query()
            ->whereKey($id)->where('type', $pageType)->where('template', $template)->first();

        if (! $source instanceof SiteSection) {
            throw ValidationException::withMessages(['source_page_id' => 'The selected page is not a matching template.']);
        }
        if ($public && (string) $source->getAttribute('state') !== 'published') {
            throw ValidationException::withMessages(['source_page_id' => 'Publish the source page before publishing its component.']);
        }
    }

    /**
     * Resolve an entire template from its existing page owner, not a subset of
     * separately selected Artwork or Journal entry IDs.
     *
     * @param array<string,mixed> $block
     * @return array<string,mixed>|null
     */
    public function presentation(array $block): ?array
    {
        $type = (string) ($block['type'] ?? '');
        $public = ! $this->preview->active();
        try {
            $this->assertValid($block, $public);
        } catch (ValidationException $exception) {
            if (! $public) {
                throw $exception;
            }

            // Unpublishing a source must not break unrelated page content.
            return null;
        }
        if (! is_int($block['source_page_id'] ?? null)) {
            return null;
        }
        $section = SiteSection::query()->findOrFail($block['source_page_id']);

        if ($type === 'gallery') {
            $category = $section->artworkCategory()->first();
            if ($category === null) {
                return null;
            }
            $batch = $this->artworks->categoryBatch((string) $category->getAttribute('slug'), $section);
            $url = $this->routes->url($section);

            return [
                'type' => $type,
                'category' => $category,
                'section' => $section,
                'artworks' => $batch['artworks'],
                'outline' => $batch['outline'],
                'hasMore' => $batch['hasMore'],
                'page' => 1,
                'divider' => ($block['gallery_presentation'] ?? 'sequence') === 'two_columns'
                    ? 'none' : GalleryPresentation::divider($category),
                'embeddedGalleryPresentation' => $block['gallery_presentation'] ?? 'sequence',
                'galleryOffsets' => GalleryPresentation::indexCardOffsets($batch['outline'], $category),
                'galleryGaps' => GalleryPresentation::indexCardGapSteps($batch['outline'], $category),
                'galleryUrl' => $url === null ? null : $this->preview->url($url),
                'media' => $this->media,
            ];
        }

        if ($type === 'hero_artworks') {
            return [
                'type' => $type,
                ...$this->heroPresentation->presentation($section),
            ];
        }

        if ($type === 'exhibitions') {
            $query = Exhibition::query()->where('site_section_id', $section->getKey())
                ->with('mediaUsages.mediaAsset.variants')
                ->orderBy('position')->orderBy('id');
            $public ? $query->where('state', 'published')
                : $query->where('state', '<>', 'archived');

            return ['type' => $type, 'section' => $section, 'exhibitions' => $query->get()];
        }

        $query = BlogPost::query()->where('site_section_id', $section->getKey())
            ->with('siteSection', 'mediaUsages.mediaAsset.variants')
            ->orderBy('position')->orderBy('id');
        $public ? $query->publiclyVisible() : $query->where('state', '<>', 'archived');

        return ['type' => $type, 'section' => $section, 'posts' => $query->get()];
    }

    /**
     * @param list<array<string,mixed>> $blocks
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
        $visit(\App\Models\CustomPageSetting::layoutProjection($blocks));

        return $resolved;
    }
}
