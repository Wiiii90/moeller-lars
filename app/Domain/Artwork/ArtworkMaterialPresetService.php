<?php

namespace App\Domain\Artwork;

use App\Models\ArtworkMaterialPreset;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ArtworkMaterialPresetService
{
    /** @param array<int, mixed> $names */
    public function sync(array $names): void
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

        DB::transaction(function () use ($normalized): void {
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
                    }
                    unset($existing[$key]);
                    continue;
                }

                ArtworkMaterialPreset::query()->create(['name' => $name]);
            }

            foreach ($existing as $preset) {
                $preset->delete();
            }
        });
    }
}
