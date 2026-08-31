<?php

namespace App\Domain\Admin;

use App\Models\CvEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CvEntryEditorialService
{
    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly EditorialRichTextValidator $richText,
        private readonly EditorialRecordService $records,
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
     * Synchronize the ordered CV collection edited inside a CV List component.
     * The form rows are transient editor state only; canonical data remains in
     * CvEntry records and their existing lifecycle/order services.
     *
     * @param list<array<string, mixed>> $rows
     */
    public function syncOrdered(array $rows): void
    {
        if (! array_is_list($rows)) {
            throw ValidationException::withMessages(['cv_entries' => 'CV entries must be an ordered list.']);
        }

        DB::transaction(function () use ($rows): void {
            /** @var array<int, CvEntry> $existing */
            $existing = CvEntry::query()
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (CvEntry $entry): int => (int) $entry->getKey())
                ->all();

            $seen = [];
            $ordered = [];

            foreach ($rows as $rowIndex => $row) {
                if (! is_array($row)) {
                    throw ValidationException::withMessages([
                        'cv_entries.'.$rowIndex => 'Each CV entry must be structured data.',
                    ]);
                }

                $id = $this->rowId($row['id'] ?? null);
                if ($id !== null) {
                    if (isset($seen[$id]) || ! isset($existing[$id])) {
                        throw ValidationException::withMessages([
                            'cv_entries.'.$rowIndex => 'A CV entry changed while this dialog was open. Reload and try again.',
                        ]);
                    }
                    $seen[$id] = true;
                    $entry = $this->update($existing[$id], $this->rowPayload($row));
                } else {
                    $entry = $this->createDraft($this->rowPayload($row));
                }

                $entry = $this->applyPublicationState($entry, $row['publication_state'] ?? 'unpublished');
                $ordered[] = $entry;
            }

            foreach ($existing as $id => $entry) {
                if (! isset($seen[$id])) {
                    $this->records->deleteCv($entry);
                }
            }

            foreach ($ordered as $position => $entry) {
                /** @var CvEntry $current */
                $current = CvEntry::query()->findOrFail($entry->getKey());
                $currentPosition = (int) $current->getAttribute('position');
                if ($currentPosition !== $position) {
                    $this->records->sortCv($current, $position);
                }
            }
        });
    }

    /**
     * Accept only fields from the current CV editorial contract while preserving
     * lifecycle and migration metadata outside normal editing.
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

        if (array_key_exists('image_media_asset_id', $data)) {
            $data['image_media_asset_id'] = is_numeric($data['image_media_asset_id'])
                ? (int) $data['image_media_asset_id']
                : null;
        }

        return $data;
    }

    private function rowId(mixed $id): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }
        if (! is_numeric($id) || (int) $id <= 0) {
            throw ValidationException::withMessages(['cv_entries' => 'A CV entry identifier is invalid.']);
        }

        return (int) $id;
    }

    /** @param array<string, mixed> $row
     *  @return array<string, mixed>
     */
    private function rowPayload(array $row): array
    {
        return [
            'section' => $row['section'] ?? 'CV',
            'title' => $row['title'] ?? null,
            'year_text' => $row['year_text'] ?? null,
            'date_precision' => $row['date_precision'] ?? 'unknown',
            'starts_on' => $row['starts_on'] ?? null,
            'ends_on' => $row['ends_on'] ?? null,
            'organisation' => $row['organisation'] ?? null,
            'location' => $row['location'] ?? null,
            'body' => $row['body'] ?? null,
            'image_media_asset_id' => $row['image_media_asset_id'] ?? null,
            'external_url' => $row['external_url'] ?? null,
        ];
    }

    private function applyPublicationState(CvEntry $entry, mixed $state): CvEntry
    {
        if (! is_string($state) || ! in_array($state, ['published', 'unpublished'], true)) {
            throw ValidationException::withMessages(['cv_entries' => 'Choose a supported CV publication state.']);
        }

        $current = (string) $entry->getAttribute('state');
        if ($state === 'published') {
            if (in_array($current, ['archived', 'hidden'], true)) {
                /** @var CvEntry $entry */
                $entry = $this->records->restoreDraft($entry);
                $current = 'draft';
            }
            if ($current === 'draft') {
                /** @var CvEntry $entry */
                $entry = $this->records->publish($entry);
            }

            return $entry;
        }

        if ($current === 'published') {
            /** @var CvEntry $entry */
            $entry = $this->records->unpublish($entry);
        }

        return $entry;
    }
}
