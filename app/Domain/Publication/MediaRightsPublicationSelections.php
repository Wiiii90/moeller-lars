<?php

namespace App\Domain\Publication;

use App\Models\MediaRightsArtifact;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\PublicationCheckpoint;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * PublicationSnapshot's single companion for immutable rights-file choices.
 *
 * Selections do not change publication row hashes or legacy snapshots.
 * Every call modifying selections must be inside the canonical publication
 * advisory lock and transaction, never invoked by an HTTP media request.
 */
final class MediaRightsPublicationSelections
{
    private const TABLE = 'media_rights_artifact_selections';

    /**
     * Legacy public/media requests must work before this optional migration
     * exists. The complete versioned artifact publication flow is opt-in.
     */
    public function enabled(): bool
    {
        return config('media.rights_preparation_enabled', false) === true;
    }

    public function deliveryEnabled(): bool
    {
        return $this->enabled() && config('media.rights_delivery_enabled', false) === true;
    }

    public function selectWorking(MediaRightsArtifact $artifact, string $role, int $sourceId): void
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Rights artifact publication is not enabled.');
        }

        if (! in_array($role, ['original', 'thumbnail'], true)
            || $sourceId <= 0 || ! is_numeric($artifact->getKey())
            || $artifact->getAttribute('role') !== $role
            || (int) $artifact->getAttribute('media_asset_id') <= 0
            || ($role === 'original'
                && (int) $artifact->getAttribute('media_asset_id') !== $sourceId)) {
            throw new InvalidArgumentException('Rights artifact selection does not match its authoritative media source.');
        }

        DB::table(self::TABLE)->upsert([[
            'scope_key' => 'working',
            'media_asset_id' => (int) $artifact->getAttribute('media_asset_id'),
            'role' => $role,
            'source_id' => $sourceId,
            'media_rights_artifact_id' => (int) $artifact->getKey(),
        ]], ['scope_key', 'role', 'source_id'], ['media_asset_id', 'media_rights_artifact_id']);
    }

    public function captureCheckpoint(PublicationCheckpoint $checkpoint): void
    {
        if (! $this->enabled()) {
            return;
        }

        $scope = $this->checkpointScope($checkpoint);
        if (DB::table(self::TABLE)->where('scope_key', $scope)->exists()) {
            throw new RuntimeException('An immutable publication already has rights artifact selections.');
        }

        DB::statement(
            "INSERT INTO media_rights_artifact_selections (scope_key, media_asset_id, role, source_id, media_rights_artifact_id)
             SELECT ?, media_asset_id, role, source_id, media_rights_artifact_id
             FROM media_rights_artifact_selections WHERE scope_key = 'working'",
            [$scope],
        );
    }

    public function restoreWorking(?PublicationCheckpoint $checkpoint): void
    {
        if (! $this->enabled()) {
            return;
        }

        DB::table(self::TABLE)->where('scope_key', 'working')->delete();

        if ($checkpoint === null) {
            return;
        }

        DB::statement(
            "INSERT INTO media_rights_artifact_selections (scope_key, media_asset_id, role, source_id, media_rights_artifact_id)
             SELECT 'working', media_asset_id, role, source_id, media_rights_artifact_id
             FROM media_rights_artifact_selections WHERE scope_key = ?",
            [$this->checkpointScope($checkpoint)],
        );
    }

    /**
     * Rights-file selections participate in the EXISTING publication Pending
     * calculation; no change to PublicationSnapshot schema or hash history.
     */
    public function pendingCount(?PublicationCheckpoint $checkpoint): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $row = DB::selectOne(
            "WITH working AS (
                SELECT role, source_id, media_rights_artifact_id
                FROM media_rights_artifact_selections WHERE scope_key = 'working'
             ), baseline AS (
                SELECT role, source_id, media_rights_artifact_id
                FROM media_rights_artifact_selections WHERE scope_key = ?
             )
             SELECT COUNT(*)::int AS aggregate
             FROM working FULL OUTER JOIN baseline USING (role, source_id)
             WHERE working.media_rights_artifact_id IS DISTINCT FROM baseline.media_rights_artifact_id",
            [$this->baselineScope($checkpoint)],
        );

        return (int) ($row->aggregate ?? 0);
    }

    /** @return list<object> */
    public function pendingDetails(?PublicationCheckpoint $checkpoint, int $limit): array
    {
        if (! $this->enabled() || $limit < 1) {
            return [];
        }

        return DB::select(
            "WITH working AS (
                SELECT role, source_id, media_rights_artifact_id
                FROM media_rights_artifact_selections WHERE scope_key = 'working'
             ), baseline AS (
                SELECT role, source_id, media_rights_artifact_id
                FROM media_rights_artifact_selections WHERE scope_key = ?
             )
             SELECT COALESCE(working.role, baseline.role) AS role,
                    COALESCE(working.source_id, baseline.source_id) AS source_id,
                    CASE WHEN baseline.media_rights_artifact_id IS NULL THEN 'added'
                         WHEN working.media_rights_artifact_id IS NULL THEN 'removed'
                         ELSE 'changed' END AS change_kind
             FROM working FULL OUTER JOIN baseline USING (role, source_id)
             WHERE working.media_rights_artifact_id IS DISTINCT FROM baseline.media_rights_artifact_id
             ORDER BY role, source_id LIMIT ?",
            [$this->baselineScope($checkpoint), $limit],
        );
    }

    private function baselineScope(?PublicationCheckpoint $checkpoint): string
    {
        return $checkpoint === null ? 'checkpoint:0' : $this->checkpointScope($checkpoint);
    }

    public function selectedForPublic(MediaAsset|MediaVariant $source): ?MediaRightsArtifact
    {
        if (! $this->deliveryEnabled()) {
            return null;
        }

        $checkpointId = PublicationCheckpoint::query()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->value('id');

        return is_numeric($checkpointId)
            ? $this->selected('checkpoint:'.(int) $checkpointId, $source)
            : null;
    }

    public function selectedForWorking(MediaAsset|MediaVariant $source): ?MediaRightsArtifact
    {
        if (! $this->enabled()) {
            return null;
        }

        return $this->selected('working', $source);
    }

    private function selected(string $scope, MediaAsset|MediaVariant $source): ?MediaRightsArtifact
    {
        $role = $source instanceof MediaVariant ? 'thumbnail' : 'original';
        $row = DB::table(self::TABLE)
            ->where('scope_key', $scope)
            ->where('role', $role)
            ->where('source_id', (int) $source->getKey())
            ->first(['media_rights_artifact_id', 'media_asset_id']);

        if ($row === null) {
            return null;
        }

        $assetId = $source instanceof MediaVariant
            ? (int) $source->getAttribute('media_asset_id')
            : (int) $source->getKey();
        if ($assetId <= 0 || (int) $row->media_asset_id !== $assetId) {
            throw new RuntimeException('Published rights selection references a different media asset.');
        }

        $artifact = MediaRightsArtifact::query()->find($row->media_rights_artifact_id);
        if (! $artifact instanceof MediaRightsArtifact
            || (int) $artifact->getAttribute('media_asset_id') !== $assetId
            || $artifact->getAttribute('role') !== $role
            || $artifact->getAttribute('source_sha256') !== $source->getAttribute('sha256')) {
            throw new RuntimeException('Published rights artifact does not match its authoritative source.');
        }

        return $artifact;
    }

    private function checkpointScope(PublicationCheckpoint $checkpoint): string
    {
        $id = (int) $checkpoint->getKey();
        if ($id <= 0) {
            throw new InvalidArgumentException('A persisted publication checkpoint is required.');
        }

        return 'checkpoint:'.$id;
    }
}
