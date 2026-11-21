<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Artwork\GalleryPresentation;
use App\Domain\Artwork\ArtworkSort;
use App\Models\ArtworkCategory;
use App\Models\GalleryHeroSetting;
use App\Models\MediaAsset;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class GalleryHeroConfigurationService
{
    use GalleryHeroDummyGalleryMembership;
    use GalleryHeroArtworkOverrides;

    public const DEFAULT_GROUP_SIZE = 12;

    public const MAX_GROUP_SIZE = 50;

    public const WEIGHT_TOTAL = 10000;

    public const SOURCE_RULES = [
        ...ArtworkSort::SOURCE_RULES,
        'gallery_newest_media_added' => 'Gallery Order: Newest Media',
        'gallery_newest_artwork' => 'Gallery Order: Newest Artwork',
        'gallery_oldest_media_added' => 'Gallery Order: Oldest Media',
        'gallery_oldest_artwork' => 'Gallery Order: Oldest Artwork',
    ];

    public const SELECTION_MODES = [
        'global' => 'Total',
        'per_gallery' => 'Per Gallery',
    ];

    /** @var array{count:int,unit:string} */
    public const DEFAULT_ROTATION_INTERVAL = [
        'count' => 1,
        'unit' => 'weeks',
    ];

    public function __construct(private readonly AdminAuditService $audit) {}

    /**
     * Canonical active Source profile, or an inactive profile when $mode is
     * provided. All source modes own independent candidates, ordered groups,
     * weights, rules, strategy and rotation. Genuine persisted legacy values
     * seed the new profiles once, not a parallel runtime data structure.
     *
     * @return array{
     *     group_source:string,
     *     enabled_gallery_ids:list<int>,
     *     dummy_galleries:list<array<string,mixed>>,
     *     disabled_auto_artwork_ids:list<int>,
     *     sourced_weights:array<string,int>,
     *     source_order:list<int>,
     *     display_strategy:string,
     *     rotation_interval:array{count:int,unit:string},
     *     rotation_started_at:?string,
     *     source_rule:string,
     *     selection_mode:string,
     *     group_size:int,
     *     show_details:bool,
     *     show_gallery_link:bool,
     *     show_navigation:bool,
     *     video_sound_enabled:bool,
     *     enable_viewer:bool,
     *     show_media_credits:bool,
     *     show_media_copyright:bool,
     *     caption_layout:string
     * }
     */
    public function configuration(GalleryHeroSetting $settings, ?string $mode = null): array
    {
        $artwork = $settings->configuration();
        $persistedMode = is_string($artwork['hero_mode'] ?? null) ? $artwork['hero_mode'] : 'automatic';
        $storedSource = $this->enumOrNull($artwork['group_source'] ?? null, ['automatic', 'semi_automatic', 'manual'])
            ?? (in_array($persistedMode, ['fixed', 'manual'], true) ? 'manual' : 'automatic');
        $selectedSource = $this->enum($mode, ['automatic', 'semi_automatic', 'manual'], $storedSource);

        $profile = $this->readSourceProfile($artwork, $selectedSource);

        return [
            'group_source' => $selectedSource,
            'enabled_gallery_ids' => $this->readEnabledGalleryIds($artwork['enabled_gallery_ids'] ?? []),
            ...$profile,
            'dummy_galleries' => $this->readDummyGalleries($artwork),
            'hero_artwork_overrides' => $this->readHeroArtworkOverrides($artwork['hero_artwork_overrides'] ?? []),
            'show_details' => (bool) ($artwork['show_details'] ?? true),
            'show_gallery_link' => (bool) ($artwork['show_gallery_link'] ?? false),
            'show_navigation' => (bool) ($artwork['show_navigation'] ?? false),
            'video_sound_enabled' => (bool) ($artwork['video_sound_enabled'] ?? false),
            'enable_viewer' => (bool) ($artwork['enable_viewer'] ?? true),
            'show_media_credits' => (bool) ($artwork['show_media_credits'] ?? false),
            'show_media_copyright' => (bool) ($artwork['show_media_copyright'] ?? false),
            'caption_layout' => $this->enum(
                $artwork['caption_layout'] ?? null,
                array_keys(GalleryPresentation::CAPTION_LAYOUTS),
                GalleryPresentation::CAPTION_RIGHTS_LEFT,
            ),
        ];
    }

    /** @return list<int> */
    private function readEnabledGalleryIds(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $id) {
            if (is_int($id) && $id > 0) {
                $ids[$id] = $id;
            }
        }
        sort($ids, SORT_NUMERIC);

        return array_values($ids);
    }

    /**
     * Hero source choices belong to one page, not ArtworkCategory. No other
     * Hero page is modified by the editor that invokes this operation.
     *
     * @param list<int> $galleryIds
     */
    public function setRegularGalleriesEnabled(GalleryHeroSetting $settings, array $galleryIds, bool $enabled): int
    {
        $ids = $this->readEnabledGalleryIds($galleryIds);
        if (count($ids) !== count(array_unique($galleryIds))) {
            throw ValidationException::withMessages(['source' => 'Choose valid Gallery sources.']);
        }
        if ($ids === []) {
            return 0;
        }

        return DB::transaction(function () use ($settings, $ids, $enabled): int {
            /** @var GalleryHeroSetting $fresh */
            $fresh = GalleryHeroSetting::query()->whereKey($settings->getKey())
                ->lockForUpdate()->firstOrFail();
            if ($enabled && ArtworkCategory::query()->whereKey($ids)
                ->whereHas('siteSection', static fn ($section) => $section->where('state', 'published'))
                ->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'source' => 'Publish each selected Gallery before enabling it as a Hero source.',
                ]);
            }

            $configuration = $fresh->configuration();
            $before = $this->readEnabledGalleryIds($configuration['enabled_gallery_ids'] ?? []);
            $after = $enabled
                ? $this->readEnabledGalleryIds([...$before, ...$ids])
                : array_values(array_diff($before, $ids));
            if ($before === $after) {
                return 0;
            }

            $configuration['enabled_gallery_ids'] = $after;
            $fresh->setAttribute('configuration', $configuration);
            $fresh->save();
            $this->audit->record(
                $this->audit->requireActor(), 'site_section.updated', 'site_section',
                (int) $fresh->getAttribute('site_section_id'),
            );

            return count(array_diff($before, $after)) + count(array_diff($after, $before));
        });
    }

    /** Hidden Galleries require an explicit new selection before being reused. */
    public function disableGalleryAcrossHeroes(int $galleryId): void
    {
        foreach (GalleryHeroSetting::query()->orderBy('id')->get(['id']) as $settings) {
            $this->setRegularGalleriesEnabled($settings, [$galleryId], false);
        }
    }

    /**
     * Source profiles own their independent ranking, candidate amount,
     * display, weights and rotation. Dummy Gallery membership is shared
     * Hero presentation, independent of the currently active source mode.
     *
     * @param array<string,mixed> $artwork
     * @return array<string,mixed>
     */
    private function readSourceProfile(array $artwork, string $mode): array
    {
        $profiles = is_array($artwork['source_profiles'] ?? null) ? $artwork['source_profiles'] : [];
        $stored = is_array($profiles[$mode] ?? null) ? $profiles[$mode] : [];
        $source = array_replace($artwork, $stored);
        $legacyMode = is_string($artwork['hero_mode'] ?? null) ? $artwork['hero_mode'] : 'automatic';
        $strategy = $this->enumOrNull($source['display_strategy'] ?? null, ['ordered', 'random', 'sequential']);
        if ($strategy === null) {
            $legacySelection = $legacyMode === 'random'
                ? 'random' : $this->enum($artwork['automatic_selection'] ?? null, ['newest', 'random'], 'newest');
            $strategy = $legacySelection === 'random' ? 'random' : 'ordered';
        }

        $legacyRule = ($artwork['newest_by'] ?? null) === 'added' ? 'newest_media_added' : 'newest_artwork';
        $weights = $this->readSourcedWeights($source['sourced_weights'] ?? []);
        if ($mode === 'manual' && $weights === []) {
            // The retired Manual source kept Random chances on members,
            // not in sourced_weights. Preserve those persisted chances.
            foreach ($this->readManualGroup($source['manual_group'] ?? []) as $member) {
                $weights[self::memberKey($member)] = $member['weight'];
            }
        }
        return [
            'display_strategy' => $strategy,
            'rotation_interval' => $this->readRotationInterval($source['rotation_interval'] ?? null),
            'rotation_started_at' => $this->readRotationStartedAt($source['rotation_started_at'] ?? null),
            'source_rule' => $this->enum($source['source_rule'] ?? null, array_keys(self::SOURCE_RULES), $legacyRule),
            'selection_mode' => ($source['selection_mode'] ?? null) === 'one_per_gallery'
                ? 'per_gallery' : $this->enum(
                    $source['selection_mode'] ?? null, array_keys(self::SELECTION_MODES), 'global',
                ),
            'group_size' => ($source['selection_mode'] ?? null) === 'one_per_gallery'
                ? 1 : $this->boundedGroupSize($source['group_size'] ?? null),
            'disabled_auto_artwork_ids' => $mode !== 'manual'
                ? $this->readDisabledAutoArtworkIds($source['disabled_auto_artwork_ids'] ?? []) : [],
            'sourced_weights' => $weights,
            'source_order' => $this->readSourceOrder($source['source_order'] ?? []),
        ];
    }

    /**
     * Persist one source profile per mode, while Dummy Galleries are a
     * shared Hero Artwork collection consumed by Manual and Semi-Automatic.
     *
     * @param array<string,mixed> $artwork
     * @return array<string,array<string,mixed>>
     */
    private function sourceProfiles(array $artwork): array
    {
        $profiles = [];
        foreach (['automatic', 'semi_automatic', 'manual'] as $mode) {
            $profile = $this->readSourceProfile($artwork, $mode);
            $profiles[$mode] = [
                'display_strategy' => $profile['display_strategy'],
                'rotation_interval' => $profile['rotation_interval'],
                'rotation_started_at' => $profile['rotation_started_at'],
            ];
            $profiles[$mode]['source_rule'] = $profile['source_rule'];
            $profiles[$mode]['selection_mode'] = $profile['selection_mode'];
            $profiles[$mode]['group_size'] = $profile['group_size'];
            $profiles[$mode]['source_order'] = $profile['source_order'];
            $profiles[$mode]['sourced_weights'] = $profile['sourced_weights'];
            if ($mode !== 'manual') {
                $profiles[$mode]['disabled_auto_artwork_ids'] = $profile['disabled_auto_artwork_ids'];
            }
        }
        return $profiles;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array{before:list<int|string>,after:list<int|string>}|null  $orderingState
     */
    public function updateArtworkSettings(GalleryHeroSetting $settings, array $input, ?array $orderingState = null): bool
    {
        return DB::transaction(function () use ($settings, $input, $orderingState): bool {
            /** @var GalleryHeroSetting $fresh */
            $fresh = GalleryHeroSetting::query()
                ->whereKey($settings->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $current = $this->configuration($fresh);
            $groupSource = $this->requestedGroupSource($input, $current['group_source']);
            $profile = $this->configuration($fresh, $groupSource);

            $disabledAutoIds = array_key_exists('disabled_auto_artwork_ids', $input)
                ? $this->validatedDisabledAutoArtworkIds($input['disabled_auto_artwork_ids'])
                : $profile['disabled_auto_artwork_ids'];
            $sourcedWeights = array_key_exists('sourced_weights', $input)
                ? $this->validatedSourcedWeights($input['sourced_weights'])
                : $profile['sourced_weights'];
            $displayStrategy = $this->requestedDisplayStrategy($input, $profile['display_strategy']);
            $sourceRule = $this->enum(
                $input['source_rule'] ?? $profile['source_rule'],
                array_keys(self::SOURCE_RULES),
                $profile['source_rule'],
            );
            $selectionMode = $this->enum(
                $input['selection_mode'] ?? $profile['selection_mode'],
                array_keys(self::SELECTION_MODES),
                $profile['selection_mode'],
            );
            $groupSize = $this->validatedGroupSize($input['group_size'] ?? $profile['group_size']);
            $sourceOrder = array_key_exists('source_order', $input)
                ? $this->validatedSourceOrder($input['source_order'])
                : $profile['source_order'];
            $artworkBefore = $fresh->configuration();
            $dummyGalleries = array_key_exists('dummy_galleries', $input)
                ? $this->validatedDummyGalleries($input['dummy_galleries'])
                : $this->readDummyGalleries($artworkBefore);
            $heroOverrides = array_key_exists('hero_artwork_overrides', $input)
                ? $this->validatedHeroArtworkOverrides($input['hero_artwork_overrides'])
                : $this->readHeroArtworkOverrides($artworkBefore['hero_artwork_overrides'] ?? []);
            $rotationInterval = $this->requestedRotationInterval($input, $profile['rotation_interval']);
            $rotationStartedAt = $profile['rotation_started_at'];

            $showDetails = (bool) ($input['show_details'] ?? $current['show_details']);
            $showGalleryLink = (bool) ($input['show_gallery_link'] ?? $current['show_gallery_link']);
            $showNavigation = (bool) ($input['show_navigation'] ?? $current['show_navigation']);
            $videoSoundEnabled = (bool) ($input['video_sound_enabled'] ?? $current['video_sound_enabled']);
            $enableViewer = (bool) ($input['enable_viewer'] ?? $current['enable_viewer']);
            $showMediaCredits = (bool) ($input['show_media_credits'] ?? $current['show_media_credits']);
            $showMediaCopyright = (bool) ($input['show_media_copyright'] ?? $current['show_media_copyright']);
            $captionLayout = $this->enum(
                $input['caption_layout'] ?? $current['caption_layout'],
                array_keys(GalleryPresentation::CAPTION_LAYOUTS),
                $current['caption_layout'],
            );

            $sourceOrderChanged = $sourceOrder !== $profile['source_order'];
            $dummyGalleriesChanged = $dummyGalleries !== $current['dummy_galleries'];
            $intervalChanged = $rotationInterval !== $profile['rotation_interval'];
            $strategyChanged = $displayStrategy !== $profile['display_strategy'];
            $automaticGroupDefinitionChanged = $groupSize !== $profile['group_size']
                || $sourceRule !== $profile['source_rule']
                || $selectionMode !== $profile['selection_mode']
                || $disabledAutoIds !== $profile['disabled_auto_artwork_ids']
                || $dummyGalleriesChanged;
            if ($displayStrategy === 'sequential'
                && ($rotationStartedAt === null || $intervalChanged
                    || $strategyChanged || $automaticGroupDefinitionChanged || $sourceOrderChanged)) {
                $rotationStartedAt = CarbonImmutable::now('UTC')->toIso8601String();
            }

            $artwork = $fresh->configuration();
            $profiles = $this->sourceProfiles($artwork);
            $profiles[$groupSource]['display_strategy'] = $displayStrategy;
            $profiles[$groupSource]['rotation_interval'] = $rotationInterval;
            $profiles[$groupSource]['rotation_started_at'] = $rotationStartedAt;
            $profiles[$groupSource]['source_rule'] = $sourceRule;
            $profiles[$groupSource]['selection_mode'] = $selectionMode;
            $profiles[$groupSource]['group_size'] = $groupSize;
            $profiles[$groupSource]['source_order'] = $sourceOrder;
            $profiles[$groupSource]['sourced_weights'] = $sourcedWeights;
            if ($groupSource !== 'manual') {
                $profiles[$groupSource]['disabled_auto_artwork_ids'] = $disabledAutoIds;
            }

            // A deleted Dummy Gallery must not leave priority entries behind:
            // negative IDs can be reused by a later new Dummy Gallery.
            $deletedSources = array_values(array_diff(
                array_column($current['dummy_galleries'], 'id'),
                array_column($dummyGalleries, 'id'),
            ));
            if ($dummyGalleriesChanged) {
                $currentDummyKeys = [];
                foreach ($dummyGalleries as $source) {
                    foreach ($source['members'] as $member) {
                        if (isset($member['artwork_id'])) {
                            $currentDummyKeys[self::heroArtworkOverrideKey(
                                (int) $source['id'], (int) $member['artwork_id'],
                            )] = true;
                        }
                    }
                }
                foreach (array_keys($heroOverrides) as $identity) {
                    if ((int) explode('|', $identity, 2)[0] < 0
                        && ! isset($currentDummyKeys[$identity])) {
                        unset($heroOverrides[$identity]);
                    }
                }
            }

            if ($deletedSources !== []) {
                foreach (array_keys($heroOverrides) as $identity) {
                    $sourceId = (int) explode('|', $identity, 2)[0];
                    if (in_array($sourceId, $deletedSources, true)) {
                        unset($heroOverrides[$identity]);
                    }
                }
                foreach ($profiles as &$sourceProfile) {
                    $sourceProfile['source_order'] = array_values(array_filter(
                        $sourceProfile['source_order'],
                        static fn (int $id): bool => ! in_array($id, $deletedSources, true),
                    ));
                }
                unset($sourceProfile);
            }

            $artwork['show_details'] = $showDetails;
            $artwork['show_gallery_link'] = $showGalleryLink;
            $artwork['show_navigation'] = $showNavigation;
            $artwork['video_sound_enabled'] = $videoSoundEnabled;
            $artwork['enable_viewer'] = $enableViewer;
            $artwork['show_media_credits'] = $showMediaCredits;
            $artwork['show_media_copyright'] = $showMediaCopyright;
            $artwork['caption_layout'] = $captionLayout;
            $artwork['group_source'] = $groupSource;
            $artwork['source_profiles'] = $profiles;
            $artwork['dummy_galleries'] = $dummyGalleries;
            $artwork['hero_artwork_overrides'] = $heroOverrides;
            unset(
                $artwork['manual_group'], $artwork['sourced_weights'],
                $artwork['manual_group_enabled'], $artwork['disabled_auto_artwork_ids'],
                $artwork['display_strategy'], $artwork['rotation_interval'],
                $artwork['rotation_started_at'], $artwork['source_rule'],
                $artwork['selection_mode'], $artwork['group_size'], $artwork['newest_by'],
                $artwork['hero_mode'], $artwork['automatic_selection'],
                $artwork['fixed_artwork_id'], $artwork['pool_rule'],
                $artwork['pool_year'], $artwork['manual_include_ids'],
                // Obsolete pre-cutover layout toggles are retired at the next edit.
                $artwork['landscape_detail_enabled'], $artwork['portrait_detail_enabled'],
                $artwork['artwork_page_surface_mode'], $artwork['artwork_menu_surface_wide'],
            );

            $fresh->setAttribute('configuration', $artwork);

            if (! $fresh->isDirty('configuration')) {
                return false;
            }

            $fresh->save();
            $actor = $this->audit->requireActor();
            $sectionId = (int) $fresh->getAttribute('site_section_id');
            if ($orderingState !== null) {
                $this->audit->recordOrdering(
                    $actor,
                    'site_section.gallery_hero_artworks_reordered',
                    'site_section',
                    $sectionId,
                    'gallery-hero-artworks:'.$sectionId,
                    $orderingState['before'],
                    $orderingState['after'],
                );
            } else {
                $this->audit->record(
                    $actor,
                    'site_section.updated',
                    'site_section',
                    $sectionId,
                );
            }

            return true;
        });
    }

    /** @return list<int> */
    private function readSourceOrder(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }
        $ids = [];
        foreach ($value as $valueId) {
            $id = filter_var($valueId, FILTER_VALIDATE_INT);
            if ($id !== false && $id !== 0 && ! in_array($id, $ids, true)) {
                $ids[] = (int) $id;
            }
        }
        return array_slice($ids, 0, 500);
    }

    /** @return list<int> */
    private function validatedSourceOrder(mixed $value): array
    {
        $ids = $this->readSourceOrder($value);
        if (! is_array($value) || ! array_is_list($value) || count($ids) !== count($value)) {
            throw ValidationException::withMessages(['source_order' => 'Choose distinct, valid source Galleries.']);
        }
        return $ids;
    }

    public function moveSource(GalleryHeroSetting $settings, int $sourceId, string $direction, array $visibleIds): bool
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw ValidationException::withMessages(['source_order' => 'Invalid Gallery movement.']);
        }
        return DB::transaction(function () use ($settings, $sourceId, $direction, $visibleIds): bool {
            $fresh = GalleryHeroSetting::query()->whereKey($settings->getKey())
                ->lockForUpdate()->firstOrFail();
            $current = $this->configuration($fresh);
            $order = array_values(array_unique(array_merge(
                array_values(array_filter(
                    $current['source_order'],
                    static fn (int $id): bool => in_array($id, $visibleIds, true),
                )),
                $visibleIds,
            )));
            $position = array_search($sourceId, $order, true);
            if ($position === false) {
                throw ValidationException::withMessages(['source_order' => 'Gallery no longer in this Hero source.']);
            }
            $target = $position + ($direction === 'up' ? -1 : 1);
            if (! isset($order[$target])) {
                return false;
            }
            [$order[$position], $order[$target]] = [$order[$target], $order[$position]];

            // Gallery priority is a profile setting, not the obsolete
            // manual Artwork ordering scope used by historical Audit records.
            return $this->updateArtworkSettings($fresh, ['source_order' => $order]);
        });
    }

    public function updateCandidateWeight(GalleryHeroSetting $settings, string $key, int $basisPoints): bool
    {
        if ($basisPoints < 0 || $basisPoints > self::WEIGHT_TOTAL) {
            throw ValidationException::withMessages(['weight' => 'Hero percentage must be between 0 and 100.']);
        }

        return DB::transaction(function () use ($settings, $key, $basisPoints): bool {
            $fresh = GalleryHeroSetting::query()->whereKey($settings->getKey())
                ->lockForUpdate()->firstOrFail();
            $configuration = $this->configuration($fresh);
            if ($configuration['display_strategy'] !== 'random') {
                throw ValidationException::withMessages(['weight' => 'Hero chances can be changed only for Random strategy.']);
            }

            $resolution = app(GalleryHeroResolver::class)->resolve($fresh);
            $candidates = $resolution['group']->values();
            $keys = $candidates->map(static fn (GalleryHeroCandidate $candidate): string => $candidate->key())->all();
            if (! in_array($key, $keys, true)) {
                throw ValidationException::withMessages(['candidate' => 'The sourced Hero candidate changed. Reload and try again.']);
            }
            $weights = $resolution['weights'];
            $otherKeys = array_values(array_filter($keys, static fn (string $value): bool => $value !== $key));
            $otherValues = array_map(static fn (string $value): int => $weights[$value] ?? 0, $otherKeys);
            $newWeights = $configuration['sourced_weights'];
            $newWeights[$key] = $otherKeys === [] ? self::WEIGHT_TOTAL : $basisPoints;
            $allocated = $this->allocateBudget($otherValues, self::WEIGHT_TOTAL - $newWeights[$key]);
            foreach ($otherKeys as $index => $otherKey) {
                $newWeights[$otherKey] = $allocated[$index];
            }

            return $this->updateArtworkSettings($fresh, ['sourced_weights' => $newWeights]);
        });
    }

    /** @return list<array{artwork_id?:int,media_asset_id?:int,weight:int,enabled:bool}> */
    public function normalizeManualGroup(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw ValidationException::withMessages(['manual_group' => 'The Hero group must be an ordered list.']);
        }
        $members = [];
        $seen = [];
        foreach ($value as $member) {
            if (! is_array($member)) {
                throw ValidationException::withMessages(['manual_group' => 'Hero candidates must be structured values.']);
            }
            $identity = $this->memberIdentity($member);
            $weight = filter_var($member['weight'] ?? null, FILTER_VALIDATE_INT);
            if ($identity === null || $weight === false || $weight < 0 || $weight > self::WEIGHT_TOTAL) {
                throw ValidationException::withMessages(['manual_group' => 'Choose a valid Hero candidate and percentage.']);
            }
            $enabled = $member['enabled'] ?? true;
            if (! is_bool($enabled)) {
                throw ValidationException::withMessages(['manual_group' => 'Candidate enabled state must be true or false.']);
            }
            $key = self::memberKey($identity);
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(['manual_group' => 'The Hero group cannot contain duplicate candidates.']);
            }
            $seen[$key] = true;
            $members[] = [...$identity, 'weight' => (int) $weight, 'enabled' => $enabled];
        }
        if ($members === []) {
            return [];
        }

        $enabledIndices = array_keys(array_filter(
            $members, static fn (array $member): bool => $member['enabled'],
        ));
        if ($enabledIndices !== []) {
            $weights = $this->allocateBudget(
                array_map(static fn (int $index): int => $members[$index]['weight'], $enabledIndices),
                self::WEIGHT_TOTAL,
            );
            foreach ($enabledIndices as $offset => $index) {
                $members[$index]['weight'] = $weights[$offset];
            }
        }

        return $members;
    }

    /** @param array<string,mixed> $member
     * @return array{artwork_id?:int,media_asset_id?:int}|null
     */
    private function memberIdentity(array $member): ?array
    {
        $artworkId = $this->nullablePositiveInt($member['artwork_id'] ?? null);
        $mediaId = $this->nullablePositiveInt($member['media_asset_id'] ?? null);
        if (($artworkId === null) === ($mediaId === null)) {
            return null;
        }

        return $artworkId !== null ? ['artwork_id' => $artworkId] : ['media_asset_id' => $mediaId];
    }

    /** @param array<string,int> $member */
    public static function memberKey(array $member): string
    {
        if (isset($member['media_asset_id'])) {
            return 'media:'.$member['media_asset_id'];
        }

        return 'artwork:'.$member['artwork_id'];
    }

    /** @return list<array{artwork_id?:int,media_asset_id?:int,weight:int,enabled:bool}> */
    private function readManualGroup(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }
        $members = [];
        $seen = [];
        foreach ($value as $member) {
            if (! is_array($member)) {
                continue;
            }
            $identity = $this->memberIdentity($member);
            $weight = filter_var($member['weight'] ?? null, FILTER_VALIDATE_INT);
            if ($identity === null || $weight === false || $weight < 0 || $weight > self::WEIGHT_TOTAL) {
                continue;
            }
            $enabled = $member['enabled'] ?? true;
            if (! is_bool($enabled)) {
                continue;
            }
            $key = self::memberKey($identity);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $members[] = [...$identity, 'weight' => (int) $weight, 'enabled' => $enabled];
        }

        return $members === [] ? [] : $this->normalizeManualGroup($members);
    }

    /** @param list<int> $weights
     * @return list<int>
     */
    private function allocateBudget(array $weights, int $budget): array
    {
        if ($weights === []) {
            return [];
        }
        if ($budget < 0) {
            throw ValidationException::withMessages(['weight' => 'Manual Hero weight budget is invalid.']);
        }

        $sum = array_sum($weights);
        if ($sum <= 0) {
            $base = intdiv($budget, count($weights));
            $remainder = $budget - ($base * count($weights));

            return array_map(
                static fn (int $index): int => $base + ($index < $remainder ? 1 : 0),
                array_keys($weights),
            );
        }

        $allocated = [];
        $remainders = [];
        $used = 0;
        foreach ($weights as $index => $weight) {
            $numerator = $weight * $budget;
            $floor = intdiv($numerator, $sum);
            $allocated[$index] = $floor;
            $remainders[$index] = $numerator % $sum;
            $used += $floor;
        }

        $left = $budget - $used;
        $order = array_keys($weights);
        usort($order, static function (int $leftIndex, int $rightIndex) use ($remainders): int {
            $comparison = $remainders[$rightIndex] <=> $remainders[$leftIndex];

            return $comparison !== 0 ? $comparison : ($leftIndex <=> $rightIndex);
        });
        for ($offset = 0; $offset < $left; $offset++) {
            $allocated[$order[$offset % count($order)]]++;
        }
        ksort($allocated);

        return array_values($allocated);
    }

    /** @return list<int> */
    private function readDisabledAutoArtworkIds(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $valueId) {
            $id = filter_var($valueId, FILTER_VALIDATE_INT);
            if ($id !== false && $id > 0) {
                $ids[] = (int) $id;
            }
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    /** @return list<int> */
    private function validatedDisabledAutoArtworkIds(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)
            || count($value) > 10000
            || count($this->readDisabledAutoArtworkIds($value)) !== count($value)) {
            throw ValidationException::withMessages([
                'disabled_auto_artwork_ids' => 'Choose distinct, valid Artwork exclusions.',
            ]);
        }

        return $this->readDisabledAutoArtworkIds($value);
    }

    public function setAutomaticCandidateEnabled(GalleryHeroSetting $settings, int $artworkId, bool $enabled): bool
    {
        if ($artworkId < 1) {
            throw ValidationException::withMessages(['candidate' => 'Select a valid Gallery Artwork.']);
        }

        return DB::transaction(function () use ($settings, $artworkId, $enabled): bool {
            /** @var GalleryHeroSetting $fresh */
            $fresh = GalleryHeroSetting::query()
                ->whereKey($settings->getKey())->lockForUpdate()->firstOrFail();
            $current = $this->configuration($fresh);
            if (! in_array($current['group_source'], ['automatic', 'semi_automatic'], true)) {
                throw ValidationException::withMessages([
                    'candidate' => 'Only Automatic and Semi-Automatic permit Gallery exclusions.',
                ]);
            }

            $disabled = $current['disabled_auto_artwork_ids'];
            $present = in_array($artworkId, $disabled, true);
            if ($present === ! $enabled) {
                return false;
            }

            if ($enabled) {
                $disabled = array_values(array_diff($disabled, [$artworkId]));
            } else {
                $key = 'artwork:'.$artworkId;
                $active = app(GalleryHeroResolver::class)->resolve($fresh)['group'];
                if (! $active->contains(static fn (GalleryHeroCandidate $candidate): bool =>
                    $candidate->key() === $key && ($candidate->sourceId === null || $candidate->sourceId > 0))) {
                    throw ValidationException::withMessages([
                        'candidate' => 'This automatic candidate is no longer in the current Hero group.',
                    ]);
                }
                $disabled[] = $artworkId;
            }

            return $this->updateArtworkSettings($fresh, ['disabled_auto_artwork_ids' => $disabled]);
        });
    }

    private function readSourcedWeights(mixed $value): array
    {
        if (! is_array($value) || array_is_list($value)) {
            return [];
        }

        $weights = [];
        foreach ($value as $key => $weight) {
            if (! is_string($key) || ! preg_match('/^(artwork|media):[1-9][0-9]*$/D', $key)) {
                continue;
            }
            $number = filter_var($weight, FILTER_VALIDATE_INT);
            if ($number === false || $number < 0 || $number > self::WEIGHT_TOTAL) {
                continue;
            }
            $weights[$key] = (int) $number;
        }

        ksort($weights);

        return $weights;
    }

    /**
     * Resolve percentages for the *current* sourced membership. Unknown
     * candidates start near the average already configured chance, so adding
     * a Gallery never silently reduces their default chance to almost zero.
     *
     * @param \Illuminate\Support\Collection<int,GalleryHeroCandidate> $group
     * @param array<string,int> $stored
     * @return array<string,int>
     */
    public function sourcedGroupWeights(\Illuminate\Support\Collection $group, array $stored): array
    {
        $keys = $group->map(static fn (GalleryHeroCandidate $candidate): string => $candidate->key())->values()->all();
        if ($keys === []) {
            return [];
        }

        $known = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $stored)) {
                $known[] = $stored[$key];
            }
        }
        $fallback = $known === [] ? 1 : max(1, (int) round(array_sum($known) / count($known)));
        $raw = array_map(static fn (string $key): int => $stored[$key] ?? $fallback, $keys);
        $balanced = $this->allocateBudget($raw, self::WEIGHT_TOTAL);

        return array_combine($keys, $balanced);
    }

    /** @return array<string,int> */
    private function validatedSourcedWeights(mixed $value): array
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value)) || count($value) > 1000) {
            throw ValidationException::withMessages([
                'weight' => 'The sourced Hero weights must contain at most 1000 valid candidate entries.',
            ]);
        }
        $weights = $this->readSourcedWeights($value);
        if (count($weights) !== count($value)) {
            throw ValidationException::withMessages([
                'weight' => 'Invalid sourced Hero candidate or percentage.',
            ]);
        }

        return $weights;
    }

    /** @param array<string, mixed> $input */
    private function requestedGroupSource(array $input, string $current): string
    {
        return array_key_exists('group_source', $input)
            ? $this->enum($input['group_source'], ['automatic', 'semi_automatic', 'manual'], $current)
            : $current;
    }

    /** @param array<string, mixed> $input */
    private function requestedDisplayStrategy(array $input, string $current): string
    {
        return array_key_exists('display_strategy', $input)
            ? $this->enum($input['display_strategy'], ['ordered', 'random', 'sequential'], $current)
            : $current;
    }

    /** @param array<string, mixed> $input
     * @param  array{count:int,unit:string}  $current
     * @return array{count:int,unit:string}
     */
    private function requestedRotationInterval(array $input, array $current): array
    {
        if (array_key_exists('rotation_interval', $input)) {
            return $this->validatedRotationInterval($input['rotation_interval']);
        }
        if (array_key_exists('rotation_interval_count', $input) || array_key_exists('rotation_interval_unit', $input)) {
            return $this->validatedRotationInterval([
                'count' => $input['rotation_interval_count'] ?? $current['count'],
                'unit' => $input['rotation_interval_unit'] ?? $current['unit'],
            ]);
        }

        return $current;
    }

    /** @return array{count:int,unit:string} */
    private function readRotationInterval(mixed $value): array
    {
        if (! is_array($value)) {
            return self::DEFAULT_ROTATION_INTERVAL;
        }
        $count = filter_var($value['count'] ?? null, FILTER_VALIDATE_INT);
        $unit = $this->enumOrNull($value['unit'] ?? null, ['days', 'weeks', 'months']);
        if ($count === false || $count < 1 || $unit === null) {
            return self::DEFAULT_ROTATION_INTERVAL;
        }

        return ['count' => (int) $count, 'unit' => $unit];
    }

    /** @return array{count:int,unit:string} */
    private function validatedRotationInterval(mixed $value): array
    {
        if (! is_array($value)) {
            throw ValidationException::withMessages(['rotation_interval' => 'Choose a valid Sequential rotation interval.']);
        }
        $count = filter_var($value['count'] ?? null, FILTER_VALIDATE_INT);
        $unit = $this->enumOrNull($value['unit'] ?? null, ['days', 'weeks', 'months']);
        if ($count === false || $count < 1 || $unit === null) {
            throw ValidationException::withMessages(['rotation_interval' => 'Sequential rotation interval needs a positive count in days, weeks or calendar months.']);
        }

        return ['count' => (int) $count, 'unit' => $unit];
    }

    private function readRotationStartedAt(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc()->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private function enum(mixed $value, array $allowed, string $default): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
    }

    private function enumOrNull(mixed $value, array $allowed): ?string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : null;
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

}
