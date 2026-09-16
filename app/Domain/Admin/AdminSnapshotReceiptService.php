<?php

namespace App\Domain\Admin;

use App\Domain\Publication\PublicationSnapshot;
use App\Models\AdminActionReceipt;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class AdminSnapshotReceiptService
{
    public function __construct(private readonly AdminMutationSnapshotBuffer $buffer) {}

    public function discardForAuditEvent(AuditEvent $event): void
    {
        $this->buffer->takeForAudit(
            (string) $event->getAttribute('entity_type'),
            (int) $event->getAttribute('entity_id'),
        );
    }

    public function recordForAuditEvent(AuditEvent $event, User $actor): ?AdminActionReceipt
    {
        $snapshots = $this->buffer->takeForAudit(
            (string) $event->getAttribute('entity_type'),
            (int) $event->getAttribute('entity_id'),
        );

        if ($snapshots === null || ! $this->actionSupportsSnapshotUndo((string) $event->getAttribute('action'))) {
            return null;
        }

        $receipt = new AdminActionReceipt;
        $receipt->fill([
            'audit_event_id' => $event->getKey(),
            'admin_user_id' => $actor->getKey(),
            'action_key' => (string) $event->getAttribute('action'),
            'inverse_action_key' => 'admin.undo_applied',
            'entity_type' => (string) $event->getAttribute('entity_type'),
            'entity_id' => (int) $event->getAttribute('entity_id'),
            'before_state' => 'snapshot',
            'after_state' => 'snapshot',
            'snapshot_payload' => ['rows' => $snapshots],
            'receipt_version' => AdminActionReceiptService::RECEIPT_VERSION,
            'expires_at' => now()->addDays(AdminActionReceiptService::RETENTION_DAYS),
            'undone_at' => null,
            'created_at' => now(),
        ]);
        $receipt->save();

        $this->prune($actor);

        return $receipt;
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
            ->where('receipt_version', AdminActionReceiptService::RECEIPT_VERSION)
            ->whereNotNull('snapshot_payload')
            ->whereNull('undone_at')
            ->where('expires_at', '>', now())
            ->get();

        $available = [];
        foreach ($receipts as $receipt) {
            if (! $this->isAvailable($receipt)) {
                continue;
            }

            $available[(int) $receipt->getAttribute('audit_event_id')] = [
                'id' => (int) $receipt->getKey(),
                'action_key' => (string) $receipt->getAttribute('action_key'),
                'inverse_action_key' => 'admin.undo_applied',
                'inverse_label' => AdminActionCatalog::definition('admin.undo_applied')['label'],
            ];
        }

        return $available;
    }

    public function restore(AdminActionReceipt $receipt): void
    {
        $snapshots = $this->snapshots($receipt);
        if (! $this->snapshotsMatchCurrentState($snapshots, lock: true)) {
            $this->conflict();
        }

        try {
            $this->deleteRowsCreatedByAction($snapshots);
            $this->restoreRowsDeletedByAction($snapshots);
            $this->restoreRowsUpdatedByAction($snapshots);
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'undo' => 'Undo is no longer available because related data changed afterwards.',
            ]);
        }

        if (! $this->snapshotsMatchBeforeState($snapshots)) {
            throw new RuntimeException('The snapshot Undo did not restore the expected atomic state.');
        }
    }

    public function isSnapshotReceipt(AdminActionReceipt $receipt): bool
    {
        return is_array($receipt->getAttribute('snapshot_payload'));
    }

    private function isAvailable(AdminActionReceipt $receipt): bool
    {
        try {
            return $this->snapshotsMatchCurrentState($this->snapshots($receipt));
        } catch (ValidationException) {
            return false;
        }
    }

    /**
     * @return list<array{entity_type:string,table:string,row_id:int,before:?array<string,mixed>,after:?array<string,mixed>}>
     */
    private function snapshots(AdminActionReceipt $receipt): array
    {
        $payload = $receipt->getAttribute('snapshot_payload');
        if (! is_array($payload)) {
            throw ValidationException::withMessages(['undo' => 'This Undo receipt has no row snapshot.']);
        }

        $rows = $payload['rows'] ?? null;
        if (is_array($rows) && array_is_list($rows)) {
            $snapshots = $rows;
        } elseif (isset($payload['entity_type'], $payload['table'], $payload['row_id'])) {
            // Backward compatibility for the first single-row snapshot receipt format.
            $snapshots = [$payload];
        } else {
            throw ValidationException::withMessages(['undo' => 'This Undo receipt contains an invalid row snapshot.']);
        }

        $validated = [];
        foreach ($snapshots as $snapshot) {
            if (! is_array($snapshot)) {
                throw ValidationException::withMessages(['undo' => 'This Undo receipt contains an invalid row snapshot.']);
            }

            $entityType = $snapshot['entity_type'] ?? null;
            $table = $snapshot['table'] ?? null;
            $rowId = $snapshot['row_id'] ?? null;
            $before = $snapshot['before'] ?? null;
            $after = $snapshot['after'] ?? null;

            if (
                ! is_string($entityType)
                || ! PublicationSnapshot::tracksAuditEntityType($entityType)
                || ! is_string($table)
                || ! in_array($table, PublicationSnapshot::TABLES, true)
                || ! is_int($rowId)
                || $rowId < 1
                || ($before !== null && ! is_array($before))
                || ($after !== null && ! is_array($after))
                || ($before === null && $after === null)
                || (is_array($before) && isset($before['id']) && (int) $before['id'] !== $rowId)
                || (is_array($after) && isset($after['id']) && (int) $after['id'] !== $rowId)
            ) {
                throw ValidationException::withMessages(['undo' => 'This Undo receipt contains an invalid row snapshot.']);
            }

            $validated[] = [
                'entity_type' => $entityType,
                'table' => $table,
                'row_id' => $rowId,
                'before' => is_array($before) ? $this->canonicalize($before) : null,
                'after' => is_array($after) ? $this->canonicalize($after) : null,
            ];
        }

        return $validated;
    }

    /**
     * @param list<array{entity_type:string,table:string,row_id:int,before:?array<string,mixed>,after:?array<string,mixed>}> $snapshots
     */
    private function snapshotsMatchCurrentState(array $snapshots, bool $lock = false): bool
    {
        foreach ($snapshots as $snapshot) {
            $query = DB::table($snapshot['table'])->where('id', $snapshot['row_id']);
            if ($lock) {
                $query->lockForUpdate();
            }
            $row = $query->first();
            $after = $snapshot['after'];

            if ($after === null) {
                if ($row !== null) {
                    return false;
                }

                continue;
            }

            if ($row === null || $this->comparablePayload((array) $row) !== $this->comparablePayload($after)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array{entity_type:string,table:string,row_id:int,before:?array<string,mixed>,after:?array<string,mixed>}> $snapshots
     */
    private function snapshotsMatchBeforeState(array $snapshots): bool
    {
        foreach ($snapshots as $snapshot) {
            $row = DB::table($snapshot['table'])->where('id', $snapshot['row_id'])->first();
            $before = $snapshot['before'];

            if ($before === null) {
                if ($row !== null) {
                    return false;
                }

                continue;
            }

            if ($row === null || $this->comparablePayload((array) $row) !== $this->comparablePayload($before)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array{entity_type:string,table:string,row_id:int,before:?array<string,mixed>,after:?array<string,mixed>}> $snapshots
     */
    private function deleteRowsCreatedByAction(array $snapshots): void
    {
        foreach ($this->orderedSnapshots($snapshots, reverse: true) as $snapshot) {
            if ($snapshot['before'] !== null || $snapshot['after'] === null) {
                continue;
            }

            DB::table($snapshot['table'])->where('id', $snapshot['row_id'])->delete();
        }
    }

    /**
     * @param list<array{entity_type:string,table:string,row_id:int,before:?array<string,mixed>,after:?array<string,mixed>}> $snapshots
     */
    private function restoreRowsDeletedByAction(array $snapshots): void
    {
        foreach ($this->orderedSnapshots($snapshots) as $snapshot) {
            if ($snapshot['before'] === null || $snapshot['after'] !== null) {
                continue;
            }

            $row = $snapshot['before'];
            $row['id'] = $snapshot['row_id'];
            DB::table($snapshot['table'])->insert($row);
        }
    }

    /**
     * @param list<array{entity_type:string,table:string,row_id:int,before:?array<string,mixed>,after:?array<string,mixed>}> $snapshots
     */
    private function restoreRowsUpdatedByAction(array $snapshots): void
    {
        foreach ($this->orderedSnapshots($snapshots) as $snapshot) {
            if ($snapshot['before'] === null || $snapshot['after'] === null) {
                continue;
            }

            $restore = $snapshot['before'];
            unset($restore['id'], $restore['created_at']);
            if (array_key_exists('updated_at', $restore)) {
                $restore['updated_at'] = now();
            }

            DB::table($snapshot['table'])->where('id', $snapshot['row_id'])->update($restore);
        }
    }

    /**
     * @param list<array{entity_type:string,table:string,row_id:int,before:?array<string,mixed>,after:?array<string,mixed>}> $snapshots
     * @return list<array{entity_type:string,table:string,row_id:int,before:?array<string,mixed>,after:?array<string,mixed>}>
     */
    private function orderedSnapshots(array $snapshots, bool $reverse = false): array
    {
        $rank = array_flip(PublicationSnapshot::RESTORE_TABLES);
        usort($snapshots, static function (array $left, array $right) use ($rank): int {
            $tableOrder = ($rank[$left['table']] ?? PHP_INT_MAX) <=> ($rank[$right['table']] ?? PHP_INT_MAX);

            return $tableOrder !== 0 ? $tableOrder : ($left['row_id'] <=> $right['row_id']);
        });

        return $reverse ? array_reverse($snapshots) : $snapshots;
    }

    private function actionSupportsSnapshotUndo(string $action): bool
    {
        if (! str_ends_with($action, '.updated') || ! AdminActionCatalog::has($action)) {
            return false;
        }

        return in_array(AdminActionCatalog::definition($action)['family'], ['edit', 'settings'], true);
    }

    private function prune(User $actor): void
    {
        AdminActionReceipt::query()
            ->where('admin_user_id', $actor->getKey())
            ->where('expires_at', '<=', now())
            ->delete();

        $excessIds = AdminActionReceipt::query()
            ->where('admin_user_id', $actor->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->skip(AdminActionReceiptService::MAX_RECEIPTS_PER_USER)
            ->pluck('id');

        if ($excessIds->isNotEmpty()) {
            AdminActionReceipt::query()->whereIn('id', $excessIds)->delete();
        }
    }

    /** @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function comparablePayload(array $payload): array
    {
        unset($payload['id'], $payload['created_at'], $payload['updated_at']);

        return $this->canonicalize($payload);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $nested) {
            $value[$key] = $this->canonicalize($nested);
        }

        return $value;
    }

    private function conflict(): never
    {
        throw ValidationException::withMessages([
            'undo' => 'Undo is no longer available because this action or related data changed afterwards.',
        ]);
    }
}
