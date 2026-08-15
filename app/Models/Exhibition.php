<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['slug', 'title', 'state', 'position', 'kind', 'venue', 'city', 'country', 'description', 'external_url', 'hero_media_asset_id', 'starts_on', 'ends_on', 'date_text', 'legacy_id', 'legacy_source', 'migration_batch_id', 'migrated_at', 'published_at'])]
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

    public function heroMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'hero_media_asset_id');
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
}
