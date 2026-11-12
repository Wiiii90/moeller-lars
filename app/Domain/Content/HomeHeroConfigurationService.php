<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Artwork\GalleryPresentation;
use App\Models\HomePresentationSetting;
use App\Models\MediaAsset;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class HomeHeroConfigurationService
{
    public const DEFAULT_GROUP_SIZE = 12;

    public const MAX_GROUP_SIZE = 50;

    public const WEIGHT_TOTAL = 10000;

    public const SOURCE_RULES = [
        'newest_media_added' => 'Newest by Media Added',
        'newest_artwork' => 'Newest by Artwork',
        'oldest_media_added' => 'Oldest by Media Added',
        'oldest_artwork' => 'Oldest by Artwork',
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
     *     manual_group_enabled:bool,
     *     disabled_auto_artwork_ids:list<int>,
     *     sourced_weights:array<string,int>,
     *     display_strategy:string,
     *     manual_group:list<array{artwork_id?:int,media_asset_id?:int,weight:int,enabled:bool}>,
     *     manual_group_count:int,
     *     rotation_interval:array{count:int,unit:string},
     *     rotation_started_at:?string,
     *     source_rule:string,
     *     group_size:int,
     *     show_details:bool,
     *     show_gallery_link:bool,
     *     show_navigation:bool,
     *     video_sound_enabled:bool,
     *     enable_viewer:bool,
     *     landscape_detail_enabled:bool,
     *     portrait_detail_enabled:bool,
     *     artwork_page_surface_mode:string,
     *     artwork_menu_surface_wide:bool,
     *     show_media_credits:bool,
     *     show_media_copyright:bool,
     *     caption_layout:string
     * }
     */
    public function configuration(HomePresentationSetting $settings, ?string $mode = null): array
    {
        $root = $settings->configuration();
        $artwork = is_array($root[HomeTemplate::Artwork->value] ?? null)
            ? $root[HomeTemplate::Artwork->value]
            : [];
        $persistedMode = is_string($artwork['hero_mode'] ?? null) ? $artwork['hero_mode'] : 'automatic';
        $storedSource = $this->enumOrNull($artwork['group_source'] ?? null, ['automatic', 'semi_automatic', 'manual'])
            ?? (in_array($persistedMode, ['fixed', 'manual'], true) ? 'manual' : 'automatic');
        $selectedSource = $this->enum($mode, ['automatic', 'semi_automatic', 'manual'], $storedSource);

        $profile = $this->readSourceProfile($artwork, $selectedSource);

        return [
            'group_source' => $selectedSource,
            ...$profile,
            'manual_group_count' => count($profile['manual_group']),
            'show_details' => (bool) ($artwork['show_details'] ?? true),
            'show_gallery_link' => (bool) ($artwork['show_gallery_link'] ?? false),
            'show_navigation' => (bool) ($artwork['show_navigation'] ?? false),
            'video_sound_enabled' => (bool) ($artwork['video_sound_enabled'] ?? false),
            'enable_viewer' => (bool) ($artwork['enable_viewer'] ?? true),
            'landscape_detail_enabled' => (bool) ($artwork['landscape_detail_enabled'] ?? false),
            'portrait_detail_enabled' => (bool) ($artwork['portrait_detail_enabled'] ?? false),
            'artwork_page_surface_mode' => $this->enum(
                $artwork['artwork_page_surface_mode'] ?? null,
                array_keys(GalleryPresentation::ARTWORK_SURFACE_OPTIONS),
                GalleryPresentation::ARTWORK_SURFACE_INHERIT,
            ),
            'artwork_menu_surface_wide' => (bool) ($artwork['artwork_menu_surface_wide'] ?? false),
            'show_media_credits' => (bool) ($artwork['show_media_credits'] ?? false),
            'show_media_copyright' => (bool) ($artwork['show_media_copyright'] ?? false),
            'caption_layout' => $this->enum(
                $artwork['caption_layout'] ?? null,
                array_keys(GalleryPresentation::CAPTION_LAYOUTS),
                GalleryPresentation::CAPTION_RIGHTS_LEFT,
            ),
        ];
    }

    /**
     * The stored profiles have exactly one owner per Source. Prior root-level
     * settings are legitimate saved data and seed each profile once; an empty
     * saved profile (including an empty Manual list) always wins thereafter.
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

        $manual = $mode === 'automatic' ? [] : $this->readManualGroup($source['manual_group'] ?? null);
        $legacyFixed = $this->nullablePositiveInt($artwork['fixed_artwork_id'] ?? null);
        if ($mode !== 'automatic' && $manual === [] && ! array_key_exists('manual_group', $stored)
            && $legacyFixed !== null && in_array($legacyMode, ['fixed', 'manual'], true)) {
            $manual = [['artwork_id' => $legacyFixed, 'weight' => self::WEIGHT_TOTAL]];
        }
        $legacyRule = ($artwork['newest_by'] ?? null) === 'added' ? 'newest_media_added' : 'newest_artwork';
        return [
            'display_strategy' => $strategy,
            'rotation_interval' => $this->readRotationInterval($source['rotation_interval'] ?? null),
            'rotation_started_at' => $this->readRotationStartedAt($source['rotation_started_at'] ?? null),
            'source_rule' => $this->enum($source['source_rule'] ?? null, array_keys(self::SOURCE_RULES), $legacyRule),
            'group_size' => $this->boundedGroupSize($source['group_size'] ?? null),
            'manual_group' => $manual,
            'manual_group_enabled' => $mode === 'semi_automatic' ? (bool) ($source['manual_group_enabled'] ?? true) : $mode === 'manual',
            'disabled_auto_artwork_ids' => $mode !== 'manual'
                ? $this->readDisabledAutoArtworkIds($source['disabled_auto_artwork_ids'] ?? []) : [],
            'sourced_weights' => $mode === 'manual' ? [] : $this->readSourcedWeights($source['sourced_weights'] ?? []),
        ];
    }

    /**
     * Persist only fields that actually belong to the corresponding mode.
     * This is one canonical profile map with independent stored Manual and
     * Semi-Automatic candidates, never parallel root-level state.
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
            if ($mode !== 'manual') {
                $profiles[$mode]['source_rule'] = $profile['source_rule'];
                $profiles[$mode]['group_size'] = $profile['group_size'];
                $profiles[$mode]['sourced_weights'] = $profile['sourced_weights'];
                $profiles[$mode]['disabled_auto_artwork_ids'] = $profile['disabled_auto_artwork_ids'];
            }
            if ($mode !== 'automatic') {
                $profiles[$mode]['manual_group'] = $profile['manual_group'];
            }
            if ($mode === 'semi_automatic') {
                $profiles[$mode]['manual_group_enabled'] = $profile['manual_group_enabled'];
            }
        }
        return $profiles;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array{before:list<int|string>,after:list<int|string>}|null  $orderingState
     */
    public function updateArtworkSettings(HomePresentationSetting $settings, array $input, ?array $orderingState = null): bool
    {
        return DB::transaction(function () use ($settings, $input, $orderingState): bool {
            /** @var HomePresentationSetting $fresh */
            $fresh = HomePresentationSetting::query()
                ->whereKey($settings->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $current = $this->configuration($fresh);
            $groupSource = $this->requestedGroupSource($input, $current['group_source']);
            $profile = $this->configuration($fresh, $groupSource);

            $manualGroupEnabled = (bool) ($input['manual_group_enabled'] ?? $profile['manual_group_enabled']);
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
            $groupSize = $this->validatedGroupSize($input['group_size'] ?? $profile['group_size']);
            $manualGroup = array_key_exists('manual_group', $input)
                ? $this->normalizeManualGroup($input['manual_group'])
                : $profile['manual_group'];
            $rotationInterval = $this->requestedRotationInterval($input, $profile['rotation_interval']);
            $rotationStartedAt = $profile['rotation_started_at'];

            $showDetails = (bool) ($input['show_details'] ?? $current['show_details']);
            $showGalleryLink = (bool) ($input['show_gallery_link'] ?? $current['show_gallery_link']);
            $showNavigation = (bool) ($input['show_navigation'] ?? $current['show_navigation']);
            $videoSoundEnabled = (bool) ($input['video_sound_enabled'] ?? $current['video_sound_enabled']);
            $enableViewer = (bool) ($input['enable_viewer'] ?? $current['enable_viewer']);
            $expandLandscape = (bool) ($input['landscape_detail_enabled'] ?? $current['landscape_detail_enabled']);
            $expandPortrait = (bool) ($input['portrait_detail_enabled'] ?? $current['portrait_detail_enabled']);
            $pageSurfaceMode = $this->enum(
                $input['artwork_page_surface_mode'] ?? $current['artwork_page_surface_mode'],
                array_keys(GalleryPresentation::ARTWORK_SURFACE_OPTIONS),
                $current['artwork_page_surface_mode'],
            );
            $wideArtworkMenu = (bool) ($input['artwork_menu_surface_wide'] ?? $current['artwork_menu_surface_wide']);
            $showMediaCredits = (bool) ($input['show_media_credits'] ?? $current['show_media_credits']);
            $showMediaCopyright = (bool) ($input['show_media_copyright'] ?? $current['show_media_copyright']);
            $captionLayout = $this->enum(
                $input['caption_layout'] ?? $current['caption_layout'],
                array_keys(GalleryPresentation::CAPTION_LAYOUTS),
                $current['caption_layout'],
            );

            $manualSequenceChanged = $this->manualGroupKeys($manualGroup) !== $this->manualGroupKeys($profile['manual_group'])
                || array_map(static fn (array $member): bool => $member['enabled'], $manualGroup)
                    !== array_map(static fn (array $member): bool => $member['enabled'], $profile['manual_group']);
            $intervalChanged = $rotationInterval !== $profile['rotation_interval'];
            $strategyChanged = $displayStrategy !== $profile['display_strategy'];
            $automaticGroupDefinitionChanged = $groupSource !== 'manual'
                && ($groupSize !== $profile['group_size'] || $sourceRule !== $profile['source_rule']
                    || $disabledAutoIds !== $profile['disabled_auto_artwork_ids']
                    || $manualGroupEnabled !== $profile['manual_group_enabled']);
            if ($displayStrategy === 'sequential'
                && ($rotationStartedAt === null || $manualSequenceChanged || $intervalChanged
                    || $strategyChanged || $automaticGroupDefinitionChanged)) {
                $rotationStartedAt = CarbonImmutable::now('UTC')->toIso8601String();
            }

            $root = $fresh->configuration();
            $artwork = is_array($root[HomeTemplate::Artwork->value] ?? null)
                ? $root[HomeTemplate::Artwork->value] : [];
            $profiles = $this->sourceProfiles($artwork);
            $profiles[$groupSource]['display_strategy'] = $displayStrategy;
            $profiles[$groupSource]['rotation_interval'] = $rotationInterval;
            $profiles[$groupSource]['rotation_started_at'] = $rotationStartedAt;
            if ($groupSource !== 'manual') {
                $profiles[$groupSource]['source_rule'] = $sourceRule;
                $profiles[$groupSource]['group_size'] = $groupSize;
                $profiles[$groupSource]['sourced_weights'] = $sourcedWeights;
                $profiles[$groupSource]['disabled_auto_artwork_ids'] = $disabledAutoIds;
            }
            if ($groupSource !== 'automatic') {
                $profiles[$groupSource]['manual_group'] = $manualGroup;
            }
            if ($groupSource === 'semi_automatic') {
                $profiles[$groupSource]['manual_group_enabled'] = $manualGroupEnabled;
            }

            $artwork['show_details'] = $showDetails;
            $artwork['show_gallery_link'] = $showGalleryLink;
            $artwork['show_navigation'] = $showNavigation;
            $artwork['video_sound_enabled'] = $videoSoundEnabled;
            $artwork['enable_viewer'] = $enableViewer;
            $artwork['landscape_detail_enabled'] = $expandLandscape;
            $artwork['portrait_detail_enabled'] = $expandPortrait;
            $artwork['artwork_page_surface_mode'] = $pageSurfaceMode;
            $artwork['artwork_menu_surface_wide'] = $wideArtworkMenu;
            $artwork['show_media_credits'] = $showMediaCredits;
            $artwork['show_media_copyright'] = $showMediaCopyright;
            $artwork['caption_layout'] = $captionLayout;
            $artwork['group_source'] = $groupSource;
            $artwork['source_profiles'] = $profiles;
            unset(
                $artwork['manual_group'], $artwork['sourced_weights'],
                $artwork['manual_group_enabled'], $artwork['disabled_auto_artwork_ids'],
                $artwork['display_strategy'], $artwork['rotation_interval'],
                $artwork['rotation_started_at'], $artwork['source_rule'],
                $artwork['group_size'], $artwork['newest_by'],
                $artwork['hero_mode'], $artwork['automatic_selection'],
                $artwork['fixed_artwork_id'], $artwork['pool_rule'],
                $artwork['pool_year'], $artwork['manual_include_ids'],
            );

            $root[HomeTemplate::Artwork->value] = $artwork;
            $fresh->setAttribute('template', HomeTemplate::Artwork->value);
            $fresh->setAttribute('configuration', $root);

            if (! $fresh->isDirty(['template', 'configuration'])) {
                return false;
            }

            $fresh->save();
            $actor = $this->audit->requireActor();
            $sectionId = (int) $fresh->getAttribute('site_section_id');
            if ($orderingState !== null) {
                $this->audit->recordOrdering(
                    $actor,
                    'site_section.home_artworks_reordered',
                    'site_section',
                    $sectionId,
                    'home-artworks:'.$sectionId,
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

    /**
     * Add a Storage asset only to a modal draft; no persistence or audit occurs
     * until the full ordered Manual group is submitted.
     *
     * @param  list<array<string,mixed>>  $draft
     * @return list<array{artwork_id?:int,media_asset_id?:int,weight:int,enabled:bool}>
     */
    public function appendDraftCandidate(array $draft, int $mediaAssetId): array
    {
        $asset = $mediaAssetId > 0
            ? MediaAsset::query()->with('variants')->find($mediaAssetId)
            : null;
        if (! $asset instanceof MediaAsset || ! HomeHeroCandidate::mediaIsEligible($asset)) {
            throw ValidationException::withMessages([
                'pending_media_asset_id' => 'Choose an available image with a public preview and ALT text, or an available video with ALT text.',
            ]);
        }

        $group = $this->normalizeManualGroup(array_values($draft));
        if (count($group) >= self::MAX_GROUP_SIZE) {
            throw ValidationException::withMessages([
                'manual_group' => 'The Hero group cannot exceed '.self::MAX_GROUP_SIZE.' manually selected candidates.',
            ]);
        }
        if (in_array('media:'.$mediaAssetId, $this->manualGroupKeys($group), true)) {
            throw ValidationException::withMessages([
                'pending_media_asset_id' => 'That Media File is already in the group.',
            ]);
        }

        $enabledIndices = array_keys(array_filter(
            $group, static fn (array $entry): bool => $entry['enabled'],
        ));
        $weight = intdiv(self::WEIGHT_TOTAL, count($enabledIndices) + 1);
        $scaled = $this->allocateBudget(
            array_map(static fn (int $index): int => $group[$index]['weight'], $enabledIndices),
            self::WEIGHT_TOTAL - $weight,
        );
        foreach ($enabledIndices as $offset => $index) {
            $group[$index]['weight'] = $scaled[$offset];
        }
        $group[] = ['media_asset_id' => $mediaAssetId, 'weight' => $weight, 'enabled' => true];

        return $this->normalizeManualGroup($group);
    }

    /**
     * One checked commit for the complete draft, including reorder, enable,
     * removal and additions. Reject stale editors rather than overwriting a
     * different saved group or source mode.
     *
     * @param  list<array<string,mixed>>  $expected
     * @param  list<array<string,mixed>>  $draft
     */
    public function commitManualGroup(
        HomePresentationSetting $settings,
        string $expectedSource,
        array $expected,
        array $draft,
    ): bool {
        return DB::transaction(function () use ($settings, $expectedSource, $expected, $draft): bool {
            /** @var HomePresentationSetting $fresh */
            $fresh = HomePresentationSetting::query()
                ->whereKey($settings->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $current = $this->configuration($fresh);
            if ($fresh->getAttribute('template') !== HomeTemplate::Artwork->value
                || $expectedSource === 'automatic' || $current['group_source'] !== $expectedSource
                || $current['manual_group'] !== $expected) {
                throw ValidationException::withMessages([
                    'manual_group' => 'The Hero source or Manual group changed. Reopen Edit Group before saving.',
                ]);
            }

            $members = $this->normalizeManualGroup(array_values($draft));
            $existingKeys = $this->manualGroupKeys($expected);
            foreach ($members as $member) {
                if (in_array(self::memberKey($member), $existingKeys, true)) {
                    continue;
                }
                if (! isset($member['media_asset_id'])) {
                    throw ValidationException::withMessages([
                        'manual_group' => 'New group members must be selected from Storage.',
                    ]);
                }

                $asset = MediaAsset::query()->with('variants')->find($member['media_asset_id']);
                if (! $asset instanceof MediaAsset || ! HomeHeroCandidate::mediaIsEligible($asset)) {
                    throw ValidationException::withMessages([
                        'manual_group' => 'A newly selected Media File is no longer eligible. Remove it and try again.',
                    ]);
                }
            }

            $beforeKeys = $this->manualGroupKeys($expected);
            $afterKeys = $this->manualGroupKeys($members);
            $beforeSorted = $beforeKeys;
            $afterSorted = $afterKeys;
            sort($beforeSorted);
            sort($afterSorted);
            $sameMembers = $beforeSorted === $afterSorted;
            $expectedByKey = [];
            foreach ($expected as $member) {
                $expectedByKey[self::memberKey($member)] = $member;
            }
            $onlyReordered = $sameMembers && $beforeKeys !== $afterKeys;
            foreach ($members as $member) {
                if (($expectedByKey[self::memberKey($member)] ?? null) !== $member) {
                    $onlyReordered = false;
                    break;
                }
            }

            $orderAudit = $onlyReordered ? ['before' => $beforeKeys, 'after' => $afterKeys] : null;

            return $this->updateArtworkSettings($fresh, ['manual_group' => $members], $orderAudit);
        });
    }

    public function removeCandidate(HomePresentationSetting $settings, string $key): bool
    {
        $current = $this->configuration($settings->fresh());
        $group = $current['manual_group'];
        $index = array_search($key, $this->manualGroupKeys($group), true);
        if ($index === false) {
            throw ValidationException::withMessages(['candidate' => 'This candidate is not in the selected group.']);
        }
        array_splice($group, $index, 1);
        if ($group !== []) {
            $group = $this->normalizeManualGroup($group);
        }

        return $this->updateArtworkSettings($settings, ['manual_group' => $group]);
    }

    /** Enable or disable a stored member without dropping its order, media reference or weight. */
    public function setManualCandidateEnabled(HomePresentationSetting $settings, string $key, bool $enabled): bool
    {
        return DB::transaction(function () use ($settings, $key, $enabled): bool {
            /** @var HomePresentationSetting $fresh */
            $fresh = HomePresentationSetting::query()->whereKey($settings->getKey())
                ->lockForUpdate()->firstOrFail();
            $current = $this->configuration($fresh);
            if ($current['group_source'] === 'automatic') {
                throw ValidationException::withMessages(['candidate' => 'Manual candidates belong to Manual or Semi-Automatic.']);
            }
            $group = $current['manual_group'];
            $index = array_search($key, $this->manualGroupKeys($group), true);
            if ($index === false) {
                throw ValidationException::withMessages(['candidate' => 'This candidate is no longer in the Manual group.']);
            }
            if ($group[$index]['enabled'] === $enabled) {
                return false;
            }
            $group[$index]['enabled'] = $enabled;

            return $this->updateArtworkSettings($fresh, ['manual_group' => $group]);
        });
    }

    /** @param list<string> $keys */
    public function reorderManualGroup(HomePresentationSetting $settings, array $keys): bool
    {
        $current = $this->configuration($settings->fresh());
        $group = $current['manual_group'];
        $expected = $this->manualGroupKeys($group);
        if (count($keys) !== count(array_unique($keys))) {
            throw ValidationException::withMessages(['candidate' => 'Home group order contains duplicates.']);
        }

        $left = $expected;
        $right = $keys;
        sort($left);
        sort($right);
        if ($left !== $right) {
            throw ValidationException::withMessages(['candidate' => 'Home group order has changed. Reload and try again.']);
        }
        $byKey = [];
        foreach ($group as $member) {
            $byKey[self::memberKey($member)] = $member;
        }

        return $this->updateArtworkSettings(
            $settings,
            ['manual_group' => array_map(static fn (string $key): array => $byKey[$key], $keys)],
            ['before' => $expected, 'after' => $keys],
        );
    }

    public function updateCandidateWeight(HomePresentationSetting $settings, string $key, int $basisPoints): bool
    {
        if ($basisPoints < 0 || $basisPoints > self::WEIGHT_TOTAL) {
            throw ValidationException::withMessages(['weight' => 'Hero percentage must be between 0 and 100.']);
        }
        $fresh = $settings->fresh();
        $configuration = $this->configuration($fresh);
        if ($configuration['display_strategy'] !== 'random') {
            throw ValidationException::withMessages(['weight' => 'Hero chances can be changed only for Random strategy.']);
        }

        if ($configuration['group_source'] !== 'manual') {
            $resolution = app(HomeHeroResolver::class)->resolve($fresh);
            $candidates = $resolution['group']->values();
            $keys = $candidates->map(static fn (HomeHeroCandidate $candidate): string => $candidate->key())->all();
            $target = array_search($key, $keys, true);
            if ($target === false) {
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
        }

        $group = $configuration['manual_group'];
        $target = array_search($key, $this->manualGroupKeys($group), true);
        if ($target === false) {
            throw ValidationException::withMessages(['candidate' => 'The selected Hero candidate changed.']);
        }
        if (! $group[$target]['enabled']) {
            throw ValidationException::withMessages(['weight' => 'Enable this candidate before editing its chance.']);
        }
        $indices = [];
        $other = [];
        foreach ($group as $index => $member) {
            if ($index === $target || ! $member['enabled']) {
                continue;
            }
            $indices[] = $index;
            $other[] = $member['weight'];
        }
        $scaled = $this->allocateBudget($other, self::WEIGHT_TOTAL - $basisPoints);
        foreach ($indices as $offset => $index) {
            $group[$index]['weight'] = $scaled[$offset];
        }
        $group[$target]['weight'] = $basisPoints;

        return $this->updateArtworkSettings($settings, ['manual_group' => $group]);
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

    /** @param list<array<string,int>> $group
     * @return list<string>
     */
    private function manualGroupKeys(array $group): array
    {
        return array_map(self::memberKey(...), $group);
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
            throw ValidationException::withMessages(['weight' => 'Manual Home weight budget is invalid.']);
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

    public function setAutomaticCandidateEnabled(HomePresentationSetting $settings, int $artworkId, bool $enabled): bool
    {
        if ($artworkId < 1) {
            throw ValidationException::withMessages(['candidate' => 'Select a valid Gallery Artwork.']);
        }

        return DB::transaction(function () use ($settings, $artworkId, $enabled): bool {
            /** @var HomePresentationSetting $fresh */
            $fresh = HomePresentationSetting::query()
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
                $manualKeys = $current['manual_group_enabled']
                    ? array_map(self::memberKey(...), $current['manual_group'])
                    : [];
                $active = app(HomeHeroResolver::class)->resolve($fresh)['group'];
                if (in_array($key, $manualKeys, true)
                    || ! $active->contains(static fn (HomeHeroCandidate $candidate): bool => $candidate->key() === $key)) {
                    throw ValidationException::withMessages([
                        'candidate' => 'This automatic candidate is no longer in the current Hero group.',
                    ]);
                }
                $disabled[] = $artworkId;
            }

            return $this->updateArtworkSettings($fresh, ['disabled_auto_artwork_ids' => $disabled]);
        });
    }

    /**
     * A sourced group changes as Galleries, rules or exclusions change.
     * Store explicit per-candidate chances, not a second source list; the
     * resolver rebalances the active members whenever membership changes.
     *
     * @return array<string,int>
     */
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
     * @param \Illuminate\Support\Collection<int,HomeHeroCandidate> $group
     * @param array<string,int> $stored
     * @return array<string,int>
     */
    public function sourcedGroupWeights(\Illuminate\Support\Collection $group, array $stored): array
    {
        $keys = $group->map(static fn (HomeHeroCandidate $candidate): string => $candidate->key())->values()->all();
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
