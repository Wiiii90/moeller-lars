<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('admin_activity_ordering_projections')
            || ! Schema::hasTable('admin_activity_ordering_events')
        ) {
            return;
        }

        DB::transaction(function (): void {
            DB::table('admin_activity_ordering_events')->delete();
            DB::table('admin_activity_ordering_projections')->delete();

            $committed = Schema::hasTable('publication_checkpoint_events')
                ? DB::table('publication_checkpoint_events')
                    ->pluck('audit_event_id')
                    ->mapWithKeys(static fn (mixed $id): array => [(int) $id => true])
                    ->all()
                : [];

            /** @var array<string, array<string,mixed>> $latest */
            $latest = [];

            DB::table('audit_events')
                ->whereIn('action', $this->orderingActions())
                ->select(['id', 'admin_user_id', 'action', 'entity_id', 'occurred_at', 'metadata'])
                ->orderBy('id')
                ->chunkById(500, function ($events) use (&$latest, $committed): void {
                    foreach ($events as $event) {
                        $ordering = $this->orderingMetadata($event);
                        if ($ordering === null) {
                            continue;
                        }

                        $key = (string) $event->action.'|'.$ordering['scope'];
                        $candidate = $latest[$key] ?? null;
                        $canAppend = is_array($candidate)
                            && (int) ($candidate['admin_user_id'] ?? 0) === (int) ($event->admin_user_id ?? 0)
                            && ($candidate['after_state'] ?? null) === $ordering['before_state']
                            && ! isset($committed[(int) ($candidate['last_audit_event_id'] ?? 0)]);

                        if ($canAppend) {
                            $projectionId = (int) $candidate['id'];
                            $sequence = ((int) $candidate['event_count']) + 1;

                            DB::table('admin_activity_ordering_projections')
                                ->where('id', $projectionId)
                                ->update([
                                    'last_audit_event_id' => (int) $event->id,
                                    'event_count' => $sequence,
                                    'item_count' => max((int) $candidate['item_count'], $ordering['item_count']),
                                    'after_state' => json_encode($ordering['after_state'], JSON_THROW_ON_ERROR),
                                    'ended_at' => $event->occurred_at,
                                ]);

                            $candidate['last_audit_event_id'] = (int) $event->id;
                            $candidate['event_count'] = $sequence;
                            $candidate['item_count'] = max((int) $candidate['item_count'], $ordering['item_count']);
                            $candidate['after_state'] = $ordering['after_state'];
                            $candidate['ended_at'] = $event->occurred_at;
                            $latest[$key] = $candidate;
                        } else {
                            $projectionId = (int) DB::table('admin_activity_ordering_projections')->insertGetId([
                                'admin_user_id' => $event->admin_user_id,
                                'scope' => $ordering['scope'],
                                'action' => (string) $event->action,
                                'target_label' => $ordering['target_label'],
                                'first_audit_event_id' => (int) $event->id,
                                'last_audit_event_id' => (int) $event->id,
                                'event_count' => 1,
                                'item_count' => $ordering['item_count'],
                                'before_state' => json_encode($ordering['before_state'], JSON_THROW_ON_ERROR),
                                'after_state' => json_encode($ordering['after_state'], JSON_THROW_ON_ERROR),
                                'started_at' => $event->occurred_at,
                                'ended_at' => $event->occurred_at,
                            ]);
                            $sequence = 1;
                            $latest[$key] = [
                                'id' => $projectionId,
                                'admin_user_id' => $event->admin_user_id,
                                'last_audit_event_id' => (int) $event->id,
                                'event_count' => 1,
                                'item_count' => $ordering['item_count'],
                                'before_state' => $ordering['before_state'],
                                'after_state' => $ordering['after_state'],
                                'ended_at' => $event->occurred_at,
                            ];
                        }

                        DB::table('admin_activity_ordering_events')->insert([
                            'audit_event_id' => (int) $event->id,
                            'projection_id' => $projectionId,
                            'sequence' => $sequence,
                        ]);
                    }
                });
        });
    }

    public function down(): void
    {
        DB::table('admin_activity_ordering_events')->delete();
        DB::table('admin_activity_ordering_projections')->delete();
    }

    /**
     * Canonical full-state metadata is preferred. The short-lived hash-only #177
     * format can still be composed exactly by equality, without a time heuristic.
     *
     * @return array{scope:string,target_label:?string,before_state:list<string>,after_state:list<string>,item_count:int}|null
     */
    private function orderingMetadata(object $event): ?array
    {
        $metadata = $this->metadata($event->metadata);
        $ordering = $metadata['ordering'] ?? null;
        if (! is_array($ordering)) {
            return null;
        }

        $scope = $ordering['scope'] ?? null;
        $itemCount = $ordering['item_count'] ?? null;
        if (! is_string($scope) || trim($scope) === '' || ! is_int($itemCount) || $itemCount < 0) {
            return null;
        }

        $before = $this->state($ordering['before_state'] ?? null);
        $after = $this->state($ordering['after_state'] ?? null);

        if ($before === null || $after === null) {
            $beforeHash = $ordering['before_hash'] ?? null;
            $afterHash = $ordering['after_hash'] ?? null;
            if (
                ! is_string($beforeHash)
                || preg_match('/^[a-f0-9]{64}$/', $beforeHash) !== 1
                || ! is_string($afterHash)
                || preg_match('/^[a-f0-9]{64}$/', $afterHash) !== 1
            ) {
                return null;
            }

            $before = ['compat-hash:'.$beforeHash];
            $after = ['compat-hash:'.$afterHash];
        }

        $targetLabel = $ordering['target_label'] ?? ($metadata['target_label'] ?? null);

        return [
            'scope' => trim($scope),
            'target_label' => is_string($targetLabel) && trim($targetLabel) !== ''
                ? trim($targetLabel)
                : $this->scopeLabel(trim($scope)),
            'before_state' => $before,
            'after_state' => $after,
            'item_count' => $itemCount,
        ];
    }

    /** @return list<string>|null */
    private function state(mixed $value): ?array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return null;
        }

        $state = [];
        foreach ($value as $identity) {
            if (! is_string($identity) || $identity === '') {
                return null;
            }
            $state[] = $identity;
        }

        return $state;
    }

    /** @return array<string,mixed> */
    private function metadata(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        } elseif (is_object($value)) {
            $value = (array) $value;
        }

        return is_array($value) ? $value : [];
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
