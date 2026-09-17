<?php

namespace App\Domain\Storage;

use Illuminate\Support\Facades\DB;
use Throwable;

final class SiteStorageDatabaseUsageService
{
    /** @var list<string> */
    private const ACTIVITY_TABLES = [
        'audit_events',
    ];

    /** @var list<string> */
    private const UNDO_TABLES = [
        'admin_action_receipts',
    ];

    /** @var list<string> */
    private const PUBLICATION_TABLES = [
        'publication_checkpoint_events',
        'publication_checkpoints',
        'publication_event_states',
        'publication_media_cleanups',
        'publication_working_context',
        'publication_version_payloads',
        'publication_version_row_manifests',
    ];

    /** @var list<string> */
    private const OTHER_TABLES = [
        'admin_action_stats',
        'admin_notification_history',
        'cache',
        'cache_locks',
        'dashboard_feed_pins',
        'failed_jobs',
        'job_batches',
        'jobs',
        'migrations',
        'password_reset_tokens',
        'sessions',
        'users',
    ];

    /**
     * @return array{
     *   measurement_available:bool,
     *   logical_bytes:int|null,
     *   live_content_bytes:int|null,
     *   activity_bytes:int|null,
     *   undo_bytes:int|null,
     *   publication_bytes:int|null,
     *   other_bytes:int|null,
     *   physical_database_bytes:int|null,
     *   publication_logical_payload_bytes:int|null,
     *   publication_unique_payload_bytes:int|null,
     *   publication_deduplication_percent:float|null
     * }
     */
    public function snapshot(): array
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return $this->unavailable();
        }

        try {
            $tables = array_values(array_filter(
                array_map(
                    static fn (object $row): string => (string) ($row->tablename ?? ''),
                    DB::select(<<<'SQL'
SELECT tablename
FROM pg_catalog.pg_tables
WHERE schemaname = 'public'
ORDER BY tablename
SQL),
                ),
                static fn (string $table): bool => preg_match('/^[a-z0-9_]+$/D', $table) === 1,
            ));

            $bytesByTable = [];

            foreach ($tables as $table) {
                $row = DB::selectOne(
                    "SELECT COALESCE(SUM(pg_column_size(row_data)), 0)::bigint AS bytes FROM public.\"{$table}\" AS row_data",
                );
                $bytesByTable[$table] = max(0, (int) ($row->bytes ?? 0));
            }

            $activity = $this->sumTables($bytesByTable, self::ACTIVITY_TABLES);
            $undo = $this->sumTables($bytesByTable, self::UNDO_TABLES);
            $publication = $this->sumTables($bytesByTable, self::PUBLICATION_TABLES);
            $other = $this->sumTables($bytesByTable, self::OTHER_TABLES)
                + $this->sumPrefixedTables($bytesByTable, 'pulse_');
            $total = array_sum($bytesByTable);
            $live = max(0, $total - $activity - $undo - $publication - $other);

            $physical = DB::selectOne(
                'SELECT pg_database_size(current_database())::bigint AS bytes',
            );
            $logicalPublicationPayloads = DB::selectOne(<<<'SQL'
SELECT COALESCE(SUM(pg_column_size(payload)), 0)::bigint AS bytes
FROM publication_version_rows
SQL);
            $uniquePublicationPayloads = DB::selectOne(<<<'SQL'
SELECT COALESCE(SUM(pg_column_size(payload)), 0)::bigint AS bytes
FROM publication_version_payloads
SQL);
            $logicalPayloadBytes = max(0, (int) ($logicalPublicationPayloads->bytes ?? 0));
            $uniquePayloadBytes = max(0, (int) ($uniquePublicationPayloads->bytes ?? 0));
            $deduplicationPercent = $logicalPayloadBytes > 0
                ? round(max(0, 1 - ($uniquePayloadBytes / $logicalPayloadBytes)) * 100, 1)
                : null;

            return [
                'measurement_available' => true,
                'logical_bytes' => $total,
                'live_content_bytes' => $live,
                'activity_bytes' => $activity,
                'undo_bytes' => $undo,
                'publication_bytes' => $publication,
                'other_bytes' => $other,
                'physical_database_bytes' => max(0, (int) ($physical->bytes ?? 0)),
                'publication_logical_payload_bytes' => $logicalPayloadBytes,
                'publication_unique_payload_bytes' => $uniquePayloadBytes,
                'publication_deduplication_percent' => $deduplicationPercent,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return $this->unavailable();
        }
    }

    /** @param array<string,int> $bytesByTable @param list<string> $tables */
    private function sumTables(array $bytesByTable, array $tables): int
    {
        $bytes = 0;

        foreach ($tables as $table) {
            $bytes += (int) ($bytesByTable[$table] ?? 0);
        }

        return $bytes;
    }

    /** @param array<string,int> $bytesByTable */
    private function sumPrefixedTables(array $bytesByTable, string $prefix): int
    {
        $bytes = 0;

        foreach ($bytesByTable as $table => $tableBytes) {
            if (str_starts_with($table, $prefix)) {
                $bytes += $tableBytes;
            }
        }

        return $bytes;
    }

    /**
     * @return array{
     *   measurement_available:false,
     *   logical_bytes:null,
     *   live_content_bytes:null,
     *   activity_bytes:null,
     *   undo_bytes:null,
     *   publication_bytes:null,
     *   other_bytes:null,
     *   physical_database_bytes:null,
     *   publication_logical_payload_bytes:null,
     *   publication_unique_payload_bytes:null,
     *   publication_deduplication_percent:null
     * }
     */
    private function unavailable(): array
    {
        return [
            'measurement_available' => false,
            'logical_bytes' => null,
            'live_content_bytes' => null,
            'activity_bytes' => null,
            'undo_bytes' => null,
            'publication_bytes' => null,
            'other_bytes' => null,
            'physical_database_bytes' => null,
            'publication_logical_payload_bytes' => null,
            'publication_unique_payload_bytes' => null,
            'publication_deduplication_percent' => null,
        ];
    }
}
