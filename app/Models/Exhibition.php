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
        if ($this->starts_on === null) {
            return 'unknown';
        }

        $date = CarbonImmutable::instance($date)->startOfDay();
        $startsOn = CarbonImmutable::instance($this->starts_on)->startOfDay();

        if ($date->isBefore($startsOn)) {
            return 'upcoming';
        }

        if ($this->ends_on !== null) {
            return $date->isAfter(CarbonImmutable::instance($this->ends_on)->startOfDay())
                ? 'past'
                : 'current';
        }

        return $date->isSameDay($startsOn) ? 'current' : 'past';
    }
}
