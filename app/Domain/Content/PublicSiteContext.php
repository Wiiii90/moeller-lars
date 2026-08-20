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
    ) {}

    /** @return array{navigationItems:list<array<string,mixed>>,faviconVariant:?MediaVariant} */
    public function layoutData(): array
    {
        $settings = PublicContentSetting::current();
        $asset = $settings->faviconMediaAsset;
        $faviconVariant = null;

        if ($asset instanceof MediaAsset && $asset->getAttribute('state') === 'available') {
            $faviconVariant = $this->media->thumbnailVariantForAsset($asset);
        }

        return [
            'navigationItems' => $this->navigation->items(),
            'faviconVariant' => $faviconVariant,
        ];
    }
}
