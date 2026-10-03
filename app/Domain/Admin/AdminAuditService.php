<?php

namespace App\Domain\Admin;

use App\Domain\Publication\PublicationSnapshot;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AdminAuditService
{
    private const REASONS = [
        'referenced_inline_media_deleted',
        'referenced_rich_text_media_deleted',
    ];

    public function __construct(
        private readonly AdminActionReceiptService $receipts,
        private readonly AdminMutationSnapshotBuffer $mutationSnapshots,
        private readonly AdminChangeSummary $changeSummary,
        private readonly AdminActivityPresentation $activityPresentation,
        private readonly AdminFeedbackContext $feedbackContext,
        private readonly AdminUndoContext $undoContext,
        private readonly AdminActivityOrderingProjector $orderingProjector,
    ) {}

    public function requireActor(): User
    {
        $actor = Auth::guard('web')->user();
        if (! $actor instanceof User || ! (bool) $actor->getAttribute('is_admin')) {
            throw new AuthorizationException('An admin actor is required.');
        }

        return $actor;
    }

    /**
     * Record one logical ordering mutation while preserving the immutable raw Audit event.
     *
     * @param list<int|string> $beforeState
     * @param list<int|string> $afterState
     */
    public function recordOrdering(
        User $actor,
        string $action,
        string $entityType,
        int $entityId,
        string $scope,
        array $beforeState,
        array $afterState,
        ?array $metadata = null,
    ): ?AuditEvent {
        $definition = AdminActionCatalog::definition($action);
        if (($definition['family'] ?? null) !== 'ordering') {
            throw new InvalidArgumentException('Ordering Activity requires an ordering action.');
        }

        $scope = trim($scope);
        if ($scope === '' || mb_strlen($scope) > 240) {
            throw new InvalidArgumentException('Invalid ordering Activity scope.');
        }

        $before = AdminOrderingState::normalize($beforeState);
        $after = AdminOrderingState::normalize($afterState);
        if ($before === $after) {
            return null;
        }

        $ordering = [
            'scope' => $scope,
            'target_label' => $this->orderingScopeTarget($scope),
            'before_state' => $before,
            'after_state' => $after,
            'item_count' => max(count($before), count($after)),
        ];

        $metadata ??= [];
        $metadata['ordering'] = $ordering;

        return DB::transaction(function () use ($actor, $action, $entityType, $entityId, $metadata, $ordering): AuditEvent {
            $event = $this->record($actor, $action, $entityType, $entityId, $metadata);
            $projection = $this->orderingProjector->record($event, $ordering);

            if (! $this->undoContext->receiptsSuppressed()) {
                $this->receipts->recordOrderingProjection($event, $actor, $projection);
            }

            $presentation = $this->activityPresentation->present(
                $action,
                trim((string) ($projection->getAttribute('target_label') ?? $ordering['target_label'] ?? '')),
                null,
                [
                    'scope' => (string) $projection->getAttribute('scope'),
                    'before_state' => $projection->beforeState(),
                    'after_state' => $projection->afterState(),
                    'event_count' => (int) $projection->getAttribute('event_count'),
                    'item_count' => (int) $projection->getAttribute('item_count'),
                ],
            );
            $this->feedbackContext->push((int) $event->getKey(), $presentation);

            return $event;
        });
    }

    public function record(User $actor, string $action, string $entityType, int $entityId, ?array $metadata = null): AuditEvent
    {
        if (! AdminActionCatalog::has($action)) {
            throw new InvalidArgumentException('Invalid audit action.');
        }
        if (! $this->validEntityType($action, $entityType)) {
            throw new InvalidArgumentException('Invalid audit entity type.');
        }

        $metadata ??= [];
        foreach ($metadata as $key => $value) {
            $validReference = in_array($key, [
                'artwork_id', 'media_asset_id', 'artwork_media_id',
                'previous_artwork_media_id', 'next_artwork_media_id', 'site_section_id',
                'source_publication_checkpoint_id', 'source_audit_event_id',
            ], true) && is_int($value) && $value > 0;
            $validPosition = $key === 'position' && is_int($value) && $value >= 0;
            $validReason = $key === 'reason' && is_string($value) && in_array($value, self::REASONS, true);
            $validOrdering = $key === 'ordering' && $this->validOrderingMetadata($value);

            if (! $validReference && ! $validPosition && ! $validReason && ! $validOrdering) {
                throw new InvalidArgumentException('Invalid audit metadata.');
            }
        }

        $snapshots = $this->mutationSnapshots->peekForAudit($entityType, $entityId);
        $summary = $this->changeSummary->summarize($snapshots);
        $targetLabel = $this->targetLabelFromSnapshots($snapshots, $entityType, $entityId);
        if ($targetLabel !== null) {
            $metadata['target_label'] = $targetLabel;
        }

        if ($action === 'admin.undo_applied' && is_int($metadata['source_audit_event_id'] ?? null)) {
            $sourceEvent = AuditEvent::query()->find($metadata['source_audit_event_id']);
            $sourceMetadata = $sourceEvent?->getAttribute('metadata');
            $sourceSummary = is_array($sourceMetadata) ? ($sourceMetadata['change_summary'] ?? null) : null;
            if (is_array($sourceSummary)) {
                $summary = $this->changeSummary->reverse($sourceSummary);
            }
        }

        if ($summary !== null) {
            $metadata['change_summary'] = $summary;
        }

        $event = new AuditEvent;
        $event->fill([
            'admin_user_id' => $actor->getKey(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'occurred_at' => now(),
            'request_id' => null,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
        $event->save();

        if (PublicationSnapshot::tracksAuditEntityType($entityType) || $entityType === 'publication_checkpoint') {
            $this->mutationSnapshots->markPublicationStateMayHaveChanged();
        }

        if ($this->undoContext->receiptsSuppressed()) {
            $this->receipts->discardPendingSnapshotForEvent($event);
        } else {
            $this->receipts->recordForAuditEvent($event, $actor);
        }

        if (! isset($metadata['ordering'])) {
            $presentation = $this->activityPresentation->present(
                $action,
                $targetLabel ?? '',
                $summary,
            );
            $this->feedbackContext->push((int) $event->getKey(), $presentation);
        }

        return $event;
    }

    /**
     * @param  list<array{entity_type:string,table:string,row_id:int,before:?array<string,mixed>,after:?array<string,mixed>}>|null  $snapshots
     */
    private function targetLabelFromSnapshots(?array $snapshots, string $entityType, int $entityId): ?string
    {
        $descriptor = match ($entityType) {
            'artwork' => ['table' => 'artworks', 'field' => 'title'],
            'artwork_category' => ['table' => 'artwork_categories', 'field' => 'name'],
            'site_section' => ['table' => 'site_sections', 'field' => 'title'],
            'media_asset' => ['table' => 'media_assets', 'field' => 'original_filename'],
            'artwork_material_preset' => ['table' => 'artwork_material_presets', 'field' => 'name'],
            'exhibition' => ['table' => 'exhibitions', 'field' => 'title'],
            'blog_post' => ['table' => 'blog_posts', 'field' => 'title'],
            default => null,
        };

        if ($descriptor === null) {
            return null;
        }

        foreach ($snapshots ?? [] as $snapshot) {
            if (($snapshot['entity_type'] ?? null) !== $entityType || (int) ($snapshot['row_id'] ?? 0) !== $entityId) {
                continue;
            }

            $state = is_array($snapshot['after'] ?? null)
                ? $snapshot['after']
                : (is_array($snapshot['before'] ?? null) ? $snapshot['before'] : null);
            $label = is_array($state) ? ($state[$descriptor['field']] ?? null) : null;

            return $this->normalizeTargetLabel($label);
        }

        return $this->normalizeTargetLabel(
            DB::table($descriptor['table'])
                ->where('id', $entityId)
                ->value($descriptor['field']),
        );
    }

    private function validOrderingMetadata(mixed $value): bool
    {
        return is_array($value)
            && is_string($value['scope'] ?? null)
            && trim($value['scope']) !== ''
            && mb_strlen($value['scope']) <= 240
            && (($value['target_label'] ?? null) === null || (is_string($value['target_label']) && mb_strlen($value['target_label']) <= 240))
            && $this->validOrderingState($value['before_state'] ?? null)
            && $this->validOrderingState($value['after_state'] ?? null)
            && is_int($value['item_count'] ?? null)
            && $value['item_count'] >= 0;
    }

    private function validOrderingState(mixed $value): bool
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return false;
        }

        foreach ($value as $identity) {
            if (! is_string($identity) || $identity === '') {
                return false;
            }
        }

        return true;
    }

    private function orderingScopeTarget(string $scope): ?string
    {
        if ($scope === 'site-navigation') {
            return 'Public navigation';
        }

        if (preg_match('/^gallery-artworks:(\d+)$/', $scope, $matches) === 1) {
            return $this->normalizeTargetLabel(DB::table('artwork_categories')->where('id', (int) $matches[1])->value('name'));
        }
        if (preg_match('/^artwork-media:(\d+)$/', $scope, $matches) === 1) {
            return $this->normalizeTargetLabel(DB::table('artworks')->where('id', (int) $matches[1])->value('title'));
        }
        if (preg_match('/^(?:journal-blog|journal-exhibitions|home-artworks|home-components|page-components|page-list|page-contact):(\d+)/', $scope, $matches) === 1) {
            return $this->normalizeTargetLabel(DB::table('site_sections')->where('id', (int) $matches[1])->value('title'));
        }

        return null;
    }

    private function normalizeTargetLabel(mixed $label): ?string
    {
        if (! is_string($label) || trim($label) === '') {
            return null;
        }

        return Str::limit(trim(strip_tags($label)), 240, '…');
    }

    private function validEntityType(string $action, string $entityType): bool
    {
        return PublicationSnapshot::tracksAuditEntityType($entityType)
            || $entityType === 'publication_checkpoint'
            || str_starts_with($action, $entityType.'.');
    }
}
