<?php

namespace App\Domain\Admin;

use App\Filament\Pages\SitePages;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\CvEntries\CvEntryResource;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\AuditEvent;
use App\Models\BlogPost;
use App\Models\CvEntry;
use App\Models\Exhibition;
use App\Models\MediaAsset;
use App\Models\SiteSection;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class AdminActivityFeed
{
    public const ACTIVITY_WINDOW_DAYS = 180;

    private const FILTER_WINDOWS = [7, 30, self::ACTIVITY_WINDOW_DAYS];

    public function __construct(private readonly AdminActionReceiptService $receipts) {}

    /**
     * @return array{activity: array<int, array<string, mixed>>, paginator: LengthAwarePaginator<int, AuditEvent>}
     */
    public function page(
        ?string $area = null,
        ?string $family = null,
        int $perPage = 30,
        ?User $actor = null,
        int $days = self::ACTIVITY_WINDOW_DAYS,
    ): array {
        $days = in_array($days, self::FILTER_WINDOWS, true) ? $days : self::ACTIVITY_WINDOW_DAYS;

        $query = AuditEvent::query()
            ->with('adminUser:id,name')
            ->where('occurred_at', '>=', now()->subDays($days))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        $actionKeys = $this->filteredActionKeys($area, $family);
        if ($actionKeys !== null) {
            $query->whereIn('action', $actionKeys);
        }

        /** @var LengthAwarePaginator<int, AuditEvent> $paginator */
        $paginator = $query->paginate($perPage)->withQueryString();

        return [
            'activity' => $this->project($paginator->getCollection(), $actor),
            'paginator' => $paginator,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function recent(int $limit = 7): array
    {
        /** @var EloquentCollection<int, AuditEvent> $events */
        $events = AuditEvent::query()
            ->with('adminUser:id,name')
            ->where('occurred_at', '>=', now()->subDays(self::ACTIVITY_WINDOW_DAYS))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $this->project($events);
    }

    /** @return array<int, string>|null */
    private function filteredActionKeys(?string $area, ?string $family): ?array
    {
        $areaKeys = $area !== null && $area !== '' ? AdminActionCatalog::keysForArea($area) : null;
        $familyKeys = $family !== null && $family !== '' ? AdminActionCatalog::keysForFamily($family) : null;

        if ($areaKeys === null && $familyKeys === null) {
            return null;
        }

        if ($areaKeys === null) {
            return $familyKeys;
        }

        if ($familyKeys === null) {
            return $areaKeys;
        }

        return array_values(array_intersect($areaKeys, $familyKeys));
    }

    /**
     * @param  Collection<int, AuditEvent>  $events
     * @return array<int, array<string, mixed>>
     */
    private function project(Collection $events, ?User $actor = null): array
    {
        $labels = $this->targetLabels($events);
        $undoReceipts = $actor instanceof User ? $this->receipts->availableForEvents($events, $actor) : [];

        return $events->map(function (AuditEvent $event) use ($labels, $undoReceipts): array {
            $actionKey = (string) $event->getAttribute('action');
            $entityType = (string) $event->getAttribute('entity_type');
            $entityId = (int) $event->getAttribute('entity_id');
            $definition = AdminActionCatalog::definition($actionKey);
            $target = $labels[$entityType][$entityId] ?? $this->fallbackTarget($entityType);
            /** @var CarbonInterface $occurredAt */
            $occurredAt = $event->getAttribute('occurred_at');
            $adminUser = $event->getRelationValue('adminUser');
            $receipt = $undoReceipts[(int) $event->getKey()] ?? null;
            $undo = null;

            if (is_array($receipt)) {
                $inverseLabel = (string) $receipt['inverse_label'];
                $undo = [
                    'id' => (int) $receipt['id'],
                    'inverse_label' => $inverseLabel,
                    'confirmation' => 'Undo “'.$definition['label'].'” for “'.$target.'”? This will apply “'.$inverseLabel.'”.',
                ];
            }

            return [
                'id' => (int) $event->getKey(),
                'action_key' => $actionKey,
                'action' => $definition['label'],
                'area' => $definition['area'],
                'family' => $definition['family'],
                'target' => $target,
                'url' => $this->targetUrl($entityType, $entityId, isset($labels[$entityType][$entityId])),
                'actor' => $adminUser?->getAttribute('name') ?? 'Admin',
                'when' => $occurredAt->diffForHumans(),
                'timestamp' => $occurredAt->format('Y-m-d H:i'),
                'undo' => $undo,
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, AuditEvent>  $events
     * @return array<string, array<int, string>>
     */
    private function targetLabels(Collection $events): array
    {
        $ids = $events
            ->groupBy(fn (AuditEvent $event): string => (string) $event->getAttribute('entity_type'))
            ->map(fn (Collection $group): array => $group
                ->pluck('entity_id')
                ->filter(fn (mixed $id): bool => is_int($id) || ctype_digit((string) $id))
                ->map(fn (mixed $id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all());

        return [
            'artwork' => $this->pluckLabels(Artwork::class, $ids->get('artwork', []), 'title'),
            'artwork_category' => $this->pluckLabels(ArtworkCategory::class, $ids->get('artwork_category', []), 'name'),
            'site_section' => $this->pluckLabels(SiteSection::class, $ids->get('site_section', []), 'title'),
            'media_asset' => $this->pluckLabels(MediaAsset::class, $ids->get('media_asset', []), 'original_filename'),
            'cv_entry' => $this->pluckLabels(CvEntry::class, $ids->get('cv_entry', []), 'title'),
            'exhibition' => $this->pluckLabels(Exhibition::class, $ids->get('exhibition', []), 'title'),
            'blog_post' => $this->pluckLabels(BlogPost::class, $ids->get('blog_post', []), 'title'),
            'blog_setting' => [1 => 'Blog settings'],
            'public_content_setting' => [1 => 'Website settings'],
        ];
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    private function pluckLabels(string $model, array $ids, string $attribute): array
    {
        if ($ids === []) {
            return [];
        }

        return $model::query()
            ->whereKey($ids)
            ->pluck($attribute, 'id')
            ->map(fn (mixed $label): string => (string) $label)
            ->all();
    }

    private function fallbackTarget(string $entityType): string
    {
        return match ($entityType) {
            'artwork' => 'Artwork no longer available',
            'artwork_category' => 'Gallery no longer available',
            'site_section' => 'Public page no longer available',
            'media_asset' => 'Media no longer available',
            'cv_entry' => 'Vita entry no longer available',
            'exhibition' => 'Exhibition no longer available',
            'blog_post' => 'Blog post no longer available',
            'blog_setting' => 'Blog settings',
            'public_content_setting' => 'Website settings',
            default => 'Administrative record',
        };
    }

    private function targetUrl(string $entityType, int $entityId, bool $exists): ?string
    {
        if (! $exists && ! in_array($entityType, ['blog_setting', 'public_content_setting'], true)) {
            return null;
        }

        return match ($entityType) {
            'artwork' => ArtworkResource::getUrl('edit', ['record' => $entityId]),
            'artwork_category' => ArtworkResource::getUrl('gallery', ['gallery' => $entityId]),
            'site_section' => SitePages::getUrl(),
            'media_asset' => MediaAssetResource::getUrl('view', ['record' => $entityId]),
            'cv_entry' => CvEntryResource::getUrl('edit', ['record' => $entityId]),
            'exhibition' => ExhibitionResource::getUrl('edit', ['record' => $entityId]),
            'blog_post' => BlogPostResource::getUrl('edit', ['record' => $entityId]),
            'blog_setting' => SitePages::getUrl(),
            'public_content_setting' => PublicContentSettingResource::getNavigationUrl(),
            default => null,
        };
    }
}
