<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('admin_activity_ordering_groups')
            || ! Schema::hasTable('admin_activity_ordering_events')
        ) {
            return;
        }

        DB::transaction(function (): void {
            DB::table('admin_activity_ordering_events')->delete();
            DB::table('admin_activity_ordering_groups')->delete();

            $committedEventIds = Schema::hasTable('publication_checkpoint_events')
                ? DB::table('publication_checkpoint_events')
                    ->pluck('audit_event_id')
                    ->mapWithKeys(static fn (mixed $id): array => [(int) $id => true])
                    ->all()
                : [];

            /** @var array<string, array<string, mixed>> $latest */
            $latest = [];

            DB::table('audit_events')
                ->whereIn('action', $this->orderingActions())
                ->select(['id', 'admin_user_id', 'action', 'entity_id', 'occurred_at', 'metadata'])
                ->orderBy('id')
                ->chunkById(500, function ($events) use (&$latest, $committedEventIds): void {
                    foreach ($events as $event) {
                        $ordering = $this->orderingMetadata($event->metadata);

                        if ($ordering === null) {
                            $this->insertLegacySingleton($event);

                            continue;
                        }

                        $key = $event->action.'|'.$ordering['scope'];
                        $candidate = $latest[$key] ?? null;
                        $canAppend = is_array($candidate)
                            && (int) ($candidate['admin_user_id'] ?? 0) === (int) ($event->admin_user_id ?? 0)
                            && ! hash_equals((string) $candidate['before_hash'], (string) $candidate['after_hash'])
                            && hash_equals((string) $candidate['after_hash'], $ordering['before_hash'])
                            && ! isset($committedEventIds[(int) $candidate['last_audit_event_id']]);

                        if ($canAppend) {
                            $groupId = (int) $candidate['id'];
                            $sequence = ((int) $candidate['event_count']) + 1;
                            DB::table('admin_activity_ordering_groups')
                                ->where('id', $groupId)
                                ->update([
                                    'last_audit_event_id' => (int) $event->id,
                                    'event_count' => $sequence,
                                    'item_count' => max((int) $candidate['item_count'], $ordering['item_count']),
                                    'after_hash' => $ordering['after_hash'],
                                    'ended_at' => $event->occurred_at,
                                ]);

                            $candidate['last_audit_event_id'] = (int) $event->id;
                            $candidate['event_count'] = $sequence;
                            $candidate['item_count'] = max((int) $candidate['item_count'], $ordering['item_count']);
                            $candidate['after_hash'] = $ordering['after_hash'];
                            $candidate['ended_at'] = $event->occurred_at;
                            $latest[$key] = $candidate;
                        } else {
                            $groupId = (int) DB::table('admin_activity_ordering_groups')->insertGetId([
                                'admin_user_id' => $event->admin_user_id,
                                'scope' => $ordering['scope'],
                                'action' => (string) $event->action,
                                'target_label' => $ordering['target_label'] ?? $this->scopeLabel($ordering['scope']),
                                'first_audit_event_id' => (int) $event->id,
                                'last_audit_event_id' => (int) $event->id,
                                'event_count' => 1,
                                'item_count' => $ordering['item_count'],
                                'before_hash' => $ordering['before_hash'],
                                'after_hash' => $ordering['after_hash'],
                                'started_at' => $event->occurred_at,
                                'ended_at' => $event->occurred_at,
                            ]);
                            $sequence = 1;
                            $latest[$key] = [
                                'id' => $groupId,
                                'admin_user_id' => $event->admin_user_id,
                                'last_audit_event_id' => (int) $event->id,
                                'event_count' => 1,
                                'item_count' => $ordering['item_count'],
                                'before_hash' => $ordering['before_hash'],
                                'after_hash' => $ordering['after_hash'],
                                'ended_at' => $event->occurred_at,
                            ];
                        }

                        DB::table('admin_activity_ordering_events')->insert([
                            'audit_event_id' => (int) $event->id,
                            'group_id' => $groupId,
                            'sequence' => $sequence,
                        ]);
                    }
                });
        });
    }

    public function down(): void
    {
        // The projection is derived from immutable audit_events. Reverting code does not
        // reconstruct the previous heuristic grouping because that would reintroduce
        // non-factual timer semantics.
    }

    /** @return list<string> */
    private function orderingActions(): array
    {
        return [
            'artwork.additional_media_reordered',
            'artwork_category.gallery_reordered',
            'site_section.reordered',
            'site_section.home_artworks_reordered',
            'site_section.home_components_reordered',
            'site_section.page_components_reordered',
            'site_section.list_entries_reordered',
            'site_section.contact_items_reordered',
            'blog_post.reordered',
            'exhibition.reordered',
            'cv_entry.reordered',
        ];
    }

    /** @return array{scope:string,target_label:?string,before_hash:string,after_hash:string,item_count:int}|null */
    private function orderingMetadata(mixed $metadata): ?array
    {
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        } elseif (is_object($metadata)) {
            $metadata = (array) $metadata;
        }

        if (! is_array($metadata)) {
            return null;
        }

        $ordering = $metadata['ordering'] ?? null;
        if (! is_array($ordering)) {
            return null;
        }

        $scope = $ordering['scope'] ?? null;
        $beforeHash = $ordering['before_hash'] ?? null;
        $afterHash = $ordering['after_hash'] ?? null;
        $itemCount = $ordering['item_count'] ?? null;
        $targetLabel = $ordering['target_label'] ?? null;

        if (
            ! is_string($scope)
            || trim($scope) === ''
            || ! is_string($beforeHash)
            || preg_match('/^[a-f0-9]{64}$/', $beforeHash) !== 1
            || ! is_string($afterHash)
            || preg_match('/^[a-f0-9]{64}$/', $afterHash) !== 1
            || ! is_int($itemCount)
            || $itemCount < 0
            || ($targetLabel !== null && ! is_string($targetLabel))
        ) {
            return null;
        }

        return [
            'scope' => trim($scope),
            'target_label' => is_string($targetLabel) && trim($targetLabel) !== '' ? trim($targetLabel) : null,
            'before_hash' => $beforeHash,
            'after_hash' => $afterHash,
            'item_count' => $itemCount,
        ];
    }

    private function insertLegacySingleton(object $event): void
    {
        $scope = $this->legacyScope($event);
        if ($scope === null) {
            return;
        }

        $groupId = (int) DB::table('admin_activity_ordering_groups')->insertGetId([
            'admin_user_id' => $event->admin_user_id,
            'scope' => $scope,
            'action' => (string) $event->action,
            'target_label' => $this->scopeLabel($scope),
            'first_audit_event_id' => (int) $event->id,
            'last_audit_event_id' => (int) $event->id,
            'event_count' => 1,
            'item_count' => 0,
            'before_hash' => hash('sha256', 'legacy-ordering-start:'.(int) $event->id),
            'after_hash' => hash('sha256', 'legacy-ordering-end:'.(int) $event->id),
            'started_at' => $event->occurred_at,
            'ended_at' => $event->occurred_at,
        ]);

        DB::table('admin_activity_ordering_events')->insert([
            'audit_event_id' => (int) $event->id,
            'group_id' => $groupId,
            'sequence' => 1,
        ]);
    }

    private function legacyScope(object $event): ?string
    {
        $metadata = $event->metadata;
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        } elseif (is_object($metadata)) {
            $metadata = (array) $metadata;
        }
        $metadata = is_array($metadata) ? $metadata : [];

        return match ((string) $event->action) {
            'artwork.additional_media_reordered' => 'artwork-media:'.(int) $event->entity_id,
            'artwork_category.gallery_reordered' => 'gallery-artworks:'.(int) $event->entity_id,
            'site_section.reordered' => 'site-navigation',
            'blog_post.reordered' => isset($metadata['site_section_id'])
                ? 'journal-blog:'.(int) $metadata['site_section_id']
                : null,
            'exhibition.reordered' => isset($metadata['site_section_id'])
                ? 'journal-exhibitions:'.(int) $metadata['site_section_id']
                : null,
            'cv_entry.reordered' => 'legacy-cv',
            default => null,
        };
    }

    private function scopeLabel(string $scope): ?string
    {
        if ($scope === 'site-navigation') {
            return 'Public navigation';
        }

        if (preg_match('/^gallery-artworks:(\d+)$/', $scope, $matches) === 1) {
            return DB::table('artwork_categories')->where('id', (int) $matches[1])->value('name');
        }
        if (preg_match('/^artwork-media:(\d+)$/', $scope, $matches) === 1) {
            return DB::table('artworks')->where('id', (int) $matches[1])->value('title');
        }
        if (preg_match('/^(?:journal-blog|journal-exhibitions|home-artworks|home-components|page-components|page-list|page-contact):(\d+)/', $scope, $matches) === 1) {
            return DB::table('site_sections')->where('id', (int) $matches[1])->value('title');
        }

        return null;
    }
};
