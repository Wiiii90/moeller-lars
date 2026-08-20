<?php

namespace App\Domain\Admin;

use App\Models\AdminActionReceipt;
use App\Models\Artwork;
use App\Models\ArtworkMedia;
use App\Models\AuditEvent;
use App\Models\CvEntry;
use App\Models\Exhibition;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class AdminActionReceiptService
{
    public const RECEIPT_VERSION = 1;

    public const RETENTION_DAYS = 30;

    public const MAX_RECEIPTS_PER_USER = 100;

    private const MEDIA_ACTIONS = [
        'artwork.additional_media_attached',
        'artwork.additional_media_detached',
        'artwork.additional_media_reordered',
    ];

    public function recordForAuditEvent(AuditEvent $event, User $actor): ?AdminActionReceipt
    {
        $action = (string) $event->getAttribute('action');
        $transition = match ($action) {
            'artwork.published' => ['entity' => 'artwork', 'before' => 'draft', 'after' => 'published', 'inverse' => 'artwork.unpublished'],
            'artwork.unpublished' => ['entity' => 'artwork', 'before' => 'published', 'after' => 'draft', 'inverse' => 'artwork.published'],
            'cv_entry.published' => ['entity' => 'cv_entry', 'before' => 'draft', 'after' => 'published', 'inverse' => 'cv_entry.unpublished'],
            'cv_entry.unpublished' => ['entity' => 'cv_entry', 'before' => 'published', 'after' => 'draft', 'inverse' => 'cv_entry.published'],
            'exhibition.published' => ['entity' => 'exhibition', 'before' => 'draft', 'after' => 'published', 'inverse' => 'exhibition.unpublished'],
            'exhibition.unpublished' => ['entity' => 'exhibition', 'before' => 'published', 'after' => 'draft', 'inverse' => 'exhibition.published'],
            default => null,
        };

        if ($transition !== null) {
            if ((string) $event->getAttribute('entity_type') !== $transition['entity']) {
                return null;
            }

            $target = $this->findTarget($transition['entity'], (int) $event->getAttribute('entity_id'));
            if ($target === null || (string) $target->getAttribute('state') !== $transition['after']) {
                return null;
            }

            return $this->recordStateTransition(
                $event,
                $actor,
                $target,
                $transition['before'],
                $transition['after'],
                $transition['inverse'],
            );
        }

        if (in_array($action, self::MEDIA_ACTIONS, true)) {
            return $this->recordMediaReceipt($event, $actor);
        }

        return null;
    }

    /**
     * @param  Collection<int, AuditEvent>  $events
     * @return array<int, array{id:int,action_key:string,inverse_action_key:string,inverse_label:string}>
     */
    public function availableForEvents(Collection $events, User $actor): array
    {
        $eventIds = $events
            ->map(static fn (AuditEvent $event): int => (int) $event->getKey())
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($eventIds === []) {
            return [];
        }

        /** @var EloquentCollection<int, AdminActionReceipt> $receipts */
        $receipts = AdminActionReceipt::query()
            ->where('admin_user_id', $actor->getKey())
            ->whereIn('audit_event_id', $eventIds)
            ->where('receipt_version', self::RECEIPT_VERSION)
            ->whereNull('undone_at')
            ->where('expires_at', '>', now())
            ->get();

        if ($receipts->isEmpty()) {
            return [];
        }

        $targets = $this->loadTargetStates($receipts);
        $media = $this->loadMediaContext($receipts);
        $available = [];

        foreach ($receipts as $receipt) {
            if (! $this->receiptIsAvailable($receipt, $targets, $media)) {
                continue;
            }

            $inverseActionKey = (string) $receipt->getAttribute('inverse_action_key');
            $available[(int) $receipt->getAttribute('audit_event_id')] = [
                'id' => (int) $receipt->getKey(),
                'action_key' => (string) $receipt->getAttribute('action_key'),
                'inverse_action_key' => $inverseActionKey,
                'inverse_label' => AdminActionCatalog::definition($inverseActionKey)['label'],
            ];
        }

        return $available;
    }

    public function prune(User $actor): void
    {
        AdminActionReceipt::query()
            ->where('admin_user_id', $actor->getKey())
            ->where('expires_at', '<=', now())
            ->delete();

        $excessIds = AdminActionReceipt::query()
            ->where('admin_user_id', $actor->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->skip(self::MAX_RECEIPTS_PER_USER)
            ->pluck('id');

        if ($excessIds->isNotEmpty()) {
            AdminActionReceipt::query()->whereIn('id', $excessIds)->delete();
        }
    }

    private function recordStateTransition(
        AuditEvent $event,
        User $actor,
        Artwork|CvEntry|Exhibition $target,
        string $beforeState,
        string $afterState,
        string $inverseActionKey,
    ): ?AdminActionReceipt {
        if ($beforeState === $afterState) {
            return null;
        }

        return $this->storeReceipt($event, $actor, [
            'inverse_action_key' => $inverseActionKey,
            'entity_type' => $this->entityType($target),
            'entity_id' => (int) $target->getKey(),
            'before_state' => $beforeState,
            'after_state' => $afterState,
        ]);
    }

    private function recordMediaReceipt(AuditEvent $event, User $actor): ?AdminActionReceipt
    {
        if ((string) $event->getAttribute('entity_type') !== 'artwork') {
            return null;
        }

        $artworkId = (int) $event->getAttribute('entity_id');
        if (! Artwork::query()->whereKey($artworkId)->exists()) {
            return null;
        }

        $metadata = $event->getAttribute('metadata');
        if (! is_array($metadata)) {
            return null;
        }

        return match ((string) $event->getAttribute('action')) {
            'artwork.additional_media_attached' => $this->recordAttachedMediaReceipt($event, $actor, $artworkId, $metadata),
            'artwork.additional_media_detached' => $this->recordDetachedMediaReceipt($event, $actor, $artworkId, $metadata),
            'artwork.additional_media_reordered' => $this->recordReorderedMediaReceipt($event, $actor, $artworkId, $metadata),
            default => null,
        };
    }

    /** @param array<string, mixed> $metadata */
    private function recordAttachedMediaReceipt(AuditEvent $event, User $actor, int $artworkId, array $metadata): ?AdminActionReceipt
    {
        $mediaAssetId = $this->positiveInt($metadata['media_asset_id'] ?? null);
        $artworkMediaId = $this->positiveInt($metadata['artwork_media_id'] ?? null);
        $position = $this->positiveInt($metadata['position'] ?? null);

        if ($mediaAssetId === null || $artworkMediaId === null || $position === null) {
            return null;
        }

        /** @var ArtworkMedia|null $usage */
        $usage = ArtworkMedia::query()->find($artworkMediaId);
        if (
            ! $usage
            || (int) $usage->getAttribute('artwork_id') !== $artworkId
            || (int) $usage->getAttribute('media_asset_id') !== $mediaAssetId
            || $usage->getAttribute('role') !== 'additional'
            || (int) $usage->getAttribute('position') !== $position
        ) {
            return null;
        }

        return $this->storeReceipt($event, $actor, [
            'inverse_action_key' => 'artwork.additional_media_detached',
            'entity_type' => 'artwork',
            'entity_id' => $artworkId,
            'before_state' => 'detached',
            'after_state' => 'attached',
            'media_asset_id' => $mediaAssetId,
            'artwork_media_id' => $artworkMediaId,
            'after_position' => $position,
        ]);
    }

    /** @param array<string, mixed> $metadata */
    private function recordDetachedMediaReceipt(AuditEvent $event, User $actor, int $artworkId, array $metadata): ?AdminActionReceipt
    {
        $mediaAssetId = $this->positiveInt($metadata['media_asset_id'] ?? null);
        $artworkMediaId = $this->positiveInt($metadata['artwork_media_id'] ?? null);
        $position = $this->positiveInt($metadata['position'] ?? null);

        if ($mediaAssetId === null || $artworkMediaId === null || $position === null) {
            return null;
        }

        /** @var MediaAsset|null $asset */
        $asset = MediaAsset::query()->find($mediaAssetId);
        if (! $asset || $asset->getAttribute('state') !== 'available') {
            return null;
        }

        return $this->storeReceipt($event, $actor, [
            'inverse_action_key' => 'artwork.additional_media_attached',
            'entity_type' => 'artwork',
            'entity_id' => $artworkId,
            'before_state' => 'attached',
            'after_state' => 'detached',
            'media_asset_id' => $mediaAssetId,
            'artwork_media_id' => $artworkMediaId,
            'previous_artwork_media_id' => $this->positiveInt($metadata['previous_artwork_media_id'] ?? null),
            'next_artwork_media_id' => $this->positiveInt($metadata['next_artwork_media_id'] ?? null),
            'before_position' => $position,
        ]);
    }

    /** @param array<string, mixed> $metadata */
    private function recordReorderedMediaReceipt(AuditEvent $event, User $actor, int $artworkId, array $metadata): ?AdminActionReceipt
    {
        $artworkMediaId = $this->positiveInt($metadata['artwork_media_id'] ?? null);
        $neighborId = $this->positiveInt($metadata['neighbor_artwork_media_id'] ?? null);
        $fromPosition = $this->positiveInt($metadata['from_position'] ?? null);
        $toPosition = $this->positiveInt($metadata['to_position'] ?? null);
        $direction = $metadata['direction'] ?? null;

        if (
            $artworkMediaId === null
            || $neighborId === null
            || $fromPosition === null
            || $toPosition === null
            || ! is_string($direction)
            || ! in_array($direction, ['up', 'down'], true)
        ) {
            return null;
        }

        /** @var EloquentCollection<int, ArtworkMedia> $usages */
        $usages = ArtworkMedia::query()
            ->whereIn('id', [$artworkMediaId, $neighborId])
            ->get();
        /** @var ArtworkMedia|null $moving */
        $moving = $usages->firstWhere('id', $artworkMediaId);
        /** @var ArtworkMedia|null $neighbor */
        $neighbor = $usages->firstWhere('id', $neighborId);

        if (
            ! $moving
            || ! $neighbor
            || (int) $moving->getAttribute('artwork_id') !== $artworkId
            || (int) $neighbor->getAttribute('artwork_id') !== $artworkId
            || $moving->getAttribute('role') !== 'additional'
            || $neighbor->getAttribute('role') !== 'additional'
            || (int) $moving->getAttribute('position') !== $toPosition
            || (int) $neighbor->getAttribute('position') !== $fromPosition
            || abs($fromPosition - $toPosition) !== 1
        ) {
            return null;
        }

        return $this->storeReceipt($event, $actor, [
            'inverse_action_key' => 'artwork.additional_media_reordered',
            'entity_type' => 'artwork',
            'entity_id' => $artworkId,
            'before_state' => 'ordered',
            'after_state' => 'ordered',
            'artwork_media_id' => $artworkMediaId,
            'neighbor_artwork_media_id' => $neighborId,
            'before_position' => $fromPosition,
            'after_position' => $toPosition,
            'inverse_direction' => $direction === 'up' ? 'down' : 'up',
        ]);
    }

    /** @param array<string, mixed> $data */
    private function storeReceipt(AuditEvent $event, User $actor, array $data): AdminActionReceipt
    {
        $inverseActionKey = (string) ($data['inverse_action_key'] ?? '');
        $entityType = (string) ($data['entity_type'] ?? '');
        $entityId = (int) ($data['entity_id'] ?? 0);

        if (! AdminActionCatalog::has($inverseActionKey)) {
            throw new InvalidArgumentException('Undo inverse action key is not part of the admin action catalog.');
        }

        if (
            (int) $event->getAttribute('admin_user_id') !== (int) $actor->getKey()
            || (string) $event->getAttribute('entity_type') !== $entityType
            || (int) $event->getAttribute('entity_id') !== $entityId
        ) {
            throw new InvalidArgumentException('Undo receipt does not match its immutable audit event.');
        }

        $receipt = new AdminActionReceipt;
        $receipt->fill([
            'audit_event_id' => $event->getKey(),
            'admin_user_id' => $actor->getKey(),
            'action_key' => (string) $event->getAttribute('action'),
            ...$data,
            'receipt_version' => self::RECEIPT_VERSION,
            'expires_at' => now()->addDays(self::RETENTION_DAYS),
            'undone_at' => null,
            'created_at' => now(),
        ]);
        $receipt->save();

        $this->prune($actor);

        return $receipt;
    }

    /**
     * @param  array<string, array<int, string>>  $targets
     * @param  array{usages:array<int, array{artwork_id:int,media_asset_id:int,position:int}>,ordered:array<int, array<int, int>>,asset_states:array<int, string>}  $media
     */
    private function receiptIsAvailable(AdminActionReceipt $receipt, array $targets, array $media): bool
    {
        $action = (string) $receipt->getAttribute('action_key');

        if (! in_array($action, self::MEDIA_ACTIONS, true)) {
            $entityType = (string) $receipt->getAttribute('entity_type');
            $entityId = (int) $receipt->getAttribute('entity_id');
            $currentState = $targets[$entityType][$entityId] ?? null;

            return is_string($currentState) && $currentState === (string) $receipt->getAttribute('after_state');
        }

        if ($action === 'artwork.additional_media_attached') {
            return $this->attachedMediaReceiptAvailable($receipt, $media);
        }

        if ($action === 'artwork.additional_media_detached') {
            return $this->detachedMediaReceiptAvailable($receipt, $media);
        }

        return $this->reorderedMediaReceiptAvailable($receipt, $media);
    }

    /** @param array{usages:array<int, array{artwork_id:int,media_asset_id:int,position:int}>,ordered:array<int, array<int, int>>,asset_states:array<int, string>} $media */
    private function attachedMediaReceiptAvailable(AdminActionReceipt $receipt, array $media): bool
    {
        $usageId = (int) $receipt->getAttribute('artwork_media_id');
        $usage = $media['usages'][$usageId] ?? null;

        return $usage !== null
            && $usage['artwork_id'] === (int) $receipt->getAttribute('entity_id')
            && $usage['media_asset_id'] === (int) $receipt->getAttribute('media_asset_id')
            && $usage['position'] === (int) $receipt->getAttribute('after_position');
    }

    /** @param array{usages:array<int, array{artwork_id:int,media_asset_id:int,position:int}>,ordered:array<int, array<int, int>>,asset_states:array<int, string>} $media */
    private function detachedMediaReceiptAvailable(AdminActionReceipt $receipt, array $media): bool
    {
        $artworkId = (int) $receipt->getAttribute('entity_id');
        $mediaAssetId = (int) $receipt->getAttribute('media_asset_id');
        $historicalUsageId = (int) $receipt->getAttribute('artwork_media_id');

        if (($media['asset_states'][$mediaAssetId] ?? null) !== 'available' || isset($media['usages'][$historicalUsageId])) {
            return false;
        }

        foreach ($media['usages'] as $usage) {
            if ($usage['artwork_id'] === $artworkId && $usage['media_asset_id'] === $mediaAssetId) {
                return false;
            }
        }

        $ordered = $media['ordered'][$artworkId] ?? [];
        $previousId = $receipt->getAttribute('previous_artwork_media_id');
        $nextId = $receipt->getAttribute('next_artwork_media_id');
        $previousId = is_int($previousId) ? $previousId : null;
        $nextId = is_int($nextId) ? $nextId : null;

        if ($previousId === null && $nextId === null) {
            return $ordered === [];
        }

        if ($previousId === null) {
            return ($ordered[0] ?? null) === $nextId;
        }

        if ($nextId === null) {
            return $ordered !== [] && $ordered[array_key_last($ordered)] === $previousId;
        }

        $previousIndex = array_search($previousId, $ordered, true);
        $nextIndex = array_search($nextId, $ordered, true);

        return is_int($previousIndex) && is_int($nextIndex) && $nextIndex === $previousIndex + 1;
    }

    /** @param array{usages:array<int, array{artwork_id:int,media_asset_id:int,position:int}>,ordered:array<int, array<int, int>>,asset_states:array<int, string>} $media */
    private function reorderedMediaReceiptAvailable(AdminActionReceipt $receipt, array $media): bool
    {
        $moving = $media['usages'][(int) $receipt->getAttribute('artwork_media_id')] ?? null;
        $neighbor = $media['usages'][(int) $receipt->getAttribute('neighbor_artwork_media_id')] ?? null;

        return $moving !== null
            && $neighbor !== null
            && $moving['artwork_id'] === (int) $receipt->getAttribute('entity_id')
            && $neighbor['artwork_id'] === (int) $receipt->getAttribute('entity_id')
            && $moving['position'] === (int) $receipt->getAttribute('after_position')
            && $neighbor['position'] === (int) $receipt->getAttribute('before_position')
            && abs($moving['position'] - $neighbor['position']) === 1;
    }

    /**
     * @param  EloquentCollection<int, AdminActionReceipt>  $receipts
     * @return array{usages:array<int, array{artwork_id:int,media_asset_id:int,position:int}>,ordered:array<int, array<int, int>>,asset_states:array<int, string>}
     */
    private function loadMediaContext(EloquentCollection $receipts): array
    {
        $mediaReceipts = $receipts->filter(
            fn (AdminActionReceipt $receipt): bool => in_array((string) $receipt->getAttribute('action_key'), self::MEDIA_ACTIONS, true),
        );
        $artworkIds = $mediaReceipts
            ->pluck('entity_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
        $assetIds = $mediaReceipts
            ->pluck('media_asset_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($artworkIds === []) {
            return ['usages' => [], 'ordered' => [], 'asset_states' => []];
        }

        /** @var EloquentCollection<int, ArtworkMedia> $usageModels */
        $usageModels = ArtworkMedia::query()
            ->whereIn('artwork_id', $artworkIds)
            ->where('role', 'additional')
            ->orderBy('artwork_id')
            ->orderBy('position')
            ->get(['id', 'artwork_id', 'media_asset_id', 'position']);
        $usages = [];
        $ordered = [];

        foreach ($usageModels as $usage) {
            $id = (int) $usage->getKey();
            $artworkId = (int) $usage->getAttribute('artwork_id');
            $usages[$id] = [
                'artwork_id' => $artworkId,
                'media_asset_id' => (int) $usage->getAttribute('media_asset_id'),
                'position' => (int) $usage->getAttribute('position'),
            ];
            $ordered[$artworkId] ??= [];
            $ordered[$artworkId][] = $id;
        }

        $assetStates = $assetIds === []
            ? []
            : MediaAsset::query()->whereKey($assetIds)->pluck('state', 'id')
                ->map(static fn (mixed $state): string => (string) $state)
                ->all();

        return [
            'usages' => $usages,
            'ordered' => $ordered,
            'asset_states' => $assetStates,
        ];
    }

    /**
     * @param  EloquentCollection<int, AdminActionReceipt>  $receipts
     * @return array<string, array<int, string>>
     */
    private function loadTargetStates(EloquentCollection $receipts): array
    {
        $ids = $receipts
            ->reject(fn (AdminActionReceipt $receipt): bool => in_array((string) $receipt->getAttribute('action_key'), self::MEDIA_ACTIONS, true))
            ->groupBy(fn (AdminActionReceipt $receipt): string => (string) $receipt->getAttribute('entity_type'))
            ->map(fn (Collection $group): array => $group
                ->pluck('entity_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all());

        return [
            'artwork' => $this->pluckStates(Artwork::class, $ids->get('artwork', [])),
            'cv_entry' => $this->pluckStates(CvEntry::class, $ids->get('cv_entry', [])),
            'exhibition' => $this->pluckStates(Exhibition::class, $ids->get('exhibition', [])),
        ];
    }

    /**
     * @param  class-string<Artwork|CvEntry|Exhibition>  $model
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    private function pluckStates(string $model, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $model::query()
            ->whereKey($ids)
            ->pluck('state', 'id')
            ->map(static fn (mixed $state): string => (string) $state)
            ->all();
    }

    private function findTarget(string $entityType, int $entityId): Artwork|CvEntry|Exhibition|null
    {
        return match ($entityType) {
            'artwork' => Artwork::query()->find($entityId),
            'cv_entry' => CvEntry::query()->find($entityId),
            'exhibition' => Exhibition::query()->find($entityId),
            default => null,
        };
    }

    private function entityType(Artwork|CvEntry|Exhibition $target): string
    {
        return match (true) {
            $target instanceof Artwork => 'artwork',
            $target instanceof CvEntry => 'cv_entry',
            $target instanceof Exhibition => 'exhibition',
        };
    }

    private function positiveInt(mixed $value): ?int
    {
        return is_int($value) && $value > 0 ? $value : null;
    }
}
