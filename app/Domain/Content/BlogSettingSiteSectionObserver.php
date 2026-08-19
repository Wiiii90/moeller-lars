<?php

namespace App\Domain\Content;

use App\Models\BlogSetting;

final class BlogSettingSiteSectionObserver
{
    public function __construct(private readonly SiteSectionSyncService $siteSections) {}

    public function saved(BlogSetting $settings): void
    {
        $this->siteSections->syncBlog($settings);
    }
}
