<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['audit_event_id', 'projection_id', 'sequence'])]
#[Guarded([])]
final class AdminActivityOrderingEvent extends Model
{
    protected $primaryKey = 'audit_event_id';

    public $incrementing = false;

    public $timestamps = false;

    public function projection(): BelongsTo
    {
        return $this->belongsTo(AdminActivityOrderingProjection::class, 'projection_id');
    }
}
