<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'admin_user_id',
    'scope',
    'action',
    'target_label',
    'first_audit_event_id',
    'last_audit_event_id',
    'event_count',
    'item_count',
    'before_hash',
    'after_hash',
    'started_at',
    'ended_at',
])]
#[Guarded(['id'])]
class AdminActivityOrderingGroup extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'event_count' => 'integer',
            'item_count' => 'integer',
        ];
    }

    public function returnedToIdentity(): bool
    {
        return hash_equals(
            (string) $this->getAttribute('before_hash'),
            (string) $this->getAttribute('after_hash'),
        );
    }
}
