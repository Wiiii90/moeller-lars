<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Content\ExhibitionDirectoryService;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Filament\Support\JournalEntryEditorSchema;
use App\Models\ExhibitionOrganizerPreset;
use App\Models\ExhibitionVenuePreset;
use Filament\Actions\Action;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;

trait JournalWorkspaceDirectories
{
    public function exhibitionOrganizersAction(): Action
    {
        $action = Action::make('exhibitionOrganizers')
            ->label('Organizers')
            ->modalHeading('Exhibition organizers')
            ->modalContent(fn (): View => view(
                'filament.resources.exhibitions.partials.organizer-presets-table',
                ['organizers' => app(ExhibitionDirectoryService::class)->organizers()],
            ));

        return AdminDialog::viewer($action, AdminDialogSize::Default)
            ->extraModalWindowAttributes(['class' => 'admin-dialog--table-viewer'], merge: true);
    }

    public function exhibitionVenuesAction(): Action
    {
        $action = Action::make('exhibitionVenues')
            ->label('Venues')
            ->modalHeading('Exhibition venues')
            ->modalContent(fn (): View => view(
                'filament.resources.exhibitions.partials.venue-presets-table',
                ['venues' => app(ExhibitionDirectoryService::class)->venues()],
            ));

        return AdminDialog::viewer($action, AdminDialogSize::Default)
            ->extraModalWindowAttributes(['class' => 'admin-dialog--table-viewer'], merge: true);
    }

    public function addExhibitionOrganizerAction(): Action
    {
        $action = Action::make('addExhibitionOrganizer')
            ->label('Add organizer')
            ->modalHeading('Add organizer')
            ->schema(JournalEntryEditorSchema::directoryFields('Organizer'))
            ->action(function (Action $action, array $data): void {
                if (! $this->changeExhibitionDirectory(
                    fn (): bool => app(ExhibitionDirectoryService::class)->addOrganizer($data),
                    'Organizer added',
                )) {
                    $action->halt();
                }
            });

        return AdminDialog::create($action, 'Add organizer', AdminDialogSize::Default);
    }

    public function editExhibitionOrganizerAction(): Action
    {
        $action = Action::make('editExhibitionOrganizer')
            ->label('Edit organizer')
            ->modalHeading('Edit organizer')
            ->fillForm(fn (array $arguments): array => ExhibitionOrganizerPreset::query()
                ->findOrFail((int) ($arguments['organizer'] ?? 0))
                ->only(['name', 'website', 'street', 'postal_code', 'city', 'country']))
            ->schema(JournalEntryEditorSchema::directoryFields('Organizer'))
            ->action(function (array $arguments, array $data): void {
                $this->changeExhibitionDirectory(
                    fn (): bool => app(ExhibitionDirectoryService::class)
                        ->updateOrganizer((int) ($arguments['organizer'] ?? 0), $data),
                    'Organizer updated',
                    refreshExhibitions: true,
                );
            });

        return AdminDialog::edit($action, AdminDialogSize::Default);
    }

    public function addExhibitionVenueAction(): Action
    {
        $action = Action::make('addExhibitionVenue')
            ->label('Add venue')
            ->modalHeading('Add venue')
            ->schema(JournalEntryEditorSchema::directoryFields('Venue'))
            ->action(function (Action $action, array $data): void {
                if (! $this->changeExhibitionDirectory(
                    fn (): bool => app(ExhibitionDirectoryService::class)->addVenue($data),
                    'Venue added',
                )) {
                    $action->halt();
                }
            });

        return AdminDialog::create($action, 'Add venue', AdminDialogSize::Default);
    }

    public function editExhibitionVenueAction(): Action
    {
        $action = Action::make('editExhibitionVenue')
            ->label('Edit venue')
            ->modalHeading('Edit venue')
            ->fillForm(fn (array $arguments): array => ExhibitionVenuePreset::query()
                ->findOrFail((int) ($arguments['venue'] ?? 0))
                ->only(['name', 'website', 'street', 'postal_code', 'city', 'country']))
            ->schema(JournalEntryEditorSchema::directoryFields('Venue'))
            ->action(function (array $arguments, array $data): void {
                $this->changeExhibitionDirectory(
                    fn (): bool => app(ExhibitionDirectoryService::class)
                        ->updateVenue((int) ($arguments['venue'] ?? 0), $data),
                    'Venue updated',
                );
            });

        return AdminDialog::edit($action, AdminDialogSize::Default);
    }

    public function deleteExhibitionOrganizerPreset(int $id): void
    {
        $this->changeExhibitionDirectory(
            fn (): bool => app(ExhibitionDirectoryService::class)->deleteOrganizer($id),
            'Organizer removed',
        );
    }

    public function deleteExhibitionVenuePreset(int $id): void
    {
        $this->changeExhibitionDirectory(
            fn (): bool => app(ExhibitionDirectoryService::class)->deleteVenue($id),
            'Venue removed',
        );
    }

    public function sortExhibitionOrganizerPreset(int $id, int $position): void
    {
        $this->changeExhibitionDirectory(
            fn (): bool => app(ExhibitionDirectoryService::class)->moveOrganizer($id, $position),
            'Organizer order updated',
        );
    }

    public function sortExhibitionVenuePreset(int $id, int $position): void
    {
        $this->changeExhibitionDirectory(
            fn (): bool => app(ExhibitionDirectoryService::class)->moveVenue($id, $position),
            'Venue order updated',
        );
    }

    public function moveExhibitionOrganizerPreset(int $id, string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            return;
        }
        $rows = app(ExhibitionDirectoryService::class)->organizers();
        $current = $rows->search(static fn (ExhibitionOrganizerPreset $row): bool => (int) $row->getKey() === $id);
        if ($current !== false) {
            $this->sortExhibitionOrganizerPreset($id, (int) $current + ($direction === 'up' ? -1 : 1));
        }
    }

    public function moveExhibitionVenuePreset(int $id, string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            return;
        }
        $rows = app(ExhibitionDirectoryService::class)->venues();
        $current = $rows->search(static fn (ExhibitionVenuePreset $row): bool => (int) $row->getKey() === $id);
        if ($current !== false) {
            $this->sortExhibitionVenuePreset($id, (int) $current + ($direction === 'up' ? -1 : 1));
        }
    }

    private function changeExhibitionDirectory(\Closure $mutation, string $title, bool $refreshExhibitions = false): bool
    {
        try {
            $changed = $mutation();
        } catch (ValidationException $exception) {
            $this->notifyValidationFailure('Exhibition directory entry not saved', $exception);

            return false;
        }

        if ($changed) {
            if ($refreshExhibitions) {
                $this->loadExhibitions(false);
                $this->syncJournalPreviewFocus(forceRefresh: true);
            }
            $this->forceRender();
            app(AdminNotifier::class)->notification(title: $title, status: 'success');
        }

        return $changed;
    }
}
