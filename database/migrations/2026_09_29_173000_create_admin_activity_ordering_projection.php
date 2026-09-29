<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_activity_ordering_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope', 240);
            $table->string('action', 80);
            $table->string('target_label', 240)->nullable();
            $table->foreignId('first_audit_event_id')->constrained('audit_events')->restrictOnDelete();
            $table->foreignId('last_audit_event_id')->constrained('audit_events')->restrictOnDelete();
            $table->unsignedInteger('event_count')->default(1);
            $table->unsignedInteger('item_count')->default(0);
            $table->char('before_hash', 64);
            $table->char('after_hash', 64);
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at');

            $table->unique('last_audit_event_id');
            $table->index(['admin_user_id', 'scope', 'ended_at'], 'activity_ordering_actor_scope_idx');
        });

        Schema::create('admin_activity_ordering_events', function (Blueprint $table): void {
            $table->foreignId('audit_event_id')->primary()->constrained('audit_events')->restrictOnDelete();
            $table->foreignId('group_id')->constrained('admin_activity_ordering_groups')->cascadeOnDelete();
            $table->unsignedInteger('sequence');

            $table->unique(['group_id', 'sequence']);
        });

        $this->backfillLegacyOrderingEvents();
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activity_ordering_events');
        Schema::dropIfExists('admin_activity_ordering_groups');
    }

    private function backfillLegacyOrderingEvents(): void
    {
        $orderingActions = [
            'artwork.additional_media_reordered',
            'artwork_category.gallery_reordered',
            'site_section.reordered',
            'blog_post.reordered',
            'exhibition.reordered',
            'cv_entry.reordered',
        ];

        DB::table('audit_events')
            ->whereIn('action', $orderingActions)
            ->select(['id', 'admin_user_id', 'action', 'entity_id', 'occurred_at', 'metadata'])
            ->orderBy('id')
            ->chunkById(500, function ($events): void {
                $events->each(function (object $event): void {
                    $scope = $this->legacyScope($event);
                    if ($scope === null) {
                        return;
                    }

                    $groupId = (int) DB::table('admin_activity_ordering_groups')->insertGetId([
                        'admin_user_id' => $event->admin_user_id,
                        'scope' => $scope,
                        'action' => (string) $event->action,
                        'target_label' => $this->legacyScopeLabel($scope),
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
                });
            });
    }

    private function legacyScopeLabel(string $scope): ?string
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

    private function legacyScope(object $event): ?string
    {
        $metadata = is_string($event->metadata)
            ? json_decode($event->metadata, true)
            : (is_array($event->metadata) ? $event->metadata : []);
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
};
