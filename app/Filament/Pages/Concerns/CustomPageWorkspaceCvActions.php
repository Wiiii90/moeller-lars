<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\CvEntryEditorialService;
use App\Domain\Admin\EditorialRecordService;
use App\Models\CvEntry;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Illuminate\Validation\ValidationException;

trait CustomPageWorkspaceCvActions
{
    public function addCvEntryAction(): Action
    {
        return Action::make('addCvEntry')
            ->label('Add CV entry')
            ->fillForm(fn (): array => [
                'section' => 'CV',
                'publication_state' => 'unpublished',
                'date_precision' => 'unknown',
            ])
            ->schema($this->cvEntryCreateSchema())
            ->modalHeading('Add CV entry')
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Add entry')->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action->label('Cancel')->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (array $data): void {
                $entry = app(CvEntryEditorialService::class)->createDraft($this->cvEntryPayload($data));
                if (($data['publication_state'] ?? 'unpublished') === 'published') {
                    app(EditorialRecordService::class)->publish($entry);
                }
                $this->clearSelections();
                $this->loadComponentProjection(refreshCvCount: true);
                Notification::make()->title('CV entry added')->success()->send();
            });
    }

    public function editCvEntryAction(): Action
    {
        return Action::make('editCvEntry')
            ->label('Edit')
            ->fillForm(function (array $arguments): array {
                $entry = $this->actionCvEntry($arguments);

                return $this->cvEntryEditorRow($entry);
            })
            ->schema($this->cvEntrySchema())
            ->modalHeading(fn (array $arguments): string => 'Edit '.(string) $this->actionCvEntry($arguments)->getAttribute('title'))
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Save')->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action->label('Cancel')->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (array $data, array $arguments): void {
                app(CvEntryEditorialService::class)->update($this->actionCvEntry($arguments), $this->cvEntryPayload($data));
                $this->clearSelections();
                $this->loadComponentProjection(refreshCvCount: true);
                Notification::make()->title('CV entry saved')->success()->send();
            });
    }

    public function deleteCvEntryAction(): Action
    {
        return Action::make('deleteCvEntry')
            ->label('Delete')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete CV entry?')
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Delete')->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action->label('Cancel')->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (array $arguments): void {
                app(EditorialRecordService::class)->deleteCv($this->actionCvEntry($arguments));
                $this->clearSelections();
                $this->loadComponentProjection(refreshCvCount: true);
                Notification::make()->title('CV entry deleted')->success()->send();
            });
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
