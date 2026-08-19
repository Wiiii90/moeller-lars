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
