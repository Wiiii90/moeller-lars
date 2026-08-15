<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['source_path', 'target_path', 'status_code', 'enabled', 'reason', 'legacy_id', 'legacy_source', 'migration_batch_id', 'migrated_at'])]
#[Guarded(['id'])]
class Redirect extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'migrated_at' => 'datetime'];
    }
}
