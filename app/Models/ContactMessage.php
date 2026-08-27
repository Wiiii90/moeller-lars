<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'sender_name',
    'sender_email',
    'message',
    'read_at',
    'mail_delivery_status',
    'mail_delivered_at',
])]
#[Guarded(['id'])]
final class ContactMessage extends Model
{
    public const DELIVERY_PENDING = 'pending';
    public const DELIVERY_DELIVERED = 'delivered';
    public const DELIVERY_UNAVAILABLE = 'unavailable';
    public const DELIVERY_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'mail_delivered_at' => 'datetime',
        ];
    }

    public function markRead(): void
    {
        if ($this->getAttribute('read_at') !== null) {
            return;
        }

        $this->forceFill(['read_at' => now()])->save();
    }

    public function markUnread(): void
    {
        if ($this->getAttribute('read_at') === null) {
            return;
        }

        $this->forceFill(['read_at' => null])->save();
    }

    public function markMailDelivered(): void
    {
        $this->forceFill([
            'mail_delivery_status' => self::DELIVERY_DELIVERED,
            'mail_delivered_at' => now(),
        ])->save();
    }

    public function markMailUnavailable(): void
    {
        $this->forceFill([
            'mail_delivery_status' => self::DELIVERY_UNAVAILABLE,
            'mail_delivered_at' => null,
        ])->save();
    }

    public function markMailFailed(): void
    {
        $this->forceFill([
            'mail_delivery_status' => self::DELIVERY_FAILED,
            'mail_delivered_at' => null,
        ])->save();
    }
}
