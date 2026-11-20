<?php

namespace App\Domain\Content;

use App\Domain\Media\PublicMedia;
use App\Models\GalleryHeroSetting;
use App\Models\SiteSection;

final class GalleryHeroPresentationResolver
{
    public function __construct(
        private readonly PublicMedia $media,
        private readonly GalleryHeroConfigurationService $configuration,
        private readonly GalleryHeroResolver $resolver,
        private readonly SitePreviewContext $preview,
    ) {}

    /** @return array<string, mixed> */
    public function presentation(SiteSection $section): array
    {
        $settings = GalleryHeroSetting::forSection($section);
        $configuration = $this->configuration->configuration($settings);
        $request = request();
        // One functional, session-local history entry per Hero page. Preview
        // has its own history and cannot alter a visitor's public sequence.
        $historyKey = 'gallery_hero.last_random.'
            .($this->preview->active() ? 'preview.' : 'public.')
            .$section->getKey();
        $lastVisual = $request->hasSession() ? $request->session()->get($historyKey) : null;
        $resolved = $this->resolver->resolve(
            $settings,
            previousVisualKey: is_string($lastVisual) ? $lastVisual : null,
        );
        if ($configuration['display_strategy'] === 'random'
            && $resolved['current'] instanceof GalleryHeroCandidate
            && $request->hasSession()) {
            $request->session()->put($historyKey, $resolved['current']->visualKey());
        }

        return [
            'section' => $section,
            'pageSection' => $section,
            'heroCandidate' => $resolved['current'],
            'media' => $this->media,
            'showDetails' => $configuration['show_details'],
            'showGalleryLink' => $configuration['show_gallery_link'],
            'showNavigation' => $configuration['show_navigation'],
            'allowVideoSound' => $configuration['video_sound_enabled'],
            'enableViewer' => $configuration['enable_viewer'],
            'showMediaCredits' => $configuration['show_media_credits'],
            'showMediaCopyright' => $configuration['show_media_copyright'],
            'captionLayout' => $configuration['caption_layout'],
            'heroGroup' => $resolved['group'],
        ];
    }
}
