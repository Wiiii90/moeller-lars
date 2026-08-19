<?php

namespace App\Domain\Content;

use App\Models\ArtworkCategory;

final class ArtworkCategorySiteSectionObserver
{
    public function __construct(private readonly SiteSectionSyncService $siteSections) {}

    public function saved(ArtworkCategory $category): void
    {
        $this->siteSections->syncGallery($category);
    }

    public function deleting(ArtworkCategory $category): void
    {
        $this->siteSections->deleteGallery($category);
    }
}
