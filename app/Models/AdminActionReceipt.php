<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'audit_event_id',
    'admin_user_id',
    'action_key',
    'inverse_action_key',
    'entity_type',
    'entity_id',
    'before_state',
    'after_state',
    'media_asset_id',
    'artwork_media_id',
    'neighbor_artwork_media_id',
    'previous_artwork_media_id',
    'next_artwork_media_id',
    'before_position',
    'after_position',
    'inverse_direction',
    'receipt_version',
    'expires_at',
    'undone_at',
    'created_at',
])]
#[Guarded(['id'])]
final class AdminActionReceipt extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'media_asset_id' => 'integer',
            'artwork_media_id' => 'integer',
            'neighbor_artwork_media_id' => 'integer',
            'previous_artwork_media_id' => 'integer',
            'next_artwork_media_id' => 'integer',
            'before_position' => 'integer',
            'after_position' => 'integer',
            'receipt_version' => 'integer',
            'expires_at' => 'datetime',
            'undone_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function auditEvent(): BelongsTo
    {
        return $this->belongsTo(AuditEvent::class);
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
