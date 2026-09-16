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
    /** @var array<string, array{entity_type:string,table:string,row_id:int,before:array<string,mixed>,after:array<string,mixed>}> */
    private array $snapshots = [];

    /** @var array<string, array{entity_type:string,table:string,row_id:int,before:array<string,mixed>}> */
    private array $pending = [];

    public function begin(Model $model): void
    {
        $descriptor = $this->descriptor($model);
        if ($descriptor === null || ! $model->isDirty()) {
            return;
        }

        $key = $this->key($descriptor['table'], $descriptor['row_id']);
        if (isset($this->pending[$key])) {
            return;
        }

        if (isset($this->snapshots[$key])) {
            $this->pending[$key] = [
                ...$descriptor,
                'before' => $this->snapshots[$key]['before'],
            ];

            return;
        }

        $row = DB::table($descriptor['table'])->where('id', $descriptor['row_id'])->first();
        if ($row === null) {
            return;
        }

        $this->pending[$key] = [
            ...$descriptor,
            'before' => $this->meaningfulPayload((array) $row),
        ];
    }

    public function finish(Model $model): void
    {
        $descriptor = $this->descriptor($model);
        if ($descriptor === null) {
            return;
        }

        $key = $this->key($descriptor['table'], $descriptor['row_id']);
        $pending = $this->pending[$key] ?? null;
        if (! is_array($pending)) {
            return;
        }
        unset($this->pending[$key]);

        $row = DB::table($descriptor['table'])->where('id', $descriptor['row_id'])->first();
        if ($row === null) {
            return;
        }

        $after = $this->meaningfulPayload((array) $row);
        $before = $pending['before'];

        if ($before === $after) {
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

    /** @return array{entity_type:string,table:string,row_id:int,before:array<string,mixed>,after:array<string,mixed>}|null */
    public function takeForAudit(string $entityType, int $entityId): ?array
    {
        foreach ($this->snapshots as $key => $snapshot) {
            if ($snapshot['entity_type'] === $entityType && $snapshot['row_id'] === $entityId) {
                unset($this->snapshots[$key]);

                return $snapshot;
            }
        }

        if ($entityType !== 'site_section') {
            return null;
        }

        $matches = [];
        foreach ($this->snapshots as $key => $snapshot) {
            if (! in_array($snapshot['entity_type'], ['custom_page_setting', 'home_presentation_setting'], true)) {
                continue;
            }

            $siteSectionId = $snapshot['after']['site_section_id'] ?? $snapshot['before']['site_section_id'] ?? null;
            if ((int) $siteSectionId === $entityId) {
                $matches[$key] = $snapshot;
            }
        }

        if (count($matches) !== 1) {
            return null;
        }

        $key = array_key_first($matches);
        if (! is_string($key)) {
            return null;
        }

        $snapshot = $matches[$key];
        unset($this->snapshots[$key]);

        return $snapshot;
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

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function meaningfulPayload(array $payload): array
    {
        unset($payload['id'], $payload['created_at'], $payload['updated_at']);
        ksort($payload);

        return $payload;
    }

    private function key(string $table, int $rowId): string
    {
        return $table.':'.$rowId;
    }
}
