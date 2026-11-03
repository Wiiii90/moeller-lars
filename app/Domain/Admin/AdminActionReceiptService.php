<?php

namespace App\Domain\Admin;

use App\Models\AdminActionReceipt;
use App\Models\AdminActivityOrderingEvent;
use App\Models\AdminActivityOrderingProjection;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class AdminActionReceiptService
{
    public const RECEIPT_VERSION = 1;

    public const RETENTION_DAYS = 365;

    public const MAX_RECEIPTS_PER_USER = 5000;

    public function __construct(
        private readonly AdminSnapshotReceiptService $snapshots,
        private readonly AdminLifecycleRegistry $lifecycle,
        private readonly AdminOrderingState $orderingState,
    ) {}

    public function recordForAuditEvent(AuditEvent $event, User $actor): ?AdminActionReceipt
    {
        $action = (string) $event->getAttribute('action');
        $transition = $this->lifecycle->transitionForAction($action);

        if ($transition !== null) {
            $this->snapshots->discardForAuditEvent($event);

            if ((string) $event->getAttribute('entity_type') !== $transition['entity']) {
                return null;
            }

            $target = $this->lifecycle->findTarget($transition['entity'], (int) $event->getAttribute('entity_id'));
            if ($target === null || (string) $target->getAttribute('state') !== $transition['after']) {
                return null;
            }

            return $this->recordStateTransition(
                $event,
                $actor,
                $transition['entity'],
                $target,
                $transition['before'],
                $transition['after'],
                $transition['inverse'],
            );
        }

        if (AdminActionCatalog::definition($action)['family'] === 'ordering') {
            $this->snapshots->discardForAuditEvent($event);

            return null;
        }

        return $this->snapshots->recordForAuditEvent($event, $actor);
    }

    public function discardPendingSnapshotForEvent(AuditEvent $event): void
    {
        $this->snapshots->discardForAuditEvent($event);
    }

    public function recordOrderingProjection(
        AuditEvent $event,
        User $actor,
        AdminActivityOrderingProjection $projection,
    ): ?AdminActionReceipt {
        $projectionEventIds = AdminActivityOrderingEvent::query()
            ->where('projection_id', $projection->getKey())
            ->pluck('audit_event_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($projectionEventIds !== []) {
            AdminActionReceipt::query()
                ->where('admin_user_id', $actor->getKey())
                ->whereIn('audit_event_id', $projectionEventIds)
                ->delete();
        }

        if ($projection->isIdentity() || $this->orderingState->current((string) $projection->getAttribute('scope')) !== $projection->afterState()) {
            return null;
        }

        return $this->storeReceipt($event, $actor, [
            'inverse_action_key' => 'admin.undo_applied',
            'entity_type' => (string) $event->getAttribute('entity_type'),
            'entity_id' => (int) $event->getAttribute('entity_id'),
            'before_state' => 'ordering',
            'after_state' => 'ordering',
        ]);
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
            ->whereNull('snapshot_payload')
            ->whereNull('undone_at')
            ->where('expires_at', '>', now())
            ->get();

        $available = [];
        if ($receipts->isNotEmpty()) {
            $targets = $this->loadTargetStates($receipts);

            foreach ($receipts as $receipt) {
                if (! $this->receiptIsAvailable($receipt, $targets)) {
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
        }

        return array_replace($available, $this->snapshots->availableForEvents($events, $actor));
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
        string $entityType,
        Model $target,
        string $beforeState,
        string $afterState,
        string $inverseActionKey,
    ): ?AdminActionReceipt {
        if ($beforeState === $afterState) {
            return null;
        }

        return $this->storeReceipt($event, $actor, [
            'inverse_action_key' => $inverseActionKey,
            'entity_type' => $entityType,
            'entity_id' => (int) $target->getKey(),
            'before_state' => $beforeState,
            'after_state' => $afterState,
        ]);
    }

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

    /** @param array<string, array<int, string>> $targets */
    private function receiptIsAvailable(AdminActionReceipt $receipt, array $targets): bool
    {
        $action = (string) $receipt->getAttribute('action_key');

        // Keep historical events visible without re-enabling a retired
        // additional-image mutation or its obsolete Undo commands.
        if (str_starts_with($action, 'artwork.additional_media_')) {
            return false;
        }

        if (AdminActionCatalog::definition($action)['family'] === 'ordering') {
            /** @var AdminActivityOrderingEvent|null $orderingEvent */
            $orderingEvent = AdminActivityOrderingEvent::query()
                ->with('projection')
                ->whereKey((int) $receipt->getAttribute('audit_event_id'))
                ->first();
            $projection = $orderingEvent?->getRelationValue('projection');

            return $projection instanceof AdminActivityOrderingProjection
                && ! $projection->isIdentity()
                && (int) $projection->getAttribute('last_audit_event_id') === (int) $receipt->getAttribute('audit_event_id')
                && $this->orderingState->current((string) $projection->getAttribute('scope')) === $projection->afterState();
        }

        $entityType = (string) $receipt->getAttribute('entity_type');
        $entityId = (int) $receipt->getAttribute('entity_id');
        $currentState = $targets[$entityType][$entityId] ?? null;

        return is_string($currentState) && $currentState === (string) $receipt->getAttribute('after_state');
    }

    /**
     * @param  EloquentCollection<int, AdminActionReceipt>  $receipts
     * @return array<string, array<int, string>>
     */
    private function loadTargetStates(EloquentCollection $receipts): array
    {
        $ids = $receipts
            ->reject(function (AdminActionReceipt $receipt): bool {
                $action = (string) $receipt->getAttribute('action_key');

                return str_starts_with($action, 'artwork.additional_media_')
                    || AdminActionCatalog::definition($action)['family'] === 'ordering';
            })
            ->groupBy(fn (AdminActionReceipt $receipt): string => (string) $receipt->getAttribute('entity_type'))
            ->map(fn (Collection $group): array => $group
                ->pluck('entity_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all());

        /** @var array<string, array<int, int>> $idsByEntityType */
        $idsByEntityType = $ids->all();

        return $this->lifecycle->loadStates($idsByEntityType);
    }
}
