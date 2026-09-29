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
    'before_state',
    'after_state',
    'started_at',
    'ended_at',
])]
#[Guarded(['id'])]
final class AdminActivityOrderingProjection extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'before_state' => 'array',
            'after_state' => 'array',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'event_count' => 'integer',
            'item_count' => 'integer',
        ];
    }

    public function isIdentity(): bool
    {
        return $this->state('before_state') === $this->state('after_state');
    }

    /** @return list<string> */
    public function beforeState(): array
    {
        return $this->state('before_state');
    }

    /** @return list<string> */
    public function afterState(): array
    {
        return $this->state('after_state');
    }

    /** @return list<string> */
    private function state(string $attribute): array
    {
        $state = $this->getAttribute($attribute);

        return is_array($state) && array_is_list($state)
            ? array_values(array_filter($state, static fn (mixed $value): bool => is_string($value)))
            : [];
    }
}
