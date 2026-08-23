<?php

namespace App\Filament\Resources\Artworks\Pages;

use App\Filament\Resources\Artworks\ArtworkResource;
use Filament\Resources\Pages\Page;
use Livewire\WithFileUploads;

final class ManageGalleryArtworks extends Page
{
    use WithFileUploads;

    protected static string $resource = ArtworkResource::class;

    protected static ?string $title = 'Gallery artworks';

    protected string $view = 'filament.resources.artworks.pages.manage-gallery-artworks';

    /** @var array<string, mixed> */
    public array $galleryContext = [];

    /** @var list<array<string, mixed>> */
    public array $artworks = [];

    /** @var list<array{id:int,name:string,state:string}> */
    public array $moveTargets = [];

    /** @var array<int, int|string|null> */
    public array $moveTargetGalleryIds = [];

    /** @var list<int|string> */
    public array $selectedArtworkIds = [];

    public int|string|null $batchTargetGalleryId = null;

    public int $publishedCount = 0;

    /** @var array<string, mixed>|null */
    public ?array $analytics = null;

    /** @var list<array{label:string,value:string,description:string}> */
    public array $metrics = [];

    public mixed $directPrimaryMedia = null;

    public ?string $directUploadMessage = null;

    public ?int $pendingPrimaryMediaAssetId = null;

    use Concerns\GalleryWorkspaceArtworkActions;
    use Concerns\GalleryWorkspaceArtworkModals;
    use Concerns\GalleryWorkspaceBatchActions;
    use Concerns\GalleryWorkspaceDataProjection;
    use Concerns\GalleryWorkspaceFormSupport;
    use Concerns\GalleryWorkspaceMoveActions;
    use Concerns\GalleryWorkspaceReadinessSupport;
    use Concerns\GalleryWorkspaceSelectionSupport;
    use Concerns\GalleryWorkspaceUploadSettings;

    public function mount(int|string $gallery): void
    {
        $this->loadGallery((int) $gallery);
        $this->loadMoveTargets();
        $this->loadArtworks();
    }
}
