<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['public_enabled', 'listing_title', 'listing_intro'])]
#[Guarded(['id'])]
class BlogSetting extends Model
{
    use HasFactory;

    protected $table = 'blog_settings';

    protected function casts(): array
    {
        return ['public_enabled' => 'boolean'];
    }
}
