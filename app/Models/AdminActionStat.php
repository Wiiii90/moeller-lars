<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['admin_user_id', 'action_key', 'use_count', 'last_used_at'])]
#[Guarded(['id'])]
final class AdminActionStat extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'use_count' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
