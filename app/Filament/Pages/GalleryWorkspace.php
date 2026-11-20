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

    /** @var array<string, mixed> Canonical Gallery Settings form state. */
    public array $galleryStageSettingsData = [];

    /** @var array<string, mixed>|null Unsaved fields for the selected media file. */
    public ?array $stagedArtworkData = [];

    public ?int $selectedStagedMediaAssetId = null;

    public string $galleryStagePanel = 'upload';

    public string $galleryStagePreview = 'gallery';

    #[Session]
    public bool $galleryPreviewAutoFocus = true;

    public ?int $galleryStagePreviewArtworkId = null;

    /** Successfully staged new assets in the active upload batch. */
    public int $directPrimaryBatchAddedCount = 0;

    /** @var list<array{media_asset_id:int,title:string,filename:string,form:array<string,mixed>}> */
    public array $pendingBatchArtworkMedia = [];

    public string $search = '';

    #[Session]
    public string $viewMode = 'list';

    #[Session]
    public string $statusFilter = 'any';

    #[Session]
    public string $readinessFilter = 'any';

    public function mount(int|string $gallery): void
    {
        $this->viewMode = in_array($this->viewMode, ['list', 'grid', 'dense'], true) ? $this->viewMode : 'list';
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
        $this->refreshGalleryStageSettings();
    }

    public function stagedArtworkForm(Schema $schema): Schema
    {
        return $schema
            ->components(app(\App\Filament\Support\ArtworkEditDialog::class)->schema(
                creating: true,
                primaryReadOnly: true,
                compact: true,
                positionCount: count($this->artworkDataset) + 1,
                onPositionChanged: static function (mixed $state, callable $set): void {
                    $set('position_explicit', filled($state));
                },
                positionSuggestion: function (callable $get): int {
                    $gallery = \App\Models\ArtworkCategory::query()->findOrFail((int) $this->galleryContext['id']);

                    return \App\Domain\Artwork\ArtworkSort::suggestedAdditionPosition(
                        $gallery, $get('artwork_date'), $this->selectedStagedMediaAssetId,
                    );
                },
            ))
            ->statePath('stagedArtworkData');
    }

    /**
     * The selected public Artwork (or Gallery) is the non-Staged preview target.
     * Expose the same canonical result to the local Stage tabs and Livewire.
     *
     * @return array{kind:string,artwork_id:?int,url:?string}
     */
    public function galleryBackgroundPreviewTarget(): array
    {
        $ids = array_keys($this->selectedArtworkIdSet());
        $single = count($ids) === 1 ? (int) $ids[0] : null;
        foreach ($this->artworkDataset as $artwork) {
            if ($single !== null && (int) $artwork['id'] === $single
                && ($artwork['can_preview'] ?? false) === true) {
                return [
                    'kind' => 'artwork',
                    'artwork_id' => $single,
                    'url' => route('preview.artworks.show', ['slug' => $artwork['slug']]),
                ];
            }
        }

        return [
            'kind' => 'gallery',
            'artwork_id' => null,
            'url' => $this->galleryContext['preview_url'] ?? null,
        ];
    }

    /** Stage form changes refresh only a private session-backed Public preview. */
    public function syncStagedArtworkPreview(): void
    {
        if ($this->selectedStagedMediaAssetId === null) {
            return;
        }
        $raw = $this->stagedArtworkForm->getRawState();
        if (is_array($raw)) {
            $this->stagedArtworkData = $raw;
        }
        $this->rememberStagedArtworkChanges();
        $this->publishStagedArtworkPreview();
    }

    private function publishStagedArtworkPreview(): void
    {
        if ($this->selectedStagedMediaAssetId === null) {
            return;
        }
        session()->put('gallery.stage.preview.'.$this->getId(), [
            'gallery_id' => (int) $this->galleryContext['id'],
            'media_asset_id' => $this->selectedStagedMediaAssetId,
            'form' => is_array($this->stagedArtworkData) ? $this->stagedArtworkData : [],
        ]);
        if ($this->galleryPreviewAutoFocus && $this->galleryStagePanel === 'queue') {
            $this->dispatch('gallery-preview-updated',
                url: $this->galleryStagePreviewUrl(),
                title: $this->galleryStagePreviewTitle(),
            );
        }
    }

    private function forgetStagedArtworkPreview(): void
    {
        session()->forget('gallery.stage.preview.'.$this->getId());
    }

    public function toggleGalleryPreviewAutoFocus(): void
    {
        $this->galleryPreviewAutoFocus = ! $this->galleryPreviewAutoFocus;

        if ($this->galleryPreviewAutoFocus) {
            // Re-enabling auto-focus explicitly refreshes a previously pinned
            // preview, even when its type has not changed.
            $this->syncGalleryPreviewFocus(forceRefresh: true);
        }
    }

    /** The editor selection and the staged queue own the focused preview. */
    private function syncGalleryPreviewFocus(bool $forceRefresh = false): void
    {
        if (! $this->galleryPreviewAutoFocus) {
            // Pinned targets stay put except when the underlying Artwork or
            // staged media was removed and its preview route is no longer valid.
            $invalidStaged = $this->galleryStagePreview === 'staged'
                && $this->selectedStagedMediaAssetId === null;
            $invalidArtwork = $this->galleryStagePreview === 'artwork'
                && ! collect($this->artworkDataset)->contains(
                    fn (array $artwork): bool => (int) $artwork['id'] === $this->galleryStagePreviewArtworkId
                        && ($artwork['can_preview'] ?? false) === true,
                );
            if ($invalidStaged || $invalidArtwork) {
                $this->galleryStagePreview = 'gallery';
                $this->galleryStagePreviewArtworkId = null;
                $forceRefresh = true;
            }
            if ($forceRefresh) {
                $this->dispatch('gallery-preview-updated',
                    url: $this->galleryStagePreviewUrl(), title: $this->galleryStagePreviewTitle());
            }

            return;
        }

        if ($this->galleryStagePanel === 'queue' && $this->selectedStagedMediaAssetId !== null) {
            $changed = $this->galleryStagePreview !== 'staged'
                || $this->galleryStagePreviewArtworkId !== null;
            $this->galleryStagePreview = 'staged';
            $this->galleryStagePreviewArtworkId = null;
            // Switching tabs must not reload the same iframe. The selected
            // Artwork and its form updates already call the preview owner.
            if ($changed || $forceRefresh) {
                $this->publishStagedArtworkPreview();
            }

            return;
        }

        $background = $this->galleryBackgroundPreviewTarget();
        $changed = $this->galleryStagePreview !== $background['kind']
            || $this->galleryStagePreviewArtworkId !== $background['artwork_id'];

        $this->galleryStagePreview = $background['kind'];
        $this->galleryStagePreviewArtworkId = $background['artwork_id'];

        if ($changed || $forceRefresh) {
            $this->dispatch('gallery-preview-updated',
                url: $this->galleryStagePreviewUrl(), title: $this->galleryStagePreviewTitle());
        }
    }

    public function updatedSelectedArtworkIds(): void
    {
        $this->syncGalleryPreviewFocus();
    }

    public function galleryStagePreviewTitle(): string
    {
        return match ($this->galleryStagePreview) {
            'staged' => 'Interactive staged Artwork preview',
            'artwork' => 'Interactive Artwork preview',
            default => 'Interactive Gallery preview',
        };
    }

    public function galleryStagePreviewUrl(): ?string
    {
        if ($this->galleryStagePreview === 'staged' && $this->selectedStagedMediaAssetId !== null) {
            return route('preview.gallery.staged-artwork', [
                'gallery' => (int) $this->galleryContext['id'],
                'token' => $this->getId(),
            ]);
        }
        if ($this->galleryStagePreview !== 'artwork' || $this->galleryStagePreviewArtworkId === null) {
            return $this->galleryContext['preview_url'] ?? null;
        }
        foreach ($this->artworkDataset as $artwork) {
            if ((int) $artwork['id'] === $this->galleryStagePreviewArtworkId
                && ($artwork['can_preview'] ?? false) === true) {
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

    public function setViewMode(string $mode): void
    {
        if (in_array($mode, ['list', 'grid', 'dense'], true)) {
            $this->viewMode = $mode;
        }
    }

    /** Toggle one visible Artwork using the same explicit row-selection action as Journals. */
    public function toggleArtworkSelection(int $artworkId): void
    {
        if (! in_array($artworkId, array_column($this->artworks, 'id'), true)) {
            return;
        }

        $selected = array_map('intval', $this->selectedArtworkIds);
        $this->selectedArtworkIds = in_array($artworkId, $selected, true)
            ? array_values(array_diff($selected, [$artworkId]))
            : array_values(array_unique([...$selected, $artworkId]));
        $this->syncGalleryPreviewFocus();
    }

    public function toggleVisibleArtworkSelection(): void
    {
        $visibleIds = array_map(static fn (array $row): int => (int) $row['id'], $this->artworks);
        if ($visibleIds === []) {
            return;
        }
        $selected = array_map('intval', $this->selectedArtworkIds);
        $allSelected = count(array_intersect($visibleIds, $selected)) === count($visibleIds);
        $this->selectedArtworkIds = $allSelected
            ? array_values(array_diff($selected, $visibleIds))
            : array_values(array_unique([...$selected, ...$visibleIds]));
        $this->syncGalleryPreviewFocus();
    }

    public function updatedSearch(): void
    {
        $this->projectArtworks();
        $this->syncGalleryPreviewFocus();
    }

    public function updatedStatusFilter(): void
    {
        if (! in_array($this->statusFilter, ['any', 'published', 'draft'], true)) {
            $this->statusFilter = 'any';
        }

        $this->projectArtworks();
        $this->syncGalleryPreviewFocus();
    }

    public function updatedReadinessFilter(): void
    {
        if (! in_array($this->readinessFilter, ['any', 'ready', 'needs-attention'], true)) {
            $this->readinessFilter = 'any';
        }

        $this->projectArtworks();
        $this->syncGalleryPreviewFocus();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'any';
        $this->readinessFilter = 'any';
        $this->projectArtworks();
        $this->syncGalleryPreviewFocus();
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
