<?php

namespace App\Filament\Resources\CvEntries\Pages;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\EditorialRichTextValidator;
use App\Filament\Concerns\UsesEditorOverlay;
use App\Filament\Resources\CvEntries\CvEntryResource;
use App\Models\CvEntry;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateCvEntry extends CreateRecord
{
    use UsesEditorOverlay;

    protected static string $resource = CvEntryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        app(EditorialRichTextValidator::class)->validate($data['body'] ?? null, 'body');

        foreach (['state', 'position', 'published_at', 'legacy_id', 'legacy_source', 'migration_batch_id', 'migrated_at'] as $field) {
            unset($data[$field]);
        }

        $data['state'] = 'draft';
        $data['published_at'] = null;

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $actor = app(AdminAuditService::class)->requireActor();

        return DB::transaction(function () use ($data, $actor): Model {
            $lastPosition = CvEntry::query()->orderByDesc('position')->lockForUpdate()->value('position');
            $data['position'] = $lastPosition === null ? 0 : ((int) $lastPosition) + 1;

            $entry = new CvEntry;
            $entry->fill($data);
            $entry->save();
            app(AdminAuditService::class)->record($actor, 'cv_entry.created', 'cv_entry', $entry->getKey());

            return $entry;
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->editorReturnUrl(CvEntryResource::getUrl('index'));
    }
}
