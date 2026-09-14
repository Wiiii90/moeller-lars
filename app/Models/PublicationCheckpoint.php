<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'admin_user_id',
    'message',
    'change_count',
    'published_at',
    'hash',
    'snapshot_hash',
    'schema_hash',
    'snapshot_available',
    'operation',
    'parent_publication_checkpoint_id',
    'source_publication_checkpoint_id',
])]
#[Guarded(['id'])]
final class PublicationCheckpoint extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'change_count' => 'integer',
            'published_at' => 'datetime',
            'snapshot_available' => 'boolean',
        ];
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(PublicationCheckpointEvent::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_publication_checkpoint_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_publication_checkpoint_id');
    }

    public function shortHash(int $length = 10): string
    {
        $hash = (string) ($this->getAttribute('hash') ?? '');

        return $hash !== '' ? substr($hash, 0, max(7, min(16, $length))) : '#'.$this->getKey();
    }
}
