<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['publication_checkpoint_id', 'audit_event_id', 'created_at'])]
#[Guarded(['id'])]
final class PublicationCheckpointEvent extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function checkpoint(): BelongsTo
    {
        return $this->belongsTo(PublicationCheckpoint::class, 'publication_checkpoint_id');
    }

    public function auditEvent(): BelongsTo
    {
        return $this->belongsTo(AuditEvent::class);
    }
}
