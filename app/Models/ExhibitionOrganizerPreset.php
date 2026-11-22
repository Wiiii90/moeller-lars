<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'position'])]
#[Guarded(['id'])]
final class ExhibitionOrganizerPreset extends Model
{
}
