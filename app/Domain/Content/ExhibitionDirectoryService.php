<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Models\ExhibitionOrganizerPreset;
use App\Models\ExhibitionVenuePreset;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ExhibitionDirectoryService
{
    public function __construct(private readonly AdminAuditService $audit) {}

    /**
     * Resolve a Stage-typed Organizer name to the independent catalog.
     * This is called only inside the Exhibition's actual write transaction:
     * abandoned form edits do not create orphan catalog entries.
     */
    public function resolveOrganizerName(string $name): string
    {
        $name = $this->name($name);
        $existing = ExhibitionOrganizerPreset::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

        if ($existing instanceof ExhibitionOrganizerPreset) {
            return (string) $existing->getAttribute('name');
        }

        $this->addOrganizer($name);

        return $name;
    }

    public function resolveVenueName(string $name): string
    {
        $name = $this->name($name);
        $existing = ExhibitionVenuePreset::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

        if ($existing instanceof ExhibitionVenuePreset) {
            return (string) $existing->getAttribute('name');
        }

        $this->addVenue(['name' => $name]);

        return $name;
    }

    /** @return EloquentCollection<int, ExhibitionOrganizerPreset> */
    public function organizers(): EloquentCollection
    {
        return ExhibitionOrganizerPreset::query()->orderByRaw('LOWER(name)')->orderBy('name')->orderBy('id')->get();
    }

    public function addOrganizer(string|array $details): bool
    {
        $values = $this->directoryValues(is_string($details) ? ['name' => $details] : $details, true);
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($values, $actor): bool {
            $rows = ExhibitionOrganizerPreset::query()->lockForUpdate()->get();
            $this->uniqueName($rows, $values['name']);

            $preset = ExhibitionOrganizerPreset::query()->create($values);
            $this->audit->record($actor, 'exhibition_organizer_preset.created', 'exhibition_organizer_preset', (int) $preset->getKey());

            return true;
        });
    }

    public function renameOrganizer(int $id, string $name): bool
    {
        return $this->updateOrganizer($id, ['name' => $name]);
    }

    /** Metadata edits leave historical Exhibition snapshots untouched. */
    public function updateOrganizer(int $id, array $details): bool
    {
        $values = $this->directoryValues($details, false);
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($id, $values, $actor): bool {
            $rows = ExhibitionOrganizerPreset::query()->lockForUpdate()->get();
            /** @var ExhibitionOrganizerPreset|null $preset */
            $preset = $rows->firstWhere('id', $id);
            if (! $preset instanceof ExhibitionOrganizerPreset) {
                throw ValidationException::withMessages(['name' => 'This organizer no longer exists.']);
            }
            $previous = (string) $preset->getAttribute('name');
            $name = (string) ($values['name'] ?? $previous);
            $renamed = $previous !== $name;
            if ($renamed) {
                $this->uniqueName($rows, $name, $id);
            }
            $preset->fill($values);
            if (! $preset->isDirty()) {
                return false;
            }
            $preset->save();

            $this->audit->record($actor, 'exhibition_organizer_preset.updated', 'exhibition_organizer_preset', $id);

            return true;
        });
    }

    public function deleteOrganizer(int $id): bool
    {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($id, $actor): bool {
            $rows = ExhibitionOrganizerPreset::query()->lockForUpdate()->get();
            /** @var ExhibitionOrganizerPreset|null $preset */
            $preset = $rows->firstWhere('id', $id);
            if (! $preset instanceof ExhibitionOrganizerPreset) {
                return false;
            }
            // Deleting a preset does not remove historical Exhibition values.
            $preset->delete();
            $this->audit->record($actor, 'exhibition_organizer_preset.deleted', 'exhibition_organizer_preset', $id);

            return true;
        });
    }

    /** @return EloquentCollection<int, ExhibitionVenuePreset> */
    public function venues(): EloquentCollection
    {
        return ExhibitionVenuePreset::query()->orderByRaw('LOWER(name)')->orderBy('name')->orderBy('id')->get();
    }

    public function addVenue(array $details): bool
    {
        $values = $this->directoryValues($details, true);
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($values, $actor): bool {
            $rows = ExhibitionVenuePreset::query()->lockForUpdate()->get();
            $this->uniqueVenueName($rows, $values['name']);
            $venue = ExhibitionVenuePreset::query()->create($values);
            $this->audit->record($actor, 'exhibition_venue_preset.created', 'exhibition_venue_preset', (int) $venue->getKey());

            return true;
        });
    }

    /** Venue metadata is reusable; existing Exhibition snapshots never change. */
    public function updateVenue(int $id, array $details): bool
    {
        $values = $this->directoryValues($details, false);
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($id, $values, $actor): bool {
            $rows = ExhibitionVenuePreset::query()->lockForUpdate()->get();
            /** @var ExhibitionVenuePreset|null $venue */
            $venue = $rows->firstWhere('id', $id);
            if (! $venue instanceof ExhibitionVenuePreset) {
                throw ValidationException::withMessages(['name' => 'This venue no longer exists.']);
            }
            $name = $values['name'] ?? (string) $venue->getAttribute('name');
            if ($name !== (string) $venue->getAttribute('name')) {
                $this->uniqueVenueName($rows, $name, $id);
            }
            $venue->fill($values);
            if (! $venue->isDirty()) {
                return false;
            }
            $venue->save();
            $this->audit->record($actor, 'exhibition_venue_preset.updated', 'exhibition_venue_preset', $id);

            return true;
        });
    }

    public function deleteVenue(int $id): bool
    {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($id, $actor): bool {
            $rows = ExhibitionVenuePreset::query()->lockForUpdate()->get();
            /** @var ExhibitionVenuePreset|null $venue */
            $venue = $rows->firstWhere('id', $id);
            if (! $venue instanceof ExhibitionVenuePreset) {
                return false;
            }
            $venue->delete();
            $this->audit->record($actor, 'exhibition_venue_preset.deleted', 'exhibition_venue_preset', $id);

            return true;
        });
    }

    /** @return array<string, string|null> */
    private function directoryValues(array $details, bool $create): array
    {
        $rules = [
            'name' => [$create ? 'required' : 'sometimes', 'string', 'max:240'],
            'street' => ['sometimes', 'nullable', 'string', 'max:500'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:16'],
            'city' => ['sometimes', 'nullable', 'string', 'max:160'],
            'country' => ['sometimes', 'nullable', 'string', 'max:160'],
            'website' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ];
        $values = Validator::make($details, $rules)->validate();
        foreach ($values as $field => $value) {
            if (is_string($value)) {
                $values[$field] = trim($value);
            }
        }
        if (array_key_exists('name', $values)) {
            $values['name'] = $this->name((string) $values['name']);
        }
        if (isset($values['website']) && $values['website'] !== '') {
            $website = $values['website'];
            $scheme = strtolower((string) parse_url($website, PHP_URL_SCHEME));
            if (! in_array($scheme, ['http', 'https'], true) || filter_var($website, FILTER_VALIDATE_URL) === false) {
                throw ValidationException::withMessages(['website' => 'Enter an absolute HTTP or HTTPS website URL.']);
            }
        }
        foreach (['street', 'postal_code', 'city', 'country', 'website'] as $field) {
            if (($values[$field] ?? null) === '') {
                $values[$field] = null;
            }
        }

        return $values;
    }

    /** @param EloquentCollection<int, ExhibitionVenuePreset> $rows */
    private function uniqueVenueName(EloquentCollection $rows, string $name, ?int $ignore = null): void
    {
        foreach ($rows as $venue) {
            if ((int) $venue->getKey() !== $ignore && mb_strtolower(trim((string) $venue->getAttribute('name'))) === mb_strtolower($name)) {
                throw ValidationException::withMessages(['name' => 'This venue already exists.']);
            }
        }
    }

    private function name(string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 240) {
            throw ValidationException::withMessages(['name' => 'Enter a name of at most 240 characters.']);
        }

        return $name;
    }

    /** @param EloquentCollection<int, ExhibitionOrganizerPreset> $rows */
    private function uniqueName(EloquentCollection $rows, string $name, ?int $ignore = null): void
    {
        foreach ($rows as $preset) {
            if ((int) $preset->getKey() !== $ignore && mb_strtolower(trim((string) $preset->getAttribute('name'))) === mb_strtolower($name)) {
                throw ValidationException::withMessages(['name' => 'This organizer already exists.']);
            }
        }
    }

}
