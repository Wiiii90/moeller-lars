<?php

namespace App\Domain\Admin;

use App\Models\AdminActionStat;
use App\Models\AuditEvent;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class AdminQuickActionService
{
    private const DEFAULT_ORDER = [
        'add_artwork',
        'pages',
        'files',
        'general',
        'open_site',
    ];

    private const RECENT_SEQUENCE_LIMIT = 120;

    /** @return list<array{key:string,score:int}> */
    public function forUser(User $user): array
    {
        $scores = [];
        foreach (self::DEFAULT_ORDER as $index => $key) {
            $scores[$key] = 100 - ($index * 5);
        }

        /** @var EloquentCollection<int, AdminActionStat> $stats */
        $stats = AdminActionStat::query()
            ->where('admin_user_id', $user->getKey())
            ->get(['action_key', 'use_count', 'last_used_at']);

        foreach ($stats as $stat) {
            $destinationKey = $this->destinationKey((string) $stat->getAttribute('action_key'));
            if ($destinationKey === null) {
                continue;
            }

            $useCount = max(0, (int) $stat->getAttribute('use_count'));
            /** @var CarbonInterface|null $lastUsedAt */
            $lastUsedAt = $stat->getAttribute('last_used_at');
            if ($useCount === 0 || $lastUsedAt === null) {
                continue;
            }

            $scores[$destinationKey] += ($this->frequencyTier($useCount) * 100)
                + ($this->recencyTier($lastUsedAt) * 10);
        }

        foreach ($this->sequenceBonuses($user) as $key => $bonus) {
            $scores[$key] += $bonus;
        }

        $ranked = self::DEFAULT_ORDER;
        $defaultRanks = array_flip(self::DEFAULT_ORDER);
        usort($ranked, static function (string $left, string $right) use ($scores, $defaultRanks): int {
            $score = $scores[$right] <=> $scores[$left];
            if ($score !== 0) {
                return $score;
            }

            return $defaultRanks[$left] <=> $defaultRanks[$right];
        });

        return array_map(
            static fn (string $key): array => ['key' => $key, 'score' => $scores[$key]],
            $ranked,
        );
    }

    private function destinationKey(string $actionKey): ?string
    {
        return match (true) {
            $actionKey === 'artwork.created' => 'add_artwork',
            str_starts_with($actionKey, 'artwork.'),
            str_starts_with($actionKey, 'artwork_category.'),
            str_starts_with($actionKey, 'site_section.'),
            str_starts_with($actionKey, 'exhibition.'),
            str_starts_with($actionKey, 'cv_entry.'),
            str_starts_with($actionKey, 'blog_post.'),
            str_starts_with($actionKey, 'blog_setting.') => 'pages',
            str_starts_with($actionKey, 'media.') => 'files',
            str_starts_with($actionKey, 'public_content_setting.') => 'general',
            default => null,
        };
    }

    /** @return array<string, int> */
    private function sequenceBonuses(User $user): array
    {
        $events = AuditEvent::query()
            ->where('admin_user_id', $user->getKey())
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_SEQUENCE_LIMIT)
            ->get(['action'])
            ->reverse()
            ->values();

        $sequence = [];
        foreach ($events as $event) {
            $key = $this->destinationKey((string) $event->getAttribute('action'));
            if ($key !== null) {
                $sequence[] = $key;
            }
        }

        if (count($sequence) < 2) {
            return [];
        }

        $current = $sequence[count($sequence) - 1];
        $transitions = [];
        for ($index = 0, $last = count($sequence) - 1; $index < $last; $index++) {
            if ($sequence[$index] !== $current || $sequence[$index + 1] === $current) {
                continue;
            }

            $next = $sequence[$index + 1];
            $transitions[$next] = ($transitions[$next] ?? 0) + 1;
        }

        return array_map(static fn (int $count): int => min(100, $count * 25), $transitions);
    }

    private function frequencyTier(int $useCount): int
    {
        return min(7, (int) floor(log(max(1, $useCount), 2)) + 1);
    }

    private function recencyTier(CarbonInterface $lastUsedAt): int
    {
        $age = max(0, now()->getTimestamp() - $lastUsedAt->getTimestamp());

        return match (true) {
            $age <= 86400 => 4,
            $age <= 604800 => 3,
            $age <= 2592000 => 2,
            $age <= 7776000 => 1,
            default => 0,
        };
    }
}
