<?php

namespace App\Domain\Migration;

use App\Models\CvEntry;
use Illuminate\Database\Eloquent\Builder;

final class LegacyProfileTargetResolver
{
    public function resolve(string $legacySource, ?int $legacyId): ?CvEntry
    {
        /** @var Builder<CvEntry> $query */
        $query = CvEntry::query();
        $query
            ->where('legacy_source', $legacySource)
            ->where('migration_batch_id', LegacyPublicCvImporter::BATCH);

        if ($legacyId !== null) {
            return $query->where('legacy_id', $legacyId)->first();
        }

        return $query
            ->orderBy('position')
            ->orderBy('id')
            ->first();
    }
}
