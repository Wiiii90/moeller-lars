<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name'])]
#[Guarded(['id'])]
class ArtworkMaterialPreset extends Model
{
}
