<?php

namespace App\Filament\Resources\Exhibitions\Pages;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\EditorialRecordService;
use App\Domain\Admin\EditorialRichTextValidator;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Models\Exhibition;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditExhibition extends EditRecord
{
    protected static string $resource = ExhibitionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        app(EditorialRichTextValidator::class)->validate($data['description'] ?? null, 'description');

        foreach (['state', 'position', 'published_at', 'legacy_id', 'legacy_source', 'migration_batch_id', 'migrated_at'] as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = app(AdminAuditService::class)->requireActor();

        return DB::transaction(function () use ($record, $data, $actor): Model {
            /** @var Exhibition $exhibition */
            $exhibition = $record;
            $exhibition->fill($data);

            if ($exhibition->isDirty()) {
                $exhibition->save();
                app(AdminAuditService::class)->record($actor, 'exhibition.updated', 'exhibition', $exhibition->getKey());
            }

            return $exhibition;
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label('Publish')
                ->visible(fn (): bool => $this->exhibition()->getAttribute('state') === 'draft')
                ->action(function (): void {
                    try {
                        app(EditorialRecordService::class)->publish($this->exhibition());
                    } catch (ValidationException) {
                        Notification::make()->title('Exhibition cannot be published')->danger()->send();

                        return;
                    }

                    $this->exhibition()->refresh();
                    Notification::make()->title('Exhibition published')->success()->send();
                }),
            Action::make('unpublish')
                ->label('Unpublish')
                ->visible(fn (): bool => $this->exhibition()->getAttribute('state') === 'published')
                ->requiresConfirmation()
                ->action(function (): void {
                    app(EditorialRecordService::class)->unpublish($this->exhibition());
                    $this->exhibition()->refresh();
                    Notification::make()->title('Exhibition unpublished')->success()->send();
                }),
            Action::make('archive')
                ->label('Archive')
                ->visible(fn (): bool => $this->exhibition()->getAttribute('state') !== 'archived')
                ->requiresConfirmation()
                ->action(function (): void {
                    app(EditorialRecordService::class)->archive($this->exhibition());
                    $this->exhibition()->refresh();
                    Notification::make()->title('Exhibition archived')->success()->send();
                }),
            Action::make('restoreDraft')
                ->label('Restore to draft')
                ->visible(fn (): bool => in_array($this->exhibition()->getAttribute('state'), ['archived', 'hidden'], true))
                ->action(function (): void {
                    app(EditorialRecordService::class)->restoreDraft($this->exhibition());
                    $this->exhibition()->refresh();
                    Notification::make()->title('Exhibition restored to draft')->success()->send();
                }),
        ];
    }

    private function exhibition(): Exhibition
    {
        /** @var Exhibition $record */
        $record = $this->getRecord();

        return $record;
    }
}
