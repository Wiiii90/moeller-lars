<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Artwork\ArtworkDraftService;
use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Domain\Artwork\ArtworkPublicationService;
use App\Filament\Support\AdminIcon;
use App\Filament\Support\Dialogs\AdminDialog;
use Filament\Actions\Action;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait GalleryWorkspaceBatchActions
{
    public function removeSelectedArtworksAction(): Action
    {
        $action = Action::make('removeSelectedArtworks')
            ->label('Remove')
            ->action(function (): void {
                try {
                    $artworks = $this->selectedArtworks();
                    DB::transaction(function () use ($artworks): void {
                        foreach ($artworks as $artwork) {
                            app(ArtworkGalleryAssignmentService::class)->detach($artwork);
                        }
                    });
                } catch (ValidationException $exception) {
                    $this->notifyValidationFailure('Selected artworks could not be removed', $exception);

                    return;
                }

                $count = $artworks->count();
                $this->clearSelection();
                $this->refreshWorkspaceAfterMutation();
                app(AdminNotifier::class)->notification(
                    title: $count.' selected '.($count === 1 ? 'artwork was' : 'artworks were').' removed',
                    status: 'success',
                );
            });

        return AdminDialog::confirm(
            $action,
            'Remove artworks?',
            'Artwork records and files in Storage remain. Published artworks must be unpublished first.',
            'Remove',
            icon: AdminIcon::Detach,
        );
    }

    public function deleteSelectedArtworksAction(): Action
    {
        $action = Action::make('deleteSelectedArtworks')
            ->label('Delete')
            ->color('danger')
            ->action(function (): void {
                try {
                    $artworks = $this->selectedArtworks();
                    DB::transaction(function () use ($artworks): void {
                        foreach ($artworks as $artwork) {
                            app(ArtworkDraftService::class)->delete($artwork);
                        }
                    });
                } catch (ValidationException $exception) {
                    $this->notifyValidationFailure('Selected artworks could not be deleted', $exception);

                    return;
                }

                $count = $artworks->count();
                $this->clearSelection();
                $this->refreshWorkspaceAfterMutation();
                app(AdminNotifier::class)->notification(
                    title: $count.' '.($count === 1 ? 'artwork deleted' : 'artworks deleted'),
                    status: 'success',
                );
            });

        return AdminDialog::confirm(
            $action,
            'Delete artworks?',
            'Only draft artworks can be deleted. Files in Storage are preserved even when they become unreferenced.',
            'Delete',
            danger: true,
        );
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

        return AdminDialog::confirm(
            $action,
            'Unpublish artworks?',
            submitLabel: 'Unpublish',
            icon: AdminIcon::Unpublish,
        );
    }
}
