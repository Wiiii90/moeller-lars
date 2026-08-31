<?php

namespace App\Domain\Publication;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PublicationSchemaGuard
{
    public function assertParity(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('Publication snapshots require PostgreSQL schema parity checks.');
        }

        $rows = DB::table('information_schema.columns')
            ->whereIn('table_schema', ['public', 'committed'])
            ->whereIn('table_name', PublicationSnapshot::TABLES)
            ->orderBy('table_name')
            ->orderBy('table_schema')
            ->orderBy('ordinal_position')
            ->get([
                'table_schema',
                'table_name',
                'column_name',
                'ordinal_position',
                'data_type',
                'udt_name',
                'is_nullable',
                'character_maximum_length',
                'numeric_precision',
                'numeric_scale',
                'datetime_precision',
                'is_identity',
                'identity_generation',
                'is_generated',
            ]);

        foreach (PublicationSnapshot::TABLES as $table) {
            $public = $this->signature($rows->where('table_schema', 'public')->where('table_name', $table));
            $committed = $this->signature($rows->where('table_schema', 'committed')->where('table_name', $table));

            if ($public === [] || $public !== $committed) {
                throw new RuntimeException("Publication snapshot schema drift detected for {$table}.");
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private function signature(iterable $rows): array
    {
        $signature = [];

        foreach ($rows as $row) {
            $signature[] = [
                'column_name' => (string) $row->column_name,
                'data_type' => (string) $row->data_type,
                'udt_name' => (string) $row->udt_name,
                'is_nullable' => (string) $row->is_nullable,
                'character_maximum_length' => $row->character_maximum_length === null ? null : (int) $row->character_maximum_length,
                'numeric_precision' => $row->numeric_precision === null ? null : (int) $row->numeric_precision,
                'numeric_scale' => $row->numeric_scale === null ? null : (int) $row->numeric_scale,
                'datetime_precision' => $row->datetime_precision === null ? null : (int) $row->datetime_precision,
                'is_identity' => (string) $row->is_identity,
                'identity_generation' => $row->identity_generation === null ? null : (string) $row->identity_generation,
                'is_generated' => (string) $row->is_generated,
            ];
        }

        return $signature;
    }
}
