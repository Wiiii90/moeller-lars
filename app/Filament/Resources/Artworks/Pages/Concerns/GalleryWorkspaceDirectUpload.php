<?php

namespace App\Filament\Resources\Artworks\Pages\Concerns;

use App\Domain\Artwork\ArtworkDraftService;
use App\Domain\Artwork\ArtworkPrimaryMediaService;
use App\Domain\Media\MediaAssetEditorialService;
use App\Domain\Media\MediaIngestService;
use App\Domain\Media\MediaTypePolicy;
use App\Models\Artwork;
use App\Models\MediaAsset;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

trait GalleryWorkspaceDirectUpload
{
    /** @return array{summary:string,added:int,duplicates:int,failed:int} */
    public function processDirectPrimaryMedia(): array
    {
        $uploads = array_values(array_filter(
            $this->directPrimaryMedia,
            static fn (mixed $upload): bool => $upload instanceof TemporaryUploadedFile,
        ));
        $this->resetErrorBag('directPrimaryMedia');
        $this->pendingPrimaryMediaAssetId = null;
        $this->pendingBatchArtworkMedia = [];

        if ($uploads === []) {
            $this->reset('directPrimaryMedia');

            return [
                'summary' => 'Upload failed',
                'added' => 0,
                'duplicates' => 0,
                'failed' => 1,
            ];
        }

        $ingest = app(MediaIngestService::class);
        $addedAssets = [];
        $duplicates = 0;
        $failures = [];

        foreach ($uploads as $upload) {
            try {
                $result = $ingest->ingestUnique($upload);
                /** @var MediaAsset $asset */
                $asset = $result['asset'];
                $duplicate = (bool) $result['duplicate'];

                if (! $this->isGalleryPrimaryVisual($asset)) {
                    if (! $duplicate) {
                        app(MediaAssetEditorialService::class)->delete($asset);
                    }

                    $failures[] = [
                        'filename' => $this->directUploadFilename($upload),
                        'message' => 'Gallery direct upload supports images and videos only.',
                    ];

                    continue;
                }

                if ($duplicate) {
                    $duplicates++;

                    continue;
                }

                $addedAssets[] = $asset;
            } catch (ValidationException $exception) {
                $failures[] = [
                    'filename' => $this->directUploadFilename($upload),
                    'message' => $this->firstValidationMessage($exception),
                ];
            } catch (Throwable $exception) {
                report($exception);
                $failures[] = [
                    'filename' => $this->directUploadFilename($upload),
                    'message' => 'The file could not be uploaded.',
                ];
            }
        }

        $added = count($addedAssets);
        $failed = count($failures);
        $total = count($uploads);
        $summary = $this->directUploadSummary($total, $added, $duplicates, $failed);
        $this->reset('directPrimaryMedia');

        $this->notifyDirectUploadResult($summary, $added, $duplicates, $failures);

        if ($added === 1) {
            $asset = $addedAssets[0];
            $this->pendingPrimaryMediaAssetId = (int) $asset->getKey();
            $this->mountAction('addArtwork');
        } elseif ($added > 1) {
            $this->pendingBatchArtworkMedia = array_map(
                fn (MediaAsset $asset): array => $this->pendingBatchArtworkRow($asset),
                $addedAssets,
            );
            $this->mountAction('batchAddArtworks');
        }

        return [
            'summary' => $summary,
            'added' => $added,
            'duplicates' => $duplicates,
            'failed' => $failed,
        ];
    }

    public function batchAddArtworksAction(): Action
    {
        return Action::make('batchAddArtworks')
            ->label('Add artworks')
            ->modalHeading(fn (): string => 'Add '.count($this->pendingBatchArtworkMedia).' artworks')
            ->fillForm(fn (): array => ['artworks' => $this->pendingBatchArtworkMedia])
            ->schema([
                Repeater::make('artworks')
                    ->label('New artwork drafts')
                    ->extraAttributes(['class' => 'gallery-batch-artworks'])
                    ->schema([
                        Hidden::make('media_asset_id'),
                        Placeholder::make('visual')
                            ->label('Media')
                            ->content(fn (callable $get): HtmlString|string => $this->batchArtworkVisual((int) $get('media_asset_id'))),
                        Placeholder::make('filename')
                            ->label('File')
                            ->content(fn (callable $get): string => $this->batchArtworkFilename((int) $get('media_asset_id'))),
                        TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->maxLength(240),
                    ])
                    ->columns(3)
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false),
            ])
            ->modalSubmitActionLabel(fn (): string => 'Add '.count($this->pendingBatchArtworkMedia).' artworks')
            ->modalCancelActionLabel('Cancel')
            ->modalWidth(Width::SevenExtraLarge)
            ->action(function (array $data): void {
                $rows = is_array($data['artworks'] ?? null) ? array_values($data['artworks']) : [];
                $pendingIds = collect($this->pendingBatchArtworkMedia)
                    ->pluck('media_asset_id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->sort()
                    ->values()
                    ->all();
                $submittedIds = collect($rows)
                    ->map(static fn (mixed $row): int => is_array($row) ? (int) ($row['media_asset_id'] ?? 0) : 0)
                    ->filter(static fn (int $id): bool => $id > 0)
                    ->sort()
                    ->values()
                    ->all();

                if ($pendingIds === [] || $submittedIds !== $pendingIds || count($rows) !== count($pendingIds)) {
                    throw ValidationException::withMessages([
                        'artworks' => 'The uploaded media selection changed. Review the batch and try again.',
                    ]);
                }

                $galleryId = (int) $this->galleryContext['id'];
                $assets = MediaAsset::query()
                    ->whereIn('id', $pendingIds)
                    ->where('state', 'available')
                    ->get()
                    ->keyBy(static fn (MediaAsset $asset): int => (int) $asset->getKey());

                if ($assets->count() !== count($pendingIds)) {
                    throw ValidationException::withMessages([
                        'artworks' => 'One or more uploaded Media Files are no longer available.',
                    ]);
                }

                DB::transaction(function () use ($rows, $assets, $galleryId): void {
                    foreach ($rows as $row) {
                        if (! is_array($row)) {
                            continue;
                        }

                        $assetId = (int) ($row['media_asset_id'] ?? 0);
                        $title = preg_replace('/\s+/u', ' ', trim((string) ($row['title'] ?? '')));
                        $title = is_string($title) ? $title : trim((string) ($row['title'] ?? ''));
                        if ($title === '') {
                            throw ValidationException::withMessages(['artworks' => 'Each uploaded file needs an artwork title.']);
                        }

                        $asset = $assets->get($assetId);
                        if (! $asset instanceof MediaAsset || ! $this->isGalleryPrimaryVisual($asset)) {
                            throw ValidationException::withMessages(['artworks' => 'Each artwork requires an available image or video Media File.']);
                        }

                        $artwork = app(ArtworkDraftService::class)->create([
                            'artwork_category_id' => $galleryId,
                            'slug' => $this->uniqueBatchArtworkSlug($title),
                            'title' => $title,
                            'work_date' => null,
                        ]);
                        app(ArtworkPrimaryMediaService::class)->attachAsset($artwork, $asset);
                    }
                });

                $count = count($rows);
                $this->pendingBatchArtworkMedia = [];
                $this->refreshWorkspaceAfterMutation();
                Notification::make()
                    ->title($count.' artworks added')
                    ->body('The new artworks were created as drafts with their uploaded Media Files as primary media.')
                    ->success()
                    ->send();
            });
    }

    private function isGalleryPrimaryVisual(MediaAsset $asset): bool
    {
        $mime = (string) $asset->getAttribute('mime_type');

        return MediaTypePolicy::isImage($mime) || MediaTypePolicy::isVideo($mime);
    }

    /** @return array{media_asset_id:int,title:string} */
    private function pendingBatchArtworkRow(MediaAsset $asset): array
    {
        return [
            'media_asset_id' => (int) $asset->getKey(),
            'title' => $this->defaultArtworkTitle((string) $asset->getAttribute('original_filename')),
        ];
    }

    private function defaultArtworkTitle(string $filename): string
    {
        $title = trim((string) pathinfo($filename, PATHINFO_FILENAME));

        return $title === '' ? 'Untitled' : $title;
    }

    private function batchArtworkFilename(int $assetId): string
    {
        $asset = MediaAsset::query()->find($assetId);

        return $asset instanceof MediaAsset ? (string) $asset->getAttribute('original_filename') : 'Unavailable Media File';
    }

    private function batchArtworkVisual(int $assetId): HtmlString|string
    {
        /** @var MediaAsset|null $asset */
        $asset = MediaAsset::query()->with('variants')->find($assetId);
        if (! $asset instanceof MediaAsset || $asset->getAttribute('state') !== 'available') {
            return 'Media unavailable';
        }

        $mime = (string) $asset->getAttribute('mime_type');
        $filename = e((string) $asset->getAttribute('original_filename'));
        if (MediaTypePolicy::isImage($mime)) {
            $variant = $asset->getRelation('variants')->first(static fn ($candidate): bool => $candidate->getAttribute('variant_kind') === MediaIngestService::THUMBNAIL_KIND
                && $candidate->getAttribute('transform_profile') === MediaIngestService::TRANSFORM_PROFILE
                && $candidate->getAttribute('state') === 'available');
            $url = $variant === null
                ? route('admin.media.original', $asset)
                : route('admin.media.variant', $variant);

            return new HtmlString('<div class="admin-component-image-preview"><img src="'.e($url).'" alt="" loading="lazy" decoding="async"><span>'.$filename.'</span></div>');
        }

        if (MediaTypePolicy::isVideo($mime)) {
            $url = route('admin.media.original', $asset);

            return new HtmlString('<div class="admin-component-image-preview"><video src="'.e($url).'" width="176" height="132" preload="metadata" muted playsinline aria-label="Video preview for '.$filename.'"></video><span>'.$filename.'</span></div>');
        }

        return 'Unsupported Media File';
    }

    private function uniqueBatchArtworkSlug(string $title): string
    {
        $base = Str::slug($title);
        $base = $base === '' ? 'artwork' : mb_substr($base, 0, 160);
        $slug = $base;
        $suffix = 2;

        while (Artwork::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /** @param list<array{filename:string,message:string}> $failures */
    private function notifyDirectUploadResult(string $summary, int $added, int $duplicates, array $failures): void
    {
        if ($failures !== []) {
            $details = array_map(
                static fn (array $failure): string => $failure['filename'].' — '.$failure['message'],
                array_slice($failures, 0, 4),
            );
            if (count($failures) > 4) {
                $details[] = '+'.(count($failures) - 4).' more';
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

            return;
        }

        if ($added > 0) {
            Notification::make()->title($added === 1 ? 'File uploaded' : 'Files uploaded')->body($summary)->success()->send();

            return;
        }

        if ($duplicates > 0) {
            Notification::make()->title('Already in Media Files')->body($summary)->info()->send();
        }
    }

    private function directUploadSummary(int $total, int $added, int $duplicates, int $failed): string
    {
        if ($added > 0 && $duplicates === 0 && $failed === 0) {
            return $added.' '.($added === 1 ? 'file' : 'files').' added to Media Files';
        }

        if ($added === 0 && $duplicates > 0 && $failed === 0) {
            return $duplicates.' '.($duplicates === 1 ? 'file is' : 'files are').' already in Media Files';
        }

        $parts = [];
        if ($added > 0) {
            $parts[] = $added.' '.($added === 1 ? 'file added' : 'files added');
        }
        if ($duplicates > 0) {
            $parts[] = $duplicates.' already in Media Files';
        }
        if ($failed > 0) {
            $parts[] = $failed.' failed';
        }

        return $parts === [] ? ($total === 1 ? 'Upload failed' : 'Uploads failed') : implode(' · ', $parts);
    }

    private function directUploadFilename(TemporaryUploadedFile $upload): string
    {
        $filename = str_replace('\\', '/', $upload->getClientOriginalName());
        $basename = basename($filename);

        return $basename === '' ? 'upload' : $basename;
    }
}
