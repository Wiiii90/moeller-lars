<?php

namespace App\Filament\Resources\Exhibitions\Pages;

use App\Domain\Content\ExhibitionDraftService;
use App\Filament\Concerns\UsesEditorOverlay;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateExhibition extends CreateRecord
{
    use UsesEditorOverlay;

    protected static string $resource = ExhibitionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(ExhibitionDraftService::class)->create($data);
    }

    protected function getRedirectUrl(): string
    {
        $sectionId = (int) $this->getRecord()->getAttribute('site_section_id');

        return $this->editorReturnUrl(ExhibitionResource::getUrl('index', ['section' => $sectionId]));
    }
}
