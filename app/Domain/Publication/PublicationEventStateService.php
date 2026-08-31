<?php

namespace App\Domain\Publication;

use App\Models\AuditEvent;
use App\Models\PublicationEventState;
use Illuminate\Support\Facades\DB;

final class PublicationEventStateService
{
    /**
     * Audit entity -> snapshot rows whose current divergence gives that event generation publication meaning.
     * @var array<string, list<array{table:string,column:string}>>
     */
    private const ENTITY_ROWS = [
        'artwork_category' => [
            ['table' => 'artwork_categories', 'column' => 'id'],
            ['table' => 'artworks', 'column' => 'artwork_category_id'],
        ],
        'artwork' => [
            ['table' => 'artworks', 'column' => 'id'],
            ['table' => 'artwork_media', 'column' => 'artwork_id'],
        ],
        'media_asset' => [
            ['table' => 'media_assets', 'column' => 'id'],
            ['table' => 'media_variants', 'column' => 'media_asset_id'],
        ],
        'site_section' => [
            ['table' => 'site_sections', 'column' => 'id'],
            ['table' => 'custom_page_settings', 'column' => 'site_section_id'],
            ['table' => 'journal_settings', 'column' => 'site_section_id'],
            ['table' => 'home_presentation_settings', 'column' => 'site_section_id'],
        ],
        'cv_entry' => [
            ['table' => 'cv_entries', 'column' => 'id'],
        ],
        'exhibition' => [
            ['table' => 'exhibitions', 'column' => 'id'],
            ['table' => 'exhibition_media', 'column' => 'exhibition_id'],
            ['table' => 'journal_entry_media', 'column' => 'exhibition_id'],
        ],
        'blog_post' => [
            ['table' => 'blog_posts', 'column' => 'id'],
            ['table' => 'journal_entry_media', 'column' => 'blog_post_id'],
        ],
        'public_content_setting' => [
            ['table' => 'public_content_settings', 'column' => 'id'],
        ],
    ];

    public static function tracks(string $entityType): bool
    {
        return array_key_exists($entityType, self::ENTITY_ROWS);
    }

    public function record(AuditEvent $event): void
    {
        $entityType = (string) $event->getAttribute('entity_type');
        $entityId = (int) $event->getAttribute('entity_id');
        if (! self::tracks($entityType) || $entityId < 1) {
            return;
        }

        $pending = $this->entityHasPendingChanges($entityType, $entityId);
        $now = now();

        if (! $pending) {
            $this->markGenerationNotPending($entityType, $entityId, $now);
        }

        DB::table('publication_event_states')->updateOrInsert(
            ['audit_event_id' => (int) $event->getKey()],
            [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'status' => $pending ? PublicationEventState::STATUS_PENDING : PublicationEventState::STATUS_NOT_PENDING,
                'updated_at' => $now,
            ],
        );
    }

    /** @return list<int> */
    public function pendingEventIdsForCommit(): array
    {
        $entities = DB::table('publication_event_states')
            ->leftJoin('publication_checkpoint_events', 'publication_checkpoint_events.audit_event_id', '=', 'publication_event_states.audit_event_id')
            ->whereNull('publication_checkpoint_events.audit_event_id')
            ->where('publication_event_states.status', PublicationEventState::STATUS_PENDING)
            ->select(['publication_event_states.entity_type', 'publication_event_states.entity_id'])
            ->distinct()
            ->orderBy('publication_event_states.entity_type')
            ->orderBy('publication_event_states.entity_id')
            ->get();

        $ids = [];

        foreach ($entities as $entity) {
            $entityType = (string) $entity->entity_type;
            $entityId = (int) $entity->entity_id;
            if (! $this->entityHasPendingChanges($entityType, $entityId)) {
                $this->markGenerationNotPending($entityType, $entityId, now());
                continue;
            }

            $entityIds = DB::table('publication_event_states')
                ->leftJoin('publication_checkpoint_events', 'publication_checkpoint_events.audit_event_id', '=', 'publication_event_states.audit_event_id')
                ->whereNull('publication_checkpoint_events.audit_event_id')
                ->where('publication_event_states.entity_type', $entityType)
                ->where('publication_event_states.entity_id', $entityId)
                ->where('publication_event_states.status', PublicationEventState::STATUS_PENDING)
                ->orderBy('publication_event_states.audit_event_id')
                ->pluck('publication_event_states.audit_event_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            $ids = array_merge($ids, $entityIds);
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    public function entityHasPendingChanges(string $entityType, int $entityId): bool
    {
        $rows = self::ENTITY_ROWS[$entityType] ?? [];
        foreach ($rows as $definition) {
            $table = $definition['table'];
            $column = $definition['column'];
            $result = DB::selectOne(
                "SELECT EXISTS (SELECT 1 FROM public.{$table} AS working FULL OUTER JOIN committed.{$table} AS committed USING (id) WHERE (working.{$column} = ? OR committed.{$column} = ?) AND to_jsonb(working) IS DISTINCT FROM to_jsonb(committed)) AS pending",
                [$entityId, $entityId],
            );

            if (in_array($result?->pending ?? false, [true, 1, '1', 't'], true)) {
                return true;
            }
        }

        return false;
    }

    private function markGenerationNotPending(string $entityType, int $entityId, mixed $updatedAt): void
    {
        DB::table('publication_event_states')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('status', PublicationEventState::STATUS_PENDING)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('publication_checkpoint_events')
                    ->whereColumn('publication_checkpoint_events.audit_event_id', 'publication_event_states.audit_event_id');
            })
            ->update([
                'status' => PublicationEventState::STATUS_NOT_PENDING,
                'updated_at' => $updatedAt,
            ]);
    }
}
