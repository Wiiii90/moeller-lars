<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminMediaUploadFeedback;
use App\Domain\Admin\AdminNotifier;
use App\Domain\Artwork\ArtworkDraftService;
use App\Domain\Artwork\ArtworkPrimaryMediaService;
use App\Domain\Media\MediaAssetEditorialService;
use App\Domain\Artwork\ArtworkSort;
use App\Models\ArtworkCategory;
use App\Domain\Media\MediaTypePolicy;
use App\Models\Artwork;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait GalleryWorkspaceDirectUpload
{
    /** Report client transfer errors that never reach the ingest action. */
    public function notifyDirectPrimaryTransferFailure(string $message): void
    {
        app(AdminMediaUploadFeedback::class)->failure($message);
    }

    /** Each transfer appends to the unfinished queue without replacing edits. */
    public function beginDirectPrimaryMediaBatch(): void
    {
        $this->rememberStagedArtworkChanges();
        $this->directPrimaryBatchStartCount = count($this->pendingBatchArtworkMedia);
    }

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
        $pendingIds = array_map('intval', array_column($this->pendingBatchArtworkMedia, 'media_asset_id'));
        if (in_array($assetId, $pendingIds, true)) {
            return;
        }

        $this->pendingBatchArtworkMedia[] = $this->pendingBatchArtworkRow($asset);
    }

    /**
     * Finalize a multi-file queue after each file was transferred and ingested
     * in its own bounded request. The existing Artwork dialogs remain the owner.
     *
     * @param  list<string>  $errors
     * @return array{summary:string,added:int,failed:int,duplicates:int}
     */
    public function finishDirectPrimaryMediaBatch(int $failed = 0, int $duplicates = 0, array $errors = []): array
    {
        $failed = max(0, min(10000, $failed));
        $duplicates = max(0, min(10000, $duplicates));
        $rows = $this->pendingBatchArtworkMedia;
        $added = max(0, count($rows) - $this->directPrimaryBatchStartCount);
        $this->directPrimaryBatchStartCount = count($rows);
        $summary = app(AdminMediaUploadFeedback::class)->batch(
            $added, $duplicates, $failed, $errors, 'gallery',
        );

        if ($added > 0) {
            $this->selectStagedArtwork((int) $rows[array_key_last($rows)]['media_asset_id']);
        }

        return ['summary' => $summary, 'added' => $added, 'failed' => $failed, 'duplicates' => $duplicates];
    }

    /** Remember unsaved editorial state against one uploaded Storage asset. */
    private function rememberStagedArtworkChanges(): void
    {
        if ($this->selectedStagedMediaAssetId === null || ! is_array($this->stagedArtworkData)) {
            return;
        }

        foreach ($this->pendingBatchArtworkMedia as &$row) {
            if ((int) $row['media_asset_id'] === $this->selectedStagedMediaAssetId) {
                $row['form'] = $this->stagedArtworkData;
                break;
            }
        }
        unset($row);
    }

    public function selectStagedArtwork(int $assetId): void
    {
        $selected = null;
        foreach ($this->pendingBatchArtworkMedia as $row) {
            if ((int) $row['media_asset_id'] === $assetId) {
                $selected = $row;
                break;
            }
        }
        if ($selected === null) {
            return;
        }

        $this->rememberStagedArtworkChanges();
        $this->selectedStagedMediaAssetId = $assetId;
        $this->galleryStagePanel = 'queue';
        $this->galleryStagePreview = 'staged';
        $this->galleryStagePreviewArtworkId = null;
        $gallery = ArtworkCategory::query()->findOrFail((int) $this->galleryContext['id']);
        $form = is_array($selected['form'] ?? null) ? $selected['form'] : [];
        if ($form === []) {
            $form = [
                'title' => (string) $selected['title'],
                'slug' => '',
                'primary_media_asset_id' => $assetId,
                'primary_alt_text' => null,
                'artwork_date' => null,
                'medium' => null,
                'description' => null,
                'position' => ArtworkSort::suggestedAdditionPosition($gallery, null, $assetId),
                'position_explicit' => false,
            ];
        }
        // The queue row, not a disabled form control, owns the media link.
        $form['primary_media_asset_id'] = $assetId;
        $this->stagedArtworkData = $form;
        $this->stagedArtworkForm->fill($form);
        $this->publishStagedArtworkPreview();
    }

    public function removeStagedArtwork(int $assetId): void
    {
        $this->rememberStagedArtworkChanges();
        $this->pendingBatchArtworkMedia = array_values(array_filter(
            $this->pendingBatchArtworkMedia,
            static fn (array $row): bool => (int) $row['media_asset_id'] !== $assetId,
        ));
        if ($this->selectedStagedMediaAssetId === $assetId) {
            $this->selectedStagedMediaAssetId = null;
            $this->stagedArtworkData = [];
            if ($this->pendingBatchArtworkMedia !== []) {
                $this->selectStagedArtwork((int) $this->pendingBatchArtworkMedia[0]['media_asset_id']);
            } else {
                $this->galleryStagePanel = 'settings';
                $this->forgetStagedArtworkPreview();
                $this->previewGalleryStage();
            }
        }

        app(AdminNotifier::class)->notification(
            title: 'Media removed from Stage',
            body: 'The uploaded file remains available in Storage.',
            status: 'success',
        );
    }

    /** The single and batch commands both delegate to the canonical Artwork owners. */
    private function createStagedArtwork(array $row, array $data): void
    {
        $assetId = (int) $row['media_asset_id'];
        $asset = MediaAsset::query()->whereKey($assetId)->where('state', 'available')->first();
        if (! $asset instanceof MediaAsset || ! $this->isGalleryPrimaryVisual($asset)) {
            throw ValidationException::withMessages([
                'stagedArtworkData.primary_media_asset_id' => 'The uploaded Media File is no longer available.',
            ]);
        }

         $explicit = (bool) ($data['position_explicit'] ?? false);
        $payload = $this->normalizeArtworkFormData($data);
        if (! $explicit) {
            unset($payload['position']);
        }
        unset($payload['position_explicit']);
        $alt = filled($data['primary_alt_text'] ?? null) ? (string) $data['primary_alt_text'] : null;

        $artwork = app(ArtworkDraftService::class)->create($payload, $assetId);
        app(ArtworkPrimaryMediaService::class)->attachAsset($artwork, $asset);
        if ($alt !== null) {
            app(MediaAssetEditorialService::class)->updatePrimaryAltOverride($artwork, $alt);
        }
    }

    public function createSelectedStagedArtwork(): void
    {
        $id = $this->selectedStagedMediaAssetId;
        if ($id === null) {
            return;
        }
        $selected = collect($this->pendingBatchArtworkMedia)
            ->first(static fn (array $row): bool => (int) $row['media_asset_id'] === $id);
        if (! is_array($selected)) {
            return;
        }

        // Filament validates exactly the same schema as standalone Add Artwork.
        $state = $this->stagedArtworkForm->getState();
        try {
            DB::transaction(function () use ($selected, $state): void {
                $this->createStagedArtwork($selected, $state);
            });
        } catch (ValidationException $exception) {
            // Failure rolls back Artwork creation, media links and audit.
            // The queued item and its current editorial form remain intact.
            $this->notifyValidationFailure('Staged Artwork not created', $exception);

            return;
        }
        $this->forgetCompletedStagedArtworks([$id]);
        app(AdminNotifier::class)->notification(
            title: 'Artwork draft created',
            status: 'success',
        );
    }

    /** Entire batch is atomic; a validation failure leaves the queue intact. */
    public function createAllStagedArtworks(): void
    {
        if ($this->pendingBatchArtworkMedia === []) {
            return;
        }
        $selected = $this->selectedStagedMediaAssetId;
        $selectedState = $selected !== null ? $this->stagedArtworkForm->getState() : null;
        $this->rememberStagedArtworkChanges();
        $items = $this->pendingBatchArtworkMedia;
        foreach ($items as &$row) {
            if ((int) $row['media_asset_id'] === $selected && is_array($selectedState)) {
                $row['form'] = $selectedState;
            }
        }
        unset($row);

        // New-first insertion reverses a batch; process in reverse so the
        // final Manual sequence preserves the artist's staged queue order.
        $gallery = ArtworkCategory::query()->findOrFail((int) $this->galleryContext['id']);
        $placement = \App\Domain\Artwork\GalleryPresentation::addPlacement($gallery);
        if ($placement === \App\Domain\Artwork\GalleryPresentation::ADD_FIRST
            || ($placement === \App\Domain\Artwork\GalleryPresentation::ADD_FOLLOW
                && \App\Domain\Artwork\GalleryPresentation::sortMode($gallery) === \App\Domain\Artwork\GalleryPresentation::SORT_MANUAL)) {
            $items = array_reverse($items);
        }

        try {
            DB::transaction(function () use ($items): void {
                foreach ($items as $row) {
                    $data = is_array($row['form'] ?? null) ? $row['form'] : [];
                    if ($data === []) {
                        $data = [
                            'title' => $row['title'],
                            'slug' => '',
                            'primary_media_asset_id' => $row['media_asset_id'],
                            'position_explicit' => false,
                        ];
                    }
                    try {
                        $this->createStagedArtwork($row, $data);
                    } catch (ValidationException $exception) {
                        $detail = collect($exception->errors())->flatten()->first();

                        throw ValidationException::withMessages([
                            'artworks' => (string) $row['title'].': '.
                                (is_string($detail) ? $detail : 'This Artwork is not valid.'),
                        ]);
                    }
                }
            });
        } catch (ValidationException $exception) {
            // All-or-nothing: no queue item disappears after a failed batch.
            $this->notifyValidationFailure('No staged Artworks were created', $exception);

            return;
        }
        $count = count($items);
        $this->forgetCompletedStagedArtworks(array_map(
            static fn (array $row): int => (int) $row['media_asset_id'], $items,
        ));
        app(AdminNotifier::class)->notification(title: $count.' Artwork drafts created', status: 'success');
    }

    /** Uploaded originals always remain in Storage when staging ends. */
    private function forgetCompletedStagedArtworks(array $assetIds): void
    {
        $this->pendingBatchArtworkMedia = array_values(array_filter(
            $this->pendingBatchArtworkMedia,
            static fn (array $row): bool => ! in_array((int) $row['media_asset_id'], $assetIds, true),
        ));
        $this->selectedStagedMediaAssetId = null;
        $this->stagedArtworkData = [];
        $this->refreshWorkspaceAfterMutation();
        if ($this->pendingBatchArtworkMedia === []) {
            $this->galleryStagePanel = 'settings';
            $this->forgetStagedArtworkPreview();
            $this->previewGalleryStage();
        } else {
            $this->selectStagedArtwork((int) $this->pendingBatchArtworkMedia[0]['media_asset_id']);
        }
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
            'form' => [],
        ];
    }

    private function defaultArtworkTitle(string $filename): string
    {
        $title = trim((string) pathinfo($filename, PATHINFO_FILENAME));

        return $title === '' ? 'Untitled' : $title;
    }

}
