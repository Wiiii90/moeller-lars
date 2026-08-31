<?php

namespace App\Filament\Resources\MediaAssets\Pages;

use App\Domain\Media\MediaAssetEditorialService;
use App\Domain\Media\MediaIngestService;
use App\Domain\Media\MediaTypePolicy;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Support\AdminForm;
use App\Filament\Support\MediaReferenceCatalog;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

final class ListMediaAssets extends Page
{
    use WithFileUploads;

    /** @var list<int> */
    private const PAGE_SIZES = [25, 50, 100];

    /** @var list<string> */
    private const VIEW_MODES = ['list', 'grid', 'dense'];

    private const DEFAULT_PAGE_SIZE = 50;

    private const VIEW_COOKIE = 'admin_media_files_view';

    private const VIEW_COOKIE_MINUTES = 60 * 24 * 365;

    protected static string $resource = MediaAssetResource::class;

    protected string $view = 'filament.resources.media-assets.pages.list-media-assets';

    /** @var list<array<string, mixed>> */
    public array $assets = [];

    /** @var list<array{label:string,options:list<array{value:string,label:string}>}> */
    public array $usageGroups = [];

    /** @var list<int> */
    public array $selectedAssets = [];

    public string $type = 'all';

    public string $usage = 'all';

    public string $state = 'available';

    public string $viewMode = 'list';

    public string $search = '';

    /** @var list<TemporaryUploadedFile> */
    public array $directMedia = [];

    public int $page = 1;

    public int $pageSize = self::DEFAULT_PAGE_SIZE;

    public int $total = 0;

    public int $pages = 1;

    public int $libraryFiles = 0;

    public int $libraryImages = 0;

    public int $libraryVideos = 0;

    public int $libraryAudio = 0;

    public int $libraryUnreferenced = 0;

    public string $librarySize = '0 B';

    public function mount(): void
    {
        $storedView = request()->cookie(self::VIEW_COOKIE);
        $this->viewMode = is_string($storedView) && in_array($storedView, self::VIEW_MODES, true)
            ? $storedView
            : 'list';
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

    public function updatedUsage(): void
    {
        $this->refreshFromFirstPage();
    }

    public function updatedState(): void
    {
        $this->refreshFromFirstPage();
    }

    public function updatedPageSize(mixed $value): void
    {
        $this->pageSize = $this->normalizePageSize($value);
        $this->page = 1;
        $this->loadLibrary();
    }

    /** @return array{summary:string,added:int,duplicates:int,failed:int} */
    public function processDirectMedia(): array
    {
        $uploads = array_values(array_filter(
            $this->directMedia,
            static fn (mixed $upload): bool => $upload instanceof TemporaryUploadedFile,
        ));
        $this->resetErrorBag('directMedia');

        if ($uploads === []) {
            $this->reset('directMedia');

            return [
                'summary' => 'Upload failed',
                'added' => 0,
                'duplicates' => 0,
                'failed' => 1,
            ];
        }

        $service = app(MediaIngestService::class);
        $added = 0;
        $duplicates = 0;
        $failures = [];

        foreach ($uploads as $upload) {
            try {
                $result = $service->ingestUnique($upload);
                if ($result['duplicate']) {
                    $duplicates++;
                } else {
                    $added++;
                }
            } catch (ValidationException $exception) {
                $failures[] = [
                    'filename' => $this->uploadFilename($upload),
                    'message' => $this->uploadValidationMessage($exception),
                ];
            } catch (Throwable $exception) {
                report($exception);
                $failures[] = [
                    'filename' => $this->uploadFilename($upload),
                    'message' => 'The file could not be uploaded.',
                ];
            }
        }

        $failed = count($failures);
        $total = count($uploads);
        $this->reset('directMedia');

        if ($added > 0) {
            $this->page = 1;
        }

        $this->loadLibrary();

        if ($failures !== []) {
            $details = array_map(
                static fn (array $failure): string => $failure['filename'].' — '.$failure['message'],
                array_slice($failures, 0, 4),
            );
            if ($failed > 4) {
                $details[] = '+'.($failed - 4).' more';
            }

            $notification = Notification::make()
                ->title(($added + $duplicates) > 0 ? 'Upload completed with issues' : 'Upload failed')
                ->body(implode("\n", $details));

            if (($added + $duplicates) > 0) {
                $notification->warning();
            } else {
                $notification->danger();
            }

            $notification->send();
        } elseif ($added > 0) {
            Notification::make()
                ->title($total === 1 ? 'File uploaded' : 'Files uploaded')
                ->body($this->directUploadSummary($total, $added, $duplicates, 0))
                ->success()
                ->send();
        } elseif ($duplicates > 0) {
            Notification::make()
                ->title('Already in Media Files')
                ->body($total === 1 ? null : $duplicates.' files already exist in Media Files')
                ->info()
                ->send();
        }

        return [
            'summary' => $this->directUploadSummary($total, $added, $duplicates, $failed),
            'added' => $added,
            'duplicates' => $duplicates,
            'failed' => $failed,
        ];
    }

    public function setViewMode(string $mode): void
    {
        if (! in_array($mode, self::VIEW_MODES, true)) {
            return;
        }

        $this->normalizeSelection();
        $this->viewMode = $mode;
        Cookie::queue(
            self::VIEW_COOKIE,
            $mode,
            self::VIEW_COOKIE_MINUTES,
            '/admin',
            null,
            null,
            true,
            false,
            'lax',
        );
    }

    public function toggleAssetSelection(int $assetId): void
    {
        $this->normalizeSelection();

        $selectable = false;
        foreach ($this->assets as $asset) {
            if (($asset['id'] ?? null) === $assetId && ($asset['selectable'] ?? false) === true) {
                $selectable = true;
                break;
            }
        }

        if (! $selectable) {
            return;
        }

        if (in_array($assetId, $this->selectedAssets, true)) {
            $this->selectedAssets = $this->normalizeSelectedAssets(array_filter(
                $this->selectedAssets,
                static fn (int $selectedId): bool => $selectedId !== $assetId,
            ));

            return;
        }

        $this->selectedAssets = $this->normalizeSelectedAssets([...$this->selectedAssets, $assetId]);
    }

    public function toggleVisibleSelection(): void
    {
        $this->normalizeSelection();

        $visibleIds = collect($this->assets)
            ->filter(static fn (array $asset): bool => ($asset['selectable'] ?? false) === true)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        if ($visibleIds === []) {
            return;
        }

        $allVisibleSelected = count(array_intersect($visibleIds, $this->selectedAssets)) === count($visibleIds);

        $this->selectedAssets = $allVisibleSelected
            ? $this->normalizeSelectedAssets(array_diff($this->selectedAssets, $visibleIds))
            : $this->normalizeSelectedAssets(array_merge($this->selectedAssets, $visibleIds));
    }

    public function resetFilters(): void
    {
        $this->type = 'all';
        $this->usage = 'all';
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
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Close')
                ->extraAttributes(['class' => 'media-dialog-footer__cancel']))
            ->extraModalFooterActions(fn (array $arguments): array => $this->previewFooterActions($arguments))
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'media-file-dialog']);
    }

    public function editAction(): Action
    {
        return Action::make('edit')
            ->label('Edit')
            ->modalHeading(fn (array $arguments): string => 'Edit '.(string) $this->actionAsset($arguments)->getAttribute('original_filename'))
            ->fillForm(fn (array $arguments): array => $this->editFormData($this->actionAsset($arguments)))
            ->schema($this->mediaEditSchema())
            ->action(function (array $data, array $arguments): void {
                $this->saveMetadata($this->actionAsset($arguments), $data);
            })
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label('Save')
                ->extraAttributes(['class' => 'media-dialog-footer__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cancel')
                ->extraAttributes(['class' => 'media-dialog-footer__cancel']))
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'media-file-dialog']);
    }

    public function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('Delete')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => 'Delete '.(string) $this->actionAsset($arguments)->getAttribute('original_filename').'?')
            ->modalContent(fn (array $arguments): View => view(
                'filament.resources.media-assets.partials.delete-dialog',
                $this->deleteDialogData($this->actionAsset($arguments)),
            ))
            ->modalSubmitAction(fn (Action $action, array $arguments): Action => $action
                ->label('Delete')
                ->extraAttributes(['class' => 'media-dialog-footer__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cancel')
                ->extraAttributes(['class' => 'media-dialog-footer__cancel']))
            ->action(function (Action $action, array $arguments): void {
                if (! $this->deleteAsset($this->actionAsset($arguments))) {
                    $action->halt();
                }
            })
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes(['class' => 'media-file-dialog']);
    }

    public function deleteSelectedAction(): Action
    {
        return Action::make('deleteSelected')
            ->label('Delete selected')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete selected files?')
            ->modalContent(fn (): View => view(
                'filament.resources.media-assets.partials.delete-selected-dialog',
                $this->deleteSelectedDialogData(),
            ))
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label('Delete')
                ->extraAttributes(['class' => 'media-dialog-footer__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cancel')
                ->extraAttributes(['class' => 'media-dialog-footer__cancel']))
            ->action(function (): void {
                $this->normalizeSelection();
                $ids = $this->selectedAssets;
                if ($ids === []) {
                    Notification::make()->title('No files selected')->warning()->send();

                    return;
                }

                /** @var EloquentCollection<int, MediaAsset> $records */
                $records = MediaAsset::query()->whereIn('id', $ids)->get();
                $recordsById = $records->keyBy(static fn (MediaAsset $asset): int => (int) $asset->getKey());
                $service = app(MediaAssetEditorialService::class);
                $deleted = 0;
                $failed = [];
                $remaining = [];

                foreach ($ids as $id) {
                    $asset = $recordsById->get($id);
                    if (! $asset instanceof MediaAsset) {
                        $failed[$id] = 'The file could not be found.';
                        $remaining[] = $id;

                        continue;
                    }

                    try {
                        $service->delete($asset);
                        $deleted++;
                    } catch (Throwable $exception) {
                        if (! $exception instanceof ValidationException) {
                            report($exception);
                        }

                        $fresh = $asset->fresh();
                        if ($fresh instanceof MediaAsset && $fresh->getAttribute('state') === 'deleted') {
                            $deleted++;
                            $failed[$id] = 'Stored file cleanup could not be completed.';

                            continue;
                        }

                        $failed[$id] = $exception instanceof ValidationException
                            ? $this->validationMessage($exception)
                            : 'The file could not be deleted.';
                        $remaining[] = $id;
                    }
                }

                $this->selectedAssets = $this->normalizeSelectedAssets($remaining);
                $this->loadLibrary();

                if ($failed !== []) {
                    $details = array_slice(array_values(array_unique($failed)), 0, 4);
                    if (count($failed) > 4) {
                        $details[] = '+'.(count($failed) - 4).' more';
                    }

                    Notification::make()
                        ->title('Some selected files need attention')
                        ->body($deleted.' deleted. '.implode(' ', $details))
                        ->warning()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Selected files deleted')
                    ->success()
                    ->send();
            })
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes(['class' => 'media-file-dialog']);
    }

    private function refreshFromFirstPage(): void
    {
        $this->page = 1;
        $this->loadLibrary();
    }

    private function loadLibrary(): void
    {
        $this->normalizeSelection();
        $this->pageSize = $this->normalizePageSize($this->pageSize);

        $catalog = app(MediaReferenceCatalog::class);
        $this->usageGroups = $catalog->destinationGroups();
        $this->loadLibraryMetrics($catalog);

        $query = $this->filteredQuery($catalog);
        $this->total = (clone $query)->count();

        $this->pages = max(1, (int) ceil($this->total / $this->pageSize));
        $this->page = min($this->page, $this->pages);

        $catalog->eagerLoad($query);

        /** @var EloquentCollection<int, MediaAsset> $records */
        $records = $this->orderResults($query)
            ->forPage($this->page, $this->pageSize)
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
            $available = $state !== 'deleted';

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
                'editable' => $available,
                'selectable' => $available,
                'deletable' => $available,
            ];
        })->all();
    }

    private function loadLibraryMetrics(MediaReferenceCatalog $catalog): void
    {
        $metrics = $catalog->libraryMetrics();

        $this->libraryFiles = $metrics['files'];
        $this->libraryImages = $metrics['images'];
        $this->libraryVideos = $metrics['videos'];
        $this->libraryAudio = $metrics['audio'];
        $this->libraryUnreferenced = $metrics['unreferenced'];
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
        $catalog->applyUsageFilter($query, $this->usage);

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
        if ($this->type === 'audio') {
            $query->whereIn('mime_type', MediaTypePolicy::AUDIO_MIME_TYPES);

            return;
        }
        if (in_array($this->type, MediaTypePolicy::acceptedMimeTypes(), true)) {
            $query->where('mime_type', $this->type);
        }
    }

    /**
     * @param  Builder<MediaAsset>  $query
     * @return Builder<MediaAsset>
     */
    private function orderResults(Builder $query): Builder
    {
        $query->orderByDesc('created_at');
        $query->orderByDesc('id');

        return $query;
    }

    /** @param array<string, mixed> $arguments */
    private function actionAsset(array $arguments): MediaAsset
    {
        $id = $arguments['asset'] ?? null;
        abort_unless(is_numeric($id), 404);

        return $this->assetById((int) $id);
    }

    private function assetById(int $assetId): MediaAsset
    {
        /** @var MediaAsset|null $asset */
        $asset = MediaAsset::query()->find($assetId);
        abort_unless($asset instanceof MediaAsset, 404);

        return $asset;
    }

    private function removeSelection(int $assetId): void
    {
        $this->normalizeSelection();
        $this->selectedAssets = $this->normalizeSelectedAssets(array_filter(
            $this->selectedAssets,
            static fn (int $selectedId): bool => $selectedId !== $assetId,
        ));
    }

    private function normalizeSelection(): void
    {
        $this->selectedAssets = $this->normalizeSelectedAssets($this->selectedAssets);
    }

    /**
     * @param  array<array-key, mixed>  $assetIds
     * @return list<int>
     */
    private function normalizeSelectedAssets(array $assetIds): array
    {
        $normalized = [];

        foreach ($assetIds as $assetId) {
            if (! is_numeric($assetId)) {
                continue;
            }

            $id = (int) $assetId;
            if ($id <= 0) {
                continue;
            }

            $normalized[$id] = $id;
        }

        return array_values($normalized);
    }

    private function normalizePageSize(mixed $value): int
    {
        $pageSize = is_numeric($value) ? (int) $value : self::DEFAULT_PAGE_SIZE;

        return in_array($pageSize, self::PAGE_SIZES, true) ? $pageSize : self::DEFAULT_PAGE_SIZE;
    }

    private function uploadFilename(TemporaryUploadedFile $upload): string
    {
        return basename(str_replace('\\', '/', $upload->getClientOriginalName()));
    }

    private function uploadValidationMessage(ValidationException $exception): string
    {
        $message = collect($exception->errors())->flatten()->first();

        return is_string($message) ? $message : 'The file could not be uploaded.';
    }

    private function directUploadSummary(int $total, int $added, int $duplicates, int $failed): string
    {
        if ($total === 1) {
            if ($added === 1) {
                return '1 file added to Media Files';
            }

            if ($duplicates === 1) {
                return 'Already in Media Files';
            }

            return '1 failed';
        }

        $parts = [];
        if ($added > 0) {
            $parts[] = $added.' '.($added === 1 ? 'file' : 'files').' added'.($duplicates === 0 && $failed === 0 ? ' to Media Files' : '');
        }
        if ($duplicates > 0) {
            $parts[] = $duplicates.' already in Media Files';
        }
        if ($failed > 0) {
            $parts[] = $failed.' failed';
        }

        return implode(' · ', $parts);
    }

    private function validationMessage(ValidationException $exception): string
    {
        $message = collect($exception->errors())->flatten()->first();

        return is_string($message) ? $message : 'The file could not be deleted.';
    }

    /** @return array<int, mixed> */
    private function mediaEditSchema(): array
    {
        return [
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
                ])
                ->columns(2),
            AdminForm::section('Copyright')
                ->schema([
                    Select::make('copyright_notice_mode')
                        ->label('Copyright notice')
                        ->options([
                            MediaAsset::COPYRIGHT_INHERIT => 'Inherit General default',
                            MediaAsset::COPYRIGHT_OVERRIDE => 'Use asset override',
                            MediaAsset::COPYRIGHT_NONE => 'No notice',
                        ])
                        ->required()
                        ->helperText('Inheritance is explicit. No notice suppresses the General default for this file.'),
                    Textarea::make('copyright_notice')
                        ->label('Asset copyright override')
                        ->maxLength(500)
                        ->nullable()
                        ->helperText('Used only when Copyright notice is set to Use asset override.')
                        ->columnSpanFull(),
                ]),
        ];
    }

    /** @return array<string, mixed> */
    private function editFormData(MediaAsset $asset): array
    {
        return [
            'alt_text' => $asset->getAttribute('alt_text'),
            'credit' => $asset->getAttribute('credit'),
            'copyright_notice_mode' => $asset->getAttribute('copyright_notice_mode') ?: MediaAsset::COPYRIGHT_INHERIT,
            'copyright_notice' => $asset->getAttribute('copyright_notice'),
        ];
    }

    /** @param array<string, mixed> $data */
    private function saveMetadata(MediaAsset $asset, array $data): void
    {
        app(MediaAssetEditorialService::class)->updateMetadata($asset, [
            'alt_text' => $data['alt_text'] ?? null,
            'credit' => $data['credit'] ?? null,
            'copyright_notice_mode' => $data['copyright_notice_mode'] ?? MediaAsset::COPYRIGHT_INHERIT,
            'copyright_notice' => $data['copyright_notice'] ?? null,
        ]);

        $this->loadLibrary();
        Notification::make()->title('File metadata saved')->success()->send();
    }

    /**
     * @param array<string, mixed> $arguments
     * @return list<Action>
     */
    private function previewFooterActions(array $arguments): array
    {
        $asset = $this->actionAsset($arguments);
        $assetId = (int) $asset->getKey();
        $state = (string) $asset->getAttribute('state');
        $actions = [];

        if ($state === 'available') {
            $actions[] = Action::make('previewDownload')
                ->label('Download')
                ->url(route('admin.media.download', ['mediaAsset' => $assetId]))
                ->extraAttributes(['class' => 'media-dialog-footer__utility']);
        }

        if ($state !== 'deleted') {
            $actions[] = $this->previewEditAction($assetId);
            $actions[] = $this->previewDeleteAction($assetId);
        }

        return $actions;
    }

    private function previewEditAction(int $assetId): Action
    {
        return Action::make('previewEdit')
            ->label('Edit')
            ->extraAttributes(['class' => 'media-dialog-footer__utility'])
            ->modalHeading(fn (): string => 'Edit '.(string) $this->assetById($assetId)->getAttribute('original_filename'))
            ->fillForm(fn (): array => $this->editFormData($this->assetById($assetId)))
            ->schema($this->mediaEditSchema())
            ->action(function (array $data) use ($assetId): void {
                $this->saveMetadata($this->assetById($assetId), $data);
            })
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label('Save')
                ->extraAttributes(['class' => 'media-dialog-footer__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cancel')
                ->cancelParentActions()
                ->extraAttributes(['class' => 'media-dialog-footer__cancel']))
            ->extraModalFooterActions([
                Action::make('backToPreview')
                    ->label('Back to preview')
                    ->extraAttributes(['class' => 'media-dialog-footer__utility'])
                    ->action(function (): void {})
                    ->cancelParentActions('previewEdit'),
            ])
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'media-file-dialog']);
    }

    private function previewDeleteAction(int $assetId): Action
    {
        return Action::make('previewDelete')
            ->label('Delete')
            ->color('danger')
            ->extraAttributes(['class' => 'media-dialog-footer__primary'])
            ->requiresConfirmation()
            ->modalHeading(fn (): string => 'Delete '.(string) $this->assetById($assetId)->getAttribute('original_filename').'?')
            ->modalContent(fn (): View => view(
                'filament.resources.media-assets.partials.delete-dialog',
                $this->deleteDialogData($this->assetById($assetId)),
            ))
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label('Delete')
                ->extraAttributes(['class' => 'media-dialog-footer__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cancel')
                ->extraAttributes(['class' => 'media-dialog-footer__cancel']))
            ->action(function (Action $action) use ($assetId): void {
                if (! $this->deleteAsset($this->assetById($assetId))) {
                    $action->halt();
                }
            })
            ->cancelParentActions()
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes(['class' => 'media-file-dialog']);
    }

    private function deleteAsset(MediaAsset $asset): bool
    {
        $assetId = (int) $asset->getKey();

        try {
            app(MediaAssetEditorialService::class)->delete($asset);
        } catch (Throwable $exception) {
            if (! $exception instanceof ValidationException) {
                report($exception);
            }

            $fresh = $asset->fresh();
            if ($fresh instanceof MediaAsset && $fresh->getAttribute('state') === 'deleted') {
                $this->removeSelection($assetId);
                $this->loadLibrary();
                Notification::make()
                    ->title('File cleanup failed')
                    ->body('The file was removed from Media Files, but stored file cleanup could not be completed.')
                    ->danger()
                    ->send();

                return true;
            }

            Notification::make()
                ->title('File not deleted')
                ->body($exception instanceof ValidationException
                    ? $this->validationMessage($exception)
                    : 'The file could not be deleted.')
                ->danger()
                ->send();

            return false;
        }

        $this->removeSelection($assetId);
        $this->loadLibrary();
        Notification::make()->title('File deleted')->success()->send();

        return true;
    }

    /** @return array{references:list<array{type:string,label:string,url:?string}>} */
    private function deleteDialogData(MediaAsset $asset): array
    {
        return ['references' => $this->assetReferences($asset)];
    }

    private function hasReferences(MediaAsset $asset): bool
    {
        return $this->assetReferences($asset) !== [];
    }

    /** @return list<array{type:string,label:string,url:?string}> */
    private function assetReferences(MediaAsset $asset): array
    {
        $catalog = app(MediaReferenceCatalog::class);
        $catalog->loadAssetReferences($asset);

        return $catalog->references($asset);
    }

    /** @return array{selectedCount:int,referencedFileCount:int,referenceCount:int,references:list<array{filename:string,type:string,label:string}>} */
    private function deleteSelectedDialogData(): array
    {
        $ids = $this->normalizeSelectedAssets($this->selectedAssets);
        /** @var EloquentCollection<int, MediaAsset> $records */
        $records = MediaAsset::query()->whereIn('id', $ids)->get();
        $catalog = app(MediaReferenceCatalog::class);
        $referencedFileCount = 0;
        $references = [];

        foreach ($records as $asset) {
            $catalog->loadAssetReferences($asset);
            $assetReferences = $catalog->references($asset);
            if ($assetReferences !== []) {
                $referencedFileCount++;
            }

            foreach ($assetReferences as $reference) {
                $references[] = [
                    'filename' => (string) $asset->getAttribute('original_filename'),
                    'type' => $reference['type'],
                    'label' => $reference['label'],
                ];
            }
        }

        return [
            'selectedCount' => $records->count(),
            'referencedFileCount' => $referencedFileCount,
            'referenceCount' => count($references),
            'references' => $references,
        ];
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
                'copyright_notice' => (string) ($asset->effectiveCopyrightNotice() ?? ''),
                'copyright_source' => $asset->copyrightNoticeSourceLabel(),
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
        if ($bytes < 1024 * 1024 * 1024 * 1024) {
            return number_format($bytes / (1024 * 1024 * 1024), 2).' GB';
        }

        return number_format($bytes / (1024 * 1024 * 1024 * 1024), 2).' TB';
    }
}
