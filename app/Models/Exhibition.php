<?php

namespace App\Models;

use App\Domain\Content\ExhibitionMapPresentation;
use App\Domain\Content\SafeLinkPolicy;
use App\Domain\Content\RegionalFormats;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['site_section_id', 'slug', 'title', 'state', 'archived_from_state', 'position', 'kind', 'organizer', 'venue', 'city', 'country', 'location_text', 'postal_code', 'description', 'external_url', 'directions_url', 'starts_on', 'ends_on', 'date_text', 'opening_text', 'vernissage_at', 'show_vernissage', 'latitude', 'longitude', 'geocoded_at', 'gallery_enabled', 'gallery_presentation', 'map_enabled', 'legacy_id', 'legacy_source', 'migration_batch_id', 'migrated_at', 'published_at'])]
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
            'show_vernissage' => 'boolean',
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
            return RegionalFormats::publicDate($start);
        }
        $end = CarbonImmutable::instance($ends);

        return RegionalFormats::publicDateRange($start, $end);
    }

    /** Public bylines prioritize the organizer and never append an unrelated city. */
    public function summaryAttribution(): ?string
    {
        foreach (['organizer', 'venue'] as $field) {
            $value = trim((string) ($this->getAttribute($field) ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /** The map identifies the physical place, not the organizing institution. */
    public function mapLocationLabel(): ?string
    {
        $location = collect([
            $this->getAttribute('venue'),
            $this->getAttribute('city'),
            $this->getAttribute('country'),
        ])
            ->filter(static fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->map(static fn (string $value): string => trim($value))
            ->unique()
            ->implode(' · ');

        return $location !== '' ? $location : null;
    }

    public function shouldShowPublicVernissage(?CarbonInterface $date = null): bool
    {
        if (! (bool) $this->getAttribute('show_vernissage') || $this->vernissageDisplay() === null) {
            return false;
        }

        $date ??= now();
        if ($this->temporalState($date) === 'past') {
            return false;
        }

        $vernissage = $this->getAttribute('vernissage_at');
        if ($vernissage instanceof CarbonInterface) {
            return ! CarbonImmutable::instance($date)->isAfter($vernissage);
        }

        // Historical free-text openings have no reliable timestamp.
        return $this->temporalState($date) === 'upcoming';
    }

    public function vernissageDisplay(): ?string
    {
        $normalized = $this->getAttribute('vernissage_at');
        if ($normalized instanceof CarbonInterface) {
            return RegionalFormats::publicDateTime($normalized);
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
        $postal = trim((string) ($this->getAttribute('postal_code') ?? ''));
        $city = trim((string) ($this->getAttribute('city') ?? ''));
        $postalCity = trim($postal.' '.$city);

        $parts = collect([
            $this->streetAddress(),
            $postalCity !== '' ? $postalCity : null,
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

    public function galleryPresentation(): string
    {
        $presentation = (string) ($this->getAttribute('gallery_presentation') ?? 'grid');

        return in_array($presentation, ['grid', 'mosaic', 'slideshow'], true) ? $presentation : 'grid';
    }

    public function shouldShowPublicMap(?CarbonInterface $date = null): bool
    {
        return (bool) $this->getAttribute('map_enabled') && $this->hasCoordinates();
    }

    /** @return array{embed_url:string, public_url:string, latitude:float, longitude:float}|null */
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
