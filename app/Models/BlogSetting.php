<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['listing_title', 'listing_intro'])]
#[Guarded(['id'])]
class BlogSetting extends Model
{
    use HasFactory;

    protected $table = 'blog_settings';

    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new \LogicException('The blog setting singleton cannot be deleted.');
        });
    }
}
