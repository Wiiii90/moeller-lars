<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Content\ExhibitionOrganizerPresetService;
use App\Filament\Support\AdminRowAction;
use App\Filament\Support\Controls\AdminControl;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Models\ExhibitionOrganizerPreset;
use App\Models\ExhibitionVenuePreset;
use Filament\Actions\Action;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;

trait JournalWorkspaceOrganizers
{
    public function exhibitionOrganizersAction(): Action
    {
        $action = Action::make('exhibitionOrganizers')
            ->label('Organizers')
            ->modalHeading('Exhibition organizers')
            ->modalContent(fn (): View => view(
                'filament.resources.exhibitions.partials.organizer-presets-table',
                ['organizers' => app(ExhibitionOrganizerPresetService::class)->organizers()],
            ));

        return AdminDialog::viewer($action, AdminDialogSize::Default)
            ->extraModalWindowAttributes(['class' => 'admin-dialog--table-viewer'], merge: true);
    }

    public function exhibitionOrganizerVenuesAction(): Action
    {
        $action = Action::make('exhibitionOrganizerVenues')
            ->label('Venues')
            ->modalHeading(fn (array $arguments): string => 'Venues · '.(string) ExhibitionOrganizerPreset::query()
                ->whereKey((int) ($arguments['organizer'] ?? 0))->value('name'))
            ->modalContent(fn (array $arguments): View => view(
                'filament.resources.exhibitions.partials.venue-presets-table',
                [
                    'organizerId' => (int) ($arguments['organizer'] ?? 0),
                    'venues' => app(ExhibitionOrganizerPresetService::class)->venues((int) ($arguments['organizer'] ?? 0)),
                ],
            ));

        return AdminDialog::viewer($action, AdminDialogSize::Default)
            ->extraModalWindowAttributes(['class' => 'admin-dialog--table-viewer'], merge: true);
    }

    /** @return list<mixed> */
    private function organizerVenueFields(): array
    {
        return [
            AdminControl::text('name', 'Venue')->required()->maxLength(240)->columnSpanFull(),
            AdminControl::text('street', 'Street address')->maxLength(500)->columnSpanFull(),
            AdminControl::text('postal_code', 'Postal code')->maxLength(16),
            AdminControl::text('city', 'City')->maxLength(160),
            AdminControl::text('country', 'Country')->maxLength(160)->columnSpanFull(),
        ];
    }

    public function addExhibitionVenuePresetAction(): Action
    {
        $action = Action::make('addExhibitionVenuePreset')
            ->label('Add venue')
            ->modalHeading('Add venue')
            ->schema($this->organizerVenueFields())
            ->action(function (Action $action, array $arguments, array $data): void {
                if (! $this->changeExhibitionPreset(
                    fn (): bool => app(ExhibitionOrganizerPresetService::class)->addVenue((int) ($arguments['organizer'] ?? 0), $data),
                    'Venue added',
                )) {
                    $action->halt();
                }
            });

        return AdminDialog::create($action, 'Add venue', AdminDialogSize::Default);
    }

    public function editExhibitionVenuePresetAction(): Action
    {
        $action = Action::make('editExhibitionVenuePreset')
            ->label('Edit venue')
            ->modalHeading('Edit venue')
            ->fillForm(fn (array $arguments): array => ExhibitionVenuePreset::query()
                ->findOrFail((int) ($arguments['venue'] ?? 0))
                ->only(['name', 'street', 'postal_code', 'city', 'country']))
            ->schema($this->organizerVenueFields())
            ->action(function (array $arguments, array $data): void {
                $this->changeExhibitionPreset(
                    fn (): bool => app(ExhibitionOrganizerPresetService::class)->updateVenue((int) ($arguments['venue'] ?? 0), $data),
                    'Venue updated',
                );
            });

        return AdminDialog::edit($action, AdminDialogSize::Default);
    }

    public function addExhibitionOrganizerPreset(string $name): bool
    {
        return $this->changeExhibitionPreset(
            fn (): bool => app(ExhibitionOrganizerPresetService::class)->addOrganizer($name),
            'Organizer added',
        );
    }

    public function renameExhibitionOrganizerPreset(int $id, string $name): bool
    {
        return $this->changeExhibitionPreset(
            fn (): bool => app(ExhibitionOrganizerPresetService::class)->renameOrganizer($id, $name),
            'Organizer renamed',
            refreshExhibitions: true,
        );
    }

    public function deleteExhibitionOrganizerPreset(int $id): void
    {
        $this->changeExhibitionPreset(
            fn (): bool => app(ExhibitionOrganizerPresetService::class)->deleteOrganizer($id),
            'Organizer removed',
        );
    }

    public function sortExhibitionOrganizerPreset(int $id, int $position): void
    {
        $this->changeExhibitionPreset(
            fn (): bool => app(ExhibitionOrganizerPresetService::class)->moveOrganizer($id, $position),
            'Organizer order updated',
        );
    }

    public function moveExhibitionOrganizerPreset(int $id, string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            return;
        }
        $rows = app(ExhibitionOrganizerPresetService::class)->organizers();
        $current = $rows->search(static fn (ExhibitionOrganizerPreset $row): bool => (int) $row->getKey() === $id);
        if ($current === false) {
            return;
        }
        $this->sortExhibitionOrganizerPreset($id, (int) $current + ($direction === 'up' ? -1 : 1));
    }

    public function deleteExhibitionVenuePreset(int $id): void
    {
        $this->changeExhibitionPreset(
            fn (): bool => app(ExhibitionOrganizerPresetService::class)->deleteVenue($id),
            'Venue removed',
        );
    }

    private function changeExhibitionPreset(\Closure $mutation, string $title, bool $refreshExhibitions = false): bool
    {
        try {
            $changed = $mutation();
        } catch (ValidationException $exception) {
            $this->notifyValidationFailure('Organizer or venue not saved', $exception);

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
