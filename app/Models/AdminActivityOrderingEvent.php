<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['audit_event_id', 'group_id', 'sequence'])]
#[Guarded([])]
class AdminActivityOrderingEvent extends Model
{
    protected $primaryKey = 'audit_event_id';

    public $incrementing = false;

    public $timestamps = false;

    public function group(): BelongsTo
    {
        return $this->belongsTo(AdminActivityOrderingGroup::class, 'group_id');
    }
}
