<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Artwork\ArtworkDraftService;
use App\Domain\Artwork\ArtworkPublicationService;
use App\Domain\Media\MediaAssetEditorialService;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Models\Artwork;
use App\Models\MediaAsset;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

trait GalleryWorkspaceBatchActions
{
    public function deleteSelectedArtworksAction(): Action
    {
        $action = Action::make('deleteSelectedArtworks')
            ->label('Delete')
            ->color('danger')
            ->fillForm(fn (): array => [
                'delete_media_files' => false,
                'selection_snapshot' => $this->selectedArtworkDeletionSnapshot(),
            ])
            ->schema([
                Checkbox::make('delete_media_files')
                    ->label('Also delete original media files from Storage'),
                Hidden::make('selection_snapshot'),
            ])
            ->modalContent(fn (): View => view(
                'filament.resources.artworks.partials.delete-dialog',
                $this->selectedArtworkDeletionSummary(),
            ))
            ->action(function (array $data): void {
                try {
                    $artworks = $this->selectedArtworks();
                    $deleteFiles = ($data['delete_media_files'] ?? false) === true;
                    if (! hash_equals(
                        $this->selectedArtworkDeletionSnapshot(),
                        (string) ($data['selection_snapshot'] ?? ''),
                    )) {
                        throw ValidationException::withMessages([
                            'artworks' => 'The selection, files or their references changed. Reopen Delete to review them.',
                        ]);
                    }

                    if (! $deleteFiles) {
                        // Artwork-only deletions can stay atomic. Original
                        // Storage assets and history are preserved.
                        DB::transaction(function () use ($artworks): void {
                            foreach ($artworks as $artwork) {
                                app(ArtworkDraftService::class)->delete($artwork);
                            }
                        });
                        $deleted = $artworks->count();
                        $failed = [];
                        $cleanupPending = 0;
                    } else {
                        // Storage owns its own durable post-commit physical
                        // cleanup. Run each confirmed Artwork+Media mutation
                        // separately so a failure cannot roll back earlier
                        // database writes after their file cleanup has begun.
                        $deleted = 0;
                        $failed = [];
                        $cleanupPending = 0;
                        foreach ($artworks as $row) {
                            $id = (int) $row->getKey();
                            $artwork = Artwork::query()->find($id);
                            if (! $artwork instanceof Artwork) {
                                $failed[] = (string) $row->getAttribute('title').': no longer available.';
                                continue;
                            }
                            $usage = $artwork->artworkMedia()->where('role', 'primary')->with('mediaAsset')->first();
                            $asset = $usage?->getRelationValue('mediaAsset');
                            try {
                                if ($asset instanceof MediaAsset && $asset->getAttribute('state') === 'available') {
                                    app(MediaAssetEditorialService::class)->delete($asset, $artwork);
                                } else {
                                    app(ArtworkDraftService::class)->delete($artwork);
                                }
                                $deleted++;
                            } catch (Throwable $exception) {
                                if (! Artwork::query()->whereKey($id)->exists()) {
                                    // Logical deletion committed, but physical
                                    // cleanup can still be pending in the outbox.
                                    report($exception);
                                    $deleted++;
                                    $cleanupPending++;
                                    continue;
                                }
                                if (! $exception instanceof ValidationException) {
                                    report($exception);
                                }
                                $detail = $exception instanceof ValidationException
                                    ? collect($exception->errors())->flatten()->first()
                                    : null;
                                $failed[] = (string) $row->getAttribute('title').': '.
                                    (is_string($detail) && $detail !== '' ? $detail : 'Deletion failed.');
                            }
                        }
                    }
                } catch (ValidationException $exception) {
                    $this->notifyValidationFailure('Selected artworks could not be deleted', $exception);

                    return;
                }

                if ($deleted > 0) {
                    $this->clearSelection();
                    $this->refreshWorkspaceAfterMutation();
                }
                app(AdminNotifier::class)->notification(
                    title: $deleted.' '.($deleted === 1 ? 'artwork deleted' : 'artworks deleted'),
                    body: $failed !== [] || $cleanupPending > 0
                        ? implode(' ', array_slice($failed, 0, 3)).
                            ($cleanupPending > 0 ? ' Some physical file cleanup is pending.' : '')
                        : null,
                    status: $failed !== []
                        ? ($deleted > 0 ? 'warning' : 'danger')
                        : ($cleanupPending > 0 ? 'warning' : 'success'),
                );
            });

        return AdminDialog::confirm(
            $action,
            'Delete selected artworks?',
            'Delete selected Artworks from Working. Optionally remove their original Storage files and all remaining media references. Public changes become visible after the next Site Commit.',
            'Delete',
            danger: true,
        );
    }

    /** A selection and its media references are re-read for the confirmation. */
    private function selectedArtworkDeletionSnapshot(): string
    {
        return hash('sha256', json_encode($this->selectedArtworkDeletionSummary(), JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private function selectedArtworkDeletionSummary(): array
    {
        $artworks = $this->selectedArtworks();
        $assets = [];
        foreach ($artworks as $artwork) {
            $usage = $artwork->artworkMedia()->where('role', 'primary')->with('mediaAsset')->first();
            $asset = $usage?->getRelationValue('mediaAsset');
            if ($asset instanceof MediaAsset && $asset->getAttribute('state') === 'available') {
                $assets[(int) $asset->getKey()] = $asset;
            }
        }

        $references = [];
        foreach ($assets as $asset) {
            foreach ($this->primaryMediaReferences($asset) as $reference) {
                $references[] = [
                    'filename' => (string) $asset->getAttribute('original_filename'),
                    'type' => $reference['type'],
                    'label' => $reference['label'],
                ];
            }
        }

        return [
            'selectedCount' => $artworks->count(),
            'fileCount' => count($assets),
            'referenceCount' => count($references),
            'references' => $references,
            'artworkIds' => $artworks->modelKeys(),
            'mediaIds' => array_keys($assets),
        ];
    }

    public function publishSelectedArtworksAction(): Action
    {
        return Action::make('publishSelectedArtworks')
            ->label('Publish')
            ->action(function (): void {
                try {
                    $artworks = $this->selectedArtworks();
                    $changed = DB::transaction(function () use ($artworks): int {
                        $changed = 0;
                        foreach ($artworks as $artwork) {
                            $before = (string) $artwork->getAttribute('state');
                            $updated = app(ArtworkPublicationService::class)->publish($artwork);
                            if ($before !== (string) $updated->getAttribute('state')) {
                                $changed++;
                            }
                        }

                        return $changed;
                    });
                } catch (ValidationException $exception) {
                    $this->notifyValidationFailure('Selected artworks cannot be published', $exception);

                    return;
                }

                $this->refreshWorkspaceAfterMutation();
                if ($changed > 0) {
                    app(AdminNotifier::class)->notification(
                        title: $changed === 1 ? 'Artwork published' : $changed.' artworks published',
                        status: 'success',
                    );
                }
            });
    }

    public function unpublishSelectedArtworksAction(): Action
    {
        $action = Action::make('unpublishSelectedArtworks')
            ->label('Unpublish')
            ->action(function (): void {
                $artworks = $this->selectedArtworks();
                $changed = DB::transaction(function () use ($artworks): int {
                    $changed = 0;
                    foreach ($artworks as $artwork) {
                        $before = (string) $artwork->getAttribute('state');
                        $updated = app(ArtworkPublicationService::class)->unpublish($artwork);
                        if ($before !== (string) $updated->getAttribute('state')) {
                            $changed++;
                        }
                    }

                    return $changed;
                });

                $this->refreshWorkspaceAfterMutation();
                if ($changed > 0) {
                    app(AdminNotifier::class)->notification(
                        title: $changed === 1 ? 'Artwork unpublished' : $changed.' artworks unpublished',
                        status: 'success',
                    );
                }
            });

        return $action;
    }
}
