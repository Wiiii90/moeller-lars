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
        private readonly JournalEntryMediaService $media,
    ) {}

    /** @param array<string, mixed> $data */
    public function createDraft(array $data): Exhibition
    {
        $actor = $this->audit->requireActor();
        $sectionId = $this->journalSectionId($data['site_section_id'] ?? null);
        $payload = $this->validatedEditorialData($data, null);

        return DB::transaction(function () use ($data, $payload, $sectionId, $actor): Exhibition {
            $exhibition = new Exhibition;
            $exhibition->fill([
                ...$payload,
                'site_section_id' => $sectionId,
                'state' => 'draft',
                'position' => $this->order->nextPosition(new Exhibition, $sectionId),
                'published_at' => null,
                'description' => null,
                'legacy_id' => null,
                'legacy_source' => null,
                'migration_batch_id' => null,
                'migrated_at' => null,
            ]);
            $exhibition->save();

            $source = $this->media->syncEditor($exhibition, $data);
            $exhibition->setAttribute('description', $source === '' ? null : $source);
            $exhibition->save();

            $this->audit->record($actor, 'exhibition.created', 'exhibition', $exhibition->getKey(), [
                'site_section_id' => $sectionId,
            ]);

            return $exhibition->fresh(['mediaUsages.mediaAsset']);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Exhibition $exhibition, array $data): Exhibition
    {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($exhibition, $data, $actor): Exhibition {
            /** @var Exhibition $fresh */
            $fresh = Exhibition::query()
                ->whereKey($exhibition->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $sectionId = (int) $fresh->getAttribute('site_section_id');

            if (array_key_exists('site_section_id', $data) && (int) $data['site_section_id'] !== $sectionId) {
                throw ValidationException::withMessages([
                    'site_section_id' => 'Move exhibitions between Journals through an explicit editorial workflow.',
                ]);
            }

            $payload = $this->validatedEditorialData($data, $fresh);
            $newAddress = trim((string) ($payload['location_text'] ?? ''));
            $oldAddress = trim((string) ($fresh->getAttribute('location_text') ?? ''));
            $coordinatesProvided = isset($payload['latitude'], $payload['longitude']);
            $oldGeocodedAt = $fresh->getAttribute('geocoded_at');
            $newGeocodedAt = $payload['geocoded_at'] ?? null;
            $freshGeocode = $newGeocodedAt !== null && (string) $newGeocodedAt !== (string) $oldGeocodedAt;

            if ($newAddress !== $oldAddress && (! $coordinatesProvided || ! $freshGeocode)) {
                $payload['latitude'] = null;
                $payload['longitude'] = null;
                $payload['geocoded_at'] = null;
            }

            $fresh->fill([
                ...$payload,
                'site_section_id' => $sectionId,
            ]);

            $source = $this->media->syncEditor($fresh, $data);
            $fresh->setAttribute('description', $source === '' ? null : $source);

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

    public function publish(Exhibition $exhibition): Exhibition
    {
        return $this->transition($exhibition, 'published', ['draft'], 'published', validatePublic: true);
    }

    /** Compatibility path for reversible legacy admin actions. Canonical Journal UI archives instead. */
    public function unpublish(Exhibition $exhibition): Exhibition
    {
        return $this->transition($exhibition, 'draft', ['published'], 'unpublished');
    }

    public function archive(Exhibition $exhibition): Exhibition
    {
        return $this->transition($exhibition, 'archived', ['draft', 'published'], 'archived');
    }

    public function restoreDraft(Exhibition $exhibition): Exhibition
    {
        return $this->transition($exhibition, 'draft', ['archived'], 'restored_to_draft');
    }

    public function canMove(Exhibition $exhibition, string $direction): bool
    {
        return $this->order->canMove($exhibition, $direction);
    }

    public function move(Exhibition $exhibition, string $direction): bool
    {
        return $this->order->move($exhibition, $direction);
    }

    public function delete(Exhibition $exhibition): void
    {
        $actor = $this->audit->requireActor();

        DB::transaction(function () use ($exhibition, $actor): void {
            /** @var Exhibition $fresh */
            $fresh = Exhibition::query()
                ->whereKey($exhibition->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $fresh->getAttribute('state') === 'published') {
                throw ValidationException::withMessages([
                    'exhibition' => 'Archive this exhibition before deleting it.',
                ]);
            }

            $id = (int) $fresh->getKey();
            $sectionId = (int) $fresh->getAttribute('site_section_id');
            $usageCount = $fresh->mediaUsages()->count();
            $fresh->delete();

            $this->audit->record($actor, 'exhibition.deleted', 'exhibition', $id, [
                'site_section_id' => $sectionId,
                'detached_media_usages' => $usageCount,
            ]);
        });
    }

    /** @param list<string> $allowedFrom */
    private function transition(
        Exhibition $exhibition,
        string $state,
        array $allowedFrom,
        string $action,
        bool $validatePublic = false,
    ): Exhibition {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($exhibition, $state, $allowedFrom, $action, $validatePublic, $actor): Exhibition {
            /** @var Exhibition $fresh */
            $fresh = Exhibition::query()
                ->whereKey($exhibition->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $current = (string) $fresh->getAttribute('state');

            if ($current === $state) {
                return $fresh;
            }
            if (! in_array($current, $allowedFrom, true)) {
                throw ValidationException::withMessages([
                    'state' => 'This exhibition cannot move from '.$current.' to '.$state.'.',
                ]);
            }

            if ($validatePublic) {
                $this->assertPublicReady($fresh);
            }

            $fresh->setAttribute('state', $state);
            if ($state === 'published' && $fresh->getAttribute('published_at') === null) {
                $fresh->setAttribute('published_at', now());
            }
            $fresh->save();

            $this->audit->record($actor, 'exhibition.'.$action, 'exhibition', $fresh->getKey());

            return $fresh->fresh(['mediaUsages.mediaAsset']);
        });
    }

    private function assertPublicReady(Exhibition $exhibition): void
    {
        $startsOn = $exhibition->getAttribute('starts_on');
        $legacySource = $exhibition->getAttribute('legacy_source');
        $isLegacy = $exhibition->getAttribute('legacy_id') !== null
            || $exhibition->getAttribute('migrated_at') !== null
            || (is_string($legacySource) && trim($legacySource) !== '');

        if (! $isLegacy && ! $startsOn instanceof CarbonInterface) {
            throw ValidationException::withMessages([
                'starts_on' => 'Set the structured exhibition start date before publishing.',
            ]);
        }
        if ($exhibition->displayDate() === null) {
            throw ValidationException::withMessages([
                'starts_on' => 'Set exhibition dates before publishing.',
            ]);
        }

        $this->media->assertPublicReady($exhibition);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function validatedEditorialData(array $data, ?Exhibition $exhibition): array
    {
        $allowed = [
            'site_section_id',
            'slug',
            'title',
            'starts_on',
            'ends_on',
            'date_text',
            'vernissage_at',
            'venue',
            'location_text',
            'latitude',
            'longitude',
            'geocoded_at',
            'external_url',
        ];
        $payload = array_intersect_key($data, array_flip($allowed));
        $slugRule = Rule::unique('exhibitions', 'slug');
        if ($exhibition instanceof Exhibition) {
            $slugRule->ignore($exhibition->getKey());
        }

        $payload = Validator::make($payload, [
            'site_section_id' => ['required', 'integer', 'min:1'],
            'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slugRule],
            'title' => ['required', 'string', 'max:240'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'date_text' => ['nullable', 'string', 'max:160'],
            'vernissage_at' => ['nullable', 'date'],
            'venue' => ['nullable', 'string', 'max:240'],
            'location_text' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geocoded_at' => ['nullable', 'date'],
            'external_url' => ['nullable', 'string', 'max:2048'],
        ])->validate();

        foreach (['date_text', 'venue', 'location_text', 'external_url'] as $field) {
            if (array_key_exists($field, $payload) && is_string($payload[$field])) {
                $payload[$field] = trim($payload[$field]) === '' ? null : trim($payload[$field]);
            }
        }

        $url = $payload['external_url'] ?? null;
        if (is_string($url) && $url !== '' && ! $this->links->isAllowed($url)) {
            throw ValidationException::withMessages([
                'external_url' => 'Use a safe absolute HTTP or HTTPS URL.',
            ]);
        }

        if (($payload['latitude'] ?? null) === null || ($payload['longitude'] ?? null) === null) {
            $payload['latitude'] = null;
            $payload['longitude'] = null;
            $payload['geocoded_at'] = null;
        }

        return $payload;
    }

    private function journalSectionId(mixed $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            throw ValidationException::withMessages([
                'site_section_id' => 'Choose an Exhibitions Journal page.',
            ]);
        }

        $exists = SiteSection::query()
            ->whereKey($id)
            ->where('type', SiteNodeType::Journal->value)
            ->where('template', JournalTemplate::Exhibitions->value)
            ->exists();
        if (! $exists) {
            throw ValidationException::withMessages([
                'site_section_id' => 'The selected page is not an Exhibitions Journal.',
            ]);
        }

        return (int) $id;
    }
}
