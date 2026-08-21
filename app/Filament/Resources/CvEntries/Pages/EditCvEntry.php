<?php

namespace App\Filament\Resources\CvEntries\Pages;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\EditorialRecordService;
use App\Domain\Admin\EditorialRichTextValidator;
use App\Filament\Concerns\UsesEditorOverlay;
use App\Filament\Resources\CvEntries\CvEntryResource;
use App\Models\CvEntry;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditCvEntry extends EditRecord
{
    use UsesEditorOverlay;

    protected static string $resource = CvEntryResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        app(EditorialRichTextValidator::class)->validate($data['body'] ?? null, 'body');

        foreach (['state', 'position', 'published_at', 'legacy_id', 'legacy_source', 'migration_batch_id', 'migrated_at'] as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = app(AdminAuditService::class)->requireActor();

        return DB::transaction(function () use ($record, $data, $actor): Model {
            /** @var CvEntry $entry */
            $entry = $record;
            $entry->fill($data);

            if ($entry->isDirty()) {
                $entry->save();
                app(AdminAuditService::class)->record($actor, 'cv_entry.updated', 'cv_entry', $entry->getKey());
            }

            return $entry;
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->editorReturnUrl(CvEntryResource::getUrl('index'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label('Publish')
                ->visible(fn (): bool => $this->entry()->getAttribute('state') === 'draft')
                ->action(function (): void {
                    try {
                        app(EditorialRecordService::class)->publish($this->entry());
                    } catch (ValidationException) {
                        Notification::make()->title('Vita / CV entry cannot be published')->danger()->send();

                        return;
                    }

                    $this->entry()->refresh();
                    Notification::make()->title('Vita / CV entry published')->success()->send();
                }),
            Action::make('unpublish')
                ->label('Unpublish')
                ->visible(fn (): bool => $this->entry()->getAttribute('state') === 'published')
                ->requiresConfirmation()
                ->action(function (): void {
                    app(EditorialRecordService::class)->unpublish($this->entry());
                    $this->entry()->refresh();
                    Notification::make()->title('Vita / CV entry unpublished')->success()->send();
                }),
            Action::make('archive')
                ->label('Archive')
                ->visible(fn (): bool => $this->entry()->getAttribute('state') !== 'archived')
                ->requiresConfirmation()
                ->action(function (): void {
                    app(EditorialRecordService::class)->archive($this->entry());
                    $this->entry()->refresh();
                    Notification::make()->title('Vita / CV entry archived')->success()->send();
                }),
            Action::make('restoreDraft')
                ->label('Restore to draft')
                ->visible(fn (): bool => in_array($this->entry()->getAttribute('state'), ['archived', 'hidden'], true))
                ->action(function (): void {
                    app(EditorialRecordService::class)->restoreDraft($this->entry());
                    $this->entry()->refresh();
                    Notification::make()->title('Vita / CV entry restored to draft')->success()->send();
                }),
        ];
    }

    private function entry(): CvEntry
    {
        /** @var CvEntry $record */
        $record = $this->getRecord();

        return $record;
    }
}
