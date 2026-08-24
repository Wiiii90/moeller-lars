<?php

namespace App\Filament\Resources\MediaAssets\Pages;

use App\Domain\Media\MediaAssetEditorialService;
use App\Filament\Concerns\UsesAdminEditor;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Models\MediaAsset;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMediaAsset extends EditRecord
{
    use UsesAdminEditor;

    protected static string $resource = MediaAssetResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var MediaAsset $asset */
        $asset = $record;

        return app(MediaAssetEditorialService::class)->updateMetadata($asset, $data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->editorReturnUrl(MediaAssetResource::getUrl('index'));
    }
}
