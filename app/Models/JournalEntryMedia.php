<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['blog_post_id', 'exhibition_id', 'media_asset_id', 'role', 'position', 'alt_text_override'])]
#[Guarded(['id'])]
final class JournalEntryMedia extends Model
{
    public const ROLE_COVER = 'cover';

    public const ROLE_GALLERY = 'gallery';

    public const ROLES = [self::ROLE_COVER, self::ROLE_GALLERY];

    protected $table = 'journal_entry_media';

    public function blogPost(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class);
    }

    public function exhibition(): BelongsTo
    {
        return $this->belongsTo(Exhibition::class);
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    public function entry(): BlogPost|Exhibition|null
    {
        $post = $this->getRelationValue('blogPost');
        if ($post instanceof BlogPost) {
            return $post;
        }

        $exhibition = $this->getRelationValue('exhibition');

        return $exhibition instanceof Exhibition ? $exhibition : null;
    }
}
