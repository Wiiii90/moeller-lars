<?php

namespace App\Filament\Pages;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Admin\AdminAutoPublishPreferences;
use App\Domain\Analytics\ArtistReportingService;
use App\Domain\Artwork\ArtworkDate;
use App\Domain\Artwork\GalleryEditorialService;
use App\Domain\Artwork\PublicArtworkQuery;
use App\Domain\Content\DividerVariant;
use App\Domain\Content\HomeHeroCandidate;
use App\Domain\Content\HomeHeroConfigurationService;
use App\Domain\Content\HomeHeroResolver;
use App\Domain\Content\HomePresentationEditorialService;
use App\Domain\Content\HomePresentationResolver;
use App\Domain\Content\HomeTemplate;
use App\Domain\Content\PublicLegalContent;
use App\Domain\Content\RichTextMediaReference;
use App\Domain\Content\SitePreviewContext;
use App\Domain\Content\SocialLinks;
use App\Domain\Media\MediaPreviewVariant;
use App\Filament\Pages\Concerns\HomePresentationChildren;
use App\Filament\Pages\Concerns\CustomPageWorkspaceForms;
use App\Filament\Pages\Concerns\CustomPageWorkspaceSecondaryForms;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Support\AdminRowAction;
use App\Filament\Support\ComponentEditorTypes;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Filament\Support\HomeSettingsDialog;
use App\Filament\Support\HomeDummyGalleryDialog;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\CustomPageSetting;
use App\Models\HomePresentationSetting;
use App\Models\MediaAsset;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Session;
use Livewire\WithFileUploads;

final class HomePresentation extends Page
{
    use HomePresentationChildren;
    use CustomPageWorkspaceForms;
    use CustomPageWorkspaceSecondaryForms;
    use WithFileUploads;

    private const COMPONENT_LABELS = ComponentEditorTypes::LABELS;

    private const DIVIDER_LABELS = ComponentEditorTypes::DIVIDER_LABELS;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Home';

    public function getTitle(): string
    {
        return __('Home');
    }

    protected static ?string $slug = 'pages/home';

    protected string $view = 'filament.pages.home-presentation';

    /** @var list<array{role:string,label:string,value:string,description:string}> */
    public array $metrics = [];

    public string $template = 'artwork';

    public string $templateLabel = 'Hero Artwork';

    public string $previewUrl = '';

    public int $settingsId = 0;

    public int $homeSectionId = 0;

    #[Locked]
    public ?array $editingHomeComponentOriginal = null;

    public bool $artworkShowDetails = true;

    public bool $artworkShowGalleryLink = false;

    public bool $artworkShowNavigation = false;

    public bool $artworkEnableViewer = true;

    public bool $publicSiteGate = false;

    public string $heroGroupSource = 'automatic';

    /** @var list<int> */
    public array $disabledAutoHeroIds = [];

    /** @var list<array<string,mixed>> */
    public array $excludedHeroRows = [];

    public string $heroDisplayStrategy = 'ordered';

    public string $heroSourceRule = 'newest_artwork';

    public string $heroSelectionMode = 'global';

    public int $heroGroupSize = HomeHeroConfigurationService::DEFAULT_GROUP_SIZE;

    /** @var list<array<string,mixed>> */
    public array $dummyGalleries = [];

    /** @var list<array<string,mixed>> Uncommitted members of the Create dialog. */
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

    /** @var list<int|string> Group IDs or typed candidate keys sharing one selection model. */
    public array $selectedSourceIds = [];

    /** @var list<int> Visible Gallery source rows expanded into candidate rows. */
    public array $expandedSourceIds = [];

    /** @var list<array<string,mixed>> */
    public array $componentDataset = [];

    /** @var list<array<string,mixed>> */
    public array $components = [];

    /** @var array<string,string> */
    public array $componentTypeOptions = [
        'image' => 'Image / Video',
        'heading' => 'Heading',
        'rich_text' => 'Rich Text',
        'divider' => 'Divider',
        'list' => 'List',
        'contact' => 'Contact',
        'legal_disclaimer' => 'Legal Disclaimer',
    ];

    /** @var array<string,string> */
    public array $availableSocialPlatforms = [];

    public string $componentSearch = '';

    #[Session]
    public string $componentType = 'any';

    /** @var list<string> */
    public array $selectedComponentTargets = [];

    /** @var list<string> */
    public array $selectedHomeChildTargets = [];

    /** @var array{components:int,images:int,headings:int,rich_text:int,dividers:int,media_references:int} */
    public array $componentStats = [
        'components' => 0,
        'images' => 0,
        'headings' => 0,
        'rich_text' => 0,
        'dividers' => 0,
        'media_references' => 0,
    ];

    public bool $homeAnalyticsLoaded = false;

    public string $homeAnalyticsStatus = 'loading';

    public ?float $homeVisits = null;

    public ?float $homeViews = null;

    public function mount(): void
    {
        $this->previewUrl = app(SitePreviewContext::class)->previewSiteUrl();
        $this->reloadWorkspace();
    }

    public function loadHomeAnalytics(): void
    {
        if ($this->homeAnalyticsLoaded || $this->template !== HomeTemplate::Artwork->value) {
            return;
        }

        $this->homeAnalyticsLoaded = true;
        $report = app(ArtistReportingService::class)->customPage('/', '30d');
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

    public function updatedComponentSearch(): void
    {
        $this->clearComponentSelection();
        $this->projectComponents();
    }

    public function updatedComponentType(): void
    {
        if ($this->componentType !== 'any' && ! array_key_exists($this->componentType, $this->componentTypeOptions)) {
            $this->componentType = 'any';
        }
        $this->clearComponentSelection();
        $this->projectComponents();
    }

    public function resetComponentFilters(): void
    {
        $this->componentSearch = '';
        $this->componentType = 'any';
        $this->clearComponentSelection();
        $this->projectComponents();
    }

    public function toggleVisibleComponentSelection(): void
    {
        $visible = collect($this->components);
        $parents = $visible->pluck('target')->filter()->values()->all();
        $children = $visible->flatMap(fn (array $component): array => collect($component['children'] ?? [])
            ->pluck('target')->filter()->values()->all())->values()->all();
        $complete = count(array_intersect($parents, $this->selectedComponentTargets)) === count($parents)
            && count(array_intersect($children, $this->selectedHomeChildTargets)) === count($children);

        if ($complete) {
            $this->selectedComponentTargets = array_values(array_diff($this->selectedComponentTargets, $parents));
            $this->selectedHomeChildTargets = array_values(array_diff($this->selectedHomeChildTargets, $children));

            return;
        }
        $this->selectedComponentTargets = array_values(array_unique([...$this->selectedComponentTargets, ...$parents]));
        $this->selectedHomeChildTargets = array_values(array_unique([...$this->selectedHomeChildTargets, ...$children]));
    }

    public function settingsAction(): Action
    {
        $dialog = app(HomeSettingsDialog::class);
        $action = Action::make('settings')
            ->label('Settings')
            ->modalHeading('Home settings')
            ->fillForm(fn (): array => $dialog->fill())
            ->schema(fn (): array => $dialog->schema(
                fn (string $field, bool $value) => $this->persistHeroDisplayToggle($field, $value),
            ))
            ->action(function (array $data) use ($dialog): void {
                $previousSource = $this->heroGroupSource;
                $changed = $dialog->save($data);
                if (($data['group_source'] ?? $previousSource) !== $previousSource) {
                    $this->sourcePage = 1;
                    $this->expandedSourceIds = [];
                    $this->clearSourceSelection();
                }
                $this->reloadWorkspace();

                if ($changed) {
                    app(AdminNotifier::class)->notification(
                        title: 'Home settings saved',
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
        $changed = app(HomeHeroConfigurationService::class)->updateArtworkSettings(
            $this->settings(), [$field => $value],
        );
        if (! $changed) {
            return;
        }

        $this->reloadWorkspace();
        app(AdminNotifier::class)->notification(
            title: 'Home settings saved',
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
                    return ['name' => '', 'pending_artwork_id' => null, 'pending_media_asset_id' => null];
                })
                ->schema(fn (): array => app(HomeDummyGalleryDialog::class)->schema(0, true))
                ->action(function (array $data): void {
                    $created = app(HomeHeroConfigurationService::class)->createDummyGallery(
                        $this->settings(), (string) ($data['name'] ?? ''), $this->draftDummyMembers,
                    );
                    if ($created === null) {
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

    /** Editing an existing Dummy Gallery autosaves immediately. */
    public function editDummyGalleryAction(): Action
    {
        return AdminDialog::edit(
            Action::make('editDummyGallery')
                ->label('Edit Dummy Gallery')
                ->modalHeading('Edit Dummy Gallery')
                ->fillForm(fn (array $arguments): array => [
                    'name' => $this->dummyGallery((int) ($arguments['gallery'] ?? 0))['name'],
                    'pending_artwork_id' => null,
                    'pending_media_asset_id' => null,
                ])
                ->schema(fn (array $arguments): array => app(HomeDummyGalleryDialog::class)
                    ->schema((int) ($arguments['gallery'] ?? 0), false))
                ->action(static function (): void {}),
            AdminDialogSize::Large,
        );
    }

    public function renameDummyGallery(int $galleryId, string $name): void
    {
        $changed = app(HomeHeroConfigurationService::class)->updateDummyGallery(
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
                    $changed = app(HomeHeroConfigurationService::class)->removeDummyGallery(
                        $this->settings(), (int) ($arguments['gallery'] ?? 0),
                    );
                    if ($changed) {
                        $this->clearSourceSelection();
                        $this->reloadWorkspace();
                        app(AdminNotifier::class)->notification(title: 'Dummy Gallery deleted', status: 'success');
                    }
                }),
            'Delete Dummy Gallery and its Home memberships?',
            'The referenced Artworks and Storage files remain intact.',
            submitLabel: 'Delete',
            danger: true,
        );
    }

    private function dummyGallery(int $galleryId): array
    {
        foreach (app(HomeHeroConfigurationService::class)->configuration($this->settings())['dummy_galleries'] as $gallery) {
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
        $available = app(HomeHeroResolver::class)->dummyCandidates(
            $gallery['members'], (int) $gallery['id'], (string) $gallery['name'],
        )->keyBy(static fn (HomeHeroCandidate $candidate): string => $candidate->key());
        $rows = [];
        foreach ($gallery['members'] as $member) {
            $key = HomeHeroConfigurationService::memberKey($member);
            $candidate = $available->get($key);
            $row = $candidate instanceof HomeHeroCandidate ? $this->candidateRow($candidate) : [
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
        if (! $asset instanceof MediaAsset || ! HomeHeroCandidate::mediaIsEligible($asset)) {
            throw ValidationException::withMessages(['pending_media_asset_id' => 'Choose an eligible Storage file.']);
        }
        $key = 'media:'.$mediaAssetId;
        if (collect($this->draftDummyMembers)->contains(
            static fn (array $member): bool => HomeHeroConfigurationService::memberKey($member) === $key,
        )) {
            throw ValidationException::withMessages(['pending_media_asset_id' => 'Already added to this Dummy Gallery.']);
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
            throw ValidationException::withMessages(['pending_artwork_id' => 'Choose an eligible Artwork.']);
        }
        $key = 'artwork:'.$artworkId;
        if (collect($this->draftDummyMembers)->contains(
            static fn (array $member): bool => HomeHeroConfigurationService::memberKey($member) === $key,
        )) {
            throw ValidationException::withMessages(['pending_artwork_id' => 'Already added to this Dummy Gallery.']);
        }
        $this->draftDummyMembers[] = ['artwork_id' => $artworkId, 'enabled' => true];
    }

    public function addDummyMediaFromStorage(int $galleryId, int $mediaAssetId): void
    {
        $changed = app(HomeHeroConfigurationService::class)->addDummyMedia($this->settings(), $galleryId, $mediaAssetId);
        if ($changed) {
            $this->reloadWorkspace();
            app(AdminNotifier::class)->notification(title: 'Dummy Gallery member added', status: 'success');
        }
    }

    public function addDummyArtwork(int $galleryId, int $artworkId): void
    {
        $changed = app(HomeHeroConfigurationService::class)->addDummyArtwork($this->settings(), $galleryId, $artworkId);
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
            if (HomeHeroConfigurationService::memberKey($member) === $key && isset($member['media_asset_id'])) {
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
        $changed = app(HomeHeroConfigurationService::class)->updateDummyMember(
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
            if (HomeHeroConfigurationService::memberKey($member) === $key) {
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
            if (HomeHeroConfigurationService::memberKey($member) !== $key) {
                continue;
            }
            $changed = app(HomeHeroConfigurationService::class)->setDummyMemberEnabled(
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
            static fn (array $member): bool => HomeHeroConfigurationService::memberKey($member) !== $key,
        ));
    }

    public function removeDummyMember(int $galleryId, string $key): void
    {
        $changed = app(HomeHeroConfigurationService::class)->removeDummyMember($this->settings(), $galleryId, $key);
        if ($changed) {
            $this->reloadWorkspace();
            app(AdminNotifier::class)->notification(title: 'Dummy Gallery member removed', status: 'success');
        }
    }

    public function toggleDummyGallery(int $galleryId): void
    {
        $gallery = $this->dummyGallery($galleryId);
        $changed = app(HomeHeroConfigurationService::class)->updateDummyGallery(
            $this->settings(), $galleryId, ['enabled' => ! $gallery['enabled']],
        );
        if ($changed) {
            $this->reloadWorkspace();
            app(AdminNotifier::class)->notification(title: 'Dummy Gallery source updated', status: 'success');
        }
    }

    public function setAutomaticHeroCandidateEnabled(int $artworkId, bool $enabled): void
    {
        $changed = app(HomeHeroConfigurationService::class)->setAutomaticCandidateEnabled(
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
        $configuration = app(HomeHeroConfigurationService::class)->configuration($this->settings());
        if ($field === 'group_source') {
            if (! in_array($value, ['automatic', 'semi_automatic', 'manual'], true)) {
                throw ValidationException::withMessages(['group_source' => 'Choose a valid Hero source.']);
            }
        } elseif ($field === 'display_strategy') {
            if (! in_array($value, ['ordered', 'random', 'sequential'], true)) {
                throw ValidationException::withMessages(['display_strategy' => 'Choose a valid Hero strategy.']);
            }
        } elseif ($field === 'selection_mode') {
            if (! is_string($value) || ! array_key_exists($value, HomeHeroConfigurationService::SELECTION_MODES)) {
                throw ValidationException::withMessages(['selection_mode' => 'Choose a valid Hero selection mode.']);
            }
        } elseif ($field === 'source_rule') {
            if (! is_string($value) || ! array_key_exists($value, HomeHeroConfigurationService::SOURCE_RULES)) {
                throw ValidationException::withMessages(['source_rule' => 'Choose a valid Gallery source rule.']);
            }
        } elseif ($field === 'group_size') {
            $size = filter_var($value, FILTER_VALIDATE_INT);
            if ($size === false || $size < 1 || $size > HomeHeroConfigurationService::MAX_GROUP_SIZE) {
                throw ValidationException::withMessages(['group_size' => 'Source Size must be between 1 and 50.']);
            }
            $value = $size;
        } else {
            throw ValidationException::withMessages(['hero' => 'Unsupported Hero Stage setting.']);
        }

        $changed = app(HomeHeroConfigurationService::class)->updateArtworkSettings(
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
        app(AdminNotifier::class)->notification(title: 'Home settings saved', status: 'success');
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
        $changed = app(HomeHeroConfigurationService::class)->moveSource(
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
        $changed = app(HomeHeroConfigurationService::class)->updateCandidateWeight(
            $this->settings(), $key, (int) round(((float) $percentage) * 100),
        );
        $this->reloadWorkspace();
        if ($changed) {
            app(AdminNotifier::class)->notification('Hero candidate percentage updated', status: 'success');
        }
    }

    public function addComponentAction(): Action
    {
        $action = Action::make('addComponent')
            ->label('Add component')
            ->fillForm(fn (): array => [
                'type' => 'text',
                'publication_state' => app(AdminAutoPublishPreferences::class)->enabled(AdminAutoPublishPreferences::HOME)
                    ? 'published' : 'unpublished',
                'image_decorative' => false,
                'variant' => 'thin',
                'media_asset_id' => null,
            ])
            ->schema($this->componentEditorSchema(allowParentSelection: false))
            ->modalHeading(__('Add component'))
            ->action(function (array $data): void {
                $changed = app(HomePresentationEditorialService::class)->addComponent(
                    $this->settings(), $this->componentTemplate(), $this->componentPayload($data),
                );
                $this->reloadWorkspace();
                if ($changed) {
                    app(AdminNotifier::class)->notification(title: 'Home component added', status: 'success');
                }
            });

        return AdminDialog::create($action, 'Add component', AdminDialogSize::Large);
    }

    public function editComponentAction(): Action
    {
        return $this->homeComponentEditingAction('editComponent', committed: false);
    }

    public function editListComponentAction(): Action
    {
        return $this->homeComponentEditingAction('editListComponent', committed: true);
    }

    private function homeComponentEditingAction(string $actionName, bool $committed): Action
    {
        $action = Action::make($actionName)->label('Edit')
            ->fillForm(function (array $arguments): array {
                $component = $this->componentFromArguments($arguments);
                $this->editingHomeComponentOriginal = $component;

                return $this->componentEditorData($component);
            })
            ->schema(fn (array $arguments): array => $this->componentEditorSchema(
                editingType: (string) $this->componentFromArguments($arguments)['type'],
                allowParentSelection: true,
                onTypeChange: function (mixed $state) use ($arguments): void {
                    $component = $this->componentFromArguments($arguments);
                    $type = (string) $component['type'];
                    $targetType = is_string($state) ? $state : '';
                    if ($targetType === $type || ! array_key_exists($targetType, self::COMPONENT_LABELS)) {
                        return;
                    }

                    $this->replaceMountedAction('changeComponentType', [
                        ...$arguments,
                        'targetKind' => $targetType,
                    ]);
                },
                onImageChange: fn (mixed $state) => $this->persistHomeEditorField($arguments, null, 'media_asset_id', $state),
                onContactChange: fn (string $key, mixed $value) => $this->persistHomeEditorField($arguments, null, $key, $value),
            ))
            ->modalHeading(__('Edit component'))
            ->extraModalFooterActions(fn (array $arguments): array => $this->homeEditHeaderActions($arguments, $actionName))
            ->action(function (array $data, array $arguments): void {
                $component = $this->componentFromArguments($arguments);
                $type = (string) $component['type'];
                if (($data['type'] ?? null) !== $type) {
                    return; // Conversion belongs to the explicit type-change Action.
                }

                try {
                    $payload = $this->componentPayload($data, $component);
                    $parent = $type !== 'image' ? ($data['parent_image_index'] ?? null) : null;
                    if ($parent !== null && $parent !== '') {
                        $imageIndex = filter_var($parent, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
                        if ($imageIndex === false) {
                            throw ValidationException::withMessages(['parent_image_index' => 'Choose a decorative Image parent.']);
                        }
                        $changed = app(HomePresentationEditorialService::class)->updateAndNestComponent(
                            $this->settings(), $this->componentTemplate(),
                            (int) $arguments['index'], $type, $payload, (int) $imageIndex,
                            $this->editingHomeComponentOriginal,
                        );
                        $this->reloadWorkspace();
                        if ($changed) {
                            app(AdminNotifier::class)->notification(title: 'Home component moved into image', status: 'success');
                            $parentIndex = (int) $imageIndex - ((int) $arguments['index'] < (int) $imageIndex ? 1 : 0);
                            $children = $this->settings()->components($this->componentTemplate())[$parentIndex]['children'] ?? [];
                            $this->replaceMountedAction('editHomeImageChild', [
                                'index' => $parentIndex, 'type' => 'image',
                                'child' => count($children) - 1, 'child_type' => $type,
                            ]);
                        }

                        return;
                    }

                    $changed = app(HomePresentationEditorialService::class)->updateComponent(
                        $this->settings(), $this->componentTemplate(),
                        (int) $arguments['index'], $type, $payload,
                        $this->editingHomeComponentOriginal,
                    );
                } catch (ValidationException $exception) {
                    $path = $this->getMountedActionSchema()->getStatePath();
                    $errors = [];
                    foreach ($exception->errors() as $field => $messages) {
                        $errors[$path.'.'.($field === 'component' ? 'type' : $field)] = $messages;
                    }
                    throw ValidationException::withMessages($errors);
                }

                $this->reloadWorkspace();
                $this->editingHomeComponentOriginal = $this->componentFromArguments($arguments);
                if ($changed) {
                    $this->forceRender();
                    app(AdminNotifier::class)->notification(
                        title: 'Home component saved',
                        status: 'success',
                    );
                }
            });

        return $committed
            ? AdminDialog::command($action, 'Apply changes', AdminDialogSize::Large)
            : AdminDialog::edit($action, AdminDialogSize::Large);
    }

    /**
     * Persist individual Image/Contact edits through Home's canonical mutation.
     * Rebuild sibling Contact values from the latest committed block, as the
     * existing Custom Page editor does, rather than trusting stale modal data.
     *
     * @param array<string,mixed> $arguments
     */
    private function persistHomeEditorField(array $arguments, ?int $childIndex, string $key, mixed $value): void
    {
        $root = $this->componentFromArguments([
            'index' => $arguments['index'] ?? null,
            'type' => $childIndex === null ? ($arguments['type'] ?? null) : 'image',
        ]);
        $existing = $childIndex === null ? $root : ($root['children'][$childIndex] ?? null);
        if (! is_array($existing)) {
            throw ValidationException::withMessages(['component' => 'This Home component changed. Reopen the editor.']);
        }
        $type = (string) ($existing['type'] ?? '');

        if ($type === 'image' && $childIndex === null && $key === 'media_asset_id') {
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false || $id === null) {
                throw ValidationException::withMessages(['media_asset_id' => 'Choose an image or video from Storage.']);
            }
            $payload = [...$existing, 'media_asset_id' => (int) $id];
        } elseif ($type === 'contact') {
            $data = $this->componentEditorData($existing);
            if (str_starts_with($key, 'contact_social_selected.')) {
                $platform = substr($key, strlen('contact_social_selected.'));
                if (! array_key_exists($platform, $this->availableSocialPlatforms)) {
                    return;
                }
                $data['contact_social_selected'][$platform] = (bool) $value;
            } elseif (in_array($key, [
                'title', 'contact_email_enabled', 'contact_social_enabled',
                'contact_form_enabled', 'contact_form_state', 'contact_status_text',
            ], true)) {
                $data[$key] = $value;
            } else {
                throw new \InvalidArgumentException('Unknown Home Contact field.');
            }

            if (in_array($key, ['contact_form_state', 'contact_status_text'], true)) {
                $form = $this->getMountedActionSchema()->getRawState();
                $state = $key === 'contact_form_state'
                    ? $value : ($form['contact_form_state'] ?? $data['contact_form_state']);
                $status = $key === 'contact_status_text'
                    ? $value : ($form['contact_status_text'] ?? $data['contact_status_text']);
                $data['contact_form_state'] = $state;
                $data['contact_status_text'] = $status;
                if ($state === 'under_construction' && blank($status)) {
                    return;
                }
            }
            if ($childIndex !== null) {
                $data['contact_form_enabled'] = false;
            }
            $payload = $this->contactComponentPayload(
                $data, CustomPageSetting::componentPublished($existing), $existing,
            );
        } else {
            return;
        }

        $index = (int) $arguments['index'];
        try {
            $editor = app(HomePresentationEditorialService::class);
            $changed = $childIndex === null
                ? $editor->updateComponent(
                    $this->settings(), $this->componentTemplate(), $index, $type,
                    $payload, $this->editingHomeComponentOriginal,
                )
                : $editor->mutateChild(
                    $this->settings(), $this->componentTemplate(), $index, 'image',
                    'update', $childIndex, $payload,
                    expectedChildType: $type, expectedChild: $this->editingHomeComponentOriginal,
                );
        } catch (ValidationException $exception) {
            $path = $this->getMountedActionSchema()->getStatePath();
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors[$path.'.'.($field === 'component' ? 'type' : $field)] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }

        $freshRoot = $this->settings()->components($this->componentTemplate())[$index] ?? null;
        $this->editingHomeComponentOriginal = $childIndex === null
            ? $freshRoot
            : (is_array($freshRoot) ? ($freshRoot['children'][$childIndex] ?? null) : null);
        if (! $changed) {
            return;
        }

        $this->reloadWorkspace();
        $this->forceRender();
        app(AdminNotifier::class)->notification(title: 'Home component saved', status: 'success');
    }

    /** @param array<string,mixed> $arguments
     * @return list<Action>
     */
    private function homeEditHeaderActions(array $arguments, string $actionName): array
    {
        $component = $this->componentFromArguments($arguments);
        $index = (int) $arguments['index'];
        $type = (string) $component['type'];
        $count = count($this->componentDataset);
        $actions = [];

        foreach (['up' => AdminRowAction::MoveUp, 'down' => AdminRowAction::MoveDown] as $direction => $role) {
            $canMove = $this->componentReorderEnabled() && ($direction === 'up' ? $index > 0 : $index < $count - 1);
            $actions[] = AdminDialog::headerAction('dialogMove'.ucfirst($direction), $role, function () use ($index, $type, $direction, $actionName): void {
                $this->moveComponent($index, $type, $direction);
                $newIndex = $index + ($direction === 'up' ? -1 : 1);
                $this->replaceMountedAction($actionName, ['index' => $newIndex, 'type' => $type]);
            })->disabled(! $canMove);
        }
        $published = CustomPageSetting::componentPublished($component);
        $actions[] = AdminDialog::headerAction('dialogTogglePublication',
            $published ? AdminRowAction::Unpublish : AdminRowAction::Publish,
            function () use ($index, $type, $published, $actionName): void {
                $this->setComponentPublished($index, $type, ! $published);
                $this->replaceMountedAction($actionName, ['index' => $index, 'type' => $type]);
            });
        $actions[] = AdminDialog::headerAction('dialogDeleteComponent', AdminRowAction::Delete, function () use ($index, $type): void {
            $this->replaceMountedAction('removeComponent', ['index' => $index, 'type' => $type]);
        })->disabled(! CustomPageSetting::canDeleteComponent($component));

        return $actions;
    }

    public function setComponentPublished(int $index, string $type, bool $published): void
    {
        $changed = app(HomePresentationEditorialService::class)->setComponentPublished(
            $this->settings(), $this->componentTemplate(), $index, $type, $published,
        );
        $this->reloadWorkspace();
        if ($changed) {
            app(AdminNotifier::class)->notification(
                $published ? 'Home component published' : 'Home component unpublished',
                status: 'success',
            );
        }
    }

    public function changeComponentTypeAction(): Action
    {
        $targetComponent = function (array $arguments): array {
            $component = $this->componentFromArguments($arguments);
            $targetType = (string) ($arguments['targetKind'] ?? '');
            if (! array_key_exists($targetType, self::COMPONENT_LABELS)) {
                throw ValidationException::withMessages(['type' => 'Choose a supported component type.']);
            }

            return $component;
        };

        return AdminDialog::confirm(
            Action::make('changeComponentType')
                ->label('Change component type')
                ->action(function (array $arguments) use ($targetComponent): void {
                    $component = $targetComponent($arguments);
                    $changed = app(HomePresentationEditorialService::class)->convertComponent(
                        $this->settings(), $this->componentTemplate(),
                        (int) $arguments['index'],
                        (string) $component['type'],
                        (string) $arguments['targetKind'],
                    );
                    $this->reloadWorkspace();
                    if ($changed) {
                        app(AdminNotifier::class)->notification(
                            title: 'Home component type updated',
                            status: 'success',
                        );
                    }
                }),
            'Change type?',
            fn (array $arguments): string => 'Changing to '
                .(self::COMPONENT_LABELS[(string) $arguments['targetKind']] ?? 'another type')
                .' removes content that cannot be kept.',
            'Change type',
            condition: fn (array $arguments): bool => app(HomePresentationEditorialService::class)
                ->conversionLosesContent($targetComponent($arguments), (string) $arguments['targetKind']),
        );
    }

    public function removeComponentAction(): Action
    {
        return AdminDialog::confirm(
            Action::make('removeComponent')->label('Delete')->color('danger'),
            heading: 'Delete component?',
            description: 'The component is removed from this Home template. Other template configurations are unchanged.',
            submitLabel: 'Delete',
            danger: true,
        )->action(function (array $arguments): void {
            $component = $this->componentFromArguments($arguments);
            app(HomePresentationEditorialService::class)->deleteComponent(
                $this->settings(), $this->componentTemplate(), (int) $arguments['index'], (string) $component['type'],
            );
            $this->reloadWorkspace();
            app(AdminNotifier::class)->notification(
                title: 'Home component deleted',
                status: 'success',
            );
        });
    }

    public function deleteSelectedComponentsAction(): Action
    {
        return AdminDialog::confirm(
            Action::make('deleteSelectedComponents')->label('Delete selected')->color('danger'),
            heading: 'Delete components?',
            submitLabel: 'Delete',
            danger: true,
        )->action(function (): void {
            $targets = $this->selectedComponentTargetData();
            $children = $this->selectedHomeChildTargetData();
            if ($targets === [] && $children === []) {
                return;
            }
            app(HomePresentationEditorialService::class)->changeSelection(
                $this->settings(), $this->componentTemplate(), $targets, $children, 'delete',
            );
            $count = count($targets) + count($children);
            $this->reloadWorkspace();
            app(AdminNotifier::class)->notification(
                title: 'Selected Home items deleted',
                body: $count.' selected item'.($count === 1 ? '' : 's').' updated.',
                status: 'success',
            );
        });
    }

    public function publishSelectedHomeItems(bool $published): void
    {
        $parents = $this->selectedComponentTargetData();
        $children = $this->selectedHomeChildTargetData();
        if ($parents === [] && $children === []) {
            return;
        }
        $changed = app(HomePresentationEditorialService::class)->changeSelection(
            $this->settings(), $this->componentTemplate(), $parents, $children, $published ? 'publish' : 'unpublish',
        );
        $this->reloadWorkspace();
        if ($changed) {
            app(AdminNotifier::class)->notification(
                title: $published ? 'Selected Home items published' : 'Selected Home items unpublished',
                status: 'success',
            );
        }
    }

    public function moveComponent(int $index, string $expectedType, string $direction): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }
        $changed = app(HomePresentationEditorialService::class)->moveComponent(
            $this->settings(), $this->componentTemplate(), $index, $expectedType, $direction,
        );
        $this->reloadWorkspace();
        if ($changed) {
            app(AdminNotifier::class)->notification(
                title: 'Home component order updated',
                status: 'success',
            );
        }
    }

    public function sortComponent(string $target, int $position): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }
        $targets = collect($this->componentDataset)->pluck('target')->filter(static fn ($value): bool => is_string($value))->values()->all();
        $from = array_search($target, $targets, true);
        if ($from === false) {
            throw ValidationException::withMessages(['component' => 'The component sequence changed. Reload the workspace and try again.']);
        }
        $moved = $targets[$from];
        array_splice($targets, $from, 1);
        $position = max(0, min($position, count($targets)));
        array_splice($targets, $position, 0, [$moved]);
        $changed = app(HomePresentationEditorialService::class)->reorderComponents(
            $this->settings(), $this->componentTemplate(),
            array_map(fn (string $value): array => $this->parseComponentTarget($value), $targets),
        );
        $this->reloadWorkspace();
        if ($changed) {
            app(AdminNotifier::class)->notification(
                title: 'Home component order updated',
                status: 'success',
            );
        }
    }

    public function moveSelectedComponents(string $direction): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }
        $targets = $this->selectedComponentTargetData();
        if ($targets === []) {
            return;
        }
        $changed = app(HomePresentationEditorialService::class)->moveSelectedComponents(
            $this->settings(), $this->componentTemplate(), $targets, $direction,
        );
        $count = count($targets);
        $this->reloadWorkspace();
        if ($changed) {
            app(AdminNotifier::class)->notification(
                title: 'Selected Home components moved',
                body: $count.' component'.($count === 1 ? '' : 's').' updated.',
                status: 'success',
            );
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
            title: 'Home source preference updated',
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
                if (HomeHeroConfigurationService::memberKey($member) !== $candidateKey) {
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

        $service = app(HomeHeroConfigurationService::class);
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
                    $service = app(HomeHeroConfigurationService::class);
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
        $config = app(HomeHeroConfigurationService::class)->configuration($this->settings());
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
        $this->loadEditorSocialLinks();
        $settings = $this->settings();
        $configuration = app(HomePresentationEditorialService::class)->configuration($settings);
        $heroConfiguration = app(HomeHeroConfigurationService::class)->configuration($settings);
        $this->settingsId = (int) $settings->getKey();
        $this->homeSectionId = (int) $settings->getAttribute('site_section_id');
        $template = $settings->template();
        $this->template = $template->value;
        $this->templateLabel = $template->label();
        $this->artworkShowDetails = $heroConfiguration['show_details'];
        $this->artworkShowGalleryLink = $heroConfiguration['show_gallery_link'];
        $this->artworkShowNavigation = $heroConfiguration['show_navigation'];
        $this->artworkEnableViewer = $heroConfiguration['enable_viewer'];
        $this->heroGroupSource = $heroConfiguration['group_source'];
        $this->disabledAutoHeroIds = $heroConfiguration['disabled_auto_artwork_ids'];
        $this->excludedHeroRows = [];
        $this->heroDisplayStrategy = $heroConfiguration['display_strategy'];
        $this->heroSourceRule = $heroConfiguration['source_rule'];
        $this->heroSelectionMode = $heroConfiguration['selection_mode'];
        $this->heroGroupSize = $heroConfiguration['group_size'];
        $this->dummyGalleries = $heroConfiguration['dummy_galleries'];
        $this->rotationIntervalCount = $heroConfiguration['rotation_interval']['count'];
        $this->rotationIntervalUnit = $heroConfiguration['rotation_interval']['unit'];
        $this->rotationStartedAt = $heroConfiguration['rotation_started_at'];
        $this->publicSiteGate = (bool) $configuration[HomeTemplate::UnderConstruction->value]['public_site_gate'];

        $this->selectionIssue = null;
        $this->currentArtwork = null;
        $this->heroRailRows = [];
        $this->componentDataset = [];
        $this->components = [];
        $this->eligibleArtworkCount = 0;
        $this->sourceGalleryCount = 0;
        $this->newestEligibleYear = null;
        $this->candidatePoolCount = 0;
        $this->nextRotationAt = null;
        $this->componentStats = ['components' => 0, 'images' => 0, 'headings' => 0, 'rich_text' => 0, 'dividers' => 0, 'media_references' => 0];
        $this->clearComponentSelection();
        if (! in_array($template, [HomeTemplate::UnderConstruction, HomeTemplate::Custom], true)) {
            $this->componentSearch = '';
            $this->componentType = 'any';
        }

        match ($template) {
            HomeTemplate::Artwork => $this->loadArtworkWorkspace($settings),
            HomeTemplate::UnderConstruction, HomeTemplate::Custom => $this->loadComponentWorkspace($template, $configuration),
        };
        $this->refreshMetrics();
    }

    private function loadArtworkWorkspace(HomePresentationSetting $settings): void
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

        $resolution = app(HomeHeroResolver::class)->resolve($settings);
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
                $candidates = app(HomeHeroResolver::class)->dummyCandidates(
                    array_values(array_filter($dummy['members'], static fn (array $member): bool => $member['enabled'])),
                    (int) $dummy['id'], (string) $dummy['name'],
                );
                $dummyPotential += min($candidates->count(), $this->heroGroupSize);
            }
            $potential = $dummyPotential + $regularSlots;
            if ($potential > HomeHeroConfigurationService::MAX_GROUP_SIZE) {
                $this->selectionIssue = sprintf(
                    'Per Gallery requests up to %d candidates from %d eligible sources, exceeding the %d-candidate limit. The active group is capped at %d.',
                    $this->heroGroupSize,
                    $regularEligible + count($activeDummy),
                    HomeHeroConfigurationService::MAX_GROUP_SIZE,
                    HomeHeroConfigurationService::MAX_GROUP_SIZE,
                );
            }
        }
        $this->nextRotationAt = $resolution['next_rotation_at']?->toIso8601String();
        $current = $resolution['current'];
        if ($current instanceof HomeHeroCandidate) {
            $this->currentArtwork = $this->candidateRow($current);
        } else {
            $this->selectionIssue ??= 'No eligible Hero candidate is available.';
        }

        if ($this->disabledAutoHeroIds !== []) {
            $disabled = $publicArtworks->homeCandidatesByIds($this->disabledAutoHeroIds);
            $this->excludedHeroRows = $disabled
                ->map(function (Artwork $artwork): array {
                    $row = $this->candidateRow(HomeHeroCandidate::fromArtwork($artwork));
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
            ->map(static fn (HomeHeroCandidate $candidate): string => $candidate->key())->all();
        $currentId = $current instanceof HomeHeroCandidate ? $current->key() : null;
        $this->heroRailRows = $sequence->values()->map(function (HomeHeroCandidate $candidate) use ($currentId, $resolution, $rotationKeys): array {
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

    /** @param array<string,mixed> $configuration */
    private function loadComponentWorkspace(HomeTemplate $template, array $configuration): void
    {
        $raw = $configuration[$template->value]['components'] ?? [];
        $raw = is_array($raw) && array_is_list($raw) ? $raw : [];
        $mediaIds = collect($raw)
            ->filter(fn ($component): bool => is_array($component) && ($component['type'] ?? null) === 'image' && is_numeric($component['media_asset_id'] ?? null))
            ->map(fn (array $component): int => (int) $component['media_asset_id'])->filter()->unique()->values()->all();
        $assets = MediaAsset::query()->whereIn('id', $mediaIds)->get(['id', 'original_filename', 'alt_text'])
            ->keyBy(fn (MediaAsset $asset): int => (int) $asset->getKey());

        $referenceIds = [];
        $count = count($raw);
        $dataset = [];
        foreach ($raw as $index => $component) {
            if (! is_array($component)) {
                continue;
            }
            $type = is_string($component['type'] ?? null) ? $component['type'] : 'unknown';
            $assetId = is_numeric($component['media_asset_id'] ?? null) ? (int) $component['media_asset_id'] : null;
            $asset = $assetId === null ? null : $assets->get($assetId);
            $title = trim((string) ($component['title'] ?? ''));
            $body = is_string($component['body'] ?? null) ? trim((string) $component['body']) : '';
            $filterType = match ($type) {
                'image' => 'image',
                'text' => $title !== '' && $body === '' ? 'heading' : 'rich_text',
                'divider' => 'divider',
                'list' => 'list',
                'contact' => 'contact',
                'legal_disclaimer' => 'legal_disclaimer',
                default => 'unknown',
            };
            $typeLabel = $this->componentTypeOptions[$filterType] ?? 'Component';
            if (in_array($type, ['image', 'list'], true) && $assetId !== null && $assetId > 0) {
                $referenceIds[] = $assetId;
            }
            if ($type === 'text' && $body !== '') {
                $referenceIds = array_merge($referenceIds, RichTextMediaReference::ids($body));
            }
            if ($type === 'list') {
                foreach (($component['items'] ?? []) as $item) {
                    if (is_array($item) && is_string($item['body'] ?? null)) {
                        $referenceIds = array_merge($referenceIds, RichTextMediaReference::ids($item['body']));
                    }
                }
            }
            [$primary, $secondary] = match ($filterType) {
                'image' => [
                    $asset instanceof MediaAsset ? (string) $asset->getAttribute('original_filename') : 'Choose an image from Storage',
                    $asset instanceof MediaAsset && filled($asset->getAttribute('alt_text')) ? 'ALT: '.Str::limit((string) $asset->getAttribute('alt_text'), 90) : 'Storage image',
                ],
                'heading' => [$title !== '' ? $title : 'Untitled heading', ''],
                'rich_text' => [$title !== '' ? $title : (Str::limit($body, 110) ?: 'Empty Rich Text'), $title !== '' ? (Str::limit($body, 110) ?: 'No body text') : ''],
                'divider' => [DividerVariant::LABELS[$component['variant'] ?? 'thin'] ?? 'Thin', ''],
                'list' => [$title !== '' ? $title : 'List', count($component['items'] ?? []).' entries'],
                'contact' => ['Contact', count($component['children'] ?? []).' items'],
                'legal_disclaimer' => ['Legal Disclaimer from General', PublicLegalContent::editorSummary(PublicContentSetting::general())],
                default => ['Unsupported component', ''],
            };
            $dataset[] = [
                'index' => $index,
                'position' => $index + 1,
                'target' => $index.':'.$type,
                'type' => $type,
                'filter_type' => $filterType,
                'type_label' => $typeLabel,
                'content' => ['primary' => $primary, 'secondary' => $secondary],
                'published' => CustomPageSetting::componentPublished($component),
                'children' => $this->homeChildProjection($component, $index),
                'accepts_children' => $type === 'image' && ($component['image_decorative'] ?? false) === true,
                'search_text' => Str::lower(implode(' ', [$typeLabel, $primary, $secondary, $title, $body,
                    $type === 'list' ? collect($component['items'] ?? [])
                        ->filter(static fn (mixed $item): bool => is_array($item))
                        ->flatMap(static fn (array $item): array => array_filter([
                            $item['title'] ?? null, CustomPageSetting::listItemContext($item),
                            CustomPageSetting::listItemDetails($item),
                        ], 'is_string'))
                        ->implode(' ') : '',
                ])),
                'can_move_up' => $index > 0,
                'can_move_down' => $index < $count - 1,
                'editable' => $type !== 'legal_disclaimer' && in_array($type, CustomPageSetting::COMPONENT_TYPES, true),
            ];
        }
        $this->componentDataset = $dataset;
        $this->componentStats = [
            'components' => count($raw),
            'images' => collect($dataset)->where('filter_type', 'image')->count(),
            'headings' => collect($dataset)->where('filter_type', 'heading')->count(),
            'rich_text' => collect($dataset)->where('filter_type', 'rich_text')->count(),
            'dividers' => collect($dataset)->where('filter_type', 'divider')->count(),
            'media_references' => count(array_unique($referenceIds)),
        ];
        $this->projectComponents();
    }

    private function projectComponents(): void
    {
        $term = Str::lower(trim($this->componentSearch));
        $type = $this->componentType;
        $this->components = collect($this->componentDataset)->filter(function (array $component) use ($term, $type): bool {
            if ($type !== 'any' && ($component['filter_type'] ?? null) !== $type) {
                return false;
            }

            return $term === '' || str_contains((string) ($component['search_text'] ?? ''), $term);
        })->values()->all();
    }

    private function refreshMetrics(): void
    {
        $template = HomeTemplate::from($this->template);
        if ($template === HomeTemplate::Artwork) {
            $analyticsDescription = $this->analyticsDescription();
            $this->metrics = [
                ['role' => 'visits', 'label' => 'Visits | 30d', 'value' => $this->formatMetric($this->homeVisits), 'description' => $analyticsDescription],
                ['role' => 'views', 'label' => 'Views | 30d', 'value' => $this->formatMetric($this->homeViews), 'description' => $analyticsDescription],
                ['role' => 'sources', 'label' => 'Source Galleries', 'value' => number_format($this->sourceGalleryCount), 'description' => 'Effective sources'],
                ['role' => 'eligible', 'label' => 'Eligible Artworks', 'value' => number_format($this->eligibleArtworkCount), 'description' => 'Home eligible'],
                ['role' => 'candidates', 'label' => 'Candidate Group', 'value' => number_format($this->candidatePoolCount), 'description' => 'Effective group'],
                ['role' => 'year', 'label' => 'Newest Year', 'value' => $this->newestEligibleYear === null ? '—' : (string) $this->newestEligibleYear, 'description' => 'Eligible newest'],
            ];

            return;
        }

        if (in_array($template, [HomeTemplate::UnderConstruction, HomeTemplate::Custom], true)) {
            $this->metrics = [
                ['role' => 'components', 'label' => 'Components', 'value' => number_format($this->componentStats['components']), 'description' => 'This template'],
                ['role' => 'images', 'label' => 'Images', 'value' => number_format($this->componentStats['images']), 'description' => 'Image blocks'],
                ['role' => 'headings', 'label' => 'Headings', 'value' => number_format($this->componentStats['headings']), 'description' => 'Heading blocks'],
                ['role' => 'rich-text', 'label' => 'Rich Text', 'value' => number_format($this->componentStats['rich_text']), 'description' => 'Text blocks'],
                ['role' => 'dividers', 'label' => 'Dividers', 'value' => number_format($this->componentStats['dividers']), 'description' => 'Divider blocks'],
                ['role' => 'media', 'label' => 'Media References', 'value' => number_format($this->componentStats['media_references']), 'description' => 'Referenced files'],
            ];

            return;
        }

        $this->metrics = [];
    }

    /** @return array<string,mixed> */
    private function candidateRow(HomeHeroCandidate $candidate): array
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

    /** Home and Custom Page share the same component form fields and data
     * normalization; Home supplies its own template-scoped component list. */
    private function editorRootComponents(): array
    {
        return $this->settings()->components($this->componentTemplate());
    }

    private function loadEditorSocialLinks(): void
    {
        $this->availableSocialPlatforms = collect(SocialLinks::visible(
            PublicContentSetting::general()->getAttribute('social_links'),
        ))->mapWithKeys(static fn (array $link): array => [
            $link['platform'] => SocialLinks::label($link['platform']),
        ])->all();
    }

    private function settings(): HomePresentationSetting
    {
        if ($this->settingsId > 0) {
            /** @var HomePresentationSetting $settings */
            $settings = HomePresentationSetting::query()->findOrFail($this->settingsId);

            return $settings;
        }

        return app(HomePresentationResolver::class)->settings();
    }

    private function componentTemplate(): HomeTemplate
    {
        $template = HomeTemplate::from($this->template);
        if (! in_array($template, [HomeTemplate::UnderConstruction, HomeTemplate::Custom], true)) {
            throw ValidationException::withMessages(['component' => 'The active Home template does not use editable components.']);
        }

        return $template;
    }

    private function componentReorderEnabled(): bool
    {
        return trim($this->componentSearch) === '' && $this->componentType === 'any';
    }

    /** @param array<string,mixed> $arguments
     * @return array<string,mixed>
     */
    private function componentFromArguments(array $arguments): array
    {
        $index = filter_var($arguments['index'] ?? null, FILTER_VALIDATE_INT);
        $expectedType = is_string($arguments['type'] ?? null) ? $arguments['type'] : null;
        if ($index === false || $expectedType === null) {
            throw ValidationException::withMessages(['component' => 'The selected Home component is invalid.']);
        }
        $settings = $this->settings();
        $configuration = app(HomePresentationEditorialService::class)->configuration($settings);
        $components = $configuration[$this->componentTemplate()->value]['components'] ?? [];
        $component = is_array($components) ? ($components[(int) $index] ?? null) : null;
        if (! is_array($component) || ($component['type'] ?? null) !== $expectedType) {
            throw ValidationException::withMessages(['component' => 'This Home component changed. Reload the workspace and try again.']);
        }

        return $component;
    }

    /** @return list<array{index:int,type:string}> */
    private function selectedComponentTargetData(): array
    {
        $available = collect($this->componentDataset)->pluck('target')->filter(static fn ($target): bool => is_string($target))->flip();
        $targets = [];
        foreach (array_values(array_unique($this->selectedComponentTargets)) as $target) {
            if (! $available->has($target)) {
                throw ValidationException::withMessages(['component' => 'The selected Home components changed. Reload the workspace and try again.']);
            }
            $targets[] = $this->parseComponentTarget($target);
        }

        return $targets;
    }

    /** @return array{index:int,type:string} */
    private function parseComponentTarget(string $target): array
    {
        if (! str_contains($target, ':')) {
            throw ValidationException::withMessages(['component' => 'The Home component target is invalid.']);
        }
        [$index, $type] = explode(':', $target, 2);
        if (! ctype_digit($index) || ! in_array($type, CustomPageSetting::COMPONENT_TYPES, true)) {
            throw ValidationException::withMessages(['component' => 'The Home component target is invalid.']);
        }

        return ['index' => (int) $index, 'type' => $type];
    }

    /** @return list<array{index:int,type:string,child:int|string}> */
    private function selectedHomeChildTargetData(): array
    {
        $valid = collect($this->componentDataset)->flatMap(
            static fn (array $component): array => collect($component['children'] ?? [])
                ->pluck('target')->filter()->values()->all(),
        )->all();
        $targets = [];
        foreach (array_values(array_unique($this->selectedHomeChildTargets)) as $target) {
            if (! in_array($target, $valid, true)) {
                throw ValidationException::withMessages(['component' => 'The selected Home child changed. Reload and try again.']);
            }
            $parts = explode(':', $target, 3);
            if (count($parts) !== 3 || ! ctype_digit($parts[1])
                || ! in_array($parts[0], ['contact', 'image'], true)) {
                throw ValidationException::withMessages(['component' => 'The selected Home child is invalid.']);
            }
            if ($parts[0] === 'image' && ! ctype_digit($parts[2])) {
                throw ValidationException::withMessages(['component' => 'The selected Image child is invalid.']);
            }

            $targets[] = [
                'index' => (int) $parts[1],
                'type' => $parts[0],
                'child' => $parts[0] === 'image' ? (int) $parts[2] : $parts[2],
            ];
        }

        return $targets;
    }

    private function clearComponentSelection(): void
    {
        $this->selectedComponentTargets = [];
        $this->selectedHomeChildTargets = [];
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
            'available' => 'Home | 30d',
            default => 'Unavailable | 30d',
        };
    }
}
