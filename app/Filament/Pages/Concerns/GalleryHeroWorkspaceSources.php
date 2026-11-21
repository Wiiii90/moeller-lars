<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Analytics\ArtistReportingService;
use App\Domain\Artwork\ArtworkDate;
use App\Domain\Artwork\PublicArtworkQuery;
use App\Domain\Content\GalleryHeroCandidate;
use App\Domain\Content\GalleryHeroConfigurationService;
use App\Domain\Content\GalleryHeroResolver;
use App\Domain\Content\GalleryTemplate;
use App\Domain\Content\SitePreviewContext;
use App\Domain\Media\MediaPreviewVariant;
use App\Filament\Pages\GalleryWorkspace;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Filament\Support\GalleryHeroSettingsDialog;
use App\Filament\Support\GalleryHeroDummyGalleryDialog;
use App\Filament\Support\ArtworkEditDialog;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\GalleryHeroSetting;
use App\Models\MediaAsset;
use App\Models\SiteSection;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Session;

trait GalleryHeroWorkspaceSources
{
    public function setAutomaticHeroCandidateEnabled(int $artworkId, bool $enabled): void
    {
        $changed = app(GalleryHeroConfigurationService::class)->setAutomaticCandidateEnabled(
            $this->settings(), $artworkId, $enabled,
        );
        if (! $changed) {
            return;
        }

        $this->reloadWorkspace();
        app(AdminNotifier::class)->notification(
            title: $enabled ? 'Automatic Hero candidate restored' : 'Automatic Hero candidate excluded',
            status: 'success',
        );
    }

    /** Stage quick edits use the same audited Hero settings owner as the Settings dialog. */
    public function updateHeroStageSetting(string $field, mixed $value): void
    {
        $configuration = app(GalleryHeroConfigurationService::class)->configuration($this->settings());
        if ($field === 'group_source') {
            if (! in_array($value, ['automatic', 'semi_automatic', 'manual'], true)) {
                throw ValidationException::withMessages(['group_source' => 'Choose a valid Hero source.']);
            }
        } elseif ($field === 'display_strategy') {
            if (! in_array($value, ['ordered', 'random', 'sequential'], true)) {
                throw ValidationException::withMessages(['display_strategy' => 'Choose a valid Hero strategy.']);
            }
        } elseif ($field === 'selection_mode') {
            if (! is_string($value) || ! array_key_exists($value, GalleryHeroConfigurationService::SELECTION_MODES)) {
                throw ValidationException::withMessages(['selection_mode' => 'Choose a valid Hero selection mode.']);
            }
        } elseif ($field === 'source_rule') {
            if (! is_string($value) || ! array_key_exists($value, GalleryHeroConfigurationService::SOURCE_RULES)) {
                throw ValidationException::withMessages(['source_rule' => 'Choose a valid Gallery source rule.']);
            }
        } elseif ($field === 'group_size') {
            $size = filter_var($value, FILTER_VALIDATE_INT);
            if ($size === false || $size < 1 || $size > GalleryHeroConfigurationService::MAX_GROUP_SIZE) {
                throw ValidationException::withMessages(['group_size' => 'Source Size must be between 1 and 50.']);
            }
            $value = $size;
        } else {
            throw ValidationException::withMessages(['hero' => 'Unsupported Hero Stage setting.']);
        }

        $changed = app(GalleryHeroConfigurationService::class)->updateArtworkSettings(
            $this->settings(), [$field => $value],
        );
        if (! $changed) {
            return;
        }
        if ($field === 'group_source') {
            $this->sourcePage = 1;
            $this->expandedSourceIds = [];
            $this->clearSourceSelection();
        }
        $this->reloadWorkspace();
        app(AdminNotifier::class)->notification(title: 'Hero settings saved', status: 'success');
    }

    public function moveHeroSource(int $sourceId, string $direction): void
    {
        if (! str_starts_with($this->heroSourceRule, 'gallery_')) {
            return;
        }
        if ($this->sourceSearch !== '') {
            return;
        }
        $sources = collect($this->sourceRows(true)->items())->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $changed = app(GalleryHeroConfigurationService::class)->moveSource(
            $this->settings(), $sourceId, $direction, $sources,
        );
        if ($changed) {
            $this->reloadWorkspace();
            app(AdminNotifier::class)->notification(title: 'Hero Gallery priority updated', status: 'success');
        }
    }

    public function setHeroPercentage(string $key, mixed $percentage): void
    {
        if (! is_numeric($percentage) || (float) $percentage < 0 || (float) $percentage > 100) {
            throw ValidationException::withMessages(['weight' => 'Percentage must be between 0 and 100.']);
        }
        $changed = app(GalleryHeroConfigurationService::class)->updateCandidateWeight(
            $this->settings(), $key, (int) round(((float) $percentage) * 100),
        );
        $this->reloadWorkspace();
        if ($changed) {
            app(AdminNotifier::class)->notification('Hero candidate percentage updated', status: 'success');
        }
    }


    public function toggleGalleryEligibility(int $galleryId): void
    {
        $this->setRegularHeroGalleryEnabled($galleryId, ! in_array($galleryId, $this->enabledGalleryIds, true));
    }

    /** One mutation owner for the source row and its Hero-only Edit dialog. */
    public function setRegularHeroGalleryEnabled(int $galleryId, bool $enabled): void
    {
        ArtworkCategory::query()->whereHas('siteSection')->findOrFail($galleryId);
        $changed = app(GalleryHeroConfigurationService::class)->setRegularGalleriesEnabled(
            $this->settings(), [$galleryId], $enabled,
        );
        if ($changed === 0) {
            return;
        }
        $this->clearSourceSelection();
        $this->reloadWorkspace();
        app(AdminNotifier::class)->notification(title: 'Hero Gallery source updated', status: 'success');
    }

    /** One selection key per source membership, even for reused Artwork IDs. */
    public static function sourceCandidateSelectionId(int $sourceId, string $candidateKey): string
    {
        return $sourceId.'|'.$candidateKey;
    }

    /** Return exactly the selectable rows rendered on the current source page. */
    public function visibleSourceSelectionTargets(): array
    {
        $targets = [];
        foreach ($this->sourceRows()->items() as $row) {
            $groupId = (int) $row['id'];
            $targets[] = $groupId;
            if (in_array($groupId, $this->expandedSourceIds, true)) {
                foreach ($row['candidates'] as $candidate) {
                    $targets[] = self::sourceCandidateSelectionId($groupId, $candidate['id']);
                }
            }
        }

        return $targets;
    }

    public function toggleSourceExpansion(int $groupId): void
    {
        $source = collect($this->sourceRows()->items())
            ->first(static fn (array $row): bool => (int) $row['id'] === $groupId);
        if (! is_array($source)) {
            return;
        }

        $expanded = in_array($groupId, $this->expandedSourceIds, true);
        $keys = array_map(
            static fn (array $candidate): string => self::sourceCandidateSelectionId($groupId, $candidate['id']),
            $source['candidates'],
        );
        if ($expanded) {
            // Collapsing hides candidate rows, not the Gallery's own selection.
            $this->selectedSourceIds = array_values(array_filter(
                $this->selectedSourceIds,
                static fn (int|string $value): bool => ! in_array((string) $value, $keys, true),
            ));
        }
        $this->expandedSourceIds = $expanded
            ? array_values(array_diff($this->expandedSourceIds, [$groupId]))
            : [...$this->expandedSourceIds, $groupId];
    }

    public function toggleVisibleSourceSelection(): void
    {
        $targets = $this->visibleSourceSelectionTargets();
        if ($targets === []) {
            return;
        }

        $selected = array_values(array_unique(array_map('strval', $this->selectedSourceIds)));
        $visible = array_map('strval', $targets);
        $allSelected = count(array_intersect($selected, $visible)) === count($visible);
        if ($allSelected) {
            $this->selectedSourceIds = array_values(array_filter(
                $this->selectedSourceIds,
                static fn (int|string $id): bool => ! in_array((string) $id, $visible, true),
            ));

            return;
        }

        foreach ($targets as $target) {
            if (! in_array((string) $target, $selected, true)) {
                $this->selectedSourceIds[] = $target;
            }
        }
    }

    /** Split one selected row set by semantic identity; never cast candidates to 0. */
    private function selectedSourceTargets(): array
    {
        $groups = [];
        $candidateKeys = [];
        foreach ($this->selectedSourceIds as $value) {
            if (is_int($value) || (is_string($value) && preg_match('/^-?[0-9]+$/D', $value))) {
                $groups[] = (int) $value;
            } elseif (is_string($value)
                && preg_match('/^-?[1-9][0-9]*\\|(artwork|media):[1-9][0-9]*$/D', $value)) {
                $candidateKeys[] = $value;
            }
        }

        return [array_values(array_unique($groups)), array_values(array_unique($candidateKeys))];
    }

    /** @return array{0:int,1:string} */
    private static function selectedSourceMember(string $selection): array
    {
        [$sourceId, $candidateKey] = explode('|', $selection, 2);
        return [(int) $sourceId, $candidateKey];
    }

    /** Selection is independent from whichever source-table page is visible. */
    public function selectedSourceCapabilities(): array
    {
        [$groups, $keys] = $this->selectedSourceTargets();
        $regularIds = array_values(array_filter($groups, static fn (int $id): bool => $id > 0));
        $regular = ArtworkCategory::query()->whereKey($regularIds)->with('siteSection')->get();
        $dummy = collect($this->dummyGalleries)->keyBy('id');

        $enable = $regular->contains(fn (ArtworkCategory $gallery): bool =>
            ! in_array((int) $gallery->getKey(), $this->enabledGalleryIds, true)
            && $gallery->getRelationValue('siteSection')?->getAttribute('state') === 'published');
        $disable = $regular->contains(fn (ArtworkCategory $gallery): bool =>
            in_array((int) $gallery->getKey(), $this->enabledGalleryIds, true));
        $remove = false;
        foreach ($groups as $id) {
            if ($id >= 0 || ! $dummy->has($id)) {
                continue;
            }
            $group = $dummy->get($id);
            $enable = $enable || ! $group['enabled'];
            $disable = $disable || $group['enabled'];
            $remove = true;
        }

        $activeRegular = collect([...$this->heroRailRows, ...$this->excludedHeroRows])
            ->filter(static fn (array $row): bool => $row['is_automatic'] ?? false)
            ->keyBy(static fn (array $row): string =>
                self::sourceCandidateSelectionId((int) ($row['gallery_id'] ?? 0), $row['id']));

        foreach ($keys as $selection) {
            [$sourceId, $candidateKey] = self::selectedSourceMember($selection);
            if ($sourceId > 0) {
                $candidate = $activeRegular->get($selection);
                if (! is_array($candidate)) {
                    continue;
                }
                $enable = $enable || ($candidate['disabled'] ?? false);
                $disable = $disable || ! ($candidate['disabled'] ?? false);
                continue;
            }
            $gallery = $dummy->get($sourceId);
            if (! is_array($gallery)) {
                continue;
            }
            foreach ($gallery['members'] as $member) {
                if (GalleryHeroConfigurationService::memberKey($member) !== $candidateKey) {
                    continue;
                }
                $enable = $enable || ! $member['enabled'];
                $disable = $disable || $member['enabled'];
                $remove = true;
                break;
            }
        }

        return ['enable' => $enable, 'disable' => $disable, 'remove' => $remove];
    }

    public function setSelectedGalleryEligibility(bool $enabled): void
    {
        [$groups, $keys] = $this->selectedSourceTargets();
        if ($groups === [] && $keys === []) {
            return;
        }

        $service = app(GalleryHeroConfigurationService::class);
        $changed = DB::transaction(function () use ($groups, $keys, $enabled, $service): int {
            $count = 0;

            // Candidate state changes first: disabling a whole Gallery must
            // not invalidate a candidate selection in the same transaction.
            foreach ($keys as $selection) {
                [$sourceId, $key] = self::selectedSourceMember($selection);
                if ($sourceId < 0) {
                    $count += (int) $service->setDummyMemberEnabled(
                        $this->settings(), $sourceId, $key, $enabled,
                    );
                } elseif ($sourceId > 0 && str_starts_with($key, 'artwork:')) {
                    $count += (int) $service->setAutomaticCandidateEnabled(
                        $this->settings(), (int) substr($key, 8), $enabled,
                    );
                }
            }

            foreach ($groups as $id) {
                if ($id < 0) {
                    $count += (int) $service->updateDummyGallery(
                        $this->settings(), $id, ['enabled' => $enabled],
                    );
                }
            }

            $regularIds = array_values(array_filter($groups, static fn (int $id): bool => $id > 0));
            if ($regularIds !== []) {
                $count += $service->setRegularGalleriesEnabled($this->settings(), $regularIds, $enabled);
            }

            return $count;
        });

        $this->clearSourceSelection();
        $this->reloadWorkspace();
        if ($changed > 0) {
            app(AdminNotifier::class)->notification(
                title: $enabled ? 'Selected Hero sources enabled' : 'Selected Hero sources disabled',
                status: 'success',
            );
        }
    }

    public function removeSelectedDummyMembersAction(): Action
    {
        return AdminDialog::confirm(
            Action::make('removeSelectedDummyMembers')
                ->label('Remove')
                ->action(function (): void {
                    [$groups, $keys] = $this->selectedSourceTargets();
                    $service = app(GalleryHeroConfigurationService::class);
                    $removed = DB::transaction(function () use ($groups, $keys, $service): int {
                        $count = 0;
                        $deleted = array_fill_keys(array_filter(
                            $groups, static fn (int $id): bool => $id < 0,
                        ), true);
                        foreach (array_keys($deleted) as $id) {
                            $count += (int) $service->removeDummyGallery($this->settings(), (int) $id);
                        }
                        foreach ($keys as $selection) {
                            [$sourceId, $key] = self::selectedSourceMember($selection);
                            if ($sourceId >= 0 || isset($deleted[$sourceId])) {
                                continue;
                            }
                            $count += (int) $service->removeDummyMember($this->settings(), $sourceId, $key);
                        }
                        return $count;
                    });
                    $this->clearSourceSelection();
                    $this->reloadWorkspace();
                    if ($removed > 0) {
                        app(AdminNotifier::class)->notification(
                            title: 'Selected Dummy Gallery memberships removed',
                            status: 'success',
                        );
                    }
                }),
            'Remove selected Dummy Gallery sources or memberships?',
            'Referenced Artworks and Storage files remain intact.',
            submitLabel: 'Remove',
            danger: true,
            condition: fn (): bool => $this->selectedSourceCapabilities()['remove'],
        );
    }

    /**
     * Resolve source identity/order before page hydration. A paginated source
     * table must not load every member of every Dummy Gallery on each render.
     * Candidate search uses one bounded label lookup per member kind.
     */
    public function sourceRows(bool $all = false): LengthAwarePaginator
    {
        $config = app(GalleryHeroConfigurationService::class)->configuration($this->settings());
        $regularCandidates = collect([...$this->heroRailRows, ...$this->excludedHeroRows])
            ->filter(static fn (array $candidate): bool => $candidate['is_automatic'] ?? false)
            ->unique('id')
            ->groupBy(static fn (array $candidate): int => (int) ($candidate['gallery_id'] ?? 0));
        $rows = [];
        $dummyById = [];

        if ($this->heroGroupSource !== 'manual') {
            $galleries = ArtworkCategory::query()->whereHas('siteSection')
                ->with('siteSection')->orderBy('name')->get();
            foreach ($galleries as $gallery) {
                $id = (int) $gallery->getKey();
                $section = $gallery->getRelationValue('siteSection');
                $rows[] = [
                    'id' => $id,
                    'standalone' => false,
                    'name' => (string) $gallery->getAttribute('name'),
                    'preference_enabled' => in_array((int) $gallery->getKey(), $this->enabledGalleryIds, true),
                    'can_enable' => $section?->getAttribute('state') === 'published',
                    'candidate_count' => $regularCandidates->get($id, collect())->count(),
                    'workspace_url' => GalleryWorkspace::getUrl(['gallery' => $id]),
                    'candidates' => [],
                ];
            }
        }

        if ($this->heroGroupSource !== 'automatic') {
            foreach ($config['dummy_galleries'] as $dummy) {
                $id = (int) $dummy['id'];
                $dummyById[$id] = $dummy;
                $rows[] = [
                    'id' => $id,
                    'standalone' => true,
                    'name' => (string) $dummy['name'],
                    'preference_enabled' => (bool) $dummy['enabled'],
                    'can_enable' => true,
                    'candidate_count' => count($dummy['members']),
                    'workspace_url' => null,
                    'candidates' => [],
                ];
            }
        }

        $savedOrder = array_flip($config['source_order']);
        usort($rows, static fn (array $a, array $b): int =>
            (($savedOrder[$a['id']] ?? PHP_INT_MAX) <=> ($savedOrder[$b['id']] ?? PHP_INT_MAX))
            ?: strcmp(mb_strtolower($a['name']), mb_strtolower($b['name']))
            ?: ($a['id'] <=> $b['id']));

        $search = trim($this->sourceSearch);
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $artworkIds = [];
            $mediaIds = [];
            foreach ($dummyById as $dummy) {
                foreach ($dummy['members'] as $member) {
                    if (isset($member['artwork_id'])) {
                        $artworkIds[] = (int) $member['artwork_id'];
                    } elseif (isset($member['media_asset_id'])
                        && trim((string) ($member['title'] ?? '')) === '') {
                        $mediaIds[] = (int) $member['media_asset_id'];
                    }
                }
            }
            $matchedArtworks = $artworkIds === [] ? [] : array_fill_keys(
                Artwork::query()->whereIn('id', array_values(array_unique($artworkIds)))
                    ->whereRaw('LOWER(title) LIKE ?', ['%'.$needle.'%'])
                    ->pluck('id')->all(), true,
            );
            $matchedMedia = $mediaIds === [] ? [] : array_fill_keys(
                MediaAsset::query()->whereIn('id', array_values(array_unique($mediaIds)))
                    ->whereRaw('LOWER(original_filename) LIKE ?', ['%'.$needle.'%'])
                    ->pluck('id')->all(), true,
            );
            $rows = array_values(array_filter($rows, static function (array $row) use (
                $needle, $dummyById, $regularCandidates, $matchedArtworks, $matchedMedia,
            ): bool {
                if (str_contains(mb_strtolower($row['name']), $needle)) {
                    return true;
                }
                $id = (int) $row['id'];
                if ($id > 0) {
                    return $regularCandidates->get($id, collect())->contains(
                        static fn (array $candidate): bool =>
                            str_contains(mb_strtolower($candidate['title']), $needle),
                    );
                }
                foreach ($dummyById[$id]['members'] as $member) {
                    if (isset($member['artwork_id']) && isset($matchedArtworks[(int) $member['artwork_id']])) {
                        return true;
                    }
                    if (! isset($member['media_asset_id'])) {
                        continue;
                    }
                    $title = trim((string) ($member['title'] ?? ''));
                    if ($title !== '' && str_contains(mb_strtolower($title), $needle)) {
                        return true;
                    }
                    if ($title === '' && isset($matchedMedia[(int) $member['media_asset_id']])) {
                        return true;
                    }
                }
                return false;
            }));
        }

        $total = count($rows);
        $perPage = $all ? max(1, $total)
            : (in_array($this->sourcePerPage, [10, 25], true) ? $this->sourcePerPage : 10);
        $page = $all ? 1 : max(1, min($this->sourcePage, max(1, (int) ceil($total / $perPage))));
        $visible = array_slice($rows, ($page - 1) * $perPage, $perPage);

        if (! $all) {
            $activeDummy = collect($this->heroRailRows)
                ->filter(static fn (array $candidate): bool => $candidate['is_manual'] ?? false)
                ->keyBy(static fn (array $candidate): string =>
                    (int) $candidate['dummy_gallery_id'].'|'.$candidate['id']);
            foreach ($visible as &$row) {
                $id = (int) $row['id'];
                if (! $row['standalone']) {
                    $row['candidates'] = $regularCandidates->get($id, collect())->values()->all();
                    continue;
                }
                $candidates = $this->dummyEditorMembers($id, false);
                foreach ($candidates as &$candidate) {
                    $active = $activeDummy->get($id.'|'.$candidate['id']);
                    $candidate['is_manual'] = true;
                    $candidate['is_automatic'] = false;
                    $candidate['dummy_gallery_id'] = $id;
                    $candidate['eligible'] = $active !== null;
                    $candidate['weight'] = $active['weight'] ?? 0;
                    $candidate['percentage'] = $active['percentage'] ?? '0.00';
                }
                unset($candidate);
                $row['candidates'] = $candidates;
            }
            unset($row);
        }

        return new LengthAwarePaginator(
            $visible, $total, $perPage, $page, ['path' => request()->url(), 'pageName' => 'sourcePage'],
        );
    }
}
