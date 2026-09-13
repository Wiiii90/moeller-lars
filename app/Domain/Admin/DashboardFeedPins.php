<?php

namespace App\Domain\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DashboardFeedPins
{
    public function __construct(private readonly DashboardFeed $feed) {}

    /** @return list<array<string, mixed>> */
    public function entries(string $search = '', string $type = 'all', string $notificationFilter = 'all'): array
    {
        $userId = $this->userId();
        if ($userId === null) {
            return [];
        }

        $search = trim($search);
        $rows = DB::table('dashboard_feed_pins')
            ->where('user_id', $userId)
            ->orderBy('position')
            ->orderBy('id')
            ->get(['entry_key', 'position']);

        $entries = [];
        foreach ($rows as $row) {
            $key = (string) $row->entry_key;
            $entry = $this->feed->entry($key);
            if (! is_array($entry) || ! $this->matches($entry, $search, $type, $notificationFilter)) {
                continue;
            }

            $entry['pinned'] = true;
            $entry['pin_position'] = (int) $row->position;
            $entries[] = $entry;
        }

        return $entries;
    }

    public function toggle(string $key): void
    {
        $userId = $this->userId();
        if ($userId === null || ! is_array($this->feed->entry($key))) {
            return;
        }

        DB::transaction(function () use ($userId, $key): void {
            $existing = DB::table('dashboard_feed_pins')
                ->where('user_id', $userId)
                ->where('entry_key', $key)
                ->first();

            if ($existing !== null) {
                DB::table('dashboard_feed_pins')->where('id', $existing->id)->delete();
                $this->normalize($userId);

                return;
            }

            DB::table('dashboard_feed_pins')
                ->where('user_id', $userId)
                ->increment('position');

            $now = now();
            DB::table('dashboard_feed_pins')->insert([
                'user_id' => $userId,
                'entry_key' => $key,
                'position' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function move(string $key, int $position): void
    {
        $userId = $this->userId();
        if ($userId === null) {
            return;
        }

        DB::transaction(function () use ($userId, $key, $position): void {
            $rows = DB::table('dashboard_feed_pins')
                ->where('user_id', $userId)
                ->orderBy('position')
                ->orderBy('id')
                ->get(['id', 'entry_key']);

            $ordered = $rows->pluck('entry_key')->map(static fn (mixed $value): string => (string) $value)->all();
            $current = array_search($key, $ordered, true);
            if ($current === false) {
                return;
            }

            array_splice($ordered, (int) $current, 1);
            $target = max(0, min($position, count($ordered)));
            array_splice($ordered, $target, 0, [$key]);

            $idsByKey = $rows->mapWithKeys(static fn (object $row): array => [(string) $row->entry_key => (int) $row->id]);
            foreach ($ordered as $index => $entryKey) {
                $id = $idsByKey->get($entryKey);
                if (! is_int($id)) {
                    continue;
                }

                DB::table('dashboard_feed_pins')
                    ->where('id', $id)
                    ->update([
                        'position' => $index + 1,
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    public function forget(string $key): void
    {
        $userId = $this->userId();
        if ($userId === null) {
            return;
        }

        DB::transaction(function () use ($userId, $key): void {
            DB::table('dashboard_feed_pins')
                ->where('user_id', $userId)
                ->where('entry_key', $key)
                ->delete();

            $this->normalize($userId);
        });
    }

    private function normalize(int $userId): void
    {
        $ids = DB::table('dashboard_feed_pins')
            ->where('user_id', $userId)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $index => $id) {
            DB::table('dashboard_feed_pins')
                ->where('id', $id)
                ->update(['position' => $index + 1]);
        }
    }

    /** @param array<string, mixed> $entry */
    private function matches(array $entry, string $search, string $type, string $notificationFilter): bool
    {
        if ($type !== 'all' && ($entry['type'] ?? null) !== $type) {
            return false;
        }

        if (($entry['type'] ?? null) === 'notification'
            && $notificationFilter !== 'all'
            && ($entry['notification_status'] ?? null) !== $notificationFilter) {
            return false;
        }

        if ($search === '') {
            return true;
        }

        $haystack = match ($entry['type'] ?? null) {
            'contact' => implode(' ', [
                (string) ($entry['sender_name'] ?? ''),
                (string) ($entry['sender_email'] ?? ''),
                (string) ($entry['body'] ?? ''),
            ]),
            'notification' => implode(' ', [
                (string) ($entry['title'] ?? ''),
                (string) ($entry['body'] ?? ''),
                (string) ($entry['notification_status'] ?? ''),
            ]),
            default => implode(' ', [
                (string) ($entry['title'] ?? ''),
                (string) ($entry['body'] ?? ''),
            ]),
        };

        return Str::contains(Str::lower($haystack), Str::lower($search));
    }

    private function userId(): ?int
    {
        $id = auth()->id();

        return is_numeric($id) ? (int) $id : null;
    }
}
