<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Models\Exhibition;
use App\Models\ExhibitionOrganizerPreset;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ExhibitionOrganizerPresetService
{
    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly ExhibitionEditorialService $exhibitions,
    ) {}

    /** @return EloquentCollection<int, ExhibitionOrganizerPreset> */
    public function organizers(): EloquentCollection
    {
        return ExhibitionOrganizerPreset::query()->orderBy('position')->orderBy('id')->get();
    }

    public function addOrganizer(string $name): bool
    {
        $name = $this->name($name);
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($name, $actor): bool {
            $rows = ExhibitionOrganizerPreset::query()->lockForUpdate()->get();
            $this->uniqueName($rows, $name);

            $preset = ExhibitionOrganizerPreset::query()->create([
                'name' => $name, 'position' => $rows->count(),
            ]);
            $this->audit->record($actor, 'exhibition_organizer_preset.created', 'exhibition_organizer_preset', (int) $preset->getKey());

            return true;
        });
    }

    public function renameOrganizer(int $id, string $name): bool
    {
        $name = $this->name($name);
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($id, $name, $actor): bool {
            $rows = ExhibitionOrganizerPreset::query()->lockForUpdate()->get();
            /** @var ExhibitionOrganizerPreset|null $preset */
            $preset = $rows->firstWhere('id', $id);
            if (! $preset instanceof ExhibitionOrganizerPreset) {
                throw ValidationException::withMessages(['name' => 'This organizer no longer exists.']);
            }
            $previous = (string) $preset->getAttribute('name');
            if ($previous === $name) {
                return false;
            }
            $this->uniqueName($rows, $name, $id);
            $preset->setAttribute('name', $name);
            $preset->save();

            // A renamed organizer updates exact Working references, while
            // committed pages and historical checkpoints retain their names.
            foreach (Exhibition::query()->where('organizer', $previous)->orderBy('id')->get() as $exhibition) {
                $this->exhibitions->update($exhibition, [
                    'site_section_id' => (int) $exhibition->getAttribute('site_section_id'),
                    'title' => (string) $exhibition->getAttribute('title'),
                    'organizer' => $name,
                ]);
            }

            $this->audit->record($actor, 'exhibition_organizer_preset.renamed', 'exhibition_organizer_preset', $id);

            return true;
        });
    }

    public function deleteOrganizer(int $id): bool
    {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($id, $actor): bool {
            $rows = ExhibitionOrganizerPreset::query()->orderBy('position')->lockForUpdate()->get();
            /** @var ExhibitionOrganizerPreset|null $preset */
            $preset = $rows->firstWhere('id', $id);
            if (! $preset instanceof ExhibitionOrganizerPreset) {
                return false;
            }
            // Deleting a preset does not remove historical Exhibition values.
            $preset->delete();
            $this->audit->record($actor, 'exhibition_organizer_preset.deleted', 'exhibition_organizer_preset', $id);
            foreach ($rows->reject(static fn (ExhibitionOrganizerPreset $row): bool => (int) $row->getKey() === $id)->values() as $position => $row) {
                if ((int) $row->getAttribute('position') !== $position) {
                    $row->setAttribute('position', $position);
                    $row->save();
                }
            }

            return true;
        });
    }

    public function moveOrganizer(int $id, int $position): bool
    {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($id, $position, $actor): bool {
            $rows = ExhibitionOrganizerPreset::query()->orderBy('position')->orderBy('id')
                ->lockForUpdate()->get()->all();
            $from = array_search($id, array_map(static fn (ExhibitionOrganizerPreset $row): int => (int) $row->getKey(), $rows), true);
            if ($from === false || $position < 0 || $position >= count($rows)) {
                throw ValidationException::withMessages(['position' => 'The organizer position is no longer available.']);
            }
            if ($from === $position) {
                return false;
            }
            $row = array_splice($rows, $from, 1);
            array_splice($rows, $position, 0, $row);
            foreach ($rows as $index => $preset) {
                if ((int) $preset->getAttribute('position') !== $index) {
                    $preset->setAttribute('position', $index);
                    $preset->save();
                }
            }
            $this->audit->record($actor, 'exhibition_organizer_preset.reordered', 'exhibition_organizer_preset', $id);

            return true;
        });
    }

    private function name(string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 240) {
            throw ValidationException::withMessages(['name' => 'Enter an organizer name of at most 240 characters.']);
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
