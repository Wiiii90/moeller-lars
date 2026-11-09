<?php

namespace App\Domain\Artwork;

use App\Domain\Admin\AdminAuditService;
use App\Models\ArtworkMaterialPreset;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ArtworkMaterialPresetService
{
    public function __construct(private readonly AdminAuditService $audit) {}

    /** @return EloquentCollection<int, ArtworkMaterialPreset> */
    public function ordered(): EloquentCollection
    {
        return ArtworkMaterialPreset::query()->orderBy('position')->orderBy('id')->get();
    }

    public function add(string $name): bool
    {
        $value = $this->validName($name);
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($value, $actor): bool {
            $presets = $this->lockedPresets();
            $this->assertUniqueName($presets, $value);

            $created = ArtworkMaterialPreset::query()->create([
                'name' => $value,
                'position' => $presets->count(),
            ]);
            $this->audit->record($actor, 'artwork_material_preset.created', 'artwork_material_preset', (int) $created->getKey());

            return true;
        });
    }

    public function rename(int $id, string $name): bool
    {
        $value = $this->validName($name);
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($id, $value, $actor): bool {
            $presets = $this->lockedPresets();
            /** @var ArtworkMaterialPreset|null $preset */
            $preset = $presets->firstWhere('id', $id);
            if (! $preset instanceof ArtworkMaterialPreset) {
                throw ValidationException::withMessages(['name' => 'This material no longer exists.']);
            }
            if ((string) $preset->getAttribute('name') === $value) {
                return false;
            }

            $this->assertUniqueName($presets, $value, $id);
            $preset->forceFill(['name' => $value])->save();
            $this->audit->record($actor, 'artwork_material_preset.renamed', 'artwork_material_preset', $id);

            return true;
        });
    }

    public function delete(int $id): bool
    {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($id, $actor): bool {
            $presets = $this->lockedPresets();
            /** @var ArtworkMaterialPreset|null $preset */
            $preset = $presets->firstWhere('id', $id);
            if (! $preset instanceof ArtworkMaterialPreset) {
                return false;
            }
            $preset->delete();
            $this->audit->record($actor, 'artwork_material_preset.deleted', 'artwork_material_preset', $id);
            $this->persistOrder($presets->reject(static fn (ArtworkMaterialPreset $item): bool => (int) $item->getKey() === $id)->values());

            return true;
        });
    }

    /** Positions are zero-based inside the domain; the table shows one-based ranks. */
    public function move(int $id, int $position): bool
    {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($id, $position, $actor): bool {
            $presets = $this->lockedPresets()->values();
            $from = $presets->search(static fn (ArtworkMaterialPreset $preset): bool => (int) $preset->getKey() === $id);
            if ($from === false || $position < 0 || $position >= $presets->count()) {
                throw ValidationException::withMessages(['position' => 'The material position is no longer available.']);
            }
            if ($from === $position) {
                return false;
            }
            $rows = $presets->all();
            $moved = array_splice($rows, $from, 1);
            array_splice($rows, $position, 0, $moved);
            $this->persistOrder(collect($rows));
            $this->audit->record($actor, 'artwork_material_preset.reordered', 'artwork_material_preset', $id);

            return true;
        });
    }

    /** @return EloquentCollection<int, ArtworkMaterialPreset> */
    private function lockedPresets(): EloquentCollection
    {
        return ArtworkMaterialPreset::query()->orderBy('position')->orderBy('id')->lockForUpdate()->get();
    }

    /** @param Collection<int, ArtworkMaterialPreset> $presets */
    private function persistOrder(Collection $presets): void
    {
        foreach ($presets->values() as $index => $preset) {
            if ((int) $preset->getAttribute('position') === $index) {
                continue;
            }
            $preset->forceFill(['position' => $index])->save();
        }
    }

    private function validName(string $name): string
    {
        $value = trim($name);
        if ($value === '') {
            throw ValidationException::withMessages(['name' => 'Material is required.']);
        }
        if (mb_strlen($value) > 240) {
            throw ValidationException::withMessages(['name' => 'Material may not exceed 240 characters.']);
        }

        return $value;
    }

    /** @param EloquentCollection<int, ArtworkMaterialPreset> $presets */
    private function assertUniqueName(EloquentCollection $presets, string $name, ?int $exceptId = null): void
    {
        $key = mb_strtolower($name);
        $duplicate = $presets->first(static fn (ArtworkMaterialPreset $preset): bool => (int) $preset->getKey() !== $exceptId
            && mb_strtolower(trim((string) $preset->getAttribute('name'))) === $key,
        );
        if ($duplicate instanceof ArtworkMaterialPreset) {
            throw ValidationException::withMessages(['name' => 'This material already exists.']);
        }
    }
}
