<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Models\Exhibition;
use App\Models\SiteSection;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ExhibitionEditorialService
{
    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly JournalEntryOrderService $order,
        private readonly SafeLinkPolicy $links,
        private readonly SafeRichTextRenderer $richText,
        private readonly JournalEntryMediaService $media,
        private readonly ExhibitionGeocodingService $geocoding,
    ) {}

    public function createDraft(array $data): Exhibition
    {
        $actor = $this->audit->requireActor();
        $sectionId = $this->journalSectionId($data['site_section_id'] ?? null);
        $payload = $this->validatedEditorialData($data, null);
        $payload['location_text'] = $this->streetOnly(
            $payload['location_text'] ?? null,
            $payload['city'] ?? null,
            $payload['country'] ?? null,
        );
        if ((bool) ($payload['map_enabled'] ?? false)) {
            $payload = [...$payload, ...$this->geocodePayload($payload)];
        }

        return DB::transaction(function () use ($data, $payload, $sectionId, $actor): Exhibition {
            $entry = new Exhibition;
            $entry->fill([
                ...$payload,
                'site_section_id' => $sectionId,
                'state' => 'draft',
                'archived_from_state' => null,
                'position' => $this->order->nextPosition(new Exhibition, $sectionId),
                'published_at' => null,
                'legacy_id' => null,
                'legacy_source' => null,
                'migration_batch_id' => null,
                'migrated_at' => null,
            ]);
            $entry->save();

            if ($this->hasStructuredMediaInput($data)) {
                $this->media->syncStructuredMedia($entry, $data);
            }

            $this->audit->record($actor, 'exhibition.created', 'exhibition', $entry->getKey(), [
                'site_section_id' => $sectionId,
            ]);

            return $entry->fresh(['mediaUsages.mediaAsset']);
        });
    }

    public function update(Exhibition $exhibition, array $data): Exhibition
    {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($exhibition, $data, $actor): Exhibition {
            $fresh = Exhibition::query()->whereKey($exhibition->getKey())->lockForUpdate()->firstOrFail();
            $sectionId = (int) $fresh->getAttribute('site_section_id');
            if (array_key_exists('site_section_id', $data) && (int) $data['site_section_id'] !== $sectionId) {
                throw ValidationException::withMessages([
                    'site_section_id' => 'Move exhibitions between Journals through an explicit editorial workflow.',
                ]);
            }

            $payload = $this->validatedEditorialData($data, $fresh);
            $location = [
                'location_text' => array_key_exists('location_text', $payload) ? $payload['location_text'] : $fresh->getAttribute('location_text'),
                'city' => array_key_exists('city', $payload) ? $payload['city'] : $fresh->getAttribute('city'),
                'country' => array_key_exists('country', $payload) ? $payload['country'] : $fresh->getAttribute('country'),
            ];
            $location['location_text'] = $this->streetOnly($location['location_text'], $location['city'], $location['country']);
            $payload['location_text'] = $location['location_text'];
            $locationChanged = $this->locationSignature($location) !== $this->locationSignature($fresh->getAttributes());
            $mapEnabled = array_key_exists('map_enabled', $payload)
                ? (bool) $payload['map_enabled']
                : (bool) $fresh->getAttribute('map_enabled');

            if ($locationChanged) {
                $payload['latitude'] = null;
                $payload['longitude'] = null;
                $payload['geocoded_at'] = null;
            }

            if ($mapEnabled && ($locationChanged || ! $fresh->hasCoordinates())) {
                $payload = [...$payload, ...$this->geocodePayload($location)];
            }

            $fresh->fill([...$payload, 'site_section_id' => $sectionId]);
            if ($this->hasStructuredMediaInput($data)) {
                $this->media->syncStructuredMedia($fresh, $data);
            }

            if ($fresh->getAttribute('state') === 'published') {
                $this->assertPublicReady($fresh);
            }

            if ($fresh->isDirty()) {
                $fresh->save();
                $this->audit->record($actor, 'exhibition.updated', 'exhibition', $fresh->getKey());
            }

            return $fresh->fresh(['mediaUsages.mediaAsset']);
        });
    }

    public function publish(Exhibition $entry): Exhibition
    {
        return $this->transition($entry, 'published', ['draft'], 'published', true);
    }

    /** Compatibility path for older admin callers. Canonical Journal UI archives published exhibitions. */
    public function unpublish(Exhibition $entry): Exhibition
    {
        return $this->transition($entry, 'draft', ['published'], 'unpublished');
    }

    public function archive(Exhibition $entry): Exhibition
    {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($entry, $actor): Exhibition {
            $fresh = Exhibition::query()->whereKey($entry->getKey())->lockForUpdate()->firstOrFail();
            $current = (string) $fresh->getAttribute('state');
            if ($current === 'archived') {
                return $fresh;
            }
            if (! in_array($current, ['draft', 'published'], true)) {
                throw ValidationException::withMessages(['state' => 'This exhibition cannot be archived from '.$current.'.']);
            }

            $fresh->setAttribute('archived_from_state', $current);
            $fresh->setAttribute('state', 'archived');
            $fresh->save();
            $this->audit->record($actor, 'exhibition.archived', 'exhibition', $fresh->getKey());

            return $fresh->fresh(['mediaUsages.mediaAsset']);
        });
    }

    public function restore(Exhibition $entry): Exhibition
    {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($entry, $actor): Exhibition {
            $fresh = Exhibition::query()->whereKey($entry->getKey())->lockForUpdate()->firstOrFail();
            if ((string) $fresh->getAttribute('state') !== 'archived') {
                throw ValidationException::withMessages(['state' => 'Only archived exhibitions can be restored.']);
            }

            $target = $fresh->getAttribute('archived_from_state');
            if (! is_string($target) || ! in_array($target, ['draft', 'published'], true)) {
                $target = $fresh->getAttribute('published_at') !== null ? 'published' : 'draft';
            }

            if ($target === 'published') {
                try {
                    $this->assertPublicReady($fresh);
                } catch (ValidationException) {
                    $target = 'draft';
                }
            }

            $fresh->setAttribute('state', $target);
            $fresh->setAttribute('archived_from_state', null);
            $fresh->save();
            $this->audit->record(
                $actor,
                $target === 'published' ? 'exhibition.published' : 'exhibition.restored_to_draft',
                'exhibition',
                $fresh->getKey(),
            );

            return $fresh->fresh(['mediaUsages.mediaAsset']);
        });
    }

    public function restoreDraft(Exhibition $entry): Exhibition
    {
        return $this->restore($entry);
    }

    public function canMove(Exhibition $entry, string $direction): bool
    {
        return $this->order->canMove($entry, $direction);
    }

    public function move(Exhibition $entry, string $direction): bool
    {
        return $this->order->move($entry, $direction);
    }

    public function delete(Exhibition $entry): void
    {
        $actor = $this->audit->requireActor();

        DB::transaction(function () use ($entry, $actor): void {
            $fresh = Exhibition::query()->whereKey($entry->getKey())->lockForUpdate()->firstOrFail();
            if ((string) $fresh->getAttribute('state') === 'published') {
                throw ValidationException::withMessages(['exhibition' => 'Archive this exhibition before deleting it.']);
            }

            $id = (int) $fresh->getKey();
            $sectionId = (int) $fresh->getAttribute('site_section_id');
            $fresh->delete();
            $this->audit->record($actor, 'exhibition.deleted', 'exhibition', $id, ['site_section_id' => $sectionId]);
        });
    }

    /** @param list<string> $allowedFrom */
    private function transition(Exhibition $entry, string $state, array $allowedFrom, string $action, bool $validatePublic = false): Exhibition
    {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($entry, $state, $allowedFrom, $action, $validatePublic, $actor): Exhibition {
            $fresh = Exhibition::query()->whereKey($entry->getKey())->lockForUpdate()->firstOrFail();
            $current = (string) $fresh->getAttribute('state');
            if ($current === $state) {
                return $fresh;
            }
            if (! in_array($current, $allowedFrom, true)) {
                throw ValidationException::withMessages(['state' => 'This exhibition cannot move from '.$current.' to '.$state.'.']);
            }
            if ($validatePublic) {
                $this->assertPublicReady($fresh);
            }

            $fresh->setAttribute('state', $state);
            $fresh->setAttribute('archived_from_state', null);
            if ($state === 'published' && $fresh->getAttribute('published_at') === null) {
                $fresh->setAttribute('published_at', now());
            }
            $fresh->save();
            $this->audit->record($actor, 'exhibition.'.$action, 'exhibition', $fresh->getKey());

            return $fresh->fresh(['mediaUsages.mediaAsset']);
        });
    }

    private function assertPublicReady(Exhibition $entry): void
    {
        $startsOn = $entry->getAttribute('starts_on');
        $legacySource = $entry->getAttribute('legacy_source');
        $isLegacy = $entry->getAttribute('legacy_id') !== null
            || $entry->getAttribute('migrated_at') !== null
            || (is_string($legacySource) && trim($legacySource) !== '');

        if (! $isLegacy && ! $startsOn instanceof CarbonInterface) {
            throw ValidationException::withMessages(['starts_on' => 'Set the structured exhibition start date before publishing.']);
        }
        if ($entry->displayDate() === null) {
            throw ValidationException::withMessages(['starts_on' => 'Set exhibition dates before publishing.']);
        }
        if ((bool) $entry->getAttribute('map_enabled') && ! $entry->hasCoordinates()) {
            throw ValidationException::withMessages(['map_enabled' => 'The entered address cannot currently provide a map location.']);
        }

        $this->media->assertPublicReady($entry);
    }

    private function validatedEditorialData(array $data, ?Exhibition $entry): array
    {
        $allowed = [
            'site_section_id', 'slug', 'title', 'description', 'starts_on', 'ends_on', 'date_text', 'vernissage_at',
            'venue', 'location_text', 'city', 'country', 'external_url', 'gallery_enabled', 'map_enabled', 'map_shape',
        ];
        $payload = array_intersect_key($data, array_flip($allowed));
        $slugRule = Rule::unique('exhibitions', 'slug');
        if ($entry instanceof Exhibition) {
            $slugRule->ignore($entry->getKey());
        }

        $payload = Validator::make($payload, [
            'site_section_id' => ['required', 'integer', 'min:1'],
            'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slugRule],
            'title' => ['required', 'string', 'max:240'],
            'description' => ['nullable', 'string'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'date_text' => ['nullable', 'string', 'max:160'],
            'vernissage_at' => ['nullable', 'date'],
            'venue' => ['nullable', 'string', 'max:240'],
            'location_text' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:160'],
            'country' => ['nullable', 'string', 'max:160'],
            'external_url' => ['nullable', 'string', 'max:2048'],
            'gallery_enabled' => ['nullable', 'boolean'],
            'map_enabled' => ['nullable', 'boolean'],
            'map_shape' => ['nullable', Rule::in(['wide', 'square'])],
        ])->validate();

        foreach (['date_text', 'venue', 'location_text', 'city', 'country', 'external_url'] as $field) {
            if (array_key_exists($field, $payload) && is_string($payload[$field])) {
                $payload[$field] = trim($payload[$field]) === '' ? null : trim($payload[$field]);
            }
        }
        if (array_key_exists('description', $payload) && is_string($payload['description'])) {
            $payload['description'] = trim($payload['description']) === '' ? null : trim($payload['description']);
        }
        if (is_string($payload['description'] ?? null)) {
            $this->richText->assertValid($payload['description'], allowEmbeddedMedia: true);
        }

        $url = $payload['external_url'] ?? null;
        if (is_string($url) && $url !== '' && ! $this->links->isAllowed($url)) {
            throw ValidationException::withMessages(['external_url' => 'Use a safe absolute HTTP or HTTPS URL.']);
        }

        if (array_key_exists('gallery_enabled', $payload)) {
            $payload['gallery_enabled'] = (bool) $payload['gallery_enabled'];
        }
        if (array_key_exists('map_enabled', $payload)) {
            $payload['map_enabled'] = (bool) $payload['map_enabled'];
        }
        $payload['map_shape'] = (string) ($payload['map_shape'] ?? ($entry?->getAttribute('map_shape') ?? 'wide'));

        return $payload;
    }

    /** @param array<string,mixed> $values */
    private function geocodePayload(array $values): array
    {
        $parts = collect([$values['location_text'] ?? null, $values['city'] ?? null, $values['country'] ?? null])
            ->filter(static fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->map(static fn (string $value): string => trim($value))
            ->values();
        if ($parts->isEmpty()) {
            throw ValidationException::withMessages(['location_text' => 'Enter a street address, city or country to enable the map.']);
        }

        $match = $this->geocoding->locate($parts->implode(', '));
        if ($match === null) {
            throw ValidationException::withMessages(['location_text' => 'The entered address cannot currently provide a map location.']);
        }

        return [
            'latitude' => $match['latitude'],
            'longitude' => $match['longitude'],
            'geocoded_at' => now(),
        ];
    }

    private function streetOnly(mixed $street, mixed $city, mixed $country): ?string
    {
        $street = is_string($street) ? trim($street) : '';
        if ($street === '') {
            return null;
        }
        $city = is_string($city) ? trim($city) : '';
        $country = is_string($country) ? trim($country) : '';
        $normalize = static fn (string $value): string => mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value));
        $streetKey = $normalize($street);
        foreach (array_filter([$city, $country, collect([$city, $country])->filter()->implode(', '), collect([$city, $country])->filter()->implode(',')]) as $duplicate) {
            if ($streetKey === $normalize((string) $duplicate)) {
                return null;
            }
        }
        return $street;
    }

    /** @param array<string, mixed> $values */
    private function locationSignature(array $values): string
    {
        return collect([$values['location_text'] ?? null, $values['city'] ?? null, $values['country'] ?? null])
            ->map(static fn (mixed $value): string => is_string($value) ? mb_strtolower(trim($value)) : '')
            ->implode('|');
    }

    private function journalSectionId(mixed $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            throw ValidationException::withMessages(['site_section_id' => 'Choose an Exhibitions Journal page.']);
        }

        $exists = SiteSection::query()->whereKey($id)
            ->where('type', SiteNodeType::Journal->value)
            ->where('template', JournalTemplate::Exhibitions->value)
            ->exists();
        if (! $exists) {
            throw ValidationException::withMessages(['site_section_id' => 'The selected page is not an Exhibitions Journal.']);
        }

        return (int) $id;
    }

    private function hasStructuredMediaInput(array $data): bool
    {
        return array_key_exists('cover_media_asset_id', $data) || array_key_exists('gallery_images', $data);
    }
}
