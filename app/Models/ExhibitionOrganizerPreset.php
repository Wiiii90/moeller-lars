<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'street', 'postal_code', 'city', 'country', 'website'])]
#[Guarded(['id'])]
final class ExhibitionOrganizerPreset extends Model
{
}
