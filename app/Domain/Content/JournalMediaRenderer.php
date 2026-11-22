<?php

namespace App\Domain\Content;

use App\Domain\Media\MediaPreviewVariant;
use App\Domain\Media\MediaTypePolicy;
use App\Domain\Media\PublicMedia;
use App\Models\JournalEntryMedia;
use App\Models\JournalSetting;
use App\Models\Exhibition;
use App\Models\BlogPost;
use App\Models\MediaAsset;
use Illuminate\Support\HtmlString;
use LogicException;

final class JournalMediaRenderer
{
    public function __construct(
        private readonly PublicMedia $media,
        private readonly PublicImageViewer $viewer,
    ) {}

    /** @var array<string, bool> */
    private array $videoControlsDefaultsByOwner = [];

    /** Only new (unoverridden) media need a Journal setting lookup. */
    private function journalVideoControlsDefault(JournalEntryMedia $usage): bool
    {
        $isExhibition = $usage->getAttribute('exhibition_id') !== null;
        $ownerId = (int) ($usage->getAttribute($isExhibition ? 'exhibition_id' : 'blog_post_id') ?? 0);
        if ($ownerId <= 0) {
            return true;
        }

        $ownerKey = ($isExhibition ? 'exhibition:' : 'blog:').$ownerId;
        if (! array_key_exists($ownerKey, $this->videoControlsDefaultsByOwner)) {
            $sectionId = $isExhibition
                ? Exhibition::query()->whereKey($ownerId)->value('site_section_id')
                : BlogPost::query()->whereKey($ownerId)->value('site_section_id');
            $settings = $sectionId === null ? null : JournalSetting::query()
                ->where('site_section_id', $sectionId)
                ->first(['default_video_controls_enabled', 'default_settings_enabled']);
            // Disabling Exhibition defaults restores native video playback.
            // Blog settings remain independent of the Exhibition defaults panel.
            $this->videoControlsDefaultsByOwner[$ownerKey] = $settings === null
                || ($isExhibition && ! (bool) $settings->getAttribute('default_settings_enabled'))
                ? true : (bool) $settings->getAttribute('default_video_controls_enabled');
        }

        return $this->videoControlsDefaultsByOwner[$ownerKey];
    }

    public function inheritsDefaultNotice(JournalEntryMedia $usage): bool
    {
        $usage->loadMissing('mediaAsset');
        $asset = $usage->mediaAsset;

        return $asset instanceof MediaAsset
            && ! in_array((string) $asset->getAttribute('copyright_notice_mode'), [
                MediaAsset::COPYRIGHT_OVERRIDE,
                MediaAsset::COPYRIGHT_NONE,
            ], true);
    }

    public function render(JournalEntryMedia $usage, string $class = 'journal-entry-media', bool $priority = false, bool $includeInheritedCopyright = true, bool $includeCopyrightNotice = true, bool $showCaption = true, bool $enableViewer = false, bool $manualVideoPlayback = false): HtmlString
    {
        $usage->loadMissing('mediaAsset.variants');
        $asset = $usage->mediaAsset;
        if (! $asset instanceof MediaAsset) {
            throw new LogicException('Journal media usage requires a media asset.');
        }

        $isVideo = MediaTypePolicy::isVideo((string) $asset->getAttribute('mime_type'));
        $posterVariant = $isVideo ? MediaPreviewVariant::videoPoster($asset) : null;
        $posterUrl = $posterVariant ? $this->media->variantUrl($posterVariant) : null;
        $variant = $isVideo ? null : $this->media->thumbnailVariantForAsset($asset);
        $alt = $this->media->altTextForAsset($asset);
        $width = (int) ($variant?->getAttribute('width') ?? 0);
        $height = (int) ($variant?->getAttribute('height') ?? 0);
        $credit = trim((string) ($asset->getAttribute('credit') ?? ''));
        // Blog presentation can hide all visual copyright notices while
        // preserving separately authored credits and the stored copyright data.
        // Other Journal surfaces retain their own inheritance/override policy.
        $copyright = '';
        if ($includeCopyrightNotice && ($includeInheritedCopyright || ! $this->inheritsDefaultNotice($usage))) {
            $copyright = trim((string) ($asset->effectiveCopyrightNotice() ?? ''));
        }
        // Public Journal presentations may omit visible figure captions.
        // Asset credit, copyright inheritance and accessible ALT remain owned
        // by their existing canonical media metadata, not this display flag.
        $caption = trim(implode(' · ', array_filter([$credit, $copyright], static fn (string $value): bool => $value !== '')));

        // Video participates in viewer navigation without taking over the
        // native play/pause controls of the inline Journal presentation.
        $viewerAttributes = $enableViewer
            ? $this->viewer->attributes(
                'journal-media-'.(int) $usage->getKey(),
                $this->media->originalUrlForAsset($asset),
                $alt,
                $isVideo ? 'video' : 'image',
                ! $isVideo,
            )
            : '';

        $dimensions = $width > 0 && $height > 0 ? ' width="'.$width.'" height="'.$height.'"' : '';
        $videoControls = $isVideo
            && ($usage->videoControlsOverride() ?? $this->journalVideoControlsDefault($usage));
        $element = $isVideo
            ? '<video src="'.e($this->media->originalUrlForAsset($asset)).'" aria-label="'.e($alt).'"'.$viewerAttributes
                .($posterUrl !== null ? ' poster="'.e($posterUrl).'"' : '')
                .($posterVariant !== null ? ' width="'.(int) $posterVariant->getAttribute('width').'" height="'.(int) $posterVariant->getAttribute('height').'"' : '')
                .($videoControls ? ' controls' : '')
                .($manualVideoPlayback
                    ? ' playsinline preload="metadata"'
                    : ' muted loop playsinline preload="metadata" data-public-loop-video')
                .'></video>'
            : '<img src="'.e($this->media->variantUrl($variant)).'" alt="'.e($alt).'"'.$dimensions
                .' loading="'.($priority ? 'eager' : 'lazy').'" decoding="async" fetchpriority="'.($priority ? 'high' : 'auto').'"'.$viewerAttributes.'>';
        $figure = '<figure class="'.e($class).'">'
            .$element
            .($showCaption && $caption !== '' ? '<figcaption>'.e($caption).'</figcaption>' : '')
            .'</figure>';

        return new HtmlString($figure);
    }

    /**
     * Decorative catalogue cover preview. Never starts video playback, adds a
     * second viewer, or exposes the original image where a variant exists.
     * A legacy video without a poster shows an inert first-frame preview.
     * The caller supplies the aria-hidden wrapper because the summary already
     * names the exhibition and the expanded media retains its own accessible
     * caption and viewer.
     */
    public function renderPreview(JournalEntryMedia $usage): HtmlString
    {
        $usage->loadMissing('mediaAsset.variants');
        $asset = $usage->mediaAsset;
        if (! $asset instanceof MediaAsset) {
            throw new LogicException('Journal media usage requires a media asset.');
        }

        $variant = MediaPreviewVariant::preview($asset);
        if ($variant !== null) {
            $width = (int) $variant->getAttribute('width');
            $height = (int) $variant->getAttribute('height');
            $dimensions = $width > 0 && $height > 0 ? ' width="'.$width.'" height="'.$height.'"' : '';

            return new HtmlString('<img src="'.e($this->media->variantUrl($variant)).'" alt=""'.$dimensions
                .' loading="lazy" decoding="async">');
        }

        if (MediaTypePolicy::isVideo((string) $asset->getAttribute('mime_type'))) {
            return new HtmlString('<video src="'.e($this->media->originalUrlForAsset($asset))
                .'" muted playsinline preload="metadata" tabindex="-1"></video>');
        }

        throw new LogicException('Image cover preview requires an available thumbnail.');
    }

    public function renderRichText(string $source): HtmlString
    {
        return $this->viewer->renderRichText($source, 'journal-rich');
    }
}
