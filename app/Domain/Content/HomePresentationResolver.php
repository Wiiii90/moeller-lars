<?php

namespace App\Domain\Content;

use App\Domain\Artwork\PublicArtworkQuery;
use App\Domain\Media\PublicMedia;
use App\Models\Artwork;
use App\Models\HomePresentationSetting;
use App\Models\MediaAsset;
use App\Models\SiteSection;
use App\Routing\SiteNodeRoute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use LogicException;

final class HomePresentationResolver
{
    public function __construct(
        private readonly PublicArtworkQuery $artworks,
        private readonly PublicMedia $media,
        private readonly SiteNodeRoute $routes,
        private readonly SitePreviewContext $preview,
        private readonly SafeRichTextRenderer $richText,
        private readonly HomePresentationEditorialService $editorial,
    ) {}

    public function settings(): HomePresentationSetting
    {
        /** @var HomePresentationSetting|null $settings */
        $settings = HomePresentationSetting::query()
            ->whereHas('siteSection', fn ($query) => $query->where('type', SiteNodeType::Home->value))
            ->with('siteSection')
            ->first();

        if (! $settings instanceof HomePresentationSetting) {
            throw new LogicException('Home presentation settings are missing. Run the current database migrations.');
        }

        return $settings;
    }

    public function template(): HomeTemplate
    {
        return $this->settings()->template();
    }

    public function publicGateActive(): bool
    {
        if ($this->preview->active()) {
            return false;
        }

        $settings = $this->settings();
        if ($settings->template() !== HomeTemplate::UnderConstruction) {
            return false;
        }

        $configuration = $this->editorial->configuration($settings);

        return (bool) ($configuration['under_construction']['public_site_gate'] ?? false);
    }

    public function skipTarget(): ?SiteSection
    {
        /** @var SiteSection|null $home */
        $home = SiteSection::query()
            ->where('type', SiteNodeType::Home->value)
            ->whereNull('parent_id')
            ->first();
        if (! $home instanceof SiteSection) {
            return null;
        }

        $position = (int) $home->getAttribute('position');
        $id = (int) $home->getKey();

        /** @var EloquentCollection<int, SiteSection> $candidates */
        $candidates = SiteSection::query()
            ->whereNull('parent_id')
            ->where('state', 'published')
            ->where(function ($query) use ($position, $id): void {
                $query->where('position', '>', $position)
                    ->orWhere(function ($samePosition) use ($position, $id): void {
                        $samePosition->where('position', $position)->where('id', '>', $id);
                    });
            })
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return $candidates->first(function (SiteSection $section): bool {
            return $section->nodeType()->hasPublicPage()
                && $section->nodeType() !== SiteNodeType::Home
                && $this->routes->path($section) !== null;
        });
    }

    public function skipTargetUrl(bool $preview = false): ?string
    {
        $target = $this->skipTarget();
        if (! $target instanceof SiteSection) {
            return null;
        }

        if ($preview) {
            return $this->preview->previewUrlFor($target);
        }

        return $this->routes->url($target);
    }

    /** @return array<string, mixed> */
    public function presentation(): array
    {
        $settings = $this->settings();
        $template = $settings->template();
        $configuration = $this->editorial->configuration($settings);

        return match ($template) {
            HomeTemplate::Artwork => [
                'template' => $template,
                'artwork' => $this->resolveHeroArtwork($configuration[HomeTemplate::Artwork->value]),
                'media' => $this->media,
                'showDetails' => (bool) $configuration['artwork']['show_details'],
                'showGalleryLink' => (bool) $configuration['artwork']['show_gallery_link'],
                'gateActive' => false,
            ],
            HomeTemplate::UnderConstruction => $this->componentPresentation(
                $template,
                $settings->components(HomeTemplate::UnderConstruction),
                (bool) $configuration['under_construction']['public_site_gate'],
            ),
            HomeTemplate::Custom => $this->componentPresentation(
                $template,
                $settings->components(HomeTemplate::Custom),
                false,
            ),
            HomeTemplate::SkipHome => [
                'template' => $template,
                'target' => $this->skipTarget(),
                'targetUrl' => $this->skipTargetUrl($this->preview->active()),
                'gateActive' => false,
            ],
        };
    }

    /** @return list<int> */
    public function mediaIds(HomePresentationSetting $settings): array
    {
        $ids = [];
        foreach ([HomeTemplate::UnderConstruction, HomeTemplate::Custom] as $template) {
            foreach ($settings->components($template) as $component) {
                if (($component['type'] ?? null) === 'image'
                    && is_numeric($component['media_asset_id'] ?? null)
                    && (int) $component['media_asset_id'] > 0) {
                    $ids[] = (int) $component['media_asset_id'];
                }

                if (($component['type'] ?? null) === 'text' && is_string($component['body'] ?? null)) {
                    $ids = array_merge($ids, RichTextMediaReference::ids($component['body']));
                }
            }
        }

        return array_values(array_unique($ids));
    }

    public function referencesMedia(HomePresentationSetting $settings, int $mediaAssetId): bool
    {
        return in_array($mediaAssetId, $this->mediaIds($settings), true);
    }

    /** @param array<string, mixed> $configuration */
    private function resolveHeroArtwork(array $configuration): ?Artwork
    {
        return match ((string) ($configuration['hero_mode'] ?? 'automatic')) {
            'fixed' => $this->artworks->homeCandidateById(
                is_int($configuration['fixed_artwork_id'] ?? null)
                    ? $configuration['fixed_artwork_id']
                    : 0,
            ),
            'random' => $this->artworks->randomForHomePool(
                (string) ($configuration['pool_rule'] ?? 'newest'),
                is_int($configuration['pool_year'] ?? null) ? $configuration['pool_year'] : null,
                is_array($configuration['manual_include_ids'] ?? null)
                    ? $configuration['manual_include_ids']
                    : [],
            ),
            default => $this->artworks->latestForHome(),
        };
    }

    /** @param list<array<string, mixed>> $components
     *  @return array<string, mixed>
     */
    private function componentPresentation(HomeTemplate $template, array $components, bool $gateActive): array
    {
        $ids = collect($components)
            ->filter(fn (mixed $component): bool => is_array($component)
                && ($component['type'] ?? null) === 'image'
                && is_numeric($component['media_asset_id'] ?? null))
            ->map(fn (array $component): int => (int) $component['media_asset_id'])
            ->unique()
            ->values();

        $assets = MediaAsset::query()
            ->whereIn('id', $ids->all())
            ->where('state', 'available')
            ->with('variants')
            ->get()
            ->keyBy(fn (MediaAsset $asset): int => (int) $asset->getKey());

        return [
            'template' => $template,
            'components' => $components,
            'assets' => $assets,
            'media' => $this->media,
            'richText' => $this->richText,
            'gateActive' => $gateActive && ! $this->preview->active(),
        ];
    }
}
