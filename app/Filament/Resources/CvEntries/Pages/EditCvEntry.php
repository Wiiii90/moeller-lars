<?php

namespace App\Filament\Resources\CvEntries\Pages;

use App\Domain\Admin\CvEntryEditorialService;
use App\Filament\Concerns\UsesAdminEditor;
use App\Filament\Resources\CvEntries\CvEntryResource;
use App\Models\CvEntry;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCvEntry extends EditRecord
{
    use UsesAdminEditor;

    protected static string $resource = CvEntryResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var CvEntry $entry */
        $entry = $record;

        return app(CvEntryEditorialService::class)->update($entry, $data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->editorReturnUrl(CvEntryResource::getUrl('index'));
    }
}
