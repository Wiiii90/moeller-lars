<?php

namespace App\Filament\Pages;

use App\Domain\Artwork\ArtworkDraftService;
use App\Filament\Resources\Artworks\Pages\Concerns\GalleryWorkspaceArtworkActions;
use App\Filament\Resources\Artworks\Pages\Concerns\GalleryWorkspaceArtworkModals;
use App\Filament\Resources\Artworks\Pages\Concerns\GalleryWorkspaceBatchActions;
use App\Filament\Resources\Artworks\Pages\Concerns\GalleryWorkspaceDataProjection;
use App\Filament\Resources\Artworks\Pages\Concerns\GalleryWorkspaceDirectUpload;
use App\Filament\Resources\Artworks\Pages\Concerns\GalleryWorkspaceFormSupport;
use App\Filament\Resources\Artworks\Pages\Concerns\GalleryWorkspaceMoveActions;
use App\Filament\Resources\Artworks\Pages\Concerns\GalleryWorkspaceReadinessSupport;
use App\Filament\Resources\Artworks\Pages\Concerns\GalleryWorkspaceSelectionSupport;
use App\Filament\Resources\Artworks\Pages\Concerns\GalleryWorkspaceUploadSettings;
use App\Models\Artwork;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

final class GalleryWorkspace extends Page
{
    use WithFileUploads;
    use GalleryWorkspaceArtworkActions;
    use GalleryWorkspaceArtworkModals;
    use GalleryWorkspaceBatchActions;
    use GalleryWorkspaceDataProjection;
    use GalleryWorkspaceDirectUpload;
    use GalleryWorkspaceFormSupport;
    use GalleryWorkspaceMoveActions;
    use GalleryWorkspaceReadinessSupport;
    use GalleryWorkspaceSelectionSupport;
    use GalleryWorkspaceUploadSettings;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'pages/gallery/{gallery}';

    protected static ?string $title = 'Gallery artworks';

    protected string $view = 'filament.resources.artworks.pages.manage-gallery-artworks';

    /** @var array<string, mixed> */
    public array $galleryContext = [];

    /** @var list<array<string, mixed>> */
    public array $artworks = [];

    /** @var list<array{id:int,name:string,state:string}> */
    public array $moveTargets = [];

    /** @var list<int|string> */
    public array $selectedArtworkIds = [];

    public int $publishedCount = 0;

    /** @var array<string, mixed>|null */
    public ?array $analytics = null;

    /** @var list<array{label:string,value:string,description:string}> */
    public array $metrics = [];

    /** @var list<TemporaryUploadedFile> */
    public array $directPrimaryMedia = [];

    public ?string $directUploadMessage = null;

    public ?int $pendingPrimaryMediaAssetId = null;

    /** @var list<array{media_asset_id:int,title:string}> */
    public array $pendingBatchArtworkMedia = [];

    public string $search = '';

    public string $statusFilter = 'any';

    public string $readinessFilter = 'any';

    public function mount(int|string $gallery): void
    {
        $this->loadGallery((int) $gallery);
        $this->loadMoveTargets();
        $this->loadArtworks();
    }

    public function updatedSearch(): void
    {
        $this->loadArtworks();
    }

    public function updatedStatusFilter(): void
    {
        $this->loadArtworks();
    }

    public function updatedReadinessFilter(): void
    {
        $this->loadArtworks();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'any';
        $this->readinessFilter = 'any';
        $this->loadArtworks();
    }

    public function renameArtwork(int $artworkId, string $title): ?string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($title));
        $normalized = is_string($normalized) ? $normalized : trim($title);

        /** @var Artwork|null $artwork */
        $artwork = Artwork::query()
            ->whereKey($artworkId)
            ->where('artwork_category_id', (int) $this->galleryContext['id'])
            ->first();
        if (! $artwork instanceof Artwork) {
            return null;
        }

        $current = preg_replace('/\s+/u', ' ', trim((string) $artwork->getAttribute('title')));
        $current = is_string($current) ? $current : trim((string) $artwork->getAttribute('title'));
        if ($normalized === $current) {
            return $current;
        }

        try {
            app(ArtworkDraftService::class)->update($artwork, [
                'artwork_category_id' => (int) $artwork->getAttribute('artwork_category_id'),
                'slug' => (string) $artwork->getAttribute('slug'),
                'title' => $normalized,
                'work_date' => $artwork->getAttribute('work_date'),
            ]);
        } catch (ValidationException $exception) {
            $this->notifyValidationFailure('Artwork title was not saved', $exception);

            return null;
        }

        $this->loadArtworks();
        Notification::make()->title('Artwork title saved')->success()->send();

        return $normalized;
    }
}
