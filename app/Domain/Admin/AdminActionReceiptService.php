<?php

namespace App\Domain\Admin;

use App\Models\AdminActionReceipt;
use App\Models\Artwork;
use App\Models\AuditEvent;
use App\Models\CvEntry;
use App\Models\Exhibition;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class AdminActionReceiptService
{
    public const RECEIPT_VERSION = 1;

    public const RETENTION_DAYS = 30;

    public const MAX_RECEIPTS_PER_USER = 100;

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

        if ($transition === null || (string) $event->getAttribute('entity_type') !== $transition['entity']) {
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
        $available = [];

        foreach ($receipts as $receipt) {
            $entityType = (string) $receipt->getAttribute('entity_type');
            $entityId = (int) $receipt->getAttribute('entity_id');
            $currentState = $targets[$entityType][$entityId] ?? null;

            if (! is_string($currentState) || $currentState !== (string) $receipt->getAttribute('after_state')) {
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

        if (! AdminActionCatalog::has($inverseActionKey)) {
            throw new InvalidArgumentException('Undo inverse action key is not part of the admin action catalog.');
        }

        $entityType = $this->entityType($target);
        if (
            (int) $event->getAttribute('admin_user_id') !== (int) $actor->getKey()
            || (string) $event->getAttribute('entity_type') !== $entityType
            || (int) $event->getAttribute('entity_id') !== (int) $target->getKey()
        ) {
            throw new InvalidArgumentException('Undo receipt does not match its immutable audit event.');
        }

        $receipt = new AdminActionReceipt;
        $receipt->fill([
            'audit_event_id' => $event->getKey(),
            'admin_user_id' => $actor->getKey(),
            'action_key' => (string) $event->getAttribute('action'),
            'inverse_action_key' => $inverseActionKey,
            'entity_type' => $entityType,
            'entity_id' => $target->getKey(),
            'before_state' => $beforeState,
            'after_state' => $afterState,
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
     * @param  EloquentCollection<int, AdminActionReceipt>  $receipts
     * @return array<string, array<int, string>>
     */
    private function loadTargetStates(EloquentCollection $receipts): array
    {
        $ids = $receipts
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
}
