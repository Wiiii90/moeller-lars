<?php

namespace App\Domain\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class AdminLocalHistoryResetService
{
    /**
     * Tables are ordered from dependent/derived state toward the immutable audit root.
     *
     * Editorial/publication content is deliberately absent from this list.
     *
     * @return array<string, int>
     */
    public function reset(): array
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Local admin history reset is only available in local/testing environments.');
        }

        $tables = [
            'dashboard_feed_pins',
            'admin_notifications',
            'admin_notification_history',
            'admin_action_stats',
            'admin_activity_ordering_events',
            'admin_activity_ordering_projections',
            'admin_action_receipts',
            'publication_event_states',
            'publication_checkpoint_events',
            'audit_events',
        ];

        return DB::transaction(function () use ($tables): array {
            $deleted = [];

            foreach ($tables as $table) {
                if (! Schema::hasTable($table)) {
                    $deleted[$table] = 0;

                    continue;
                }

                $deleted[$table] = DB::table($table)->count();
                DB::table($table)->delete();
            }

            return $deleted;
        });
    }
}
