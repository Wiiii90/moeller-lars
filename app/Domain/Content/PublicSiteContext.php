<?php

namespace App\Domain\Content;

use App\Domain\Media\PublicMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\PublicContentSetting;

final class PublicSiteContext
{
    public function __construct(
        private readonly PublicNavigationService $navigation,
        private readonly PublicMedia $media,
        private readonly SitePreviewContext $preview,
    ) {}

    /** @return array{navigationItems:list<array<string,mixed>>,faviconVariant:?MediaVariant,isPreview:bool,homeUrl:string,publicBackgroundCss:?string,publicMenuBackgroundCss:?string,publicContentBackgroundCss:?string,publicLayoutCss:array{shell:string,art:string,padding:string},publicHeaderPosition:string,publicHeaderCaps:string,publicHeaderColor:?string,publicHeaderSize:string,publicHeaderJoined:bool,publicNavigationHover:bool} */
    public function layoutData(): array
    {
        $settings = PublicContentSetting::general();
        $asset = $settings->faviconMediaAsset()->first();
        $faviconVariant = null;

        if ($asset instanceof MediaAsset && $asset->getAttribute('state') === 'available') {
            $faviconVariant = $this->media->thumbnailVariantForAsset($asset);
        }

        return [
            'navigationItems' => $this->navigation->items()->values()->all(),
            'faviconVariant' => $faviconVariant,
            'isPreview' => $this->preview->active(),
            'homeUrl' => $this->preview->homeUrl(),
            'publicBackgroundCss' => PublicAppearance::backgroundCss($settings),
            'publicMenuBackgroundCss' => PublicAppearance::surfaceCss($settings, 'menu_background'),
            'publicContentBackgroundCss' => PublicAppearance::surfaceCss($settings, 'page_background'),
            'publicNavigationHover' => (bool) $settings->getAttribute('navigation_hover_preview'),
            'publicLayoutCss' => PublicAppearance::layoutCss($settings),
            'publicHeaderPosition' => PublicAppearance::headerPosition($settings->getAttribute('header_name_position')),
            'publicHeaderCaps' => PublicAppearance::headerCaps($settings->getAttribute('header_name_caps')),
            'publicHeaderColor' => PublicAppearance::normalizeColor($settings->getAttribute('header_name_color'), 'header_name_color'),
            'publicHeaderSize' => PublicAppearance::normalizeTitleSize($settings->getAttribute('header_name_size')).'px',
            'publicHeaderJoined' => $settings->getAttribute('header_content_joined') === null ? true : (bool) $settings->getAttribute('header_content_joined'),
        ];
    }
}
