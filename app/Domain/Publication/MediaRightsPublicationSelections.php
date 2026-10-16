<?php

namespace App\Domain\Publication;

use App\Models\MediaRightsArtifact;
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

    public function selectWorking(MediaRightsArtifact $artifact, string $role, int $sourceId): void
    {
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

    private function checkpointScope(PublicationCheckpoint $checkpoint): string
    {
        $id = (int) $checkpoint->getKey();
        if ($id <= 0) {
            throw new InvalidArgumentException('A persisted publication checkpoint is required.');
        }

        return 'checkpoint:'.$id;
    }
}
