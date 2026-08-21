<?php

namespace App\Filament\Resources\Exhibitions\Pages;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\EditorialRichTextValidator;
use App\Domain\Content\JournalEntryOrderService;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Models\Exhibition;
use App\Models\SiteSection;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateExhibition extends CreateRecord
{
    protected static string $resource = ExhibitionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        app(EditorialRichTextValidator::class)->validate($data['description'] ?? null, 'description');

        foreach (['state', 'position', 'published_at', 'legacy_id', 'legacy_source', 'migration_batch_id', 'migrated_at'] as $field) {
            unset($data[$field]);
        }

        $data['site_section_id'] = $this->journalSectionId($data['site_section_id'] ?? null);
        $data['state'] = 'draft';
        $data['published_at'] = null;

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $actor = app(AdminAuditService::class)->requireActor();
        $sectionId = $this->journalSectionId($data['site_section_id'] ?? null);

        return DB::transaction(function () use ($data, $sectionId, $actor): Model {
            $data['site_section_id'] = $sectionId;
            $data['position'] = app(JournalEntryOrderService::class)->nextPosition(new Exhibition, $sectionId);

            $exhibition = new Exhibition;
            $exhibition->fill($data);
            $exhibition->save();
            app(AdminAuditService::class)->record(
                $actor,
                'exhibition.created',
                'exhibition',
                $exhibition->getKey(),
                ['site_section_id' => $sectionId],
            );

            return $exhibition;
        });
    }

    private function journalSectionId(mixed $value): int
    {
        $sectionId = filter_var($value, FILTER_VALIDATE_INT);
        if ($sectionId === false || $sectionId <= 0) {
            throw ValidationException::withMessages(['site_section_id' => 'Choose an Exhibitions Journal page.']);
        }

        $exists = SiteSection::query()
            ->whereKey($sectionId)
            ->where('type', SiteSection::TYPE_JOURNAL)
            ->where('template', SiteSection::JOURNAL_TEMPLATE_EXHIBITIONS)
            ->exists();
        if (! $exists) {
            throw ValidationException::withMessages(['site_section_id' => 'The selected page is not an Exhibitions Journal.']);
        }

        return (int) $sectionId;
    }
}
