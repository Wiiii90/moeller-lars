<?php

namespace App\Domain\Admin;

use App\Filament\Pages\SitePages;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Models\AdminActionStat;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class AdminQuickActionService
{
    private const MAX_PERSONALIZED = 3;

    private const MIN_DESTINATION_USES = 2;

    /**
     * @return array<int, array{key:string,label:string,description:string,url:string,reason:string,score:int}>
     */
    public function forUser(User $user): array
    {
        /** @var EloquentCollection<int, AdminActionStat> $stats */
        $stats = AdminActionStat::query()
            ->where('admin_user_id', $user->getKey())
            ->get(['action_key', 'use_count', 'last_used_at']);

        /** @var array<string, array{use_count:int,last_used_at:CarbonInterface}> $destinations */
        $destinations = [];

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

            if (! isset($destinations[$destinationKey])) {
                $destinations[$destinationKey] = [
                    'use_count' => $useCount,
                    'last_used_at' => $lastUsedAt,
                ];

                continue;
            }

            $destinations[$destinationKey]['use_count'] += $useCount;
            if ($lastUsedAt->getTimestamp() > $destinations[$destinationKey]['last_used_at']->getTimestamp()) {
                $destinations[$destinationKey]['last_used_at'] = $lastUsedAt;
            }
        }

        $ranked = [];
        foreach ($destinations as $key => $usage) {
            if ($usage['use_count'] < self::MIN_DESTINATION_USES) {
                continue;
            }

            $definition = $this->definition($key);
            if ($definition === null) {
                continue;
            }

            $ranked[] = [
                ...$definition,
                'reason' => $this->reason($usage['use_count'], $usage['last_used_at']),
                'score' => ($this->frequencyTier($usage['use_count']) * 10) + $this->recencyTier($usage['last_used_at']),
                'use_count' => $usage['use_count'],
                'last_used_at' => $usage['last_used_at'],
            ];
        }

        usort($ranked, static function (array $left, array $right): int {
            $score = $right['score'] <=> $left['score'];
            if ($score !== 0) {
                return $score;
            }

            $frequency = $right['use_count'] <=> $left['use_count'];
            if ($frequency !== 0) {
                return $frequency;
            }

            $recency = $right['last_used_at']->getTimestamp() <=> $left['last_used_at']->getTimestamp();
            if ($recency !== 0) {
                return $recency;
            }

            return $left['key'] <=> $right['key'];
        });

        return array_map(
            static fn (array $action): array => [
                'key' => $action['key'],
                'label' => $action['label'],
                'description' => $action['description'],
                'url' => $action['url'],
                'reason' => $action['reason'],
                'score' => $action['score'],
            ],
            array_slice($ranked, 0, self::MAX_PERSONALIZED),
        );
    }

    private function destinationKey(string $actionKey): ?string
    {
        return match (true) {
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

    /** @return array{key:string,label:string,description:string,url:string}|null */
    private function definition(string $key): ?array
    {
        return match ($key) {
            'pages' => [
                'key' => $key,
                'label' => 'Manage pages',
                'description' => 'Return to the public page tree and its page-specific content workspaces.',
                'url' => SitePages::getUrl(),
            ],
            'files' => [
                'key' => $key,
                'label' => 'Open Files',
                'description' => 'Find, inspect and reuse media files.',
                'url' => MediaAssetResource::getUrl('index'),
            ],
            'general' => [
                'key' => $key,
                'label' => 'General',
                'description' => 'Edit site identity, contact, social and legal settings.',
                'url' => PublicContentSettingResource::getNavigationUrl(),
            ],
            default => null,
        };
    }

    private function frequencyTier(int $useCount): int
    {
        return min(7, (int) floor(log($useCount, 2)) + 1);
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

    private function reason(int $useCount, CarbonInterface $lastUsedAt): string
    {
        $uses = $useCount === 1 ? '1 related action' : $useCount.' related actions';

        return $uses.' · last used '.$lastUsedAt->diffForHumans();
    }
}
