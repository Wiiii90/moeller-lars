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
use App\Domain\Content\HomeRoutingSettingsService;
use App\Domain\Content\HomeTemplate;
use App\Domain\Content\PublicLegalContent;
use App\Domain\Content\RichTextMediaReference;
use App\Domain\Content\SitePreviewContext;
use App\Domain\Content\SocialLinks;
use App\Domain\Media\MediaTypePolicy;
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
use App\Filament\Support\MediaAssetSelect;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\CustomPageSetting;
use App\Models\HomePresentationSetting;
use App\Models\MediaAsset;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use App\Routing\SiteNodeRoute;
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

    public string $heroDisplayStrategy = 'ordered';

    public string $heroNewestBy = 'artwork_date';

    public int $heroGroupSize = HomeHeroConfigurationService::DEFAULT_GROUP_SIZE;

    /** @var list<array{artwork_id?:int,media_asset_id?:int,weight:int}> */
    public array $manualHeroGroup = [];

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

    #[Session]
    public string $sourceStatusFilter = 'any';

    public int $sourcePage = 1;

    #[Session]
    public int $sourcePerPage = 10;

    /** @var list<int|string> */
    public array $selectedSourceIds = [];

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

    public bool $skipHome = false;

    public ?int $skipTargetSectionId = null;

    /** @var array<string,mixed>|null */
    public ?array $skipTarget = null;

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

    public function updatedSourceStatusFilter(): void
    {
        if (! in_array($this->sourceStatusFilter, ['any', 'published', 'unpublished'], true)) {
            $this->sourceStatusFilter = 'any';
        }
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
        $this->sourceStatusFilter = 'any';
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
                $changed = $dialog->save($data);
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
        if (! in_array($field, ['show_details', 'show_gallery_link', 'show_navigation', 'enable_viewer', 'video_sound_enabled'], true)) {
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

    public function addCandidateAction(): Action
    {
        return AdminDialog::command(
            Action::make('addCandidate')
                ->label('Add Candidate')
                ->schema([
                    MediaAssetSelect::makeId(
                        'media_asset_id',
                        'Image or video from Storage',
                        allowedMimeTypes: [...MediaTypePolicy::IMAGE_MIME_TYPES, ...MediaTypePolicy::VIDEO_MIME_TYPES],
                    )->required(),
                ])
                ->modalHeading('Add Hero candidate'),
            'Add Candidate',
            AdminDialogSize::Large,
        )->action(function (array $data): void {
            $id = filter_var($data['media_asset_id'] ?? null, FILTER_VALIDATE_INT);
            if ($id === false || $id <= 0) {
                throw ValidationException::withMessages(['media_asset_id' => 'Choose an eligible image or video from Storage.']);
            }
            $changed = app(HomeHeroConfigurationService::class)->addCandidate($this->settings(), (int) $id);
            $this->reloadWorkspace();
            if ($changed) {
                app(AdminNotifier::class)->notification(title: 'Hero candidate added', status: 'success');
            }
        });
    }

    public function removeHeroCandidate(string $key): void
    {
        $changed = app(HomeHeroConfigurationService::class)->removeCandidate($this->settings(), $key);
        $this->reloadWorkspace();
        if ($changed) {
            app(AdminNotifier::class)->notification(title: 'Hero candidate removed', status: 'success');
        }
    }

    public function sortHeroArtwork(string $target, int $position): void
    {
        if ($this->heroGroupSource !== 'manual') {
            return;
        }
        $ids = collect($this->heroRailRows)->pluck('id')->values()->all();
        $from = array_search($target, $ids, true);
        if ($from === false) {
            throw ValidationException::withMessages(['manual_group' => 'The Hero group changed. Reload and try again.']);
        }
        $moved = $ids[$from];
        array_splice($ids, $from, 1);
        $position = max(0, min($position, count($ids)));
        array_splice($ids, $position, 0, [$moved]);
        $changed = app(HomeHeroConfigurationService::class)->reorderManualGroup($this->settings(), $ids);
        $this->reloadWorkspace();
        if ($changed) {
            app(AdminNotifier::class)->notification('Hero candidate order updated', status: 'success');
        }
    }

    public function moveHeroArtwork(string $key, string $direction): void
    {
        if ($this->heroGroupSource !== 'manual' || ! in_array($direction, ['up', 'down'], true)) {
            return;
        }
        $ids = collect($this->heroRailRows)->pluck('id')->values()->all();
        $index = array_search($key, $ids, true);
        if ($index === false) {
            return;
        }
        $to = $index + ($direction === 'up' ? -1 : 1);
        if (! isset($ids[$to])) {
            return;
        }
        [$ids[$index], $ids[$to]] = [$ids[$to], $ids[$index]];
        $changed = app(HomeHeroConfigurationService::class)->reorderManualGroup($this->settings(), $ids);
        $this->reloadWorkspace();
        if ($changed) {
            app(AdminNotifier::class)->notification('Hero candidate order updated', status: 'success');
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

    public function toggleVisibleSourceSelection(): void
    {
        $visibleIds = $this->sourceRows()->getCollection()->pluck('id')->map(static fn ($id): int => (int) $id)->filter()->values()->all();
        if ($visibleIds === []) {
            return;
        }
        $selected = collect($this->selectedSourceIds)->map(static fn ($id): int => (int) $id)->filter()->unique()->values();
        if ($selected->intersect($visibleIds)->count() === count($visibleIds)) {
            $this->selectedSourceIds = $selected->reject(static fn (int $id): bool => in_array($id, $visibleIds, true))->values()->all();

            return;
        }
        $this->selectedSourceIds = $selected->merge($visibleIds)->unique()->values()->all();
    }

    /** @return array{enable:bool,disable:bool} */
    public function selectedSourceCapabilities(): array
    {
        $ids = collect($this->selectedSourceIds)->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)->unique()->all();
        if ($ids === []) {
            return ['enable' => false, 'disable' => false];
        }

        $galleries = ArtworkCategory::query()->whereKey($ids)->with('siteSection')->get();

        return [
            'enable' => $galleries->contains(static fn (ArtworkCategory $gallery): bool => ! (bool) $gallery->getAttribute('show_on_home')
                && $gallery->getRelationValue('siteSection')?->getAttribute('state') === 'published'),
            'disable' => $galleries->contains(static fn (ArtworkCategory $gallery): bool => (bool) $gallery->getAttribute('show_on_home')),
        ];
    }

    public function setSelectedGalleryEligibility(bool $enabled): void
    {
        $ids = collect($this->selectedSourceIds)->map(static fn ($id): int => (int) $id)->filter()->unique()->values()->all();
        if ($ids === []) {
            return;
        }
        $changed = DB::transaction(function () use ($ids, $enabled): int {
            $galleries = ArtworkCategory::query()->whereIn('id', $ids)->with('siteSection')->orderBy('id')->lockForUpdate()->get();
            $changed = 0;
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
                $changed += (int) $updated->wasChanged('show_on_home');
            }

            return $changed;
        });

        $this->clearSourceSelection();
        $this->reloadWorkspace();
        if ($changed > 0) {
            app(AdminNotifier::class)->notification(
                title: $enabled ? 'Home source preferences enabled' : 'Home source preferences disabled',
                body: $changed.' '.($changed === 1 ? 'Gallery' : 'Galleries').' updated.',
                status: 'success',
            );
        }
    }

    public function sourceRows(): LengthAwarePaginator
    {
        $candidateGroup = app(HomeHeroResolver::class)->resolve($this->settings())['group'];
        $grouped = $candidateGroup->groupBy(
            static fn (HomeHeroCandidate $candidate): int => (int) ($candidate->gallery()?->getKey() ?? 0),
        );
        $standalone = $grouped->get(0, collect());
        $search = trim($this->sourceSearch);
        $needle = mb_strtolower($search);

        /** @var Builder<ArtworkCategory> $query */
        $query = ArtworkCategory::query()->whereHas('siteSection')->with('siteSection')
            ->withCount('artworks');

        if ($search !== '') {
            $matchedGalleryIds = $candidateGroup
                ->filter(static fn (HomeHeroCandidate $candidate): bool => str_contains(
                    mb_strtolower($candidate->title()), $needle,
                ))
                ->map(static fn (HomeHeroCandidate $candidate): int => (int) ($candidate->gallery()?->getKey() ?? 0))
                ->filter()->unique()->values()->all();

            $query->where(function (Builder $galleries) use ($needle, $matchedGalleryIds): void {
                $galleries->whereRaw('LOWER(name) LIKE ?', ['%'.$needle.'%']);
                if ($matchedGalleryIds !== []) {
                    $galleries->orWhereIn('id', $matchedGalleryIds);
                }
            });
        }
        if ($this->sourceStatusFilter === 'published') {
            $query->whereHas('siteSection', fn (Builder $section): Builder => $section->where('state', 'published'));
        } elseif ($this->sourceStatusFilter === 'unpublished') {
            $query->whereHas('siteSection', fn (Builder $section): Builder => $section->where('state', '<>', 'published'));
        }

        $showStandalone = $standalone->isNotEmpty() && $this->sourceStatusFilter !== 'unpublished'
            && ($search === '' || str_contains('standalone storage media', $needle)
                || $standalone->contains(static fn (HomeHeroCandidate $candidate): bool => str_contains(
                    mb_strtolower($candidate->title()), $needle,
                )));

        $galleryTotal = (clone $query)->count();
        $total = $galleryTotal + (int) $showStandalone;
        $perPage = in_array($this->sourcePerPage, [10, 25], true) ? $this->sourcePerPage : 10;
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($this->sourcePage, $lastPage));
        $offset = ($page - 1) * $perPage;

        /** @var EloquentCollection<int,ArtworkCategory> $galleries */
        $galleries = $query->orderBy('name')->offset($offset)->limit($perPage)->get();
        $rows = $galleries->map(function (ArtworkCategory $gallery) use ($grouped): array {
            /** @var SiteSection|null $section */
            $section = $gallery->getRelationValue('siteSection');
            $state = (string) ($section?->getAttribute('state') ?? 'hidden');
            $preference = (bool) $gallery->getAttribute('show_on_home');
            $galleryCandidates = $grouped->get((int) $gallery->getKey(), collect());

            return [
                'id' => (int) $gallery->getKey(),
                'standalone' => false,
                'name' => (string) $gallery->getAttribute('name'),
                'preference_enabled' => $preference,
                'can_enable' => $state === 'published',
                'state' => $state,
                'status_label' => $state === 'published' ? 'Published' : 'Unpublished',
                'artworks_count' => (int) $gallery->getAttribute('artworks_count'),
                'candidate_count' => $galleryCandidates->count(),
                'workspace_url' => GalleryWorkspace::getUrl(['gallery' => $gallery->getKey()]),
                'candidates' => $galleryCandidates->map(fn (HomeHeroCandidate $candidate): array => $this->candidateRow($candidate))->values()->all(),
            ];
        })->all();

        if ($showStandalone && $offset <= $galleryTotal && ($offset + $perPage) > $galleryTotal) {
            $rows[] = [
                'id' => 0,
                'standalone' => true,
                'name' => 'Standalone Storage media',
                'preference_enabled' => false,
                'can_enable' => false,
                'state' => 'published',
                'status_label' => 'Available',
                'artworks_count' => $standalone->count(),
                'candidate_count' => $standalone->count(),
                'workspace_url' => null,
                'candidates' => $standalone->map(fn (HomeHeroCandidate $candidate): array => $this->candidateRow($candidate))->values()->all(),
            ];
        }

        return new LengthAwarePaginator($rows, $total, $perPage, $page, ['path' => request()->url(), 'pageName' => 'sourcePage']);
    }

    private function reloadWorkspace(): void
    {
        $this->loadEditorSocialLinks();
        $settings = $this->settings();
        $configuration = app(HomePresentationEditorialService::class)->configuration($settings);
        $heroConfiguration = app(HomeHeroConfigurationService::class)->configuration($settings);
        $routingConfiguration = app(HomeRoutingSettingsService::class)->configuration($settings);
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
        $this->heroDisplayStrategy = $heroConfiguration['display_strategy'];
        $this->heroNewestBy = $heroConfiguration['newest_by'];
        $this->heroGroupSize = $heroConfiguration['group_size'];
        $this->manualHeroGroup = $heroConfiguration['manual_group'];
        $this->rotationIntervalCount = $heroConfiguration['rotation_interval']['count'];
        $this->rotationIntervalUnit = $heroConfiguration['rotation_interval']['unit'];
        $this->rotationStartedAt = $heroConfiguration['rotation_started_at'];
        $this->publicSiteGate = (bool) $configuration[HomeTemplate::UnderConstruction->value]['public_site_gate'];
        $this->skipHome = $routingConfiguration['skip_home'];
        $this->skipTargetSectionId = $routingConfiguration['skip_target_section_id'];

        $this->selectionIssue = null;
        $this->currentArtwork = null;
        $this->heroRailRows = [];
        $this->componentDataset = [];
        $this->components = [];
        $this->skipTarget = null;
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
            HomeTemplate::SkipHome => $this->loadSkipWorkspace(),
        };
        $this->refreshMetrics();
    }

    private function loadArtworkWorkspace(HomePresentationSetting $settings): void
    {
        $publicArtworks = app(PublicArtworkQuery::class);
        $statistics = $publicArtworks->homeCandidateStatistics();
        $this->eligibleArtworkCount = $statistics['eligible'];
        $this->newestEligibleYear = $statistics['newest_year'];
        $this->sourceGalleryCount = ArtworkCategory::query()->where('show_on_home', true)
            ->whereHas('siteSection', fn (Builder $section): Builder => $section->where('state', 'published'))->count();

        $resolution = app(HomeHeroResolver::class)->resolve($settings);
        $this->candidatePoolCount = $resolution['group']->count();
        $this->nextRotationAt = $resolution['next_rotation_at']?->toIso8601String();
        $current = $resolution['current'];
        if ($current instanceof HomeHeroCandidate) {
            $this->currentArtwork = $this->candidateRow($current);
        } else {
            $this->selectionIssue = $this->heroGroupSource === 'manual'
                ? 'No stored Manual candidate is currently eligible.'
                : 'No eligible Hero candidate is available.';
        }

        if ($this->heroGroupSource === 'manual') {
            $this->heroRailRows = $this->manualHeroRows($resolution);

            return;
        }

        $sequence = $this->heroDisplayStrategy === 'sequential' ? $resolution['sequence'] : $resolution['group'];
        $currentId = $current instanceof HomeHeroCandidate ? $current->key() : null;
        $this->heroRailRows = $sequence->values()->map(function (HomeHeroCandidate $candidate, int $index) use ($currentId, $resolution): array {
            $row = $this->candidateRow($candidate);
            $row['eligible'] = true;
            $row['weight'] = $resolution['weights'][$candidate->key()] ?? null;
            $row['percentage'] = null;
            $row['sequence_label'] = $this->heroDisplayStrategy === 'sequential'
                ? ($index === 0 ? 'Current' : ($index === 1 ? 'Next' : 'Then'))
                : ($row['id'] === $currentId ? 'Current' : null);

            return $row;
        })->all();
    }

    /** @param array<string,mixed> $resolution
     * @return list<array<string,mixed>>
     */
    private function manualHeroRows(array $resolution): array
    {
        $storedKeys = array_map(HomeHeroConfigurationService::memberKey(...), $this->manualHeroGroup);
        $eligible = $resolution['group']->keyBy(static fn (HomeHeroCandidate $candidate): string => $candidate->key());
        $sequenceKeys = $resolution['sequence']->map(static fn (HomeHeroCandidate $candidate): string => $candidate->key())->values()->all();
        $weightByKey = collect($this->manualHeroGroup)->mapWithKeys(
            static fn (array $member): array => [HomeHeroConfigurationService::memberKey($member) => $member['weight']],
        )->all();

        $displayKeys = $this->heroDisplayStrategy === 'sequential'
            ? array_values(array_unique([...$sequenceKeys, ...$storedKeys]))
            : $storedKeys;
        $rows = [];
        foreach ($displayKeys as $displayIndex => $key) {
            $candidate = $eligible->get($key);
            $row = $candidate instanceof HomeHeroCandidate ? $this->candidateRow($candidate) : [
                'id' => $key,
                'title' => 'Unavailable candidate '.$key,
                'year' => null,
                'date' => null,
                'gallery' => null,
                'gallery_id' => null,
                'thumbnail_url' => null,
                'gallery_url' => null,
            ];
            $isEligible = $candidate instanceof HomeHeroCandidate;
            $sequenceIndex = array_search($key, $sequenceKeys, true);
            $weight = (int) ($weightByKey[$key] ?? 0);
            $row['eligible'] = $isEligible;
            $row['weight'] = $weight;
            $row['percentage'] = number_format($weight / 100, 2, '.', '');
            $row['sequence_label'] = ! $isEligible
                ? 'Unavailable'
                : ($this->heroDisplayStrategy === 'sequential'
                    ? ($sequenceIndex === 0 ? 'Current' : ($sequenceIndex === 1 ? 'Next' : 'Then'))
                    : null);
            $row['can_move_up'] = $displayIndex > 0;
            $row['can_move_down'] = $displayIndex < count($displayKeys) - 1;
            $rows[] = $row;
        }

        return $rows;
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
                    $asset instanceof MediaAsset && filled($asset->getAttribute('alt_text')) ? 'ALT · '.Str::limit((string) $asset->getAttribute('alt_text'), 90) : 'Storage image',
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

    private function loadSkipWorkspace(): void
    {
        $target = app(HomePresentationResolver::class)->skipTarget();
        if (! $target instanceof SiteSection) {
            return;
        }
        $path = app(SiteNodeRoute::class)->path($target);
        $this->skipTarget = [
            'id' => (int) $target->getKey(),
            'label' => (string) ($target->getAttribute('navigation_label') ?: $target->getAttribute('title')),
            'type' => $target->nodeType()->label($target->journalTemplate()),
            'path' => $path ?? '—',
            'url' => app(SiteNodeRoute::class)->url($target),
        ];
    }

    private function refreshMetrics(): void
    {
        $template = HomeTemplate::from($this->template);
        if ($template === HomeTemplate::Artwork) {
            $analyticsDescription = $this->analyticsDescription();
            $this->metrics = [
                ['role' => 'visits', 'label' => 'Visits · 30d', 'value' => $this->formatMetric($this->homeVisits), 'description' => $analyticsDescription],
                ['role' => 'views', 'label' => 'Views · 30d', 'value' => $this->formatMetric($this->homeViews), 'description' => $analyticsDescription],
                ['role' => 'sources', 'label' => 'Source Galleries', 'value' => number_format($this->sourceGalleryCount), 'description' => 'Effective sources'],
                ['role' => 'eligible', 'label' => 'Eligible Artworks', 'value' => number_format($this->eligibleArtworkCount), 'description' => 'Home eligible'],
                ['role' => 'candidates', 'label' => 'Candidate Group', 'value' => number_format($this->heroGroupSource === 'manual' ? count($this->manualHeroGroup) : $this->candidatePoolCount), 'description' => $this->heroGroupSource === 'manual' ? 'Stored members' : 'Effective group'],
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
            'year' => $artwork?->getAttribute('year'),
            'date' => $artwork instanceof Artwork ? ArtworkDate::fromArtwork($artwork) : null,
            'gallery' => $gallery instanceof ArtworkCategory ? (string) $gallery->getAttribute('name') : null,
            'gallery_id' => $gallery?->getKey(),
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
            return 'Loading · 30d';
        }

        return match ($this->homeAnalyticsStatus) {
            'stale' => 'Cached · 30d',
            'available' => 'Home · 30d',
            default => 'Unavailable · 30d',
        };
    }
}
