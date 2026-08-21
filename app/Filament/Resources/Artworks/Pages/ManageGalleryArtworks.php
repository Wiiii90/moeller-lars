<?php

namespace App\Filament\Resources\Artworks\Pages;

use App\Domain\Analytics\ArtistReportingService;
use App\Domain\Artwork\ArtworkCategoryEditorialService;
use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Domain\Media\MediaIngestService;
use App\Filament\Pages\SitePages;
use App\Filament\Resources\ArtworkCategories\ArtworkCategoryResource;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\SiteSection;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class ManageGalleryArtworks extends Page
{
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

    public function mount(int|string $gallery): void
    {
        $this->loadGallery((int) $gallery);
        $this->loadMoveTargets();
        $this->loadArtworks();
    }

    public function moveArtwork(int $artworkId, string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException('Artwork order direction must be up or down.');
        }

        $galleryId = (int) $this->galleryContext['id'];
        /** @var ArtworkCategory $category */
        $category = ArtworkCategory::query()->findOrFail($galleryId);
        $orderedIds = Artwork::query()
            ->where('artwork_category_id', $galleryId)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $index = array_search($artworkId, $orderedIds, true);
        if ($index === false) {
            return;
        }

        $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;
        if (! array_key_exists($targetIndex, $orderedIds)) {
            return;
        }

        [$orderedIds[$index], $orderedIds[$targetIndex]] = [$orderedIds[$targetIndex], $orderedIds[$index]];
        app(ArtworkCategoryEditorialService::class)->reorderArtworks($category, $orderedIds);

        Notification::make()->title('Gallery order updated')->success()->send();
        $this->loadArtworks();
    }

    public function reassignArtwork(int $artworkId): void
    {
        $targetGalleryId = (int) ($this->moveTargetGalleryIds[$artworkId] ?? 0);
        if ($targetGalleryId <= 0) {
            Notification::make()->title('Choose a destination Gallery')->warning()->send();

            return;
        }

        $galleryId = (int) $this->galleryContext['id'];
        /** @var Artwork|null $artwork */
        $artwork = Artwork::query()
            ->whereKey($artworkId)
            ->where('artwork_category_id', $galleryId)
            ->first();
        /** @var ArtworkCategory|null $destination */
        $destination = ArtworkCategory::query()
            ->whereKey($targetGalleryId)
            ->whereKeyNot($galleryId)
            ->first();

        if (! $artwork || ! $destination) {
            Notification::make()->title('Artwork could not be moved')->danger()->send();

            return;
        }

        try {
            app(ArtworkGalleryAssignmentService::class)->reassign($artwork, $destination);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Artwork could not be moved')
                ->body($this->firstValidationMessage($exception))
                ->danger()
                ->send();

            return;
        }

        unset($this->moveTargetGalleryIds[$artworkId]);
        $this->selectedArtworkIds = array_values(array_filter(
            $this->selectedArtworkIds,
            static fn (int|string $id): bool => (int) $id !== $artworkId,
        ));

        Notification::make()
            ->title('Artwork moved')
            ->body('Its media references were preserved; no MediaAsset was deleted or duplicated.')
            ->success()
            ->send();
        $this->loadArtworks();
    }

    public function reassignSelectedArtworks(): void
    {
        $targetGalleryId = (int) $this->batchTargetGalleryId;
        $galleryId = (int) $this->galleryContext['id'];
        $selectedIds = collect($this->selectedArtworkIds)
            ->map(static fn (int|string $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($selectedIds->isEmpty()) {
            Notification::make()->title('Select artworks to move')->warning()->send();

            return;
        }

        /** @var ArtworkCategory|null $destination */
        $destination = ArtworkCategory::query()
            ->whereKey($targetGalleryId)
            ->whereKeyNot($galleryId)
            ->first();
        if (! $destination) {
            Notification::make()->title('Choose a destination Gallery')->warning()->send();

            return;
        }

        /** @var EloquentCollection<int, Artwork> $artworks */
        $artworks = Artwork::query()
            ->where('artwork_category_id', $galleryId)
            ->whereIn('id', $selectedIds->all())
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        if ($artworks->count() !== $selectedIds->count()) {
            Notification::make()->title('Selection changed; review it and try again')->warning()->send();
            $this->loadArtworks();

            return;
        }

        if (
            $artworks->contains(static fn (Artwork $artwork): bool => $artwork->getAttribute('state') === 'published')
            && ! $destination->siteSection()->where('state', 'published')->exists()
        ) {
            Notification::make()
                ->title('Selected artworks could not be moved')
                ->body('Published artwork can only move to a published Gallery.')
                ->danger()
                ->send();

            return;
        }

        DB::transaction(function () use ($artworks, $destination): void {
            foreach ($artworks as $artwork) {
                app(ArtworkGalleryAssignmentService::class)->reassign($artwork, $destination);
            }
        });

        $count = $artworks->count();
        $this->selectedArtworkIds = [];
        $this->batchTargetGalleryId = null;
        $this->moveTargetGalleryIds = [];

        Notification::make()
            ->title($count === 1 ? 'Artwork moved' : $count.' artworks moved')
            ->body('Media references remain shared and unchanged.')
            ->success()
            ->send();
        $this->loadArtworks();
    }

    private function loadGallery(int $galleryId): void
    {
        /** @var ArtworkCategory $category */
        $category = ArtworkCategory::query()->findOrFail($galleryId);
        /** @var SiteSection $section */
        $section = $category->siteSection()->with('parent')->firstOrFail();
        /** @var SiteSection|null $parent */
        $parent = $section->getRelationValue('parent');
        $isPublished = $section->getAttribute('state') === 'published';

        $this->galleryContext = [
            'id' => (int) $category->getKey(),
            'name' => (string) $category->getAttribute('name'),
            'slug' => (string) $category->getAttribute('slug'),
            'state' => (string) $section->getAttribute('state'),
            'parent_name' => $parent?->getAttribute('title'),
            'path' => '/'.ltrim((string) $category->getAttribute('slug'), '/'),
            'pages_url' => SitePages::getUrl(),
            'all_artworks_url' => ArtworkResource::getUrl('index'),
            'settings_url' => ArtworkCategoryResource::getUrl('edit', ['record' => $category->getKey()]),
            'create_url' => ArtworkResource::getUrl('create', ['gallery' => $category->getKey()]),
            'public_url' => $isPublished
                ? route('artworks.category', ['category' => $category->getAttribute('slug')])
                : null,
        ];
    }

    private function loadMoveTargets(): void
    {
        /** @var EloquentCollection<int, ArtworkCategory> $categories */
        $categories = ArtworkCategory::query()
            ->whereKeyNot((int) $this->galleryContext['id'])
            ->whereHas('siteSection')
            ->with('siteSection')
            ->orderBy('name')
            ->get();

        $this->moveTargets = $categories
            ->map(static function (ArtworkCategory $category): array {
                /** @var SiteSection|null $section */
                $section = $category->getRelationValue('siteSection');

                return [
                    'id' => (int) $category->getKey(),
                    'name' => (string) $category->getAttribute('name'),
                    'state' => (string) ($section?->getAttribute('state') ?? 'hidden'),
                ];
            })
            ->values()
            ->all();
    }

    private function loadArtworks(): void
    {
        /** @var EloquentCollection<int, Artwork> $records */
        $records = Artwork::query()
            ->where('artwork_category_id', $this->galleryContext['id'])
            ->with([
                'artworkMedia' => static fn ($query) => $query
                    ->where('role', 'primary')
                    ->orderBy('position'),
                'artworkMedia.mediaAsset.variants' => static fn ($query) => $query
                    ->where('variant_kind', 'thumbnail')
                    ->where('transform_profile', MediaIngestService::TRANSFORM_PROFILE),
            ])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $galleryPublished = $this->galleryContext['state'] === 'published';
        $this->publishedCount = $records
            ->filter(static fn (Artwork $artwork): bool => $artwork->getAttribute('state') === 'published')
            ->count();

        $count = $records->count();
        $this->artworks = $records
            ->values()
            ->map(function (Artwork $artwork, int $index) use ($galleryPublished, $count): array {
                $isPublished = $artwork->getAttribute('state') === 'published';
                /** @var EloquentCollection<int, ArtworkMedia> $mediaUsages */
                $mediaUsages = $artwork->getRelation('artworkMedia');
                $primaries = $mediaUsages->where('role', 'primary')->values();
                /** @var ArtworkMedia|null $primary */
                $primary = $primaries->count() === 1 ? $primaries->first() : null;
                /** @var MediaAsset|null $primaryAsset */
                $primaryAsset = $primary?->getRelationValue('mediaAsset');
                /** @var MediaVariant|null $thumbnail */
                $thumbnail = $primaryAsset instanceof MediaAsset
                    ? $primaryAsset->getRelation('variants')->first(
                        static fn (MediaVariant $candidate): bool => $candidate->getAttribute('variant_kind') === 'thumbnail'
                            && $candidate->getAttribute('transform_profile') === MediaIngestService::TRANSFORM_PROFILE
                            && $candidate->getAttribute('state') === 'available'
                    )
                    : null;

                return [
                    'id' => (int) $artwork->getKey(),
                    'sequence' => $index + 1,
                    'title' => (string) $artwork->getAttribute('title'),
                    'state' => (string) $artwork->getAttribute('state'),
                    'state_label' => ucfirst((string) $artwork->getAttribute('state')),
                    'readiness_label' => $this->readinessLabel($artwork, $galleryPublished, $primaries->count(), $primaryAsset, $thumbnail),
                    'is_ready' => $this->isReadyToPublish($artwork, $galleryPublished, $primaries->count(), $primaryAsset, $thumbnail),
                    'year' => $artwork->getAttribute('work_year'),
                    'medium' => $artwork->getAttribute('medium'),
                    'dimensions' => $artwork->getAttribute('dimensions'),
                    'thumbnail_url' => ArtworkResource::thumbnailUrl($artwork),
                    'edit_url' => ArtworkResource::getUrl('edit', [
                        'record' => $artwork->getKey(),
                        'gallery' => $this->galleryContext['id'],
                    ]),
                    'media_preview_url' => $primaryAsset instanceof MediaAsset && $primaryAsset->getAttribute('state') === 'available'
                        ? MediaAssetResource::getUrl('view', ['record' => $primaryAsset->getKey(), 'artwork' => $artwork->getKey()])
                        : null,
                    'public_url' => $galleryPublished && $isPublished
                        ? route('artworks.show', ['slug' => $artwork->getAttribute('slug')])
                        : null,
                    'can_move_up' => $index > 0,
                    'can_move_down' => $index < $count - 1,
                ];
            })
            ->all();

        $analyticsKeys = $records
            ->pluck('analytics_key')
            ->filter(static fn (mixed $key): bool => is_string($key) && trim($key) !== '')
            ->values()
            ->all();

        $this->analytics = app(ArtistReportingService::class)->gallery(
            (string) $this->galleryContext['path'],
            $analyticsKeys,
            '30d',
        );
    }

    private function isReadyToPublish(
        Artwork $artwork,
        bool $galleryPublished,
        int $primaryCount,
        ?MediaAsset $primaryAsset,
        ?MediaVariant $thumbnail,
    ): bool {
        $altText = $primaryAsset?->getAttribute('alt_text');

        return $artwork->getAttribute('state') !== 'archived'
            && $galleryPublished
            && $primaryCount === 1
            && $primaryAsset instanceof MediaAsset
            && $primaryAsset->getAttribute('state') === 'available'
            && is_string($altText)
            && trim($altText) !== ''
            && $thumbnail instanceof MediaVariant;
    }

    private function readinessLabel(
        Artwork $artwork,
        bool $galleryPublished,
        int $primaryCount,
        ?MediaAsset $primaryAsset,
        ?MediaVariant $thumbnail,
    ): string {
        if ($artwork->getAttribute('state') === 'published') {
            return 'Published';
        }

        if ($artwork->getAttribute('state') === 'archived') {
            return 'Archived';
        }

        if (! $galleryPublished) {
            return 'Gallery hidden';
        }

        if ($primaryCount !== 1 || ! $primaryAsset instanceof MediaAsset) {
            return 'Needs primary image';
        }

        if ($primaryAsset->getAttribute('state') !== 'available') {
            return 'Image unavailable';
        }

        $altText = $primaryAsset->getAttribute('alt_text');
        if (! is_string($altText) || trim($altText) === '') {
            return 'Needs ALT text';
        }

        if (! $thumbnail instanceof MediaVariant) {
            return 'Thumbnail pending';
        }

        return 'Ready to publish';
    }

    private function firstValidationMessage(ValidationException $exception): string
    {
        $message = collect($exception->errors())->flatten()->first();

        return is_string($message) && $message !== '' ? $message : 'The Gallery assignment is not valid.';
    }
}
