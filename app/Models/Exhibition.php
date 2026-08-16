<?php

namespace App\Models;

use App\Domain\Content\SafeLinkPolicy;
use App\Domain\Content\SafeRichTextRenderer;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['slug', 'title', 'state', 'position', 'kind', 'venue', 'city', 'country', 'description', 'external_url', 'directions_url', 'starts_on', 'ends_on', 'date_text', 'legacy_id', 'legacy_source', 'migration_batch_id', 'migrated_at', 'published_at'])]
#[Guarded(['id'])]
class Exhibition extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'migrated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function mediaUsages(): HasMany
    {
        return $this->hasMany(ExhibitionMedia::class);
    }

    public function mediaAssets(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'exhibition_media')
            ->withPivot(['role', 'position', 'alt_text_override'])
            ->withTimestamps();
    }

    public function temporalState(CarbonInterface $date): string
    {
        /** @var CarbonInterface|null $startsOn */
        $startsOn = $this->getAttribute('starts_on');

        if ($startsOn === null) {
            return 'unknown';
        }

        $date = CarbonImmutable::instance($date)->startOfDay();
        $startsOn = CarbonImmutable::instance($startsOn)->startOfDay();

        if ($date->isBefore($startsOn)) {
            return 'upcoming';
        }

        /** @var CarbonInterface|null $endsOn */
        $endsOn = $this->getAttribute('ends_on');

        if ($endsOn !== null) {
            return $date->isAfter(CarbonImmutable::instance($endsOn)->startOfDay())
                ? 'past'
                : 'current';
        }

        return $date->isSameDay($startsOn) ? 'current' : 'past';
    }

    protected static function booted(): void
    {
        static::saving(function (self $exhibition): void {
            if ($exhibition->getAttribute('state') !== 'published') {
                return;
            }

            foreach (['title', 'date_text'] as $field) {
                $value = $exhibition->getAttribute($field);
                if (! is_string($value) || trim($value) === '') {
                    throw ValidationException::withMessages([$field => 'Published exhibitions require explicit '.$field.'.']);
                }
            }

            $description = $exhibition->getAttribute('description');
            if ($description !== null) {
                if (! is_string($description)) {
                    throw ValidationException::withMessages(['description' => 'Exhibition description must be text.']);
                }
                app(SafeRichTextRenderer::class)->assertValid($description);
            }

            foreach (['external_url', 'directions_url'] as $field) {
                $url = $exhibition->getAttribute($field);
                if ($url !== null && (! is_string($url)
                    || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
                    || ! app(SafeLinkPolicy::class)->isAllowed($url))) {
                    throw ValidationException::withMessages([$field => 'Exhibition links must be valid HTTP or HTTPS URLs.']);
                }
            }

            if ($exhibition->getAttribute('published_at') === null) {
                $exhibition->setAttribute('published_at', now());
            }
        });
    }
}
