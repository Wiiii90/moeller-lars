<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'position'])]
#[Guarded(['id'])]
final class ExhibitionOrganizerPreset extends Model
{
    public function venues(): HasMany
    {
        return $this->hasMany(ExhibitionVenuePreset::class, 'organizer_preset_id')
            ->orderBy('position')->orderBy('id');
    }
}
