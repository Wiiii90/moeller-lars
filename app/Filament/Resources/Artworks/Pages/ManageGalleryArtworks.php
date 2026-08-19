<?php

namespace App\Filament\Resources\Artworks\Pages;

use App\Domain\Artwork\ArtworkCategoryEditorialService;
use App\Filament\Pages\SitePages;
use App\Filament\Resources\ArtworkCategories\ArtworkCategoryResource;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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

    public int $publishedCount = 0;

    public function mount(int|string $gallery): void
    {
        $this->loadGallery((int) $gallery);
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

    private function loadGallery(int $galleryId): void
    {
        /** @var ArtworkCategory $category */
        $category = ArtworkCategory::query()->with('parent')->findOrFail($galleryId);
        /** @var ArtworkCategory|null $parent */
        $parent = $category->getRelationValue('parent');
        $isPublished = $category->getAttribute('state') === 'published';

        $this->galleryContext = [
            'id' => (int) $category->getKey(),
            'name' => (string) $category->getAttribute('name'),
            'slug' => (string) $category->getAttribute('slug'),
            'state' => (string) $category->getAttribute('state'),
            'parent_name' => $parent?->getAttribute('name'),
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

    private function loadArtworks(): void
    {
        /** @var EloquentCollection<int, Artwork> $records */
        $records = Artwork::query()
            ->where('artwork_category_id', $this->galleryContext['id'])
            ->with('artworkMedia.mediaAsset.variants')
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
            ->map(static function (Artwork $artwork, int $index) use ($galleryPublished, $count): array {
                $isPublished = $artwork->getAttribute('state') === 'published';
                /** @var EloquentCollection<int, ArtworkMedia> $mediaUsages */
                $mediaUsages = $artwork->getRelation('artworkMedia');
                /** @var ArtworkMedia|null $primary */
                $primary = $mediaUsages->firstWhere('role', 'primary');
                /** @var MediaAsset|null $primaryAsset */
                $primaryAsset = $primary?->getRelationValue('mediaAsset');

                return [
                    'id' => (int) $artwork->getKey(),
                    'sequence' => $index + 1,
                    'title' => (string) $artwork->getAttribute('title'),
                    'state' => (string) $artwork->getAttribute('state'),
                    'state_label' => ucfirst((string) $artwork->getAttribute('state')),
                    'year' => $artwork->getAttribute('work_year'),
                    'medium' => $artwork->getAttribute('medium'),
                    'dimensions' => $artwork->getAttribute('dimensions'),
                    'thumbnail_url' => ArtworkResource::thumbnailUrl($artwork),
                    'edit_url' => ArtworkResource::getUrl('edit', ['record' => $artwork->getKey()]),
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
    }
}
