<?php

namespace App\Models;

use App\Domain\Publication\PublicationEventStateService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

#[Fillable(['admin_user_id', 'action', 'entity_type', 'entity_id', 'occurred_at', 'request_id', 'metadata'])]
#[Guarded(['id'])]
class AuditEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::created(function (self $event): void {
            $entityType = (string) $event->getAttribute('entity_type');
            if (PublicationEventStateService::tracks($entityType) && Schema::hasTable('publication_event_states')) {
                app(PublicationEventStateService::class)->record($event);
            }
        });

        static::updating(function (): never {
            throw new \LogicException('Audit events are append-only.');
        });

        static::deleting(function (): never {
            throw new \LogicException('Audit events are append-only.');
        });
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function publicationCheckpointEvent(): HasOne
    {
        return $this->hasOne(PublicationCheckpointEvent::class);
    }

    public function publicationEventState(): HasOne
    {
        return $this->hasOne(PublicationEventState::class);
    }
}
