<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['audit_event_id', 'entity_type', 'entity_id', 'status', 'updated_at'])]
#[Guarded(['id'])]
class PublicationEventState extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_NOT_PENDING = 'not_pending';

    public $timestamps = false;

    public function auditEvent(): BelongsTo
    {
        return $this->belongsTo(AuditEvent::class);
    }
}
