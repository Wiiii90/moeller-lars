<?php

namespace App\Domain\Artwork;

use App\Domain\Admin\AdminAuditService;
use App\Models\ArtworkMaterialPreset;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ArtworkMaterialPresetService
{
    public function __construct(private readonly AdminAuditService $audit) {}

    public function add(string $name): ArtworkMaterialPreset
    {
        $value = trim($name);
        if ($value === '') {
            throw ValidationException::withMessages([
                'name' => 'Material is required.',
            ]);
        }
        if (mb_strlen($value) > 240) {
            throw ValidationException::withMessages([
                'name' => 'Material may not exceed 240 characters.',
            ]);
        }

        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($value, $actor): ArtworkMaterialPreset {
            /** @var EloquentCollection<int, ArtworkMaterialPreset> $presets */
            $presets = ArtworkMaterialPreset::query()
                ->lockForUpdate()
                ->get();
            $key = mb_strtolower($value);
            $existing = $presets->first(
                static fn (ArtworkMaterialPreset $preset): bool => mb_strtolower(trim((string) $preset->getAttribute('name'))) === $key,
            );

            if ($existing instanceof ArtworkMaterialPreset) {
                return $existing;
            }

            $created = ArtworkMaterialPreset::query()->create(['name' => $value]);
            $this->audit->record(
                $actor,
                'artwork_material_preset.created',
                'artwork_material_preset',
                (int) $created->getKey(),
            );

            return $created;
        });
    }

    /** @param array<int, mixed> $names */
    public function sync(array $names): bool
    {
        $normalized = [];
        foreach ($names as $name) {
            if (! is_string($name)) {
                continue;
            }

            $value = trim($name);
            if ($value === '') {
                continue;
            }
            if (mb_strlen($value) > 240) {
                throw ValidationException::withMessages([
                    'presets' => 'Material presets may not exceed 240 characters.',
                ]);
            }

            $key = mb_strtolower($value);
            $normalized[$key] ??= $value;
        }

        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($normalized, $actor): bool {
            $changed = false;

            /** @var EloquentCollection<int, ArtworkMaterialPreset> $presets */
            $presets = ArtworkMaterialPreset::query()
                ->lockForUpdate()
                ->get();

            /** @var array<string, ArtworkMaterialPreset> $existing */
            $existing = $presets
                ->keyBy(static fn (ArtworkMaterialPreset $preset): string => mb_strtolower((string) $preset->getAttribute('name')))
                ->all();

            foreach ($normalized as $key => $name) {
                if (isset($existing[$key])) {
                    if ((string) $existing[$key]->getAttribute('name') !== $name) {
                        $existing[$key]->forceFill(['name' => $name])->save();
                        $this->audit->record(
                            $actor,
                            'artwork_material_preset.renamed',
                            'artwork_material_preset',
                            (int) $existing[$key]->getKey(),
                        );
                        $changed = true;
                    }
                    unset($existing[$key]);

                    continue;
                }

                $created = ArtworkMaterialPreset::query()->create(['name' => $name]);
                $this->audit->record(
                    $actor,
                    'artwork_material_preset.created',
                    'artwork_material_preset',
                    (int) $created->getKey(),
                );
                $changed = true;
            }

            foreach ($existing as $preset) {
                $presetId = (int) $preset->getKey();
                $preset->delete();
                $this->audit->record(
                    $actor,
                    'artwork_material_preset.deleted',
                    'artwork_material_preset',
                    $presetId,
                );
                $changed = true;
            }

            return $changed;
        });
    }
}
