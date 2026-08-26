<?php

namespace App\Models;

use App\Domain\Content\ExhibitionMapPresentation;
use App\Domain\Content\SafeLinkPolicy;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['site_section_id', 'slug', 'title', 'state', 'archived_from_state', 'position', 'kind', 'venue', 'city', 'country', 'location_text', 'description', 'external_url', 'directions_url', 'starts_on', 'ends_on', 'date_text', 'opening_text', 'vernissage_at', 'latitude', 'longitude', 'geocoded_at', 'gallery_enabled', 'map_enabled', 'map_shape', 'legacy_id', 'legacy_source', 'migration_batch_id', 'migrated_at', 'published_at'])]
#[Guarded(['id'])]
class Exhibition extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'vernissage_at' => 'datetime',
            'geocoded_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'gallery_enabled' => 'boolean',
            'map_enabled' => 'boolean',
            'migrated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function siteSection(): BelongsTo
    {
        return $this->belongsTo(SiteSection::class);
    }

    public function mediaUsages(): HasMany
    {
        return $this->hasMany(JournalEntryMedia::class)
            ->orderBy('role')
            ->orderBy('position')
            ->orderBy('id');
    }

    /** Legacy compatibility only. Canonical Journal media uses mediaUsages(). */
    public function legacyMediaAssets(): BelongsToMany
    {
        return $this->legacyMediaRelation();
    }

    /** Legacy compatibility alias retained for existing read paths. */
    public function mediaAssets(): BelongsToMany
    {
        return $this->legacyMediaRelation();
    }

    private function legacyMediaRelation(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'exhibition_media')
            ->withPivot(['role', 'position', 'alt_text_override'])
            ->withTimestamps();
    }

    public function temporalState(CarbonInterface $date): string
    {
        $startsOn = $this->getAttribute('starts_on');
        if ($startsOn === null) {
            return 'unknown';
        }

        $date = CarbonImmutable::instance($date)->startOfDay();
        $startsOn = CarbonImmutable::instance($startsOn)->startOfDay();
        if ($date->isBefore($startsOn)) {
            return 'upcoming';
        }

        $endsOn = $this->getAttribute('ends_on');
        if ($endsOn !== null) {
            return $date->isAfter(CarbonImmutable::instance($endsOn)->startOfDay()) ? 'past' : 'current';
        }

        return $date->isSameDay($startsOn) ? 'current' : 'past';
    }

    public function displayDate(): ?string
    {
        $override = trim((string) ($this->getAttribute('date_text') ?? ''));
        if ($override !== '') {
            return $override;
        }

        $starts = $this->getAttribute('starts_on');
        if (! $starts instanceof CarbonInterface) {
            return null;
        }
        $start = CarbonImmutable::instance($starts);
        $ends = $this->getAttribute('ends_on');
        if (! $ends instanceof CarbonInterface || $start->isSameDay($ends)) {
            return $start->format('j M Y');
        }
        $end = CarbonImmutable::instance($ends);

        return $start->year === $end->year
            ? $start->format('j M').' – '.$end->format('j M Y')
            : $start->format('j M Y').' – '.$end->format('j M Y');
    }

    public function vernissageDisplay(): ?string
    {
        $normalized = $this->getAttribute('vernissage_at');
        if ($normalized instanceof CarbonInterface) {
            return CarbonImmutable::instance($normalized)->format('j M Y · H:i');
        }

        $legacy = trim((string) ($this->getAttribute('opening_text') ?? ''));
        return $legacy !== '' ? $legacy : null;
    }

    public function streetAddress(): ?string
    {
        $street = trim((string) ($this->getAttribute('location_text') ?? ''));
        if ($street === '') {
            return null;
        }

        $city = trim((string) ($this->getAttribute('city') ?? ''));
        $country = trim((string) ($this->getAttribute('country') ?? ''));
        $normalized = static fn (string $value): string => mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value));
        $streetKey = $normalized($street);
        $duplicates = array_filter([
            $city,
            $country,
            collect([$city, $country])->filter()->implode(', '),
            collect([$city, $country])->filter()->implode(','),
        ]);

        foreach ($duplicates as $duplicate) {
            if ($streetKey === $normalized((string) $duplicate)) {
                return null;
            }
        }

        return $street;
    }

    public function address(): ?string
    {
        $parts = collect([
            $this->streetAddress(),
            $this->getAttribute('city'),
            $this->getAttribute('country'),
        ])->filter(static fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->map(static fn (string $value): string => trim($value));

        $seen = [];
        $parts = $parts->filter(static function (string $value) use (&$seen): bool {
            $key = mb_strtolower(preg_replace('/\s+/u', ' ', $value) ?? $value);
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;
            return true;
        });

        $address = $parts->implode(', ');

        return $address !== '' ? $address : null;
    }

    public function hasCoordinates(): bool
    {
        return is_numeric($this->getAttribute('latitude')) && is_numeric($this->getAttribute('longitude'));
    }

    public function shouldShowPublicGallery(): bool
    {
        return (bool) $this->getAttribute('gallery_enabled');
    }

    public function shouldShowPublicMap(?CarbonInterface $date = null): bool
    {
        return (bool) $this->getAttribute('map_enabled') && $this->hasCoordinates();
    }

    /** @return array{embed_url:string, public_url:string, shape:string}|null */
    public function mapPresentation(): ?array
    {
        return app(ExhibitionMapPresentation::class)->for($this);
    }

    public function mapEmbedUrl(): ?string
    {
        return $this->mapPresentation()['embed_url'] ?? null;
    }

    public function publicMapUrl(): ?string
    {
        return $this->mapPresentation()['public_url'] ?? null;
    }

    public function mapShape(): string
    {
        return app(ExhibitionMapPresentation::class)->shape((string) ($this->getAttribute('map_shape') ?? 'wide'));
    }

    /** Legacy compatibility for older callers; canonical public UI uses publicMapUrl(). */
    public function publicDirectionsUrl(): ?string
    {
        return $this->publicMapUrl();
    }

    protected static function booted(): void
    {
        static::saving(function (self $exhibition): void {
            if ($exhibition->getAttribute('state') !== 'published') {
                return;
            }

            if (trim((string) $exhibition->getAttribute('title')) === '') {
                throw ValidationException::withMessages(['title' => 'Published exhibitions require a title.']);
            }
            if ($exhibition->displayDate() === null) {
                throw ValidationException::withMessages(['starts_on' => 'Published exhibitions require exhibition dates.']);
            }
            if ((bool) $exhibition->getAttribute('map_enabled') && ! $exhibition->hasCoordinates()) {
                throw ValidationException::withMessages(['map_enabled' => 'Enabled exhibition maps require a resolved map location.']);
            }

            foreach (['external_url', 'directions_url'] as $field) {
                $url = $exhibition->getAttribute($field);
                if ($url !== null && (! is_string($url) || ! app(SafeLinkPolicy::class)->isAllowed($url))) {
                    throw ValidationException::withMessages([$field => 'Exhibition links must be safe HTTP or HTTPS URLs.']);
                }
            }

            if ($exhibition->getAttribute('published_at') === null) {
                $exhibition->setAttribute('published_at', now());
            }
        });
    }
}
