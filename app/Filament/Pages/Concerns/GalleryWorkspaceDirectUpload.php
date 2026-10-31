<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Artwork\ArtworkDraftService;
use App\Domain\Artwork\ArtworkPrimaryMediaService;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Models\Artwork;
use App\Models\MediaAsset;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

trait GalleryWorkspaceDirectUpload
{
    /** Report client transfer errors that never reach the ingest action. */
    public function notifyDirectPrimaryTransferFailure(string $message): void
    {
        app(AdminNotifier::class)->notification(
            title: 'Upload failed',
            body: mb_substr($message, 0, 400),
            status: 'danger',
        );
    }

    /** Begin a selected stack without retaining stale assets from an earlier upload. */
    public function beginDirectPrimaryMediaBatch(): void
    {
        $this->pendingPrimaryMediaAssetId = null;
        $this->pendingBatchArtworkMedia = [];
    }

    /** @return array{summary:string,added:int,duplicates:int,failed:int,errors:list<string>} */
    /**
     * Queue the canonical available asset returned by the shared resumable
     * ingest endpoint. Only images/videos can become Artwork primary media.
     */
    public function queueResumablePrimaryMediaAsset(int $assetId): void
    {
        $asset = MediaAsset::query()->whereKey($assetId)->where('state', 'available')->first();
        if (! $asset instanceof MediaAsset || ! $this->isGalleryPrimaryVisual($asset)) {
            throw ValidationException::withMessages(['media' => 'This media file is not a valid Artwork image or video.']);
        }
        foreach ($this->pendingBatchArtworkMedia as $row) {
            if ((int) ($row['media_asset_id'] ?? 0) === $assetId) {
                return;
            }
        }
        $this->pendingBatchArtworkMedia[] = $this->pendingBatchArtworkRow($asset);
    }

    /**
     * Finalize a multi-file queue after each file was transferred and ingested
     * in its own bounded request. The existing Artwork dialogs remain the owner.
     *
     * @param list<string> $errors
     * @return array{summary:string,added:int,failed:int,duplicates:int}
     */
    public function finishDirectPrimaryMediaBatch(int $failed = 0, int $duplicates = 0, array $errors = []): array
    {
        $failed = max(0, min(10000, $failed));
        $duplicates = max(0, min(10000, $duplicates));
        $rows = $this->pendingBatchArtworkMedia;
        $added = count($rows);
        $total = $added + $failed;
        $summary = $this->directUploadSummary($total, $added, $duplicates, $failed);
        $details = [];
        foreach (array_slice($errors, 0, 4) as $message) {
            if (is_string($message) && trim($message) !== '') {
                $details[] = ['filename' => 'File', 'message' => mb_substr($message, 0, 240)];
            }
        }
        if ($failed > count($details)) {
            $details[] = ['filename' => 'Files', 'message' => ($failed - count($details)).' further failures'];
        }

        $this->notifyDirectUploadResult($summary, $added, $duplicates, $details);

        if ($added === 1) {
            $this->pendingPrimaryMediaAssetId = (int) $rows[0]['media_asset_id'];
            $this->pendingBatchArtworkMedia = [];
            $this->mountAction('addArtwork');
        } elseif ($added > 1) {
            $this->mountAction('batchAddArtworks');
        }

        return ['summary' => $summary, 'added' => $added, 'failed' => $failed, 'duplicates' => $duplicates];
    }

    public function batchAddArtworksAction(): Action
    {
        return AdminDialog::create(
            Action::make('batchAddArtworks')
                ->label('Add artworks')
                ->modalHeading('Add artworks')
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
                            Textarea::make('primary_alt_text')
                                ->label('Primary media ALT text')
                                ->rows(2)
                                ->maxLength(500)
                                ->columnSpanFull()
                                ->helperText('Describe the image or video for screen readers. Required to publish unless the Media File already has ALT text.'),
                        ])
                        ->columns(3)
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false),
                ])
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
                            'artworks' => 'One or more uploaded files in Storage are no longer available.',
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
                            if (filled($row['primary_alt_text'] ?? null)) {
                                app(MediaAssetEditorialService::class)->updatePrimaryAltOverride($artwork, (string) $row['primary_alt_text']);
                            }
                        }
                    });

                    $count = count($rows);
                    $this->pendingBatchArtworkMedia = [];
                    $this->refreshWorkspaceAfterMutation();
                    app(AdminNotifier::class)->notification(
                        title: $count.' artworks added',
                        body: 'The new artworks were created as drafts with their uploaded files from Storage as primary media.',
                        status: 'success',
                    );
                }),
            'Add artworks',
            AdminDialogSize::Large,
        );
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
            $variant = \App\Domain\Media\MediaPreviewVariant::imageThumbnail($asset);
            $url = $variant === null
                ? route('admin.media.original', $asset)
                : route('admin.media.variant', $variant);

            return new HtmlString('<div class="admin-component-image-preview"><img src="'.e($url).'" alt="" loading="lazy" decoding="async"><span>'.$filename.'</span></div>');
        }

        if (MediaTypePolicy::isVideo($mime)) {
            $url = route('admin.media.original', $asset);
            $poster = \App\Domain\Media\MediaPreviewVariant::videoPoster($asset);
            $posterAttribute = $poster !== null
                ? ' poster="'.e(route('admin.media.variant', $poster)).'"'
                : '';

            return new HtmlString('<div class="admin-component-image-preview"><video src="'.e($url).'"'.$posterAttribute.' width="176" height="132" preload="metadata" muted playsinline aria-label="Video preview for '.$filename.'"></video><span>'.$filename.'</span></div>');
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

            app(AdminNotifier::class)->notification(
                title: ($added + $duplicates) > 0 ? 'Upload completed with issues' : 'Upload failed',
                body: implode("\n", $details),
                status: ($added + $duplicates) > 0 ? 'warning' : 'danger',
            );

            return;
        }

        if ($added > 0) {
            app(AdminNotifier::class)->notification(
                title: $added === 1 ? 'Media ready for Artwork' : 'Media ready for Artworks',
                body: $summary,
                status: 'success',
            );

            return;
        }

        if ($duplicates > 0) {
            app(AdminNotifier::class)->notification(
                title: 'Already in Storage',
                body: $summary,
                status: 'info',
            );
        }
    }

    private function directUploadSummary(int $total, int $ready, int $reused, int $failed): string
    {
        if ($ready === 0) {
            return $failed > 0
                ? ($total === 1 ? 'Upload failed' : 'Uploads failed')
                : 'No new Artwork media selected';
        }

        $parts = [
            $ready.' '.($ready === 1 ? 'Media File is' : 'Media Files are').' ready for Artwork creation',
        ];
        if ($reused > 0) {
            $parts[] = $reused.' '.($reused === 1 ? 'existing original reused' : 'existing originals reused');
        }
        if ($failed > 0) {
            $parts[] = $failed.' failed';
        }

        return implode(' · ', $parts);
    }

}
