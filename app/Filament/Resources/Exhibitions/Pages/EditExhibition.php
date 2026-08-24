<?php

namespace App\Filament\Resources\Exhibitions\Pages;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\EditorialRichTextValidator;
use App\Filament\Concerns\UsesAdminEditor;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Models\Exhibition;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditExhibition extends EditRecord
{
    use UsesAdminEditor;

    protected static string $resource = ExhibitionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        app(EditorialRichTextValidator::class)->validate($data['description'] ?? null, 'description');

        foreach (['state', 'position', 'published_at', 'legacy_id', 'legacy_source', 'migration_batch_id', 'migrated_at'] as $field) {
            unset($data[$field]);
        }

        $data['site_section_id'] = (int) $this->exhibition()->getAttribute('site_section_id');

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = app(AdminAuditService::class)->requireActor();

        return DB::transaction(function () use ($record, $data, $actor): Model {
            /** @var Exhibition $exhibition */
            $exhibition = $record;
            $originalSectionId = (int) $exhibition->getAttribute('site_section_id');
            $data['site_section_id'] = $originalSectionId;
            $exhibition->fill($data);

            if ((int) $exhibition->getAttribute('site_section_id') !== $originalSectionId) {
                throw ValidationException::withMessages(['site_section_id' => 'Move exhibitions between Journals through an explicit editorial workflow.']);
            }

            if ($exhibition->isDirty()) {
                $exhibition->save();
                app(AdminAuditService::class)->record($actor, 'exhibition.updated', 'exhibition', $exhibition->getKey());
            }

            return $exhibition;
        });
    }

    protected function getRedirectUrl(): string
    {
        $sectionId = (int) $this->exhibition()->getAttribute('site_section_id');

        return $this->editorReturnUrl(ExhibitionResource::getUrl('index', ['section' => $sectionId]));
    }

    private function exhibition(): Exhibition
    {
        /** @var Exhibition $record */
        $record = $this->getRecord();

        return $record;
    }
}
