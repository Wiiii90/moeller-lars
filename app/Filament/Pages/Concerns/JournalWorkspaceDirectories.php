<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Content\ExhibitionDirectoryService;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Filament\Support\JournalEntryEditorSchema;
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
                'filament.resources.exhibitions.partials.directory-table',
                ['directoryKind' => 'Organizer', 'records' => app(ExhibitionDirectoryService::class)->organizers()],
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
                'filament.resources.exhibitions.partials.directory-table',
                ['directoryKind' => 'Venue', 'records' => app(ExhibitionDirectoryService::class)->venues()],
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

    /** Every table field persists in place on change; no separate Edit dialog. */
    public function saveExhibitionOrganizerField(int $id, string $field, ?string $value): void
    {
        abort_unless(in_array($field, ['name', 'street', 'postal_code', 'city', 'country', 'website'], true), 404);
        $this->changeExhibitionDirectory(
            fn (): bool => app(ExhibitionDirectoryService::class)->updateOrganizer($id, [$field => $value]),
            'Organizer updated',
        );
    }

    public function saveExhibitionVenueField(int $id, string $field, ?string $value): void
    {
        abort_unless(in_array($field, ['name', 'street', 'postal_code', 'city', 'country', 'website'], true), 404);
        $this->changeExhibitionDirectory(
            fn (): bool => app(ExhibitionDirectoryService::class)->updateVenue($id, [$field => $value]),
            'Venue updated',
        );
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

    private function changeExhibitionDirectory(\Closure $mutation, string $title): bool
    {
        try {
            $changed = $mutation();
        } catch (ValidationException $exception) {
            $this->notifyValidationFailure('Exhibition directory entry not saved', $exception);

            return false;
        }

        if ($changed) {
            $this->forceRender();
            app(AdminNotifier::class)->notification(title: $title, status: 'success');
        }

        return $changed;
    }
}
