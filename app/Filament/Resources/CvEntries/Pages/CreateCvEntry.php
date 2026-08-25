<?php

namespace App\Filament\Resources\CvEntries\Pages;

use App\Domain\Admin\CvEntryEditorialService;
use App\Filament\Concerns\UsesAdminEditor;
use App\Filament\Resources\CvEntries\CvEntryResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCvEntry extends CreateRecord
{
    use UsesAdminEditor;

    protected static string $resource = CvEntryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CvEntryEditorialService::class)->createDraft($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->editorReturnUrl(CvEntryResource::getUrl('index'));
    }
}
