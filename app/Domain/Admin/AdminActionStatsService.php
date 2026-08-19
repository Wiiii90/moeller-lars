<?php

namespace App\Domain\Admin;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class AdminActionStatsService
{
    public function record(User $actor, string $actionKey, CarbonInterface $occurredAt): void
    {
        DB::statement(
            <<<'SQL'
                INSERT INTO admin_action_stats (admin_user_id, action_key, use_count, last_used_at)
                VALUES (?, ?, 1, ?)
                ON CONFLICT (admin_user_id, action_key)
                DO UPDATE SET
                    use_count = admin_action_stats.use_count + 1,
                    last_used_at = GREATEST(admin_action_stats.last_used_at, EXCLUDED.last_used_at)
            SQL,
            [$actor->getKey(), $actionKey, $occurredAt],
        );
    }
}
