<?php

namespace App\Filament\Support;

use App\Domain\Media\MediaStorageBreakdown;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class MediaStorageAnalysisStore
{
    private const CACHE_SECONDS = 300;

    public function __construct(
        private readonly MediaStorageReferenceCatalog $references,
        private readonly MediaStorageBreakdown $breakdown,
    ) {}

    /**
     * Build the complete file analysis once, keep it server-side, and return
     * only the opaque cache token plus compact library metrics and the analysis
     * needed by the current request.
     *
     * @param array<string, int> $authoritativeFiles
     * @return array{token:string,analysis:array<string,mixed>,metrics:array{files:int,images:int,videos:int,audio:int,unreferenced:int,bytes:int}}
     */
    public function create(array $authoritativeFiles): array
    {
        $metrics = $this->references->libraryMetrics();

        /** @var EloquentCollection<int, MediaAsset> $assets */
        $assets = new EloquentCollection();
        if ($authoritativeFiles !== []) {
            $assetQuery = MediaAsset::query()->whereIn('storage_key', array_keys($authoritativeFiles));
            $this->references->eagerLoad($assetQuery);
            $assets = $assetQuery->get();
        }

        $referencesByAssetId = $this->references->referencesByAssetId($assets);
        $referencedIds = $this->references->referencedIds(
            $assets->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all(),
        );
        $analysis = $this->breakdown->analyze(
            $authoritativeFiles,
            $assets,
            $referencesByAssetId,
            $referencedIds,
        );

        $token = Str::random(48);
        Cache::put($this->cacheKey($token), $analysis, self::CACHE_SECONDS);

        return ['token' => $token, 'analysis' => $analysis, 'metrics' => $metrics];
    }

    /** @return array<string,mixed>|null */
    public function get(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $analysis = Cache::get($this->cacheKey($token));

        return is_array($analysis) ? $analysis : null;
    }

    private function cacheKey(string $token): string
    {
        return 'media-storage:analysis:'.$token;
    }
}
