<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Content\ExhibitionOrganizerPresetService;
use App\Filament\Support\AdminRowAction;
use App\Filament\Support\Controls\AdminControl;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Models\ExhibitionOrganizerPreset;
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

    private function changeExhibitionPreset(\Closure $mutation, string $title, bool $refreshExhibitions = false): bool
    {
        try {
            $changed = $mutation();
        } catch (ValidationException $exception) {
            $this->notifyValidationFailure('Organizer not saved', $exception);

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
