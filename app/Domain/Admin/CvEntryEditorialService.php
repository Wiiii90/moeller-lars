<?php

namespace App\Domain\Admin;

use App\Models\CvEntry;
use Illuminate\Support\Facades\DB;

final class CvEntryEditorialService
{
    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly EditorialRichTextValidator $richText,
    ) {}

    /** @param array<string, mixed> $data */
    public function createDraft(array $data): CvEntry
    {
        $data = $this->editableData($data);
        $this->richText->validate($data['body'] ?? null, 'body', allowEmbeddedMedia: true);
        $data['state'] = 'draft';
        $data['published_at'] = null;
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($data, $actor): CvEntry {
            $lastPosition = CvEntry::query()
                ->orderByDesc('position')
                ->lockForUpdate()
                ->value('position');
            $data['position'] = $lastPosition === null ? 0 : ((int) $lastPosition) + 1;

            $entry = new CvEntry;
            $entry->fill($data);
            $entry->save();
            $this->audit->record($actor, 'cv_entry.created', 'cv_entry', (int) $entry->getKey());

            return $entry->fresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function update(CvEntry $entry, array $data): CvEntry
    {
        $data = $this->editableData($data);
        $this->richText->validate($data['body'] ?? null, 'body', allowEmbeddedMedia: true);
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($entry, $data, $actor): CvEntry {
            /** @var CvEntry $fresh */
            $fresh = CvEntry::query()
                ->whereKey($entry->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $fresh->fill($data);

            if ($fresh->isDirty()) {
                $fresh->save();
                $this->audit->record($actor, 'cv_entry.updated', 'cv_entry', (int) $fresh->getKey());
            }

            return $fresh->fresh();
        });
    }

    /**
     * Preserve compatibility-only editable fields supplied by the legacy resource,
     * while preventing lifecycle, ordering, migration and publication metadata writes.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function editableData(array $data): array
    {
        foreach ([
            'state',
            'position',
            'published_at',
            'legacy_id',
            'legacy_source',
            'migration_batch_id',
            'migrated_at',
        ] as $field) {
            unset($data[$field]);
        }

        return $data;
    }
}
