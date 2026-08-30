<?php

namespace App\Filament\Support;

use App\Domain\Media\MediaReferenceQuery;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class MediaStorageReferenceCatalog
{
    public function __construct(
        private readonly MediaReferenceCatalog $catalog,
        private readonly MediaReferenceQuery $referenceQuery,
    ) {}

    /** @param Builder<MediaAsset> $query */
    public function eagerLoad(Builder $query): void
    {
        $this->catalog->eagerLoad($query);
    }

    /** @return array{files:int,images:int,videos:int,audio:int,unreferenced:int,bytes:int} */
    public function libraryMetrics(): array
    {
        return $this->catalog->libraryMetrics();
    }

    /**
     * Resolve the canonical referenced set in one bounded query. The same
     * MediaReferenceQuery powers the Files workspace's in-use/unreferenced
     * filters, so Storage does not maintain a second definition of "unused".
     *
     * @param list<int> $assetIds
     * @return list<int>
     */
    public function referencedIds(array $assetIds): array
    {
        $assetIds = array_values(array_unique(array_filter(
            array_map('intval', $assetIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($assetIds === []) {
            return [];
        }

        /** @var Builder<MediaAsset> $query */
        $query = MediaAsset::query()->whereIn('id', $assetIds);
        $this->referenceQuery->apply($query, true);

        return $query->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    /**
     * Add Storage-specific area/target metadata to the canonical concrete
     * reference rows without changing the shared Files reference payload.
     *
     * @return list<array{area:string,area_label:string,target_key:string,target_label:string,type:string,label:string,url:?string}>
     */
    public function references(MediaAsset $asset): array
    {
        $rows = [];

        foreach ($this->catalog->references($asset) as $reference) {
            $type = trim((string) ($reference['type'] ?? ''));
            $label = trim((string) ($reference['label'] ?? ''));
            $url = isset($reference['url']) && is_string($reference['url']) ? $reference['url'] : null;
            [$area, $areaLabel, $targetLabel] = $this->context($type, $label);

            $rows[] = [
                'area' => $area,
                'area_label' => $areaLabel,
                'target_key' => 'reference:'.sha1(implode('|', [$area, $type, $targetLabel, $url ?? ''])),
                'target_label' => $targetLabel,
                'type' => $type,
                'label' => $label,
                'url' => $url,
            ];
        }

        $unique = [];
        foreach ($rows as $row) {
            $unique[implode('|', [$row['target_key'], $row['type'], $row['label'], $row['url'] ?? ''])] = $row;
        }

        return array_values($unique);
    }

    /**
     * @param EloquentCollection<int, MediaAsset> $assets
     * @return array<int, list<array{area:string,area_label:string,target_key:string,target_label:string,type:string,label:string,url:?string}>>
     */
    public function referencesByAssetId(EloquentCollection $assets): array
    {
        $references = [];
        foreach ($assets as $asset) {
            $references[(int) $asset->getKey()] = $this->references($asset);
        }

        return $references;
    }

    /** @return array{0:string,1:string,2:string} */
    private function context(string $type, string $label): array
    {
        if (str_starts_with($type, 'Gallery:')) {
            $gallery = trim(substr($type, strlen('Gallery:')));

            return ['galleries', 'Galleries', $gallery !== '' ? $gallery : 'Gallery'];
        }

        if (str_starts_with($type, 'Journal:')) {
            $journalType = trim(substr($type, strlen('Journal:')));
            $entry = $this->referenceSubject($label);
            $prefix = $journalType === 'Exhibitions' ? 'Exhibition' : ($journalType === 'Blog' ? 'Blog post' : $journalType);

            return ['journal', 'Journal', trim($prefix.' · '.$entry, ' ·')];
        }

        if (str_starts_with($type, 'Custom Page:')) {
            $page = trim(substr($type, strlen('Custom Page:')));

            return ['custom-pages', 'Custom pages', $page !== '' ? $page : 'Custom page'];
        }

        if ($type === 'CV') {
            return ['cv', 'CV', $this->referenceSubject($label) ?: 'CV'];
        }

        if (str_starts_with($type, 'Home:')) {
            $template = trim(substr($type, strlen('Home:')));

            return ['home', 'Home', $template === '' ? 'Home' : 'Home · '.$template];
        }

        if ($type === 'Site identity') {
            return ['site-identity', 'Site identity', 'Site identity'];
        }

        return ['referenced', $type !== '' ? $type : 'Referenced', $this->referenceSubject($label) ?: ($type !== '' ? $type : 'Referenced')];
    }

    private function referenceSubject(string $label): string
    {
        $parts = preg_split('/\s+—\s+/u', $label, 2);
        $subject = is_array($parts) ? trim((string) ($parts[0] ?? '')) : trim($label);

        return $subject;
    }
}
