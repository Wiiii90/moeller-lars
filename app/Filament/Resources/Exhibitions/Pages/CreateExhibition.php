<?php

namespace App\Filament\Resources\Exhibitions\Pages;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\EditorialRichTextValidator;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Models\Exhibition;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateExhibition extends CreateRecord
{
    protected static string $resource = ExhibitionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        app(EditorialRichTextValidator::class)->validate($data['description'] ?? null, 'description');

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
            $lastPosition = Exhibition::query()->orderByDesc('position')->lockForUpdate()->value('position');
            $data['position'] = $lastPosition === null ? 0 : ((int) $lastPosition) + 1;

            $exhibition = new Exhibition;
            $exhibition->fill($data);
            $exhibition->save();
            app(AdminAuditService::class)->record($actor, 'exhibition.created', 'exhibition', $exhibition->getKey());

            return $exhibition;
        });
    }
}
