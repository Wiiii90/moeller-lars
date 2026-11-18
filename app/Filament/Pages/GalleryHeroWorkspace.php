<?php

namespace App\Filament\Pages;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Analytics\ArtistReportingService;
use App\Domain\Artwork\ArtworkDate;
use App\Domain\Artwork\GalleryEditorialService;
use App\Domain\Artwork\PublicArtworkQuery;
use App\Domain\Content\GalleryHeroCandidate;
use App\Domain\Content\GalleryHeroConfigurationService;
use App\Domain\Content\GalleryHeroResolver;
use App\Domain\Content\GalleryTemplate;
use App\Domain\Content\SitePreviewContext;
use App\Domain\Media\MediaPreviewVariant;
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
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Session;

final class GalleryHeroWorkspace extends Page
{
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug = 'pages/gallery/hero/{section}';
    protected static ?string $title = 'Hero Artworks';
    protected string $view = 'filament.pages.gallery-hero-workspace';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canAccessPanel(Filament::getCurrentPanel());
    }

    #[Locked]
    public int $sectionId = 0;
    #[Locked]
    public int $settingsId = 0;

    public string $pageTitle = 'Hero Artworks';
    public string $previewUrl = '';
    /** @var list<array{role:string,label:string,value:string,description:string}> */
    public array $metrics = [];
    public bool $artworkShowDetails = true;
    public bool $artworkShowGalleryLink = false;
    public bool $artworkShowNavigation = false;
    public bool $artworkEnableViewer = true;
    public string $heroGroupSource = 'automatic';
    /** @var list<int> */
    public array $disabledAutoHeroIds = [];
    /** @var list<array<string,mixed>> */
    public array $excludedHeroRows = [];
    public string $heroDisplayStrategy = 'ordered';
    public string $heroSourceRule = 'newest_artwork';
    public string $heroSelectionMode = 'global';
    public int $heroGroupSize = GalleryHeroConfigurationService::DEFAULT_GROUP_SIZE;
    /** @var list<array<string,mixed>> */
    public array $dummyGalleries = [];
    /** @var list<array<string,mixed>> */
    public array $draftDummyMembers = [];
    public int $rotationIntervalCount = 1;
    public string $rotationIntervalUnit = 'weeks';
    public ?string $rotationStartedAt = null;
    public ?string $nextRotationAt = null;
    /** @var array<string,mixed>|null */
    public ?array $currentArtwork = null;
    /** @var list<array<string,mixed>> */
    public array $heroRailRows = [];
    public int $candidatePoolCount = 0;
    public ?string $selectionIssue = null;
    public int $eligibleArtworkCount = 0;
    public int $sourceGalleryCount = 0;
    public ?int $newestEligibleYear = null;
    public string $sourceSearch = '';
    public int $sourcePage = 1;
    #[Session]
    public int $sourcePerPage = 10;
    /** @var list<int|string> */
    public array $selectedSourceIds = [];
    /** @var list<int> */
    public array $expandedSourceIds = [];
    public bool $homeAnalyticsLoaded = false;
    public string $homeAnalyticsStatus = 'loading';
    public ?float $homeVisits = null;
    public ?float $homeViews = null;

    public function getTitle(): string
    {
        return $this->pageTitle;
    }

    public function mount(int|string $section): void
    {
        /** @var SiteSection $page */
        $page = SiteSection::query()->findOrFail((int) $section);
        $settings = GalleryHeroSetting::forSection($page);
        $this->sectionId = (int) $page->getKey();
        $this->settingsId = (int) $settings->getKey();
        $this->pageTitle = (string) $page->getAttribute('title');
        $this->previewUrl = app(SitePreviewContext::class)->previewUrlFor($page) ?? '';
        $this->reloadWorkspace();
    }

    public function loadHomeAnalytics(): void
    {
        if ($this->homeAnalyticsLoaded) {
            return;
        }
        $this->homeAnalyticsLoaded = true;
        /** @var SiteSection $section */
        $section = SiteSection::query()->findOrFail($this->sectionId);
        $path = app(\App\Routing\SiteNodeRoute::class)->path($section) ?? '/';
        $report = app(ArtistReportingService::class)->customPage($path, '30d');
        $this->homeAnalyticsStatus = (string) ($report['status'] ?? 'unavailable');
        $this->homeVisits = $this->analyticsMetricValue($report['page']['visits'] ?? null);
        $this->homeViews = $this->analyticsMetricValue($report['page']['views'] ?? null);
        $this->refreshMetrics();
    }

    public function updatedSourceSearch(): void
    {
        $this->sourcePage = 1;
        $this->clearSourceSelection();
    }

    public function updatedSourcePerPage(): void
    {
        if (! in_array($this->sourcePerPage, [10, 25], true)) {
            $this->sourcePerPage = 10;
        }
        $this->sourcePage = 1;
        $this->clearSourceSelection();
    }

    public function resetSourceFilters(): void
    {
        $this->sourceSearch = '';
        $this->sourcePage = 1;
        $this->clearSourceSelection();
    }

    public function goToSourcePage(int $page): void
    {
        $this->sourcePage = max(1, $page);
        $this->clearSourceSelection();
    }


    public function settingsAction(): Action
    {
        $dialog = app(GalleryHeroSettingsDialog::class);
        $action = Action::make('settings')
            ->label('Settings')
            ->modalHeading('Hero settings')
            ->fillForm(fn (): array => $dialog->fill($this->settings()))
            ->schema(fn (): array => $dialog->schema(
                $this->settings(),
                fn (string $field, bool $value) => $this->persistHeroDisplayToggle($field, $value),
            ))
            ->action(function (array $data) use ($dialog): void {
                $previousSource = $this->heroGroupSource;
                $changed = $dialog->save($this->settings(), $data);
                if (($data['group_source'] ?? $previousSource) !== $previousSource) {
                    $this->sourcePage = 1;
                    $this->expandedSourceIds = [];
                    $this->clearSourceSelection();
                }
                $this->reloadWorkspace();

                if ($changed) {
                    app(AdminNotifier::class)->notification(
                        title: 'Hero settings saved',
                        status: 'success',
                    );
                }
            });

        return AdminDialog::edit($action, AdminDialogSize::Large);
    }

    private function persistHeroDisplayToggle(string $field, bool $value): void
    {
        if (! in_array($field, [
            'show_details', 'show_gallery_link', 'show_navigation', 'enable_viewer',
            'video_sound_enabled', 'show_media_credits', 'show_media_copyright',
            'landscape_detail_enabled', 'portrait_detail_enabled', 'artwork_menu_surface_wide',
        ], true)) {
            throw new \InvalidArgumentException('Unsupported Hero display setting.');
        }

        // One existing audited mutation owner; no parallel Livewire/DB writer.
        $changed = app(GalleryHeroConfigurationService::class)->updateArtworkSettings(
            $this->settings(), [$field => $value],
        );
        if (! $changed) {
            return;
        }

        $this->reloadWorkspace();
        app(AdminNotifier::class)->notification(
            title: 'Hero settings saved',
            status: 'success',
        );
    }

    /** The Create dialog stages its gallery name and members in one commit. */
    public function addDummyGalleryAction(): Action
    {
        return AdminDialog::create(
            Action::make('addDummyGallery')
                ->label('Add Dummy Gallery')
                ->modalHeading('Add Dummy Gallery')
                ->fillForm(function (): array {
                    $this->draftDummyMembers = [];
                    return ['name' => '', 'pending_candidate' => null];
                })
                ->schema(fn (): array => app(GalleryHeroDummyGalleryDialog::class)->schema(0, true))
                ->action(function (array $data, Action $action): void {
                    $created = app(GalleryHeroConfigurationService::class)->createDummyGallery(
                        $this->settings(), (string) ($data['name'] ?? ''), $this->draftDummyMembers,
                    );
                    if ($created === null) {
                        $action->halt();
                        return;
                    }
                    $this->draftDummyMembers = [];
                    $this->reloadWorkspace();
                    app(AdminNotifier::class)->notification(title: 'Dummy Gallery added', status: 'success');
                }),
            'Add Dummy Gallery',
            AdminDialogSize::Large,
            onClear: fn (self $livewire) => $livewire->clearDraftDummyGallery(),
        );
    }

    public function clearDraftDummyGallery(): void
    {
        $this->draftDummyMembers = [];
    }

    /**
     * The source-table Edit button edits the Artwork candidate, never follows
     * the Gallery link (which already lives on the Gallery title). Use the
     * canonical Artwork dialog and editorial service; Home owns only refresh.
     */
    public function editHeroArtworkAction(): Action
    {
        $dialog = app(ArtworkEditDialog::class);

        return AdminDialog::edit(
            Action::make('editHeroArtwork')
                ->label('Edit Artwork')
                ->modalHeading('Edit Artwork')
                ->fillForm(fn (array $arguments): array => $dialog->fill($this->heroArtworkForAction($arguments)))
                ->schema(fn (array $arguments): array => $dialog->schema(
                    autosave: true,
                    primaryReadOnly: $this->heroArtworkForAction($arguments)->getAttribute('state') === 'published',
                    onPrimaryMediaChanged: function (?int $assetId) use ($dialog, $arguments): void {
                        if (! $dialog->changePrimaryMedia($this->heroArtworkForAction($arguments), $assetId)) {
                            return;
                        }
                        $this->reloadWorkspace();
                        app(AdminNotifier::class)->notification(title: 'Artwork primary media saved', status: 'success');
                    },
                    onInformationChanged: function () use ($dialog, $arguments): void {
                        // Native Repeater movement does not emit the ordinary
                        // Admin Edit change event. Persist via the same owner.
                        $state = $this->getMountedActionSchema()->getRawState();
                        if (! is_array($state) || ! $dialog->save(
                            $this->heroArtworkForAction($arguments), $state, savePrimaryMedia: false,
                        )) {
                            return;
                        }
                        $this->reloadWorkspace();
                        app(AdminNotifier::class)->notification(title: 'Artwork information saved', status: 'success');
                    },
                ))
                ->action(function (array $arguments, array $data) use ($dialog): void {
                    if (! $dialog->save($this->heroArtworkForAction($arguments), $data, savePrimaryMedia: false)) {
                        return;
                    }
                    $this->reloadWorkspace();
                    app(AdminNotifier::class)->notification(title: 'Artwork saved', status: 'success');
                }),
            AdminDialogSize::Large,
        );
    }

    private function heroArtworkForAction(array $arguments): Artwork
    {
        $id = (int) ($arguments['artwork'] ?? 0);
        if ($id <= 0) {
            throw ValidationException::withMessages(['artwork' => 'Choose an existing Artwork.']);
        }

        /** @var Artwork $artwork */
        $artwork = Artwork::query()->with('category')->findOrFail($id);

        return $artwork;
    }

    /** Editing an existing Dummy Gallery autosaves immediately. */
    public function editDummyGalleryAction(): Action
    {
        return AdminDialog::edit(
            Action::make('editDummyGallery')
                ->label('Edit Dummy Gallery')
                ->modalHeading('Edit Dummy Gallery')
                ->fillForm(fn (array $arguments): array => [
                    'name' => $this->dummyGallery((int) ($arguments['gallery'] ?? 0))['name'],
                    'pending_candidate' => null,
                ])
                ->schema(fn (array $arguments): array => app(GalleryHeroDummyGalleryDialog::class)
                    ->schema((int) ($arguments['gallery'] ?? 0), false))
                ->action(static function (): void {}),
            AdminDialogSize::Large,
        );
    }

    public function renameDummyGallery(int $galleryId, string $name): void
    {
        $changed = app(GalleryHeroConfigurationService::class)->updateDummyGallery(
            $this->settings(), $galleryId, ['name' => $name],
        );
        if ($changed) {
            $this->reloadWorkspace();
            app(AdminNotifier::class)->notification(title: 'Dummy Gallery renamed', status: 'success');
        }
    }

    public function deleteDummyGalleryAction(): Action
    {
        return AdminDialog::confirm(
            Action::make('deleteDummyGallery')
                ->label('Delete Dummy Gallery')
                ->action(function (array $arguments): void {
                    $changed = app(GalleryHeroConfigurationService::class)->removeDummyGallery(
                        $this->settings(), (int) ($arguments['gallery'] ?? 0),
                    );
                    if ($changed) {
                        $this->clearSourceSelection();
                        $this->reloadWorkspace();
                        app(AdminNotifier::class)->notification(title: 'Dummy Gallery deleted', status: 'success');
                    }
                }),
            'Delete Dummy Gallery and its Hero memberships?',
            'The referenced Artworks and Storage files remain intact.',
            submitLabel: 'Delete',
            danger: true,
        );
    }

    private function dummyGallery(int $galleryId): array
    {
        foreach (app(GalleryHeroConfigurationService::class)->configuration($this->settings())['dummy_galleries'] as $gallery) {
            if ($gallery['id'] === $galleryId) {
                return $gallery;
            }
        }
        throw ValidationException::withMessages(['dummy_galleries' => 'Dummy Gallery no longer exists.']);
    }

    /** @return list<array<string,mixed>> */
    public function dummyEditorMembers(int $galleryId, bool $creating): array
    {
        $gallery = $creating
            ? ['id' => 0, 'name' => 'New Dummy Gallery', 'members' => $this->draftDummyMembers]
            : $this->dummyGallery($galleryId);
        $available = app(GalleryHeroResolver::class)->dummyCandidates(
            $gallery['members'], (int) $gallery['id'], (string) $gallery['name'],
        )->keyBy(static fn (GalleryHeroCandidate $candidate): string => $candidate->key());
        $rows = [];
        foreach ($gallery['members'] as $member) {
            $key = GalleryHeroConfigurationService::memberKey($member);
            $candidate = $available->get($key);
            $row = $candidate instanceof GalleryHeroCandidate ? $this->candidateRow($candidate) : [
                'id' => $key, 'title' => 'Unavailable '.$key, 'thumbnail_url' => null,
                'gallery' => $gallery['name'], 'gallery_id' => $galleryId, 'date' => null,
            ];
            $artwork = $candidate?->artwork();
            $row['year'] = $artwork?->getAttribute('year') ?? ($member['year'] ?? null);
            $row['month'] = $artwork?->getAttribute('month') ?? ($member['month'] ?? null);
            $row['day'] = $artwork?->getAttribute('day') ?? ($member['day'] ?? null);
            $row['disabled'] = ! ($member['enabled'] ?? true);
            $rows[] = $row;
        }
        return $rows;
    }

    public function addDraftDummyMedia(int $mediaAssetId): void
    {
        $asset = MediaAsset::query()->with('variants')->find($mediaAssetId);
        if (! $asset instanceof MediaAsset || ! GalleryHeroCandidate::mediaIsEligible($asset)) {
            throw ValidationException::withMessages(['pending_candidate' => 'Choose an eligible Storage file.']);
        }
        $key = 'media:'.$mediaAssetId;
        if (collect($this->draftDummyMembers)->contains(
            static fn (array $member): bool => GalleryHeroConfigurationService::memberKey($member) === $key,
        )) {
            throw ValidationException::withMessages(['pending_candidate' => 'Already added to this Dummy Gallery.']);
        }
        $this->draftDummyMembers[] = [
            'media_asset_id' => $mediaAssetId, 'enabled' => true,
            'title' => (string) $asset->getAttribute('original_filename'),
            'year' => null, 'month' => null, 'day' => null,
        ];
    }

    public function addDraftDummyArtwork(int $artworkId): void
    {
        if (app(PublicArtworkQuery::class)->homeCandidatesByIds([$artworkId])->isEmpty()) {
            throw ValidationException::withMessages(['pending_candidate' => 'Choose an eligible Artwork.']);
        }
        $key = 'artwork:'.$artworkId;
        if (collect($this->draftDummyMembers)->contains(
            static fn (array $member): bool => GalleryHeroConfigurationService::memberKey($member) === $key,
        )) {
            throw ValidationException::withMessages(['pending_candidate' => 'Already added to this Dummy Gallery.']);
        }
        $this->draftDummyMembers[] = ['artwork_id' => $artworkId, 'enabled' => true];
    }

    public function addDummyMediaFromStorage(int $galleryId, int $mediaAssetId): void
    {
        $changed = app(GalleryHeroConfigurationService::class)->addDummyMedia($this->settings(), $galleryId, $mediaAssetId);
        if ($changed) {
            $this->reloadWorkspace();
            app(AdminNotifier::class)->notification(title: 'Dummy Gallery member added', status: 'success');
        }
    }

    public function addDummyArtwork(int $galleryId, int $artworkId): void
    {
        $changed = app(GalleryHeroConfigurationService::class)->addDummyArtwork($this->settings(), $galleryId, $artworkId);
        if ($changed) {
            $this->reloadWorkspace();
            app(AdminNotifier::class)->notification(title: 'Dummy Gallery member added', status: 'success');
        }
    }

    public function updateDraftDummyMember(int $unused, string $key, string $field, mixed $value): void
    {
        if (! in_array($field, ['title', 'year', 'month', 'day'], true)) {
            throw ValidationException::withMessages(['candidate' => 'Unknown Dummy Gallery field.']);
        }
        foreach ($this->draftDummyMembers as &$member) {
            if (GalleryHeroConfigurationService::memberKey($member) === $key && isset($member['media_asset_id'])) {
                $member[$field] = $value === '' ? null : $value;
                return;
            }
        }
        unset($member);
    }

    public function updateDummyMember(int $galleryId, string $key, string $field, mixed $value): void
    {
        if (! in_array($field, ['title', 'year', 'month', 'day'], true)) {
            throw ValidationException::withMessages(['candidate' => 'Unknown Dummy Gallery field.']);
        }
        $changed = app(GalleryHeroConfigurationService::class)->updateDummyMember(
            $this->settings(), $galleryId, $key, [$field => $value === '' ? null : $value],
        );
        if ($changed) {
            $this->reloadWorkspace();
            app(AdminNotifier::class)->notification(title: 'Dummy Gallery member saved', status: 'success');
        }
    }

    public function toggleDraftDummyMember(int $unused, string $key): void
    {
        foreach ($this->draftDummyMembers as &$member) {
            if (GalleryHeroConfigurationService::memberKey($member) === $key) {
                $member['enabled'] = ! $member['enabled'];
                return;
            }
        }
        unset($member);
    }

    public function toggleDummyMember(int $galleryId, string $key): void
    {
        $gallery = $this->dummyGallery($galleryId);
        foreach ($gallery['members'] as $member) {
            if (GalleryHeroConfigurationService::memberKey($member) !== $key) {
                continue;
            }
            $changed = app(GalleryHeroConfigurationService::class)->setDummyMemberEnabled(
                $this->settings(), $galleryId, $key, ! $member['enabled'],
            );
            if ($changed) {
                $this->reloadWorkspace();
                app(AdminNotifier::class)->notification(title: 'Dummy Gallery member updated', status: 'success');
            }
            return;
        }
    }

    public function removeDraftDummyMember(int $unused, string $key): void
    {
        $this->draftDummyMembers = array_values(array_filter(
            $this->draftDummyMembers,
            static fn (array $member): bool => GalleryHeroConfigurationService::memberKey($member) !== $key,
        ));
    }

    public function removeDummyMember(int $galleryId, string $key): void
    {
        $changed = app(GalleryHeroConfigurationService::class)->removeDummyMember($this->settings(), $galleryId, $key);
        if ($changed) {
            $this->reloadWorkspace();
            app(AdminNotifier::class)->notification(title: 'Dummy Gallery member removed', status: 'success');
        }
    }

    public function toggleDummyGallery(int $galleryId): void
    {
        $gallery = $this->dummyGallery($galleryId);
        $changed = app(GalleryHeroConfigurationService::class)->updateDummyGallery(
            $this->settings(), $galleryId, ['enabled' => ! $gallery['enabled']],
        );
        if ($changed) {
            $this->reloadWorkspace();
            app(AdminNotifier::class)->notification(title: 'Dummy Gallery source updated', status: 'success');
        }
    }

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
        /** @var ArtworkCategory $gallery */
        $gallery = ArtworkCategory::query()->findOrFail($galleryId);
        $enable = ! (bool) $gallery->getAttribute('show_on_home');
        if ($enable && ! $gallery->siteSection()->where('state', 'published')->exists()) {
            throw ValidationException::withMessages(['source' => 'Publish this Gallery before enabling it for Home.']);
        }
        $updated = app(GalleryEditorialService::class)->update($gallery, [
            'name' => (string) $gallery->getAttribute('name'),
            'description' => $gallery->getAttribute('description'),
            'show_on_home' => $enable,
        ]);
        if (! $updated->wasChanged('show_on_home')) {
            return;
        }
        $this->clearSourceSelection();
        $this->reloadWorkspace();
        app(AdminNotifier::class)->notification(
            title: 'Gallery source preference updated',
            status: 'success',
        );
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
            if (in_array($groupId, $this->expandedSourceIds, true)) {
                foreach ($row['candidates'] as $candidate) {
                    $targets[] = self::sourceCandidateSelectionId($groupId, $candidate['id']);
                }
            } else {
                $targets[] = $groupId;
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
        $this->selectedSourceIds = array_values(array_filter(
            $this->selectedSourceIds,
            static fn (int|string $value): bool => $expanded
                ? ! in_array($value, $keys, true)
                : (string) $value !== (string) $groupId,
        ));
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

        $enable = $regular->contains(static fn (ArtworkCategory $gallery): bool =>
            ! (bool) $gallery->getAttribute('show_on_home')
            && $gallery->getRelationValue('siteSection')?->getAttribute('state') === 'published');
        $disable = $regular->contains(static fn (ArtworkCategory $gallery): bool =>
            (bool) $gallery->getAttribute('show_on_home'));
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
            $galleries = ArtworkCategory::query()->whereKey($regularIds)
                ->with('siteSection')->orderBy('id')->lockForUpdate()->get();
            foreach ($galleries as $gallery) {
                if ((bool) $gallery->getAttribute('show_on_home') === $enabled
                    || ($enabled && $gallery->getRelationValue('siteSection')?->getAttribute('state') !== 'published')) {
                    continue;
                }
                $updated = app(GalleryEditorialService::class)->update($gallery, [
                    'name' => (string) $gallery->getAttribute('name'),
                    'description' => $gallery->getAttribute('description'),
                    'show_on_home' => $enabled,
                ]);
                $count += (int) $updated->wasChanged('show_on_home');
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
                    'preference_enabled' => (bool) $gallery->getAttribute('show_on_home'),
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


    private function reloadWorkspace(): void
    {
        $settings = $this->settings();
        $hero = app(GalleryHeroConfigurationService::class)->configuration($settings);
        $this->artworkShowDetails = $hero['show_details'];
        $this->artworkShowGalleryLink = $hero['show_gallery_link'];
        $this->artworkShowNavigation = $hero['show_navigation'];
        $this->artworkEnableViewer = $hero['enable_viewer'];
        $this->heroGroupSource = $hero['group_source'];
        $this->disabledAutoHeroIds = $hero['disabled_auto_artwork_ids'];
        $this->excludedHeroRows = [];
        $this->heroDisplayStrategy = $hero['display_strategy'];
        $this->heroSourceRule = $hero['source_rule'];
        $this->heroSelectionMode = $hero['selection_mode'];
        $this->heroGroupSize = $hero['group_size'];
        $this->dummyGalleries = $hero['dummy_galleries'];
        $this->rotationIntervalCount = $hero['rotation_interval']['count'];
        $this->rotationIntervalUnit = $hero['rotation_interval']['unit'];
        $this->rotationStartedAt = $hero['rotation_started_at'];
        $this->selectionIssue = null;
        $this->currentArtwork = null;
        $this->heroRailRows = [];
        $this->eligibleArtworkCount = 0;
        $this->sourceGalleryCount = 0;
        $this->newestEligibleYear = null;
        $this->candidatePoolCount = 0;
        $this->nextRotationAt = null;
        $this->loadArtworkWorkspace($settings);
        $this->refreshMetrics();
    }

    private function loadArtworkWorkspace(GalleryHeroSetting $settings): void
    {
        $publicArtworks = app(PublicArtworkQuery::class);
        $statistics = $publicArtworks->homeCandidateStatistics();
        $this->eligibleArtworkCount = $statistics['eligible'];
        $this->newestEligibleYear = $statistics['newest_year'];
        $activeDummy = $this->heroGroupSource === 'automatic' ? [] : array_values(array_filter(
            $this->dummyGalleries, static fn (array $gallery): bool => $gallery['enabled'],
        ));
        $regularCount = $this->heroGroupSource === 'manual' ? 0
            : ArtworkCategory::query()->where('show_on_home', true)
                ->whereHas('siteSection', fn (Builder $section): Builder => $section->where('state', 'published'))->count();
        $this->sourceGalleryCount = $regularCount + count($activeDummy);

        $resolution = app(GalleryHeroResolver::class)->resolve($settings);
        $this->candidatePoolCount = $resolution['group']->count();
        if ($this->heroSelectionMode === 'per_gallery') {
            $reserved = $this->disabledAutoHeroIds;
            foreach ($activeDummy as $dummy) {
                foreach ($dummy['members'] as $member) {
                    if (isset($member['artwork_id'])) {
                        $reserved[] = (int) $member['artwork_id'];
                    }
                }
            }
            $regularEligible = $this->heroGroupSource === 'manual' ? 0
                : $publicArtworks->eligibleHomeGalleryCount(array_values(array_unique($reserved)));
            $regularSlots = $this->heroGroupSource === 'manual' ? 0
                : $publicArtworks->eligibleHomeCandidateSlotsPerGallery(
                    $this->heroGroupSize, array_values(array_unique($reserved)),
                );
            $dummyPotential = 0;
            foreach ($activeDummy as $dummy) {
                $candidates = app(GalleryHeroResolver::class)->dummyCandidates(
                    array_values(array_filter($dummy['members'], static fn (array $member): bool => $member['enabled'])),
                    (int) $dummy['id'], (string) $dummy['name'],
                );
                $dummyPotential += min($candidates->count(), $this->heroGroupSize);
            }
            $potential = $dummyPotential + $regularSlots;
            if ($potential > GalleryHeroConfigurationService::MAX_GROUP_SIZE) {
                $this->selectionIssue = sprintf(
                    'Per Gallery requests up to %d candidates from %d eligible sources, exceeding the %d-candidate limit. The active group is capped at %d.',
                    $this->heroGroupSize,
                    $regularEligible + count($activeDummy),
                    GalleryHeroConfigurationService::MAX_GROUP_SIZE,
                    GalleryHeroConfigurationService::MAX_GROUP_SIZE,
                );
            }
        }
        $this->nextRotationAt = $resolution['next_rotation_at']?->toIso8601String();
        $current = $resolution['current'];
        if ($current instanceof GalleryHeroCandidate) {
            $this->currentArtwork = $this->candidateRow($current);
        } else {
            $this->selectionIssue ??= 'No eligible Hero candidate is available.';
        }

        if ($this->disabledAutoHeroIds !== []) {
            $disabled = $publicArtworks->homeCandidatesByIds($this->disabledAutoHeroIds);
            $this->excludedHeroRows = $disabled
                ->map(function (Artwork $artwork): array {
                    $row = $this->candidateRow(GalleryHeroCandidate::fromArtwork($artwork));
                    $row['eligible'] = false;
                    $row['disabled'] = true;
                    $row['is_manual'] = false;
                    $row['is_automatic'] = true;
                    $row['weight'] = 0;
                    $row['percentage'] = '0.00';
                    $row['sequence_label'] = 'Excluded';

                    return $row;
                })->values()->all();
        }

        // The sequence displayed for editing is the saved rank, not today's
        // rotation offset. Only the Current/Next labels follow rotation.
        $sequence = $resolution['group'];
        $rotationKeys = $resolution['sequence']
            ->map(static fn (GalleryHeroCandidate $candidate): string => $candidate->key())->all();
        $currentId = $current instanceof GalleryHeroCandidate ? $current->key() : null;
        $this->heroRailRows = $sequence->values()->map(function (GalleryHeroCandidate $candidate) use ($currentId, $resolution, $rotationKeys): array {
            $row = $this->candidateRow($candidate);
            $row['eligible'] = true;
            $row['is_manual'] = $candidate->sourceId !== null && $candidate->sourceId < 0;
            $row['is_automatic'] = ! $row['is_manual'];
            $row['dummy_gallery_id'] = $row['is_manual'] ? $candidate->sourceId : null;
            $weight = $resolution['weights'][$candidate->key()] ?? 0;
            $row['weight'] = $weight;
            $row['percentage'] = number_format($weight / 100, 2, '.', '');
            $rotationRank = array_search($candidate->key(), $rotationKeys, true);
            $row['sequence_label'] = $this->heroDisplayStrategy === 'sequential'
                ? ($rotationRank === 0 ? 'Current' : ($rotationRank === 1 ? 'Next' : 'Then'))
                : ($row['id'] === $currentId ? 'Current' : null);

            return $row;
        })->all();

    }


    private function refreshMetrics(): void
    {
        $analyticsDescription = $this->analyticsDescription();
        $this->metrics = [
            ['role' => 'visits', 'label' => 'Visits | 30d', 'value' => $this->formatMetric($this->homeVisits), 'description' => $analyticsDescription],
            ['role' => 'views', 'label' => 'Views | 30d', 'value' => $this->formatMetric($this->homeViews), 'description' => $analyticsDescription],
            ['role' => 'sources', 'label' => 'Source Galleries', 'value' => number_format($this->sourceGalleryCount), 'description' => 'Effective sources'],
            ['role' => 'eligible', 'label' => 'Eligible Artworks', 'value' => number_format($this->eligibleArtworkCount), 'description' => 'Eligible candidates'],
            ['role' => 'candidates', 'label' => 'Candidate Group', 'value' => number_format($this->candidatePoolCount), 'description' => 'Effective group'],
            ['role' => 'year', 'label' => 'Newest Year', 'value' => $this->newestEligibleYear === null ? '—' : (string) $this->newestEligibleYear, 'description' => 'Eligible newest'],
        ];
    }

    private function candidateRow(GalleryHeroCandidate $candidate): array
    {
        $artwork = $candidate->artwork();
        $asset = $candidate->mediaAsset();
        $gallery = $candidate->gallery();
        $asset?->loadMissing('variants');
        $preview = $asset instanceof MediaAsset ? MediaPreviewVariant::preview($asset) : null;

        return [
            'id' => $candidate->key(),
            'title' => $candidate->title(),
            'year' => $artwork?->getAttribute('year') ?? $candidate->year,
            'date' => $artwork instanceof Artwork ? ArtworkDate::display($artwork)
                : ($candidate->year === null ? null : implode('-', array_filter([
                    (string) $candidate->year,
                    $candidate->month === null ? '' : sprintf('%02d', $candidate->month),
                    $candidate->day === null ? '' : sprintf('%02d', $candidate->day),
                ], static fn (string $part): bool => $part !== ''))),
            'gallery' => $candidate->sourceName
                ?? ($gallery instanceof ArtworkCategory ? (string) $gallery->getAttribute('name') : null),
            'gallery_id' => $candidate->sourceId ?? $gallery?->getKey(),
            'thumbnail_url' => $artwork instanceof Artwork
                ? ArtworkResource::thumbnailUrl($artwork)
                : ($preview ? route('admin.media.variant', $preview) : null),
            'gallery_url' => $gallery instanceof ArtworkCategory
                ? GalleryWorkspace::getUrl(['gallery' => $gallery->getKey()])
                : null,
        ];
    }

    private function settings(): GalleryHeroSetting
    {
        /** @var GalleryHeroSetting $settings */
        $settings = GalleryHeroSetting::query()
            ->whereKey($this->settingsId)
            ->where('site_section_id', $this->sectionId)
            ->firstOrFail();
        GalleryHeroSetting::forSection($this->sectionId);

        return $settings;
    }

    private function clearSourceSelection(): void
    {
        $this->selectedSourceIds = [];
    }

    private function analyticsMetricValue(mixed $metric): ?float
    {
        if (! is_array($metric) || ($metric['state'] ?? null) !== 'available' || ! is_numeric($metric['value'] ?? null)) {
            return null;
        }

        return (float) $metric['value'];
    }

    private function formatMetric(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        return number_format($value, $value === floor($value) ? 0 : 1);
    }

    private function analyticsDescription(): string
    {
        if (! $this->homeAnalyticsLoaded) {
            return 'Loading | 30d';
        }

        return match ($this->homeAnalyticsStatus) {
            'stale' => 'Cached | 30d',
            'available' => 'Hero page | 30d',
            default => 'Unavailable | 30d',
        };
    }
}
