<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\CvEntryEditorialService;
use App\Domain\Admin\EditorialRecordService;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\InteractsWithAdminEditDialogAutosave;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Models\CvEntry;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

trait CustomPageWorkspaceCvActions
{
    use InteractsWithAdminEditDialogAutosave;

    public function addCvEntryAction(): Action
    {
        $action = Action::make('addCvEntry')
            ->label('Add CV entry')
            ->fillForm(fn (): array => [
                'section' => 'CV',
                'publication_state' => 'unpublished',
                'date_precision' => 'unknown',
            ])
            ->schema($this->cvEntryCreateSchema())
            ->modalHeading('Add CV entry')
            ->action(function (array $data): void {
                $entry = app(CvEntryEditorialService::class)->createDraft($this->cvEntryPayload($data));
                if (($data['publication_state'] ?? 'unpublished') === 'published') {
                    app(EditorialRecordService::class)->publish($entry);
                }
                $this->clearSelections();
                $this->loadComponentProjection(refreshCvCount: true);
                Notification::make()->title('CV entry added')->success()->send();
            });

        return AdminDialog::create($action, 'Add CV entry', AdminDialogSize::Large);
    }

    public function editCvEntryAction(): Action
    {
        $action = Action::make('editCvEntry')
            ->label('Edit')
            ->fillForm(function (array $arguments): array {
                $entry = $this->actionCvEntry($arguments);

                return $this->cvEntryEditorRow($entry);
            })
            ->schema($this->cvEntrySchema())
            ->modalHeading(fn (array $arguments): string => 'Edit '.(string) $this->actionCvEntry($arguments)->getAttribute('title'))
            ->action(function (array $data, array $arguments): void {
                app(CvEntryEditorialService::class)->update($this->actionCvEntry($arguments), $this->cvEntryPayload($data));
                $this->clearSelections();
                $this->loadComponentProjection(refreshCvCount: true);
                Notification::make()->title('CV entry saved')->success()->send();
            });

        return AdminDialog::edit($action, AdminDialogSize::Large);
    }

    public function deleteCvEntryAction(): Action
    {
        $action = Action::make('deleteCvEntry')
            ->label('Delete')
            ->color('danger')
            ->action(function (array $arguments): void {
                app(EditorialRecordService::class)->deleteCv($this->actionCvEntry($arguments));
                $this->clearSelections();
                $this->loadComponentProjection(refreshCvCount: true);
                Notification::make()->title('CV entry deleted')->success()->send();
            });

        return AdminDialog::confirm($action, 'Delete CV entry?', submitLabel: 'Delete', danger: true);
    }

    public function moveCvEntry(int $entryId, string $direction): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }

        /** @var CvEntry $entry */
        $entry = CvEntry::query()->findOrFail($entryId);
        $changed = app(EditorialRecordService::class)->move($entry, $direction);
        $this->clearSelections();
        $this->loadComponentProjection(refreshCvCount: true);

        if ($changed) {
            Notification::make()->title('CV order updated')->success()->send();
        }
    }

    public function transitionCvEntry(int $entryId, string $action): void
    {
        /** @var CvEntry $entry */
        $entry = CvEntry::query()->findOrFail($entryId);
        $service = app(EditorialRecordService::class);

        if ($action === 'publish') {
            $state = (string) $entry->getAttribute('state');
            if (in_array($state, ['archived', 'hidden'], true)) {
                /** @var CvEntry $entry */
                $entry = $service->restoreDraft($entry);
            }
            $service->publish($entry);
        } elseif ($action === 'unpublish') {
            $service->unpublish($entry);
        } else {
            throw ValidationException::withMessages(['state' => 'Unsupported CV publication action.']);
        }

        $this->clearSelections();
        $this->loadComponentProjection(refreshCvCount: true);
        Notification::make()->title('CV entry updated')->success()->send();
    }
}
