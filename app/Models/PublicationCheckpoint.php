<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['admin_user_id', 'message', 'change_count', 'published_at'])]
#[Guarded(['id'])]
final class PublicationCheckpoint extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'change_count' => 'integer',
            'published_at' => 'datetime',
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
}
