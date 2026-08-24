<?php

namespace App\Filament\Resources\Exhibitions\Pages;

use App\Domain\Content\ExhibitionEditorialService;
use App\Filament\Concerns\UsesAdminEditor;
use App\Filament\Pages\JournalWorkspace;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Models\Exhibition;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditExhibition extends EditRecord
{
    use UsesAdminEditor;

    protected static string $resource = ExhibitionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['site_section_id'] = (int) $this->exhibition()->getAttribute('site_section_id');

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Exhibition $record */
        return app(ExhibitionEditorialService::class)->update($record, $data);
    }

    protected function getRedirectUrl(): string
    {
        $sectionId = (int) $this->exhibition()->getAttribute('site_section_id');

        return $this->editorReturnUrl(JournalWorkspace::getUrl(['section' => $sectionId]));
    }

    private function exhibition(): Exhibition
    {
        /** @var Exhibition $record */
        $record = $this->getRecord();

        return $record;
    }
}
