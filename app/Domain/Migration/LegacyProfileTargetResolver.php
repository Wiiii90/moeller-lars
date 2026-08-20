<?php

namespace App\Domain\Migration;

use App\Models\CvEntry;

final class LegacyProfileTargetResolver
{
    public function resolve(string $legacySource, ?int $legacyId): ?CvEntry
    {
        $query = CvEntry::query()
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
