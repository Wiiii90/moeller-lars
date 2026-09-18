<?php

namespace App\Domain\Admin;

use App\Models\AdminNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DashboardNotificationRetention
{
    /** @return array<int, string> */
    public static function options(): array
    {
        return [
            10 => '10 notifications',
            25 => '25 notifications',
            50 => '50 notifications',
            100 => '100 notifications',
            250 => '250 notifications',
            500 => '500 notifications',
        ];
    }

    public function normalize(mixed $value): int
    {
        $limit = is_numeric($value) ? (int) $value : 10;

        return array_key_exists($limit, self::options()) ? $limit : 10;
    }

    public function limitFor(User|int $user): int
    {
        if ($user instanceof User) {
            return $this->normalize($user->getAttribute('dashboard_notification_retention'));
        }

        $value = User::query()->whereKey($user)->value('dashboard_notification_retention');

        return $this->normalize($value);
    }

    public function pruneFor(User|int $user): void
    {
        $userId = $user instanceof User ? (int) $user->getKey() : $user;
        if ($userId <= 0) {
            return;
        }

        $limit = $this->limitFor($user);
        $pinnedIds = DB::table('dashboard_feed_pins')
            ->where('user_id', $userId)
            ->where('entry_key', 'like', 'notification:%')
            ->pluck('entry_key')
            ->map(static function (mixed $key): ?int {
                $id = substr((string) $key, strlen('notification:'));

                return ctype_digit($id) ? (int) $id : null;
            })
            ->filter(static fn (?int $id): bool => $id !== null)
            ->values()
            ->all();

        $keepUnpinned = max(0, $limit - count($pinnedIds));
        $deleteIds = AdminNotification::query()
            ->where('user_id', $userId)
            ->when($pinnedIds !== [], fn ($builder) => $builder->whereNotIn('id', $pinnedIds))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->skip($keepUnpinned)
            ->pluck('id')
            ->all();

        if ($deleteIds !== []) {
            AdminNotification::query()->whereIn('id', $deleteIds)->delete();
        }
    }
}
