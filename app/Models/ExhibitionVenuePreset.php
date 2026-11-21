<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organizer_preset_id', 'name', 'street', 'postal_code', 'city', 'country', 'position'])]
#[Guarded(['id'])]
final class ExhibitionVenuePreset extends Model
{
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(ExhibitionOrganizerPreset::class, 'organizer_preset_id');
    }
}
