<?php

namespace App\Domain\Content;

use App\Domain\Media\PublicMedia;
use App\Models\HomePresentationSetting;
use App\Models\MediaAsset;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use LogicException;

final class HomePresentationResolver
{
    public function __construct(
        private readonly PublicMedia $media,
        private readonly SitePreviewContext $preview,
        private readonly SafeRichTextRenderer $richText,
        private readonly HomePresentationEditorialService $editorial,
        private readonly HomeHeroConfigurationService $heroConfiguration,
        private readonly HomeHeroResolver $hero,
    ) {}

    public function settings(): HomePresentationSetting
    {
        /** @var HomePresentationSetting|null $settings */
        $settings = HomePresentationSetting::query()
            ->whereHas('siteSection', fn ($query) => $query->where('is_home', true))
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

    /** @return array<string, mixed> */
    public function presentation(): array
    {
        $settings = $this->settings();
        $template = $settings->template();
        $configuration = $this->editorial->configuration($settings);
        $heroConfiguration = $this->heroConfiguration->configuration($settings);
        $heroResolution = $template === HomeTemplate::Artwork
            ? $this->hero->resolve($settings)
            : null;
        $presentation = match ($template) {
            HomeTemplate::Artwork => [
                'template' => $template,
                'heroCandidate' => $heroResolution['current'],
                'media' => $this->media,
                'showDetails' => $heroConfiguration['show_details'],
                'showGalleryLink' => $heroConfiguration['show_gallery_link'],
                'showNavigation' => $heroConfiguration['show_navigation'],
                'allowVideoSound' => $heroConfiguration['video_sound_enabled'],
                'enableViewer' => $heroConfiguration['enable_viewer'],
                'expandLandscape' => $heroConfiguration['landscape_detail_enabled'],
                'expandPortrait' => $heroConfiguration['portrait_detail_enabled'],
                'artworkPageSurfaceMode' => $heroConfiguration['artwork_page_surface_mode'],
                'artworkMenuSurfaceWide' => $heroConfiguration['artwork_menu_surface_wide'],
                'showMediaCredits' => $heroConfiguration['show_media_credits'],
                'showMediaCopyright' => $heroConfiguration['show_media_copyright'],
                'captionLayout' => $heroConfiguration['caption_layout'],
                'heroGroup' => $heroResolution['group'],
                'gateActive' => false,
            ],
            HomeTemplate::UnderConstruction => $this->componentPresentation(
                $template,
                $settings->components(HomeTemplate::UnderConstruction),
                (bool) $configuration['under_construction']['public_site_gate'],
                (bool) $configuration['under_construction']['image_viewer_enabled'],
            ),
            HomeTemplate::Custom => $this->componentPresentation(
                $template,
                $settings->components(HomeTemplate::Custom),
                false,
                (bool) $configuration['custom']['image_viewer_enabled'],
            ),
        };

        return [
            ...$presentation,
            'pageSection' => $settings->getRelation('siteSection'),
        ];
    }

    /** @return list<int> */
    public function mediaIds(HomePresentationSetting $settings): array
    {
        return app(\App\Domain\Media\MediaReferenceQuery::class)->mediaIdsForHome($settings);
    }

    public function referencesMedia(HomePresentationSetting $settings, int $mediaAssetId): bool
    {
        return in_array($mediaAssetId, $this->mediaIds($settings), true);
    }

    /** @param list<array<string, mixed>> $components
     * @return array<string, mixed>
     */
    private function componentPresentation(HomeTemplate $template, array $components, bool $gateActive, bool $enableImageViewer): array
    {
        $ids = collect($components)
            ->filter(fn (array $component): bool => in_array($component['type'] ?? null, ['image', 'list'], true)
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
            'imageViewer' => app(PublicImageViewer::class),
            'enableImageViewer' => $enableImageViewer,
            'generalSettings' => PublicContentSetting::general(),
            'isPreview' => $this->preview->active(),
            'gateActive' => $gateActive && ! $this->preview->active(),
        ];
    }
}
