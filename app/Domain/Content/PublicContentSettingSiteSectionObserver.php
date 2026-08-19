<?php

namespace App\Domain\Content;

use App\Models\PublicContentSetting;

final class PublicContentSettingSiteSectionObserver
{
    public function __construct(private readonly SiteSectionSyncService $siteSections) {}

    public function saved(PublicContentSetting $settings): void
    {
        $this->siteSections->syncPublicContent($settings);
    }
}
