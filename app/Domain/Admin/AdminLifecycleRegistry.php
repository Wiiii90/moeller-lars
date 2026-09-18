<?php

namespace App\Domain\Admin;

use App\Domain\Artwork\ArtworkEditorialService;
use App\Domain\Content\ExhibitionEditorialService;
use App\Models\Artwork;
use App\Models\Exhibition;
use Illuminate\Contracts\Container\Container;
use Illuminate\Validation\ValidationException;

final class AdminLifecycleRegistry
{
    /** @var array<string, array{entity:string,before:string,after:string,inverse:string}> */
    private const TRANSITIONS = [
        'artwork.published' => ['entity' => 'artwork', 'before' => 'draft', 'after' => 'published', 'inverse' => 'artwork.unpublished'],
        'artwork.unpublished' => ['entity' => 'artwork', 'before' => 'published', 'after' => 'draft', 'inverse' => 'artwork.published'],
        'exhibition.published' => ['entity' => 'exhibition', 'before' => 'draft', 'after' => 'published', 'inverse' => 'exhibition.unpublished'],
        'exhibition.unpublished' => ['entity' => 'exhibition', 'before' => 'published', 'after' => 'draft', 'inverse' => 'exhibition.published'],
    ];

    /** @var array<string, class-string<Artwork|Exhibition>> */
    private const ENTITY_MODELS = [
        'artwork' => Artwork::class,
        'exhibition' => Exhibition::class,
    ];

    public function __construct(private readonly Container $container) {}

    /** @return array{entity:string,before:string,after:string,inverse:string}|null */
    public function transitionForAction(string $action): ?array
    {
        return self::TRANSITIONS[$action] ?? null;
    }

    public function findTarget(string $entityType, int $entityId, bool $lockForUpdate = false): Artwork|Exhibition|null
    {
        $model = self::ENTITY_MODELS[$entityType] ?? null;
        if ($model === null || $entityId < 1) {
            return null;
        }

        $query = $model::query()->whereKey($entityId);
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        /** @var Artwork|Exhibition|null $target */
        $target = $query->first();

        return $target;
    }

    /**
     * @param  array<string, array<int, int>>  $idsByEntityType
     * @return array<string, array<int, string>>
     */
    public function loadStates(array $idsByEntityType): array
    {
        $states = [];

        foreach (self::ENTITY_MODELS as $entityType => $model) {
            $ids = $idsByEntityType[$entityType] ?? [];
            $states[$entityType] = $ids === []
                ? []
                : $model::query()
                    ->whereKey($ids)
                    ->pluck('state', 'id')
                    ->map(static fn (mixed $state): string => (string) $state)
                    ->all();
        }

        return $states;
    }

    public function applyInverse(Artwork|Exhibition $target, string $inverseActionKey): Artwork|Exhibition
    {
        return match ($inverseActionKey) {
            'artwork.published' => $this->container->make(ArtworkEditorialService::class)->publish($this->artwork($target)),
            'artwork.unpublished' => $this->container->make(ArtworkEditorialService::class)->unpublish($this->artwork($target)),
            'exhibition.published' => $this->container->make(ExhibitionEditorialService::class)->publish($this->exhibition($target)),
            'exhibition.unpublished' => $this->container->make(ExhibitionEditorialService::class)->unpublish($this->exhibition($target)),
            default => throw ValidationException::withMessages(['undo' => 'This editorial change has no reversible contract.']),
        };
    }

    private function artwork(Artwork|Exhibition $target): Artwork
    {
        if (! $target instanceof Artwork) {
            throw ValidationException::withMessages(['undo' => 'This lifecycle action does not match its artwork target.']);
        }

        return $target;
    }

    private function exhibition(Artwork|Exhibition $target): Exhibition
    {
        if (! $target instanceof Exhibition) {
            throw ValidationException::withMessages(['undo' => 'This lifecycle action does not match its exhibition target.']);
        }

        return $target;
    }
}
