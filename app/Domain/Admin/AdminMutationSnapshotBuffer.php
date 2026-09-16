<?php

namespace App\Domain\Admin;

use App\Domain\Publication\PublicationSnapshot;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AdminMutationSnapshotBuffer
{
    /** @var array<string, array{entity_type:string,table:string,row_id:int,before:?array<string,mixed>,after:?array<string,mixed>}> */
    private array $snapshots = [];

    /** @var array<string, array{entity_type:string,table:string,row_id:int,before:?array<string,mixed>}> */
    private array $pendingUpdates = [];

    /** @var array<string, array{entity_type:string,table:string,row_id:int,before:?array<string,mixed>}> */
    private array $pendingDeletes = [];

    public function begin(Model $model): void
    {
        $descriptor = $this->descriptor($model);
        if ($descriptor === null || ! $model->isDirty()) {
            return;
        }

        $key = $this->key($descriptor['table'], $descriptor['row_id']);
        if (isset($this->pendingUpdates[$key])) {
            return;
        }

        $this->pendingUpdates[$key] = [
            ...$descriptor,
            'before' => $this->snapshots[$key]['before'] ?? $this->rowPayload($descriptor['table'], $descriptor['row_id']),
        ];
    }

    public function finish(Model $model): void
    {
        $descriptor = $this->descriptor($model);
        if ($descriptor === null) {
            return;
        }

        $key = $this->key($descriptor['table'], $descriptor['row_id']);
        $pending = $this->pendingUpdates[$key] ?? null;
        if (! is_array($pending)) {
            return;
        }
        unset($this->pendingUpdates[$key]);

        $this->storeSnapshot(
            $descriptor,
            $pending['before'],
            $this->rowPayload($descriptor['table'], $descriptor['row_id']),
        );
    }

    public function created(Model $model): void
    {
        $descriptor = $this->descriptor($model);
        if ($descriptor === null) {
            return;
        }

        $key = $this->key($descriptor['table'], $descriptor['row_id']);
        $this->storeSnapshot(
            $descriptor,
            $this->snapshots[$key]['before'] ?? null,
            $this->rowPayload($descriptor['table'], $descriptor['row_id']),
        );
    }

    public function beginDelete(Model $model): void
    {
        $descriptor = $this->descriptor($model);
        if ($descriptor === null) {
            return;
        }

        $key = $this->key($descriptor['table'], $descriptor['row_id']);
        if (isset($this->pendingDeletes[$key])) {
            return;
        }

        $this->pendingDeletes[$key] = [
            ...$descriptor,
            'before' => $this->snapshots[$key]['before'] ?? $this->rowPayload($descriptor['table'], $descriptor['row_id']),
        ];
    }

    public function finishDelete(Model $model): void
    {
        $descriptor = $this->descriptor($model);
        if ($descriptor === null) {
            return;
        }

        $key = $this->key($descriptor['table'], $descriptor['row_id']);
        $pending = $this->pendingDeletes[$key] ?? null;
        if (! is_array($pending)) {
            return;
        }
        unset($this->pendingDeletes[$key]);

        $this->storeSnapshot($descriptor, $pending['before'], null);
    }

    /**
     * @return list<array{entity_type:string,table:string,row_id:int,before:?array<string,mixed>,after:?array<string,mixed>}>|null
     */
    public function takeForAudit(string $entityType, int $entityId): ?array
    {
        $snapshots = array_values($this->snapshots);
        $this->snapshots = [];
        $this->pendingUpdates = [];
        $this->pendingDeletes = [];

        if ($snapshots === []) {
            return null;
        }

        $matchesTarget = collect($snapshots)->contains(
            fn (array $snapshot): bool => $this->matchesAuditTarget($snapshot, $entityType, $entityId),
        );

        return $matchesTarget ? $snapshots : null;
    }

    /**
     * @param array{entity_type:string,table:string,row_id:int} $descriptor
     * @param array<string,mixed>|null $before
     * @param array<string,mixed>|null $after
     */
    private function storeSnapshot(array $descriptor, ?array $before, ?array $after): void
    {
        $key = $this->key($descriptor['table'], $descriptor['row_id']);
        $before = $this->canonicalizePayload($before);
        $after = $this->canonicalizePayload($after);

        if ($before === null && $after === null) {
            unset($this->snapshots[$key]);

            return;
        }

        if ($before !== null && $after !== null && $this->comparablePayload($before) === $this->comparablePayload($after)) {
            unset($this->snapshots[$key]);

            return;
        }

        $this->snapshots[$key] = [
            'entity_type' => $descriptor['entity_type'],
            'table' => $descriptor['table'],
            'row_id' => $descriptor['row_id'],
            'before' => $before,
            'after' => $after,
        ];
    }

    /**
     * @param array{entity_type:string,table:string,row_id:int,before:?array<string,mixed>,after:?array<string,mixed>} $snapshot
     */
    private function matchesAuditTarget(array $snapshot, string $entityType, int $entityId): bool
    {
        if ($snapshot['entity_type'] === $entityType && $snapshot['row_id'] === $entityId) {
            return true;
        }

        $foreignKey = $entityType.'_id';
        foreach ([$snapshot['before'], $snapshot['after']] as $state) {
            if (! is_array($state)) {
                continue;
            }

            $relatedId = $state[$foreignKey] ?? null;
            if (is_numeric($relatedId) && (int) $relatedId === $entityId) {
                return true;
            }
        }

        return false;
    }

    /** @return array{entity_type:string,table:string,row_id:int}|null */
    private function descriptor(Model $model): ?array
    {
        $actor = Auth::guard('web')->user();
        if (! $actor instanceof User || ! (bool) $actor->getAttribute('is_admin')) {
            return null;
        }

        $table = $model->getTable();
        if (! in_array($table, PublicationSnapshot::TABLES, true)) {
            return null;
        }

        $entityType = Str::snake(class_basename($model));
        if (! PublicationSnapshot::tracksAuditEntityType($entityType)) {
            return null;
        }

        $rowId = (int) $model->getKey();
        if ($rowId < 1) {
            return null;
        }

        return [
            'entity_type' => $entityType,
            'table' => $table,
            'row_id' => $rowId,
        ];
    }

    /** @return array<string,mixed>|null */
    private function rowPayload(string $table, int $rowId): ?array
    {
        $row = DB::table($table)->where('id', $rowId)->first();

        return $row === null ? null : $this->canonicalizePayload((array) $row);
    }

    /** @param array<string,mixed>|null $payload
     * @return array<string,mixed>|null
     */
    private function canonicalizePayload(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        return $this->canonicalize($payload);
    }

    /** @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function comparablePayload(array $payload): array
    {
        unset($payload['created_at'], $payload['updated_at']);

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

    private function key(string $table, int $rowId): string
    {
        return $table.':'.$rowId;
    }
}
