<?php

namespace App\Filament\Resources\CvEntries\Pages;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\EditorialRichTextValidator;
use App\Filament\Concerns\UsesAdminEditor;
use App\Filament\Resources\CvEntries\CvEntryResource;
use App\Models\CvEntry;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditCvEntry extends EditRecord
{
    use UsesAdminEditor;

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
}
