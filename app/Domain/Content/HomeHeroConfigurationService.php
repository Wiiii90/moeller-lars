<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Artwork\PublicArtworkQuery;
use App\Models\HomePresentationSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class HomeHeroConfigurationService
{
    public const DEFAULT_GROUP_SIZE = 12;

    public const MAX_GROUP_SIZE = 50;

    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly PublicArtworkQuery $artworks,
    ) {}

    /**
     * Translate the persisted legacy hero_mode values into the artist-facing Manual/Automatic model.
     * Existing `fixed` and `random` values remain losslessly readable without a migration.
     *
     * @return array{
     *     mode:string,
     *     hero_artwork_id:?int,
     *     selection:string,
     *     newest_by:string,
     *     group_size:int,
     *     candidate_filter:string,
     *     specific_year:?int,
     *     manual_include_ids:list<int>,
     *     show_details:bool,
     *     show_gallery_link:bool
     * }
     */
    public function configuration(HomePresentationSetting $settings): array
    {
        $root = $settings->configuration();
        $artwork = is_array($root[HomeTemplate::Artwork->value] ?? null)
            ? $root[HomeTemplate::Artwork->value]
            : [];

        $persistedMode = is_string($artwork['hero_mode'] ?? null)
            ? $artwork['hero_mode']
            : 'automatic';
        $mode = in_array($persistedMode, ['fixed', 'manual'], true) ? 'manual' : 'automatic';
        $selection = $persistedMode === 'random'
            ? 'random'
            : $this->enum($artwork['automatic_selection'] ?? null, ['newest', 'random'], 'newest');
        $newestBy = $this->enum($artwork['newest_by'] ?? null, ['artwork_date', 'added'], 'artwork_date');
        $candidateFilter = ($artwork['pool_rule'] ?? null) === 'year' ? 'year' : 'all';

        return [
            'mode' => $mode,
            'hero_artwork_id' => $this->nullablePositiveInt($artwork['fixed_artwork_id'] ?? null),
            'selection' => $selection,
            'newest_by' => $newestBy,
            'group_size' => $this->boundedGroupSize($artwork['group_size'] ?? null),
            'candidate_filter' => $candidateFilter,
            'specific_year' => $this->nullableYear($artwork['pool_year'] ?? null),
            'manual_include_ids' => $this->positiveIntList($artwork['manual_include_ids'] ?? []),
            'show_details' => (bool) ($artwork['show_details'] ?? true),
            'show_gallery_link' => (bool) ($artwork['show_gallery_link'] ?? true),
        ];
    }

    /** @param array<string, mixed> $input */
    public function updateArtworkSettings(HomePresentationSetting $settings, array $input): bool
    {
        return DB::transaction(function () use ($settings, $input): bool {
            /** @var HomePresentationSetting $fresh */
            $fresh = HomePresentationSetting::query()
                ->whereKey($settings->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $current = $this->configuration($fresh);
            $mode = $this->enum($input['mode'] ?? $current['mode'], ['manual', 'automatic'], 'automatic');
            $selection = $this->enum($input['selection'] ?? $current['selection'], ['newest', 'random'], 'newest');
            $newestBy = $this->enum($input['newest_by'] ?? $current['newest_by'], ['artwork_date', 'added'], 'artwork_date');
            $candidateFilter = $this->enum($input['candidate_filter'] ?? $current['candidate_filter'], ['all', 'year'], 'all');
            $groupSize = $this->validatedGroupSize($input['group_size'] ?? $current['group_size']);
            $heroArtworkId = $this->nullablePositiveInt($input['hero_artwork_id'] ?? $current['hero_artwork_id']);
            $specificYear = $this->nullableYear($input['specific_year'] ?? $current['specific_year']);
            $manualIncludeIds = $this->positiveIntList($input['manual_include_ids'] ?? $current['manual_include_ids']);

            if ($mode === 'manual' && $heroArtworkId === null) {
                throw ValidationException::withMessages([
                    'hero_artwork_id' => 'Choose an eligible Hero Artwork for Manual mode.',
                ]);
            }

            if ($mode === 'automatic' && $candidateFilter === 'year' && $specificYear === null) {
                throw ValidationException::withMessages([
                    'specific_year' => 'Choose a year for the Specific Year candidate filter.',
                ]);
            }

            $referenceIds = $candidateFilter === 'year' ? $manualIncludeIds : [];
            if ($mode === 'manual' && $heroArtworkId !== null) {
                $referenceIds[] = $heroArtworkId;
            }
            $referenceIds = array_values(array_unique($referenceIds));
            if ($referenceIds !== []) {
                $eligibleIds = $this->artworks->homeCandidatesByIds($referenceIds)
                    ->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all();
                sort($referenceIds);
                sort($eligibleIds);

                if ($referenceIds !== $eligibleIds) {
                    throw ValidationException::withMessages([
                        'hero_artwork' => 'Hero artwork choices must be eligible published artworks from enabled Home source Galleries.',
                    ]);
                }
            }

            if ($mode === 'automatic' && $selection === 'random') {
                $candidateCount = $this->artworks->configuredHomeCandidates(
                    $groupSize,
                    $newestBy,
                    $candidateFilter,
                    $candidateFilter === 'year' ? $specificYear : null,
                    $manualIncludeIds,
                )->count();

                if ($candidateCount < 1) {
                    throw ValidationException::withMessages([
                        'selection' => 'Random selection needs at least one eligible Hero candidate.',
                    ]);
                }
            }

            $root = $fresh->configuration();
            $artwork = is_array($root[HomeTemplate::Artwork->value] ?? null)
                ? $root[HomeTemplate::Artwork->value]
                : [];

            $artwork['show_details'] = (bool) ($input['show_details'] ?? $current['show_details']);
            $artwork['show_gallery_link'] = (bool) ($input['show_gallery_link'] ?? $current['show_gallery_link']);
            $artwork['hero_mode'] = $mode === 'manual'
                ? 'fixed'
                : ($selection === 'random' ? 'random' : 'automatic');
            $artwork['automatic_selection'] = $selection;
            $artwork['fixed_artwork_id'] = $heroArtworkId;
            $artwork['newest_by'] = $newestBy;
            $artwork['group_size'] = $groupSize;
            $artwork['pool_rule'] = $candidateFilter === 'year' ? 'year' : 'newest';
            $artwork['pool_year'] = $candidateFilter === 'year' ? $specificYear : null;
            $artwork['manual_include_ids'] = $manualIncludeIds;

            $root[HomeTemplate::Artwork->value] = $artwork;
            $fresh->setAttribute('template', HomeTemplate::Artwork->value);
            $fresh->setAttribute('configuration', $root);

            if (! $fresh->isDirty(['template', 'configuration'])) {
                return false;
            }

            $fresh->save();
            $actor = $this->audit->requireActor();
            $this->audit->record(
                $actor,
                'site_section.updated',
                'site_section',
                (int) $fresh->getAttribute('site_section_id'),
            );

            return true;
        });
    }

    private function enum(mixed $value, array $allowed, string $default): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
    }

    private function boundedGroupSize(mixed $value): int
    {
        $size = filter_var($value, FILTER_VALIDATE_INT);
        if ($size === false || $size < 1) {
            return self::DEFAULT_GROUP_SIZE;
        }

        return min((int) $size, self::MAX_GROUP_SIZE);
    }

    private function validatedGroupSize(mixed $value): int
    {
        $size = filter_var($value, FILTER_VALIDATE_INT);
        if ($size === false || $size < 1 || $size > self::MAX_GROUP_SIZE) {
            throw ValidationException::withMessages([
                'group_size' => 'Group size must be between 1 and '.self::MAX_GROUP_SIZE.'.',
            ]);
        }

        return (int) $size;
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT);

        return $id === false || $id <= 0 ? null : (int) $id;
    }

    private function nullableYear(mixed $value): ?int
    {
        $year = $this->nullablePositiveInt($value);

        return $year !== null && $year >= 1000 && $year <= 3000 ? $year : null;
    }

    /** @return list<int> */
    private function positiveIntList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $ids = [];
        foreach ($values as $value) {
            $id = $this->nullablePositiveInt($value);
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
