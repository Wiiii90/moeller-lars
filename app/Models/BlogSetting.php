<?php

namespace App\Models;

use App\Models\Concerns\SingletonRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['listing_title', 'listing_intro'])]
#[Guarded(['id'])]
class BlogSetting extends Model
{
    use HasFactory;
    use SingletonRecord;

    protected $table = 'blog_settings';
}
