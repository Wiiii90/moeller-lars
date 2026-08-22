<?php

namespace App\Filament\Resources\Artworks\Pages;

use App\Domain\Analytics\ArtistReportingService;
use App\Domain\Artwork\ArtworkDraftService;
use App\Domain\Artwork\ArtworkEditorialService;
use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Domain\Artwork\GalleryEditorialService;
use App\Domain\Media\MediaIngestService;
use App\Domain\Media\MediaTypePolicy;
use App\Filament\Pages\SitePages;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\SiteSection;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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

    public function gallerySettingsAction(): Action
    {
        return Action::make('gallerySettings')
            ->label('Settings')
            ->fillForm(function (): array {
                /** @var ArtworkCategory $gallery */
                $gallery = ArtworkCategory::query()->findOrFail((int) $this->galleryContext['id']);

                return [
                    'name' => (string) $gallery->getAttribute('name'),
                    'slug' => (string) $gallery->getAttribute('slug'),
                    'description' => $gallery->getAttribute('description'),
                    'show_on_home' => (bool) $gallery->getAttribute('show_on_home'),
                ];
            })
            ->schema([
                TextInput::make('name')
                    ->label('Gallery title')
                    ->required()
                    ->maxLength(160),
                TextInput::make('slug')
                    ->label('Public URL slug')
                    ->required()
                    ->maxLength(80)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->helperText('Changing this keeps the previous Gallery URL as a redirect.'),
                Textarea::make('description')
                    ->rows(5)
                    ->maxLength(10000)
                    ->nullable()
                    ->columnSpanFull(),
                Toggle::make('show_on_home')
                    ->label('Eligible for homepage presentation'),
            ])
            ->modalHeading('Gallery settings')
            ->modalSubmitActionLabel('Save changes')
            ->action(function (array $data): void {
                /** @var ArtworkCategory $gallery */
                $gallery = ArtworkCategory::query()->findOrFail((int) $this->galleryContext['id']);
                $currentSlug = (string) $gallery->getAttribute('slug');
                $service = app(GalleryEditorialService::class);

                DB::transaction(function () use ($service, $gallery, $currentSlug, $data): void {
                    $service->update($gallery, [
                        'name' => $data['name'],
                        'description' => $data['description'] ?? null,
                        'show_on_home' => (bool) ($data['show_on_home'] ?? false),
                    ]);

                    $newSlug = trim((string) ($data['slug'] ?? ''));
                    if ($newSlug !== $currentSlug) {
                        $service->changeSlug($gallery, $newSlug);
                    }
                });

                $this->loadGallery((int) $gallery->getKey());
                $this->loadMoveTargets();
                $this->loadArtworks();
                Notification::make()->title('Gallery settings saved')->success()->send();
            });
    }

    public function addArtworkAction(): Action
    {
        return Action::make('addArtwork')
            ->label('Add artwork')
            ->schema([
                TextInput::make('title')
                    ->required()
                    ->maxLength(240)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, callable $set, callable $get): void {
                        if (blank($get('slug')) && filled($state)) {
                            $set('slug', Str::slug($state));
                        }
                    }),
                TextInput::make('slug')
                    ->label('Public URL slug')
                    ->required()
                    ->maxLength(180)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->unique('artworks', 'slug'),
                TextInput::make('medium')->nullable()->maxLength(240),
                TextInput::make('dimensions')->nullable()->maxLength(240),
                Textarea::make('description')->nullable()->maxLength(10000)->columnSpanFull(),
                FileUpload::make('primary_media')
                    ->label('Primary image')
                    ->image()
                    ->storeFiles(false)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize((int) ceil(MediaTypePolicy::imageMaxBytes() / 1024))
                    ->helperText('Optional while drafting, but required before publication.')
                    ->columnSpanFull(),
                TextInput::make('work_year')
                    ->label('Year')
                    ->numeric()
                    ->minValue(1000)
                    ->maxValue(9999)
                    ->nullable(),
                DatePicker::make('work_date')
                    ->label('Exact date')
                    ->helperText('If set, the year is derived from this date.')
                    ->nullable(),
                Toggle::make('featured_on_home')
                    ->label('Feature on home when newest year is shared')
                    ->default(false),
            ])
            ->modalHeading('Add artwork')
            ->modalSubmitActionLabel('Create draft')
            ->action(function (array $data): void {
                $primaryMedia = $data['primary_media'] ?? null;
                if ($primaryMedia !== null && ! $primaryMedia instanceof TemporaryUploadedFile) {
                    throw ValidationException::withMessages(['primary_media' => 'A valid uploaded image is required.']);
                }

                $data['artwork_category_id'] = (int) $this->galleryContext['id'];
                $data['work_date'] = $data['work_date'] ?? null;
                $artwork = app(ArtworkDraftService::class)->create($data);

                $imageAttached = false;
                if ($primaryMedia instanceof TemporaryUploadedFile) {
                    try {
                        app(ArtworkEditorialService::class)->attachPrimaryMedia($artwork, $primaryMedia);
                        $imageAttached = true;
                    } catch (ValidationException) {
                        Notification::make()
                            ->title('Artwork created; image needs attention')
                            ->body('The draft is saved, but the primary image could not be attached.')
                            ->warning()
                            ->send();
                    }
                }

                $this->loadArtworks();

                if ($primaryMedia === null || $imageAttached) {
                    Notification::make()
                        ->title('Artwork draft created')
                        ->body($imageAttached ? 'The primary image was attached.' : 'Add a primary image before publication.')
                        ->success()
                        ->send();
                }
            });
    }

    public function moveArtwork(int $artworkId, string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException('Artwork order direction must be up or down.');
        }

        $galleryId = (int) $this->galleryContext['id'];
        /** @var ArtworkCategory $gallery */
        $gallery = ArtworkCategory::query()->findOrFail($galleryId);
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
        app(GalleryEditorialService::class)->reorderArtworks($gallery, $orderedIds);

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
        /** @var ArtworkCategory $gallery */
        $gallery = ArtworkCategory::query()->findOrFail($galleryId);
        /** @var SiteSection $section */
        $section = $gallery->siteSection()->with('parent')->firstOrFail();
        /** @var SiteSection|null $parent */
        $parent = $section->getRelationValue('parent');
        $isPublished = $section->getAttribute('state') === 'published';

        $this->galleryContext = [
            'id' => (int) $gallery->getKey(),
            'name' => (string) $gallery->getAttribute('name'),
            'slug' => (string) $gallery->getAttribute('slug'),
            'state' => (string) $section->getAttribute('state'),
            'parent_name' => $parent?->getAttribute('title'),
            'path' => '/'.ltrim((string) $gallery->getAttribute('slug'), '/'),
            'pages_url' => SitePages::getUrl(),
            'all_artworks_url' => ArtworkResource::getUrl('index'),
            'public_url' => $isPublished
                ? route('site.section', ['section' => $gallery->getAttribute('slug')])
                : null,
        ];
    }

    private function loadMoveTargets(): void
    {
        /** @var EloquentCollection<int, ArtworkCategory> $galleries */
        $galleries = ArtworkCategory::query()
            ->whereKeyNot((int) $this->galleryContext['id'])
            ->whereHas('siteSection')
            ->with('siteSection')
            ->orderBy('name')
            ->get();

        $this->moveTargets = $galleries
            ->map(static function (ArtworkCategory $gallery): array {
                /** @var SiteSection|null $section */
                $section = $gallery->getRelationValue('siteSection');

                return [
                    'id' => (int) $gallery->getKey(),
                    'name' => (string) $gallery->getAttribute('name'),
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

        return filled($artwork->getAttribute('title'))
            && filled($artwork->getAttribute('slug'))
            && $primaryCount === 1
            && $primaryAsset instanceof MediaAsset
            && $primaryAsset->getAttribute('state') === 'available'
            && $thumbnail instanceof MediaVariant
            && filled($altText)
            && $galleryPublished;
    }

    private function readinessLabel(
        Artwork $artwork,
        bool $galleryPublished,
        int $primaryCount,
        ?MediaAsset $primaryAsset,
        ?MediaVariant $thumbnail,
    ): string {
        if (! filled($artwork->getAttribute('title')) || ! filled($artwork->getAttribute('slug'))) {
            return 'Missing title or URL';
        }
        if ($primaryCount !== 1) {
            return $primaryCount === 0 ? 'Primary image required' : 'Primary image is ambiguous';
        }
        if (! $primaryAsset || $primaryAsset->getAttribute('state') !== 'available') {
            return 'Primary image unavailable';
        }
        if (! filled($primaryAsset->getAttribute('alt_text'))) {
            return 'Alt text required';
        }
        if (! $thumbnail) {
            return 'Thumbnail processing required';
        }
        if (! $galleryPublished) {
            return 'Gallery must be published';
        }

        return 'Ready';
    }

    private function firstValidationMessage(ValidationException $exception): string
    {
        $message = collect($exception->errors())->flatten()->first();

        return is_string($message) ? $message : 'The requested Gallery change is not valid.';
    }
}
