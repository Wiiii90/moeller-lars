<?php

namespace App\Filament\Resources\MediaAssets\Pages;

use App\Domain\Media\MediaIngestService;
use App\Domain\Media\MediaTypePolicy;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Support\AdminForm;
use App\Filament\Support\MediaReferenceCatalog;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
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

    public string $reference = 'all';

    public string $usedIn = 'all';

    public string $state = 'available';

    public string $viewMode = 'list';

    public string $search = '';

    public mixed $directMedia = null;

    public ?string $directUploadMessage = null;

    public int $page = 1;

    public int $total = 0;

    public int $pages = 1;

    public int $libraryFiles = 0;

    public int $libraryImages = 0;

    public int $libraryVideos = 0;

    public int $libraryUnreferenced = 0;

    public int $libraryAltMissing = 0;

    public string $librarySize = '0 B';

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

    public function updatedReference(): void
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
        $this->directUploadMessage = (string) $asset->getAttribute('original_filename').' is available in Media Files.';
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
        $this->reference = 'all';
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

    public function previewAction(): Action
    {
        return Action::make('preview')
            ->label('Preview')
            ->modalHeading(fn (array $arguments): string => (string) $this->actionAsset($arguments)->getAttribute('original_filename'))
            ->modalContent(fn (array $arguments): View => view(
                'filament.resources.media-assets.partials.preview-dialog',
                $this->previewDialogData($arguments),
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'media-file-dialog']);
    }

    public function editAction(): Action
    {
        return Action::make('edit')
            ->label('Edit')
            ->modalHeading(fn (array $arguments): string => 'Edit '.(string) $this->actionAsset($arguments)->getAttribute('original_filename'))
            ->fillForm(function (array $arguments): array {
                $asset = $this->actionAsset($arguments);

                return [
                    'alt_text' => $asset->getAttribute('alt_text'),
                    'credit' => $asset->getAttribute('credit'),
                    'copyright_notice' => $asset->getAttribute('copyright_notice'),
                ];
            })
            ->schema([
                AdminForm::section('Accessibility and credit')
                    ->schema([
                        TextInput::make('alt_text')
                            ->label('Default ALT text')
                            ->helperText('For images, describe the content and function. Individual usages may override this text.')
                            ->maxLength(500)
                            ->nullable(),
                        TextInput::make('credit')
                            ->maxLength(240)
                            ->nullable(),
                        Textarea::make('copyright_notice')
                            ->maxLength(500)
                            ->nullable()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ])
            ->action(function (array $data, array $arguments): void {
                $asset = $this->actionAsset($arguments);
                if ((string) $asset->getAttribute('state') === 'deleted') {
                    throw ValidationException::withMessages([
                        'alt_text' => 'Deleted media cannot be edited.',
                    ]);
                }

                $asset->fill([
                    'alt_text' => $data['alt_text'] ?? null,
                    'credit' => $data['credit'] ?? null,
                    'copyright_notice' => $data['copyright_notice'] ?? null,
                ]);
                $asset->save();

                $this->loadLibrary();
                Notification::make()->title('File metadata saved')->success()->send();
            })
            ->modalSubmitActionLabel('Save')
            ->modalCancelActionLabel('Cancel')
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'media-file-dialog']);
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
        $this->loadLibraryMetrics($catalog);

        $query = $this->filteredQuery($catalog);
        $this->total = (clone $query)->count();

        $this->pages = max(1, (int) ceil($this->total / self::PER_PAGE));
        $this->page = min($this->page, $this->pages);

        $catalog->eagerLoad($query);

        /** @var EloquentCollection<int, MediaAsset> $records */
        $records = $this->orderResults($query)
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
                'editable' => $state !== 'deleted',
            ];
        })->all();
    }

    private function loadLibraryMetrics(MediaReferenceCatalog $catalog): void
    {
        $metrics = $catalog->libraryMetrics();

        $this->libraryFiles = $metrics['files'];
        $this->libraryImages = $metrics['images'];
        $this->libraryVideos = $metrics['videos'];
        $this->libraryUnreferenced = $metrics['unreferenced'];
        $this->libraryAltMissing = $metrics['alt_missing'];
        $this->librarySize = self::formatBytes($metrics['bytes']);
    }

    /** @return Builder<MediaAsset> */
    private function filteredQuery(MediaReferenceCatalog $catalog): Builder
    {
        /** @var Builder<MediaAsset> $query */
        $query = MediaAsset::query()
            ->whereIn('mime_type', MediaTypePolicy::acceptedMimeTypes());

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

        if (in_array($this->reference, ['referenced', 'unreferenced'], true)) {
            $catalog->applyReferenceFilter($query, $this->reference === 'referenced');
        }

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

    /** @param Builder<MediaAsset> $query */
    private function orderResults(Builder $query): Builder
    {
        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /** @param array<string, mixed> $arguments */
    private function actionAsset(array $arguments): MediaAsset
    {
        $id = $arguments['asset'] ?? null;
        abort_unless(is_numeric($id), 404);

        /** @var MediaAsset|null $asset */
        $asset = MediaAsset::query()->find((int) $id);
        abort_unless($asset instanceof MediaAsset, 404);

        return $asset;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function previewDialogData(array $arguments): array
    {
        $asset = $this->actionAsset($arguments);
        $catalog = app(MediaReferenceCatalog::class);
        $catalog->loadAssetReferences($asset);
        $mime = (string) $asset->getAttribute('mime_type');
        $state = (string) $asset->getAttribute('state');
        $createdAt = $asset->getAttribute('created_at');
        $ids = $this->orderResults($this->filteredQuery($catalog))
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        $index = array_search((int) $asset->getKey(), $ids, true);
        $position = $index === false ? null : $index + 1;

        return [
            'asset' => [
                'id' => (int) $asset->getKey(),
                'filename' => (string) $asset->getAttribute('original_filename'),
                'kind' => MediaTypePolicy::kind($mime),
                'type_label' => MediaTypePolicy::label($mime),
                'mime' => $mime,
                'state' => $state,
                'preview_url' => $state === 'available' ? route('admin.media.original', $asset) : null,
                'alt_text' => (string) ($asset->getAttribute('alt_text') ?? ''),
                'credit' => (string) ($asset->getAttribute('credit') ?? ''),
                'copyright_notice' => (string) ($asset->getAttribute('copyright_notice') ?? ''),
                'dimensions' => $asset->getAttribute('width') && $asset->getAttribute('height')
                    ? $asset->getAttribute('width').'×'.$asset->getAttribute('height')
                    : '—',
                'size' => self::formatBytes((int) $asset->getAttribute('byte_size')),
                'checksum' => (string) $asset->getAttribute('sha256'),
                'created' => $createdAt instanceof DateTimeInterface ? $createdAt->format('Y-m-d H:i') : '—',
                'references' => $catalog->references($asset),
            ],
            'previousId' => $index !== false && $index > 0 ? $ids[$index - 1] : null,
            'nextId' => $index !== false && $index < count($ids) - 1 ? $ids[$index + 1] : null,
            'resultPosition' => $position,
            'resultTotal' => count($ids),
        ];
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
