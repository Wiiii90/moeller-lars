<?php

namespace App\Filament\Pages;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Artwork\ArtworkDraftService;
use App\Filament\Pages\Concerns\GalleryWorkspaceArtworkActions;
use App\Filament\Pages\Concerns\GalleryWorkspaceArtworkDialogs;
use App\Filament\Pages\Concerns\GalleryWorkspaceBatchActions;
use App\Filament\Pages\Concerns\GalleryWorkspaceDataProjection;
use App\Filament\Pages\Concerns\GalleryWorkspaceDirectUpload;
use App\Filament\Pages\Concerns\GalleryWorkspaceFormSupport;
use App\Filament\Pages\Concerns\GalleryWorkspaceMoveActions;
use App\Filament\Pages\Concerns\GalleryWorkspaceReadinessSupport;
use App\Filament\Pages\Concerns\GalleryWorkspaceSelectionSupport;
use App\Filament\Pages\Concerns\GalleryWorkspaceUploadSettings;
use App\Models\Artwork;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Session;
use Livewire\WithFileUploads;

final class GalleryWorkspace extends Page
{
    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canAccessPanel(Filament::getCurrentPanel());
    }

    use GalleryWorkspaceArtworkActions;
    use GalleryWorkspaceArtworkDialogs;
    use GalleryWorkspaceBatchActions;
    use GalleryWorkspaceDataProjection;
    use GalleryWorkspaceDirectUpload;
    use GalleryWorkspaceFormSupport;
    use GalleryWorkspaceMoveActions;
    use GalleryWorkspaceReadinessSupport;
    use GalleryWorkspaceSelectionSupport;
    use GalleryWorkspaceUploadSettings;
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'pages/gallery/{gallery}';

    protected static ?string $title = 'Gallery artworks';

    protected string $view = 'filament.resources.artworks.pages.manage-gallery-artworks';

    /** @var array<string, mixed> */
    public array $galleryContext = [];

    /** @var list<array<string, mixed>> */
    public array $artworks = [];

    /** @var list<array<string, mixed>> */
    public array $artworkDataset = [];

    /** @var list<array{id:int,name:string,state:string}> */
    public array $moveTargets = [];

    /** @var list<int|string> */
    public array $selectedArtworkIds = [];

    public int $publishedCount = 0;

    /** @var array<string, mixed>|null */
    public ?array $analytics = null;

    /** @var list<array{label:string,value:string,description:string}> */
    public array $metrics = [];

    public ?int $pendingPrimaryMediaAssetId = null;

    /** @var array<string, mixed>|null Unsaved fields for the selected media file. */
    public ?array $stagedArtworkData = [];

    public ?int $selectedStagedMediaAssetId = null;

    public string $galleryStagePanel = 'settings';

    public string $galleryStagePreview = 'gallery';

    public ?int $galleryStagePreviewArtworkId = null;

    public int $galleryStagePreviewRevision = 0;

    /** Queue size when the current file-upload run began. */
    public int $directPrimaryBatchStartCount = 0;

    /** @var list<array{media_asset_id:int,title:string}> */
    public array $pendingBatchArtworkMedia = [];

    public string $search = '';

    #[Session]
    public string $statusFilter = 'any';

    #[Session]
    public string $readinessFilter = 'any';

    public function mount(int|string $gallery): void
    {
        $this->statusFilter = in_array($this->statusFilter, ['any', 'published', 'draft'], true) ? $this->statusFilter : 'any';
        $this->readinessFilter = in_array($this->readinessFilter, ['any', 'ready', 'needs-attention'], true)
            ? $this->readinessFilter
            : 'any';
        $this->loadGallery((int) $gallery);
        $this->loadMoveTargets();
        $this->loadGalleryDataset();
        $this->loadGalleryAnalytics();
        $this->projectArtworks();
        $this->refreshGalleryMetrics();
    }

    public function stagedArtworkForm(Schema $schema): Schema
    {
        return $schema
            ->components(app(\App\Filament\Support\ArtworkEditDialog::class)->schema(
                creating: true,
                primaryReadOnly: true,
                positionCount: count($this->artworkDataset) + 1,
                onPositionChanged: static function (mixed $state, callable $set): void {
                    $set('position_explicit', true);
                },
                onCreateOrderSourceChanged: function (callable $get, callable $set): void {
                    if ((bool) $get('position_explicit')) {
                        return;
                    }
                    $gallery = \App\Models\ArtworkCategory::query()->findOrFail((int) $this->galleryContext['id']);
                    $set('position', \App\Domain\Artwork\ArtworkSort::suggestedPosition(
                        $gallery, $get('artwork_date'), $this->selectedStagedMediaAssetId,
                    ));
                },
            ))
            ->statePath('stagedArtworkData');
    }

    public function selectGalleryStagePanel(string $panel): void
    {
        if (! in_array($panel, ['settings', 'queue'], true)) {
            return;
        }
        $this->rememberStagedArtworkChanges();
        $this->galleryStagePanel = $panel;
    }

    public function previewGalleryStage(): void
    {
        $this->galleryStagePreview = 'gallery';
        $this->galleryStagePreviewArtworkId = null;
    }

    public function previewGalleryArtwork(int $artworkId): void
    {
        $previewable = collect($this->artworkDataset)
            ->contains(static fn (array $artwork): bool => (int) $artwork['id'] === $artworkId
                && ($artwork['state'] ?? null) === 'published');
        if (! $previewable) {
            return;
        }
        $this->galleryStagePreview = 'artwork';
        $this->galleryStagePreviewArtworkId = $artworkId;
    }

    public function stepGalleryPreview(string $direction): void
    {
        if (! in_array($direction, ['previous', 'next'], true)) {
            return;
        }
        $ids = array_values(array_map(
            static fn (array $artwork): int => (int) $artwork['id'],
            array_filter(
                $this->artworkDataset,
                static fn (array $artwork): bool => ($artwork['state'] ?? null) === 'published',
            ),
        ));
        if ($ids === []) {
            $this->previewGalleryStage();
            return;
        }
        $from = array_search($this->galleryStagePreviewArtworkId, $ids, true);
        if ($from === false) {
            $this->previewGalleryArtwork($direction === 'previous' ? $ids[array_key_last($ids)] : $ids[0]);
            return;
        }
        $target = $from + ($direction === 'next' ? 1 : -1);
        if (isset($ids[$target])) {
            $this->previewGalleryArtwork($ids[$target]);
        } elseif ($direction === 'next') {
            $this->previewGalleryStage();
        }
    }

    public function galleryStagePreviewUrl(): ?string
    {
        if ($this->galleryStagePreview !== 'artwork' || $this->galleryStagePreviewArtworkId === null) {
            return $this->galleryContext['preview_url'] ?? null;
        }
        foreach ($this->artworkDataset as $artwork) {
            if ((int) $artwork['id'] === $this->galleryStagePreviewArtworkId
                && ($artwork['state'] ?? null) === 'published') {
                return route('preview.artworks.show', ['slug' => $artwork['slug']]);
            }
        }

        return $this->galleryContext['preview_url'] ?? null;
    }

    public function getTitle(): string
    {
        $name = $this->galleryContext['name'] ?? null;

        return is_string($name) && trim($name) !== '' ? trim($name) : __('Gallery artworks');
    }

    public function updatedSearch(): void
    {
        $this->projectArtworks();
    }

    public function updatedStatusFilter(): void
    {
        if (! in_array($this->statusFilter, ['any', 'published', 'draft'], true)) {
            $this->statusFilter = 'any';
        }

        $this->projectArtworks();
    }

    public function updatedReadinessFilter(): void
    {
        if (! in_array($this->readinessFilter, ['any', 'ready', 'needs-attention'], true)) {
            $this->readinessFilter = 'any';
        }

        $this->projectArtworks();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'any';
        $this->readinessFilter = 'any';
        $this->projectArtworks();
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
            ]);
        } catch (ValidationException $exception) {
            $this->notifyValidationFailure('Artwork title was not saved', $exception);

            return null;
        }

        $this->refreshWorkspaceAfterMutation();
        app(AdminNotifier::class)->notification(
            title: 'Artwork title saved',
            status: 'success',
        );

        return $normalized;
    }
}
