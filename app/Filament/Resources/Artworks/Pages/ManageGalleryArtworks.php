<?php

namespace App\Filament\Resources\Artworks\Pages;

use App\Filament\Pages\GalleryWorkspace;
use App\Filament\Resources\Artworks\ArtworkResource;
use Filament\Resources\Pages\Page;

final class ManageGalleryArtworks extends Page
{
    protected static string $resource = ArtworkResource::class;

    protected static ?string $title = 'Gallery artworks';

    protected string $view = 'filament.resources.artworks.pages.manage-gallery-artworks';

    public function mount(int|string $gallery): void
    {
        $this->redirect(GalleryWorkspace::getUrl(['gallery' => (int) $gallery]), navigate: false);
    }
}
