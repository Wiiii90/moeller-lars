<?php

namespace App\Domain\Admin;

use Illuminate\Support\Facades\DB;

final class DashboardFeedReleaseState
{
    /** @param list<string> $keys
     * @return array<string, bool>
     */
    public function readFlags(array $keys): array
    {
        $userId = $this->userId();
        if ($userId === null || $keys === []) {
            return [];
        }

        return DB::table('dashboard_feed_release_states')
            ->where('user_id', $userId)
            ->whereIn('entry_key', $keys)
            ->get(['entry_key', 'read_at'])
            ->mapWithKeys(static fn (object $row): array => [(string) $row->entry_key => $row->read_at !== null])
            ->all();
    }

    public function markRead(string $key, bool $read): void
    {
        $userId = $this->userId();
        if ($userId === null || ! $this->isReleaseKey($key)) {
            return;
        }

        $now = now();
        DB::table('dashboard_feed_release_states')->upsert([[
            'user_id' => $userId,
            'entry_key' => $key,
            'read_at' => $read ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['user_id', 'entry_key'], ['read_at', 'updated_at']);
    }

    /** Claim an automatic pin once for each user/key. Manual unpin must stick. */
    public function claimAutomaticPin(string $key, bool $alreadyPinned): bool
    {
        $userId = $this->userId();
        if ($userId === null || ! $this->isReleaseKey($key)) {
            return false;
        }

        $now = now();
        $inserted = DB::table('dashboard_feed_release_states')->insertOrIgnore([
            'user_id' => $userId,
            'entry_key' => $key,
            'auto_pin_initialized_at' => $now,
            'auto_pinned' => ! $alreadyPinned,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted > 0) {
            return ! $alreadyPinned;
        }

        $claimed = DB::table('dashboard_feed_release_states')
            ->where('user_id', $userId)
            ->where('entry_key', $key)
            ->whereNull('auto_pin_initialized_at')
            ->update([
                'auto_pin_initialized_at' => $now,
                'auto_pinned' => ! $alreadyPinned,
                'updated_at' => $now,
            ]);

        return $claimed > 0 && ! $alreadyPinned;
    }

    /** @param list<string> $latest */
    public function needsPinReconciliation(array $latest): bool
    {
        $userId = $this->userId();
        if ($userId === null) {
            return false;
        }

        $rows = DB::table('dashboard_feed_release_states')
            ->where('user_id', $userId)
            ->get(['entry_key', 'auto_pin_initialized_at', 'auto_pinned'])
            ->keyBy('entry_key');

        foreach ($latest as $key) {
            $row = $rows->get($key);
            if ($row === null || $row->auto_pin_initialized_at === null) {
                return true;
            }
        }

        foreach ($rows as $key => $row) {
            if ($row->auto_pinned && ! in_array((string) $key, $latest, true)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function automaticallyPinnedKeys(): array
    {
        $userId = $this->userId();
        if ($userId === null) {
            return [];
        }

        return DB::table('dashboard_feed_release_states')
            ->where('user_id', $userId)
            ->where('auto_pinned', true)
            ->pluck('entry_key')
            ->map(static fn (mixed $key): string => (string) $key)
            ->all();
    }

    /** @param list<string> $keys */
    public function clearAutomaticPins(array $keys): void
    {
        $userId = $this->userId();
        if ($userId === null || $keys === []) {
            return;
        }

        DB::table('dashboard_feed_release_states')
            ->where('user_id', $userId)
            ->whereIn('entry_key', $keys)
            ->where('auto_pinned', true)
            ->update(['auto_pinned' => false, 'updated_at' => now()]);
    }

    private function isReleaseKey(string $key): bool
    {
        return str_starts_with($key, 'static:');
    }

    private function userId(): ?int
    {
        $id = auth()->id();

        return is_numeric($id) ? (int) $id : null;
    }
}
