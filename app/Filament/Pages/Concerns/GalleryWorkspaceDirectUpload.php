<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminMediaUploadFeedback;
use App\Domain\Admin\AdminNotifier;
use App\Domain\Artwork\ArtworkDraftService;
use App\Domain\Artwork\ArtworkPublicationService;
use App\Domain\Artwork\ArtworkPrimaryMediaService;
use App\Models\ArtworkCategory;
use App\Domain\Media\MediaTypePolicy;
use App\Domain\Media\MediaAssetEditorialService;
use App\Domain\Media\MediaDeletionOptions;
use App\Domain\Media\MediaReferenceQuery;
use App\Models\Artwork;
use App\Models\MediaAsset;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

trait GalleryWorkspaceDirectUpload
{
    /** One authenticated browser-session queue registry, partitioned by Gallery. */
    private function stagedArtworkQueueSessionKey(): string
    {
        return 'gallery.staged-artwork-queues.'.(int) Filament::auth()->id();
    }

    /**
     * Restore only still-available primary media from the existing Storage
     * catalog. The session stores no file bytes or second Artwork identity.
     */
    private function restoreStagedArtworkQueue(): void
    {
        $queues = session()->get($this->stagedArtworkQueueSessionKey(), []);
        $galleryId = (int) ($this->galleryContext['id'] ?? 0);
        $snapshot = is_array($queues) ? ($queues[$galleryId] ?? null) : null;
        $savedItems = is_array($snapshot) ? ($snapshot['items'] ?? null) : null;
        if (! is_array($savedItems) || $savedItems === []) {
            return;
        }

        $ids = [];
        foreach ($savedItems as $saved) {
            if (is_array($saved) && is_numeric($saved['media_asset_id'] ?? null)) {
                $id = (int) $saved['media_asset_id'];
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }
        if ($ids === []) {
            return;
        }

        $available = MediaAsset::query()
            ->whereIn('id', array_values(array_unique($ids)))
            ->where('state', 'available')
            ->get()
            ->keyBy('id');

        $restored = [];
        $seen = [];
        foreach ($savedItems as $saved) {
            if (! is_array($saved) || ! is_numeric($saved['media_asset_id'] ?? null)) {
                continue;
            }
            $id = (int) $saved['media_asset_id'];
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            /** @var MediaAsset|null $asset */
            $asset = $available->get($id);
            if (! $asset instanceof MediaAsset || ! $this->isGalleryPrimaryVisual($asset)) {
                continue;
            }
            $seen[$id] = true;
            // Missing provenance is conservatively treated as reused: an
            // unknown older receipt must never authorize Storage deletion.
            $row = $this->pendingBatchArtworkRow($asset, ($saved['reused'] ?? true) !== false);
            $row['form'] = is_array($saved['form'] ?? null) ? $saved['form'] : [];
            $restored[] = $row;
        }

        $this->pendingBatchArtworkMedia = $restored;
        if ($restored === []) {
            return;
        }

        $selected = is_numeric($snapshot['selected'] ?? null)
            ? (int) $snapshot['selected'] : 0;
        $ids = array_column($restored, 'media_asset_id');
        if (! in_array($selected, $ids, true)) {
            $selected = (int) $ids[0];
        }
        $this->selectStagedArtwork($selected);
    }

    /** Persist the current queue as a small projection of canonical media IDs. */
    private function persistStagedArtworkQueue(): void
    {
        $key = $this->stagedArtworkQueueSessionKey();
        $queues = session()->get($key, []);
        $queues = is_array($queues) ? $queues : [];
        $galleryId = (int) ($this->galleryContext['id'] ?? 0);

        if ($this->pendingBatchArtworkMedia === []) {
            unset($queues[$galleryId]);
        } else {
            $queues[$galleryId] = [
                'selected' => $this->selectedStagedMediaAssetId,
                'items' => array_map(
                    static fn (array $row): array => [
                        'media_asset_id' => (int) $row['media_asset_id'],
                        'reused' => ($row['reused'] ?? true) !== false,
                        'form' => is_array($row['form'] ?? null) ? $row['form'] : [],
                    ],
                    $this->pendingBatchArtworkMedia,
                ),
            ];
        }

        if ($queues === []) {
            session()->forget($key);
        } else {
            session()->put($key, $queues);
        }
    }

    /** A file staged in another Gallery in this session still has a consumer. */
    private function stagedInAnotherGallery(int $assetId): bool
    {
        $queues = session()->get($this->stagedArtworkQueueSessionKey(), []);
        if (! is_array($queues)) {
            return false;
        }
        $currentGallery = (int) ($this->galleryContext['id'] ?? 0);
        foreach ($queues as $galleryId => $snapshot) {
            if ((int) $galleryId === $currentGallery || ! is_array($snapshot)) {
                continue;
            }
            $items = $snapshot['items'] ?? null;
            if (! is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                if (is_array($item) && (int) ($item['media_asset_id'] ?? 0) === $assetId) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Report client transfer errors that never reach the ingest action. */
    public function notifyDirectPrimaryTransferFailure(string $message): void
    {
        app(AdminMediaUploadFeedback::class)->failure($message);
    }

    /** Each transfer appends to the unfinished queue without replacing edits. */
    public function beginDirectPrimaryMediaBatch(): void
    {
        $this->rememberStagedArtworkChanges();
        $this->directPrimaryBatchAddedCount = 0;
    }

    /**
     * Queue the canonical available asset returned by the shared resumable
     * ingest endpoint. Only images/videos can become Artwork primary media.
     */
    public function queueResumablePrimaryMediaAsset(int $assetId, bool $reused = false): void
    {
        $asset = MediaAsset::query()->whereKey($assetId)->where('state', 'available')->first();
        if (! $asset instanceof MediaAsset || ! $this->isGalleryPrimaryVisual($asset)) {
            throw ValidationException::withMessages(['media' => 'This media file is not a valid Artwork image or video.']);
        }
        $pendingIds = array_map('intval', array_column($this->pendingBatchArtworkMedia, 'media_asset_id'));
        if (in_array($assetId, $pendingIds, true)) {
            return;
        }

        $this->pendingBatchArtworkMedia[] = $this->pendingBatchArtworkRow($asset, $reused);
        $this->directPrimaryBatchAddedCount++;
        // Select the first newly accepted file of every upload batch, even
        // when an earlier Staged item remains selected. Subsequent uploads
        // extend the queue without overriding the user's current selection.
        if ($this->directPrimaryBatchAddedCount === 1 || $this->selectedStagedMediaAssetId === null) {
            $this->selectStagedArtwork($assetId);
        }
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
        $added = $this->directPrimaryBatchAddedCount;
        $this->directPrimaryBatchAddedCount = 0;
        $summary = app(AdminMediaUploadFeedback::class)->batch(
            $added, $duplicates, $failed, $errors, 'gallery',
        );

        // Queue selection is owned by the first completed asset or the
        // user's explicit navigation, not by the end of a transport batch.
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
                // Only the queue row owns the actual Storage identity.
                // The disabled picker receives that ID for display only.
                $form = $this->stagedArtworkData;
                unset($form['primary_media_asset_id'], $form['staged_media_filename']);
                $row['form'] = $form;
                break;
            }
        }
        unset($row);
    }

    /** One-based position is a projection of the queue and selected media identity. */
    public function stagedArtworkPosition(): int
    {
        if ($this->selectedStagedMediaAssetId === null) {
            return 0;
        }

        $ids = array_map('intval', array_column($this->pendingBatchArtworkMedia, 'media_asset_id'));
        $index = array_search($this->selectedStagedMediaAssetId, $ids, true);

        return $index === false ? 0 : $index + 1;
    }

    /** The selected upload supplies only a visible hint, not an entered title. */
    private function stagedArtworkTitleSuggestion(): string
    {
        foreach ($this->pendingBatchArtworkMedia as $row) {
            if ((int) $row['media_asset_id'] === $this->selectedStagedMediaAssetId) {
                return (string) $row['title'];
            }
        }

        return '';
    }

    /** Keep queue navigation on the same selection, form and Preview lifecycle. */
    public function navigateStagedArtwork(int $direction): void
    {
        if (! in_array($direction, [-1, 1], true)) {
            return;
        }

        $current = $this->stagedArtworkPosition();
        if ($current === 0) {
            return;
        }

        $total = count($this->pendingBatchArtworkMedia);
        // Navigation is circular; 0/1 queues remain bounded by the UI.
        $target = ($current - 1 + $direction + $total) % $total;
        $this->selectStagedArtwork((int) $this->pendingBatchArtworkMedia[$target]['media_asset_id']);
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
        if ($this->galleryPreviewAutoFocus) {
            $this->galleryStagePreview = 'staged';
            $this->galleryStagePreviewArtworkId = null;
        }
        $form = is_array($selected['form'] ?? null) ? $selected['form'] : [];
        if ($form === []) {
            $form = [
                'title' => null,
                'primary_media_asset_id' => $assetId,
                'artwork_date' => null,
                'medium' => null,
                'position' => null,
                'position_explicit' => false,
            ];
        }
        // Stage owns only the compact fields. Existing queue snapshots must not
        // keep hidden slug, description or ALT override values after this change.
        unset($form['slug'], $form['description'], $form['primary_alt_text']);
        if (! (bool) ($form['position_explicit'] ?? false)) {
            // Older queue snapshots contained a white numeric suggestion.
            // Automatic placement now lives only in the grey placeholder.
            $form['position'] = null;
        }
        // Render the canonical MediaAssetSelect thumbnail from the queued
        // Storage asset, never from an editable or persisted form value.
        unset($form['staged_media_filename']);
        $form['primary_media_asset_id'] = $assetId;
        $this->stagedArtworkData = $form;
        $this->stagedArtworkForm->fill($form);
        $this->publishStagedArtworkPreview();
    }

    /** Immediate Stage discard; never silently detach other content that
     * happens to reference a deduplicated Storage original.
     */
    public function discardStagedMedia(int $assetId): void
    {
        if ($assetId !== $this->selectedStagedMediaAssetId
            || $this->stagedArtworkPosition() === 0) {
            return;
        }

        $this->discardStagedAssets([$assetId]);
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

        if (blank($asset->getAttribute('alt_text'))) {
            // A previously uploaded file may predate automatic Storage ALT
            // defaults. Use the same canonical metadata writer as Storage;
            // do not invent an Artwork-specific alternate description.
            $asset = app(MediaAssetEditorialService::class)->updateMetadata($asset, [
                'alt_text' => MediaAssetEditorialService::defaultAltTextFromFilename(
                    (string) $asset->getAttribute('original_filename'),
                ),
            ]);
        }

        // The queue filename remains the default for an untouched or blank
        // title; the draft service owns a collision-safe public slug.
        $data['title'] = trim((string) ($data['title'] ?? '')) ?: (string) $row['title'];
        $explicit = (bool) ($data['position_explicit'] ?? false);
        $payload = $this->normalizeArtworkFormData($data);
        // The canonical draft service owns collision-safe slug generation.
        // Old queue snapshots must never inject fields retired from Stage.
        unset($payload['slug'], $payload['description']);
        if (! $explicit) {
            unset($payload['position']);
        }
        unset($payload['position_explicit']);

        $artwork = app(ArtworkDraftService::class)->create($payload, $assetId);
        $artwork = app(ArtworkPrimaryMediaService::class)->attachAsset($artwork, $asset);
        // Publish remains a Working-state mutation. The caller's
        // transaction rolls back creation if canonical readiness fails.
        app(ArtworkPublicationService::class)->publish($artwork);
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

        // The compact Stage form reuses the canonical Artwork field controls and rules.
        $state = $this->stagedArtworkForm->getState();
        try {
            DB::transaction(function () use ($selected, $state): void {
                $this->createStagedArtwork($selected, $state);
            });
        } catch (ValidationException $exception) {
            // Failure rolls back Artwork creation, media links and audit.
            // The queued item and its current editorial form remain intact.
            $this->notifyValidationFailure('Staged Artwork cannot be submitted', $exception);

            return;
        }
        $this->forgetCompletedStagedArtworks([$id]);
        app(AdminNotifier::class)->notification(
            title: 'Artwork submitted and published in Working',
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
        app(AdminNotifier::class)->notification(title: $count.' Artworks submitted and published in Working', status: 'success');
    }

    /** Both Stage buttons use one immediate reference-safe Storage operation. */
    public function discardAllStagedMedia(): void
    {
        $this->discardStagedAssets(array_map(
            'intval', array_column($this->pendingBatchArtworkMedia, 'media_asset_id'),
        ));
    }

    /** @param list<int> $ids */
    private function discardStagedAssets(array $ids): void
    {
        $queued = [];
        foreach ($this->pendingBatchArtworkMedia as $row) {
            $queued[(int) $row['media_asset_id']] = $row;
        }
        $ids = array_values(array_unique(array_intersect($ids, array_keys($queued))));
        if ($ids === []) {
            return;
        }

        $removed = [];
        $deleted = [];
        $retained = [];
        $missing = [];
        $failed = [];
        $cleanupDelayed = [];
        $references = app(MediaReferenceQuery::class);
        $mediaEditor = app(MediaAssetEditorialService::class);

        foreach ($ids as $id) {
            $asset = MediaAsset::query()->whereKey($id)->where('state', 'available')->first();
            if (! $asset instanceof MediaAsset) {
                // The media has already gone from Storage; do not resurrect a
                // dead queue row when the saved Gallery Stage is reopened.
                $removed[] = $id;
                $missing[] = $id;
                continue;
            }

            try {
                // A fingerprint-identical re-upload may point to a Storage
                // original which existed before this Stage. Discard cancels
                // this Stage entry but must never delete that original.
                $references->forgetContentReferences();
                $keepInStorage = ($queued[$id]['reused'] ?? true) !== false
                    || $this->stagedInAnotherGallery($id)
                    || $references->isReferenced($asset)
                    || DB::table('committed.media_assets')
                        ->where('id', $id)
                        ->where('state', '<>', 'deleted')
                        ->exists();

                if ($keepInStorage) {
                    $removed[] = $id;
                    $retained[] = $id;
                    continue;
                }

                // The canonical lock/reference guard still makes the final
                // deletion decision if another editor references the file
                // between our preflight check and the Storage mutation.
                if ($mediaEditor->delete($asset, new MediaDeletionOptions(requireUnreferenced: true))) {
                    $removed[] = $id;
                    $deleted[] = $id;
                } else {
                    $removed[] = $id;
                    $missing[] = $id;
                }
            } catch (Throwable $exception) {
                if ((string) $asset->fresh()?->getAttribute('state') === 'deleted') {
                    report($exception);
                    $removed[] = $id;
                    $deleted[] = $id;
                    $cleanupDelayed[] = $id;
                    continue;
                }

                // A newly created reference in another request prevents
                // deletion, but should not trap the file in this Stage.
                if ($exception instanceof ValidationException) {
                    $references->forgetContentReferences();
                    if ($references->isReferenced($asset)) {
                        $removed[] = $id;
                        $retained[] = $id;
                        continue;
                    }
                }
                if (! $exception instanceof ValidationException) {
                    report($exception);
                }
                $detail = $exception instanceof ValidationException
                    ? collect($exception->errors())->flatten()->first()
                    : null;
                $failed[] = (string) $asset->getAttribute('original_filename').': '.
                    (is_string($detail) && $detail !== '' ? $detail : 'Storage deletion failed.');
            }
        }

        if ($removed !== []) {
            $this->forgetCompletedStagedArtworks($removed);
        }

        $messages = [];
        if ($deleted !== []) {
            $messages[] = count($deleted).' newly uploaded '.(count($deleted) === 1 ? 'file was' : 'files were').' removed from Storage.';
        }
        if ($retained !== []) {
            $messages[] = count($retained).' reused or referenced '.(count($retained) === 1 ? 'file was' : 'files were').' kept in Storage.';
        }
        if ($missing !== []) {
            $messages[] = count($missing).' unavailable '.(count($missing) === 1 ? 'file was' : 'files were').' cleared from Stage.';
        }
        if ($cleanupDelayed !== []) {
            $messages[] = 'Physical cleanup is pending for '.count($cleanupDelayed).' '.(count($cleanupDelayed) === 1 ? 'file.' : 'files.');
        }
        if ($failed !== []) {
            $messages[] = implode(' ', array_slice($failed, 0, 3));
        }

        app(AdminNotifier::class)->notification(
            title: $removed === []
                ? 'No staged files discarded'
                : count($removed).' staged '.(count($removed) === 1 ? 'file' : 'files').' removed',
            body: $messages === [] ? null : implode(' ', $messages),
            status: $failed !== []
                ? ($removed !== [] ? 'warning' : 'danger')
                : ($cleanupDelayed !== [] ? 'warning' : 'success'),
        );
    }

    /** Remove completed queue rows after submission or confirmed Storage discard. */
    private function forgetCompletedStagedArtworks(array $assetIds): void
    {
        $selectedIndex = max(0, $this->stagedArtworkPosition() - 1);
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
            $this->syncGalleryPreviewFocus();
        } else {
            $nextIndex = min($selectedIndex, count($this->pendingBatchArtworkMedia) - 1);
            $this->selectStagedArtwork((int) $this->pendingBatchArtworkMedia[$nextIndex]['media_asset_id']);
        }
    }

    private function isGalleryPrimaryVisual(MediaAsset $asset): bool
    {
        $mime = (string) $asset->getAttribute('mime_type');

        return MediaTypePolicy::isImage($mime) || MediaTypePolicy::isVideo($mime);
    }

    /** @return array{media_asset_id:int,title:string,filename:string,reused:bool,form:array<string,mixed>} */
    private function pendingBatchArtworkRow(MediaAsset $asset, bool $reused = false): array
    {
        return [
            'media_asset_id' => (int) $asset->getKey(),
            'title' => $this->defaultArtworkTitle((string) $asset->getAttribute('original_filename')),
            'filename' => (string) $asset->getAttribute('original_filename'),
            'reused' => $reused,
            'form' => [],
        ];
    }

    private function defaultArtworkTitle(string $filename): string
    {
        $title = trim((string) pathinfo($filename, PATHINFO_FILENAME));

        return $title === '' ? 'Untitled' : $title;
    }

}
