<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('admin_activity_ordering_events');
        Schema::dropIfExists('admin_activity_ordering_groups');
        Schema::dropIfExists('admin_activity_ordering_projections');

        Schema::create('admin_activity_ordering_projections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope', 240);
            $table->string('action', 80);
            $table->string('target_label', 240)->nullable();
            $table->foreignId('first_audit_event_id')->constrained('audit_events')->restrictOnDelete();
            $table->foreignId('last_audit_event_id')->constrained('audit_events')->restrictOnDelete();
            $table->unsignedInteger('event_count')->default(1);
            $table->unsignedInteger('item_count')->default(0);
            $table->jsonb('before_state');
            $table->jsonb('after_state');
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at');

            $table->unique('last_audit_event_id');
            $table->index(['scope', 'action', 'id'], 'activity_ordering_projection_scope_idx');
        });

        Schema::create('admin_activity_ordering_events', function (Blueprint $table): void {
            $table->foreignId('audit_event_id')->primary()->constrained('audit_events')->restrictOnDelete();
            $table->foreignId('projection_id')->constrained('admin_activity_ordering_projections')->cascadeOnDelete();
            $table->unsignedInteger('sequence');

            $table->unique(['projection_id', 'sequence']);
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activity_ordering_events');
        Schema::dropIfExists('admin_activity_ordering_projections');
    }

    private function backfill(): void
    {
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
                    $ordering = $this->orderingMetadata($event);
                    $legacy = false;

                    if ($ordering === null) {
                        $scope = $this->legacyScope($event);
                        if ($scope === null) {
                            continue;
                        }

                        $legacy = true;
                        $ordering = [
                            'scope' => $scope,
                            'target_label' => $this->legacyTargetLabel($event, $scope),
                            'before_state' => ['legacy-before:'.(int) $event->id],
                            'after_state' => ['legacy-after:'.(int) $event->id],
                            'item_count' => 0,
                        ];
                    }

                    $key = (string) $event->action.'|'.$ordering['scope'];
                    $candidate = $latest[$key] ?? null;
                    $canAppend = ! $legacy
                        && is_array($candidate)
                        && ! (bool) ($candidate['legacy'] ?? false)
                        && (int) ($candidate['admin_user_id'] ?? 0) === (int) ($event->admin_user_id ?? 0)
                        && ($candidate['before_state'] ?? null) !== ($candidate['after_state'] ?? null)
                        && ($candidate['after_state'] ?? null) === $ordering['before_state']
                        && ! isset($committedEventIds[(int) ($candidate['last_audit_event_id'] ?? 0)]);

                    if ($canAppend) {
                        $groupId = (int) $candidate['id'];
                        $sequence = ((int) $candidate['event_count']) + 1;

                        DB::table('admin_activity_ordering_projections')
                            ->where('id', $groupId)
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
                        $groupId = (int) DB::table('admin_activity_ordering_projections')->insertGetId([
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
                            'id' => $groupId,
                            'admin_user_id' => $event->admin_user_id,
                            'last_audit_event_id' => (int) $event->id,
                            'event_count' => 1,
                            'item_count' => $ordering['item_count'],
                            'before_state' => $ordering['before_state'],
                            'after_state' => $ordering['after_state'],
                            'ended_at' => $event->occurred_at,
                            'legacy' => $legacy,
                        ];
                    }

                    DB::table('admin_activity_ordering_events')->insert([
                        'audit_event_id' => (int) $event->id,
                        'projection_id' => $groupId,
                        'sequence' => $sequence,
                    ]);
                }
            });
    }

    /**
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

            $before = ['hash:'.$beforeHash];
            $after = ['hash:'.$afterHash];
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

    /** @return array<string, mixed> */
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

    private function legacyScope(object $event): ?string
    {
        $metadata = $this->metadata($event->metadata);

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
            default => 'legacy:'.(string) $event->action.':'.(int) $event->entity_id.':'.(int) $event->id,
        };
    }

    private function legacyTargetLabel(object $event, string $scope): ?string
    {
        $metadata = $this->metadata($event->metadata);
        $targetLabel = $metadata['target_label'] ?? null;

        return is_string($targetLabel) && trim($targetLabel) !== ''
            ? trim($targetLabel)
            : $this->scopeLabel($scope);
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
