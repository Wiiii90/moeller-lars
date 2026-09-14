<?php

namespace App\Models;

use App\Domain\Admin\DashboardNotificationRetention;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AdminNotification extends Model
{
    protected $fillable = [
        'user_id',
        'source_id',
        'type',
        'status',
        'title',
        'body',
        'action_url',
        'action_label',
        'entity_type',
        'entity_id',
        'audit_event_id',
        'publication_checkpoint_id',
        'metadata',
        'read_at',
    ];

    protected static function booted(): void
    {
        static::created(static function (AdminNotification $notification): void {
            $userId = (int) $notification->getAttribute('user_id');
            if ($userId > 0) {
                app(DashboardNotificationRetention::class)->pruneFor($userId);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditEvent(): BelongsTo
    {
        return $this->belongsTo(AuditEvent::class);
    }

    public function publicationCheckpoint(): BelongsTo
    {
        return $this->belongsTo(PublicationCheckpoint::class);
    }

    public function markRead(): void
    {
        if ($this->read_at !== null) {
            return;
        }

        $this->forceFill(['read_at' => now()])->save();
    }

    public function markUnread(): void
    {
        if ($this->read_at === null) {
            return;
        }

        $this->forceFill(['read_at' => null])->save();
    }
}
