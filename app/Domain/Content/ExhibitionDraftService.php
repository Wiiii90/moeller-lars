<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\EditorialRichTextValidator;
use App\Models\Exhibition;
use App\Models\SiteSection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ExhibitionDraftService
{
    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly EditorialRichTextValidator $richText,
        private readonly JournalEntryOrderService $order,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): Exhibition
    {
        $this->richText->validate($data['description'] ?? null, 'description');

        foreach (['state', 'position', 'published_at', 'legacy_id', 'legacy_source', 'migration_batch_id', 'migrated_at'] as $field) {
            unset($data[$field]);
        }

        $sectionId = $this->journalSectionId($data['site_section_id'] ?? null);
        $data['site_section_id'] = $sectionId;
        $data['state'] = 'draft';
        $data['published_at'] = null;
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($data, $sectionId, $actor): Exhibition {
            $payload = $data;
            $payload['position'] = $this->order->nextPosition(new Exhibition, $sectionId);

            $exhibition = new Exhibition;
            $exhibition->fill($payload);
            $exhibition->save();
            $this->audit->record(
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
