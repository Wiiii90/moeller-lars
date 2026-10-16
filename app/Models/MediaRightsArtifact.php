<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

/**
 * Immutable shared storage identity. Publication history keeps the IDs of
 * artifacts selected by each Working/Committed media row.
 */
#[Fillable(['media_asset_id', 'role', 'revision', 'storage_key', 'source_sha256', 'sha256', 'byte_size', 'mime_type'])]
#[Guarded(['id'])]
final class MediaRightsArtifact extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
