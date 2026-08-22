<?php

namespace App\Filament\Resources\MediaAssets\Pages;

use App\Domain\Media\MediaIngestService;
use App\Domain\Media\MediaTypePolicy;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Support\MediaReferenceCatalog;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

final class ListMediaAssets extends Page
{
    use WithFileUploads;

    private const PER_PAGE = 50;

    protected static string $resource = MediaAssetResource::class;

    protected string $view = 'filament.resources.media-assets.pages.list-media-assets';

    /** @var list<array<string, mixed>> */
    public array $assets = [];

    /** @var list<array{label:string,options:list<array{value:string,label:string}>}> */
    public array $usedInGroups = [];

    public string $type = 'all';

    public string $usedIn = 'all';

    public string $state = 'available';

    public string $viewMode = 'list';

    public string $search = '';

    public mixed $directMedia = null;

    public ?string $directUploadMessage = null;

    public int $page = 1;

    public int $total = 0;

    public int $pages = 1;

    public int $inUse = 0;

    public int $unused = 0;

    public function mount(): void
    {
        $this->loadLibrary();
    }

    public function updatedSearch(): void
    {
        $this->refreshFromFirstPage();
    }

    public function updatedType(): void
    {
        $this->refreshFromFirstPage();
    }

    public function updatedUsedIn(): void
    {
        $this->refreshFromFirstPage();
    }

    public function updatedState(): void
    {
        $this->refreshFromFirstPage();
    }

    public function updatedDirectMedia(): void
    {
        $upload = $this->directMedia;
        $this->directUploadMessage = null;
        $this->resetErrorBag('directMedia');

        if (! $upload instanceof TemporaryUploadedFile) {
            return;
        }

        try {
            $asset = app(MediaIngestService::class)->ingest($upload);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();
            $this->reset('directMedia');
            $this->addError('directMedia', is_string($message) ? $message : 'The file could not be uploaded.');

            return;
        }

        $this->reset('directMedia');
        $this->page = 1;
        $this->directUploadMessage = (string) $asset->getAttribute('original_filename').' is available in Files.';
        $this->loadLibrary();
        Notification::make()->title('File uploaded')->success()->send();
    }

    public function setViewMode(string $mode): void
    {
        if (! in_array($mode, ['list', 'grid', 'dense'], true)) {
            return;
        }

        $this->viewMode = $mode;
    }

    public function resetFilters(): void
    {
        $this->type = 'all';
        $this->usedIn = 'all';
        $this->state = 'available';
        $this->search = '';
        $this->refreshFromFirstPage();
    }

    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
            $this->loadLibrary();
        }
    }

    public function nextPage(): void
    {
        if ($this->page < $this->pages) {
            $this->page++;
            $this->loadLibrary();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')
                ->label('Upload media')
                ->schema([
                    FileUpload::make('media')
                        ->required()
                        ->storeFiles(false)
                        ->acceptedFileTypes(MediaTypePolicy::acceptedMimeTypes())
                        ->maxSize((int) ceil(MediaTypePolicy::maxUploadBytes() / 1024))
                        ->helperText('JPEG, PNG, WebP, H.264 MP4, or VP8/VP9/AV1 WebM. Type-specific byte limits are operator configured.'),
                ])
                ->action(function (array $data): void {
                    if (! array_key_exists('media', $data) || ! $data['media'] instanceof TemporaryUploadedFile) {
                        throw ValidationException::withMessages(['media' => 'A valid media upload is required.']);
                    }

                    app(MediaIngestService::class)->ingest($data['media']);
                    $this->page = 1;
                    $this->loadLibrary();
                    Notification::make()->title('File uploaded')->success()->send();
                }),
        ];
    }

    private function refreshFromFirstPage(): void
    {
        $this->page = 1;
        $this->loadLibrary();
    }

    private function loadLibrary(): void
    {
        $catalog = app(MediaReferenceCatalog::class);
        $this->usedInGroups = $catalog->destinationGroups();

        $query = $this->filteredQuery($catalog);
        $this->total = (clone $query)->count();

        $referenced = clone $query;
        $catalog->applyReferenceFilter($referenced, true);
        $this->inUse = $referenced->count();

        $unreferenced = clone $query;
        $catalog->applyReferenceFilter($unreferenced, false);
        $this->unused = $unreferenced->count();

        $this->pages = max(1, (int) ceil($this->total / self::PER_PAGE));
        $this->page = min($this->page, $this->pages);

        $catalog->eagerLoad($query);

        /** @var EloquentCollection<int, MediaAsset> $records */
        $records = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->forPage($this->page, self::PER_PAGE)
            ->get();

        $this->assets = $records->map(function (MediaAsset $asset) use ($catalog): array {
            /** @var MediaVariant|null $thumbnail */
            $thumbnail = $asset->getRelationValue('variants')->first(static function (MediaVariant $variant): bool {
                return $variant->getAttribute('variant_kind') === MediaIngestService::THUMBNAIL_KIND
                    && $variant->getAttribute('transform_profile') === MediaIngestService::TRANSFORM_PROFILE
                    && $variant->getAttribute('state') === 'available';
            });
            $references = $catalog->references($asset);
            $mime = (string) $asset->getAttribute('mime_type');
            $createdAt = $asset->getAttribute('created_at');
            $state = (string) $asset->getAttribute('state');

            return [
                'id' => (int) $asset->getKey(),
                'filename' => (string) $asset->getAttribute('original_filename'),
                'state' => $state,
                'state_detail' => match ($state) {
                    'available' => 'Ready to reuse',
                    'quarantined' => 'Held for review',
                    'deleted' => 'Not reusable',
                    default => 'Unavailable',
                },
                'alt_missing' => MediaTypePolicy::isImage($mime) && blank($asset->getAttribute('alt_text')),
                'credit' => (string) ($asset->getAttribute('credit') ?? ''),
                'size' => self::formatBytes((int) $asset->getAttribute('byte_size')),
                'dimensions' => $asset->getAttribute('width') && $asset->getAttribute('height')
                    ? $asset->getAttribute('width').'×'.$asset->getAttribute('height')
                    : null,
                'usage' => count($references),
                'shared' => count($references) > 1,
                'references' => array_slice($references, 0, 2),
                'reference_overflow' => max(0, count($references) - 2),
                'mime' => $mime,
                'type_label' => MediaTypePolicy::label($mime),
                'kind' => MediaTypePolicy::kind($mime),
                'thumbnail_url' => $thumbnail === null ? null : route('admin.media.variant', $thumbnail),
                'thumbnail_width' => $thumbnail?->getAttribute('width'),
                'thumbnail_height' => $thumbnail?->getAttribute('height'),
                'created' => $createdAt instanceof DateTimeInterface ? $createdAt->format('Y-m-d') : '—',
                'edit_url' => $state === 'deleted' ? null : MediaAssetResource::getUrl('edit', ['record' => $asset->getKey()]),
                'preview_url' => MediaAssetResource::getUrl('view', ['record' => $asset->getKey()]),
            ];
        })->all();
    }

    /** @return Builder<MediaAsset> */
    private function filteredQuery(MediaReferenceCatalog $catalog): Builder
    {
        /** @var Builder<MediaAsset> $query */
        $query = MediaAsset::query();

        $term = trim($this->search);
        if ($term !== '') {
            $query->where(function (Builder $search) use ($term): void {
                $search->where('original_filename', 'ilike', '%'.$term.'%')
                    ->orWhere('alt_text', 'ilike', '%'.$term.'%')
                    ->orWhere('credit', 'ilike', '%'.$term.'%')
                    ->orWhere('copyright_notice', 'ilike', '%'.$term.'%')
                    ->orWhere('mime_type', 'ilike', '%'.$term.'%');
            });
        }

        if ($this->state !== 'all' && in_array($this->state, ['available', 'quarantined', 'deleted'], true)) {
            $query->where('state', $this->state);
        }

        $this->applyTypeFilter($query);
        $catalog->applyDestinationFilter($query, $this->usedIn);

        return $query;
    }

    /** @param Builder<MediaAsset> $query */
    private function applyTypeFilter(Builder $query): void
    {
        if ($this->type === 'image') {
            $query->whereIn('mime_type', MediaTypePolicy::IMAGE_MIME_TYPES);

            return;
        }
        if ($this->type === 'video') {
            $query->whereIn('mime_type', MediaTypePolicy::VIDEO_MIME_TYPES);

            return;
        }
        if (in_array($this->type, MediaTypePolicy::acceptedMimeTypes(), true)) {
            $query->where('mime_type', $this->type);
        }
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }
        if ($bytes < 1024 * 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1).' MB';
        }

        return number_format($bytes / (1024 * 1024 * 1024), 2).' GB';
    }
}
