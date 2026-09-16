<?php

namespace App\Domain\Admin;

use App\Domain\Publication\PublicationSnapshot;
use App\Models\AdminActionReceipt;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
        $snapshot = $this->buffer->takeForAudit(
            (string) $event->getAttribute('entity_type'),
            (int) $event->getAttribute('entity_id'),
        );

        if ($snapshot === null || ! $this->actionSupportsSnapshotUndo((string) $event->getAttribute('action'))) {
            return null;
        }

        if ($snapshot['before'] === $snapshot['after']) {
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
            'snapshot_payload' => $snapshot,
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
     * @param Collection<int, AuditEvent> $events
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
        $snapshot = $this->snapshot($receipt);
        $table = $snapshot['table'];
        $rowId = $snapshot['row_id'];

        $row = DB::table($table)->where('id', $rowId)->lockForUpdate()->first();
        if ($row === null) {
            $this->conflict();
        }

        $current = $this->meaningfulPayload((array) $row);
        if ($current !== $snapshot['after']) {
            $this->conflict();
        }

        $restore = $snapshot['before'];
        if (array_key_exists('updated_at', (array) $row)) {
            $restore['updated_at'] = now();
        }

        DB::table($table)->where('id', $rowId)->update($restore);

        $restored = DB::table($table)->where('id', $rowId)->first();
        if ($restored === null || $this->meaningfulPayload((array) $restored) !== $snapshot['before']) {
            throw new RuntimeException('The snapshot Undo did not restore the expected row state.');
        }
    }

    public function isSnapshotReceipt(AdminActionReceipt $receipt): bool
    {
        return is_array($receipt->getAttribute('snapshot_payload'));
    }

    private function isAvailable(AdminActionReceipt $receipt): bool
    {
        try {
            $snapshot = $this->snapshot($receipt);
        } catch (ValidationException) {
            return false;
        }

        $row = DB::table($snapshot['table'])->where('id', $snapshot['row_id'])->first();

        return $row !== null
            && $this->meaningfulPayload((array) $row) === $snapshot['after'];
    }

    /** @return array{entity_type:string,table:string,row_id:int,before:array<string,mixed>,after:array<string,mixed>} */
    private function snapshot(AdminActionReceipt $receipt): array
    {
        $payload = $receipt->getAttribute('snapshot_payload');
        if (! is_array($payload)) {
            throw ValidationException::withMessages(['undo' => 'This Undo receipt has no row snapshot.']);
        }

        $entityType = $payload['entity_type'] ?? null;
        $table = $payload['table'] ?? null;
        $rowId = $payload['row_id'] ?? null;
        $before = $payload['before'] ?? null;
        $after = $payload['after'] ?? null;

        if (
            ! is_string($entityType)
            || ! PublicationSnapshot::tracksAuditEntityType($entityType)
            || ! is_string($table)
            || ! in_array($table, PublicationSnapshot::TABLES, true)
            || ! is_int($rowId)
            || $rowId < 1
            || ! is_array($before)
            || ! is_array($after)
        ) {
            throw ValidationException::withMessages(['undo' => 'This Undo receipt contains an invalid row snapshot.']);
        }

        return [
            'entity_type' => $entityType,
            'table' => $table,
            'row_id' => $rowId,
            'before' => $before,
            'after' => $after,
        ];
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
    private function meaningfulPayload(array $payload): array
    {
        unset($payload['id'], $payload['created_at'], $payload['updated_at']);
        ksort($payload);

        return $payload;
    }

    private function conflict(): never
    {
        throw ValidationException::withMessages([
            'undo' => 'Undo is no longer available because this item changed afterwards.',
        ]);
    }
}
