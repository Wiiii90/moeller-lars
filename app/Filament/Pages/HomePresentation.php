<?php

namespace App\Filament\Pages;

use App\Domain\Analytics\ArtistReportingService;
use App\Domain\Artwork\GalleryEditorialService;
use App\Domain\Artwork\PublicArtworkQuery;
use App\Domain\Content\HomeHeroConfigurationService;
use App\Domain\Content\HomePresentationEditorialService;
use App\Domain\Content\HomePresentationResolver;
use App\Domain\Content\HomeTemplate;
use App\Domain\Content\RichTextMediaReference;
use App\Domain\Content\SitePreviewContext;
use App\Domain\Content\SiteSectionEditorialService;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Support\AdminRichText;
use App\Filament\Support\MediaAssetSelect;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\HomePresentationSetting;
use App\Models\MediaAsset;
use App\Models\SiteSection;
use App\Routing\SiteNodeRoute;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class HomePresentation extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Home';

    protected static ?string $slug = 'pages/home';

    protected string $view = 'filament.pages.home-presentation';

    /** @var list<array{label:string,value:string,description:string}> */
    public array $metrics = [];

    public string $template = 'artwork';

    public string $templateLabel = 'Hero Artwork';

    public string $previewUrl = '';

    public int $settingsId = 0;

    public int $homeSectionId = 0;

    public bool $showHomeInNavigation = true;

    public bool $artworkShowDetails = true;

    public bool $artworkShowGalleryLink = true;

    public bool $publicSiteGate = false;

    public string $heroMode = 'automatic';

    public ?int $fixedArtworkId = null;

    public string $heroSelection = 'newest';

    public string $heroNewestBy = 'artwork_date';

    public int $heroGroupSize = HomeHeroConfigurationService::DEFAULT_GROUP_SIZE;

    public string $heroPoolRule = 'all';

    public ?int $heroPoolYear = null;

    /** @var list<int> */
    public array $manualHeroCandidateIds = [];

    /** @var array<string, mixed>|null */
    public ?array $currentArtwork = null;

    /** @var list<array<string, mixed>> */
    public array $heroCandidates = [];

    public int $candidatePoolCount = 0;

    public ?string $selectionIssue = null;

    public int $eligibleArtworkCount = 0;

    public int $sourceGalleryCount = 0;

    public ?int $newestEligibleYear = null;

    public string $sourceSearch = '';

    public string $sourceStatusFilter = 'any';

    public string $sourceHomeFilter = 'any';

    public int $sourcePage = 1;

    public int $sourcePerPage = 10;

    /** @var list<int|string> */
    public array $selectedSourceIds = [];

    /** @var list<array<string, mixed>> */
    public array $componentDataset = [];

    /** @var list<array<string, mixed>> */
    public array $components = [];

    /** @var array<string, string> */
    public array $componentTypeOptions = [
        'image' => 'Image',
        'heading' => 'Heading',
        'rich_text' => 'Rich Text',
        'divider' => 'Divider',
    ];

    /** @var array<string, string> */
    public array $newComponentOptions = [
        'image' => 'Image',
        'rich_text' => 'Rich Text',
        'divider' => 'Divider',
    ];

    public string $componentSearch = '';

    public string $componentType = 'any';

    /** @var list<string> */
    public array $selectedComponentTargets = [];

    /** @var array{components:int,images:int,headings:int,rich_text:int,dividers:int,media_references:int} */
    public array $componentStats = [
        'components' => 0,
        'images' => 0,
        'headings' => 0,
        'rich_text' => 0,
        'dividers' => 0,
        'media_references' => 0,
    ];

    /** @var array<string, mixed>|null */
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

    public function updatedSourceHomeFilter(): void
    {
        if (! in_array($this->sourceHomeFilter, ['any', 'enabled', 'disabled'], true)) {
            $this->sourceHomeFilter = 'any';
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
        $this->sourceHomeFilter = 'any';
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

    public function settingsAction(): Action
    {
        return Action::make('settings')
            ->label('Settings')
            ->fillForm(fn (): array => [
                'template' => $this->template,
                'show_in_navigation' => $this->showHomeInNavigation,
                'show_details' => $this->artworkShowDetails,
                'show_gallery_link' => $this->artworkShowGalleryLink,
                'hero_mode' => $this->heroMode,
                'fixed_artwork_id' => $this->fixedArtworkId,
                'automatic_selection' => $this->heroSelection,
                'newest_by' => $this->heroNewestBy,
                'group_size' => $this->heroGroupSize,
                'pool_rule' => $this->heroPoolRule,
                'pool_year' => $this->heroPoolYear,
                'manual_include_ids' => $this->manualHeroCandidateIds,
                'public_site_gate' => $this->publicSiteGate,
            ])
            ->schema([
                Select::make('template')
                    ->label('Template')
                    ->options(HomeTemplate::options())
                    ->required()
                    ->live(),
                Toggle::make('show_in_navigation')
                    ->label('Show Home in navigation')
                    ->helperText('Only the public Home link changes. The Home page remains available at /.'),
                Select::make('hero_mode')
                    ->label('Mode')
                    ->options([
                        'manual' => 'Manual',
                        'automatic' => 'Automatic',
                    ])
                    ->required()
                    ->live()
                    ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value),
                $this->heroArtworkSelect('fixed_artwork_id', 'Hero artwork')
                    ->required(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value
                        && $get('hero_mode') === 'manual')
                    ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value
                        && $get('hero_mode') === 'manual'),
                TextInput::make('group_size')
                    ->label('Group size')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(HomeHeroConfigurationService::MAX_GROUP_SIZE)
                    ->required(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value
                        && $get('hero_mode') === 'automatic')
                    ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value
                        && $get('hero_mode') === 'automatic'),
                Select::make('newest_by')
                    ->label('Newest by')
                    ->options([
                        'artwork_date' => 'Artwork date',
                        'added' => 'Added',
                    ])
                    ->required(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value
                        && $get('hero_mode') === 'automatic')
                    ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value
                        && $get('hero_mode') === 'automatic'),
                Select::make('automatic_selection')
                    ->label('Selection')
                    ->options([
                        'newest' => 'Newest',
                        'random' => 'Random',
                    ])
                    ->required(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value
                        && $get('hero_mode') === 'automatic')
                    ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value
                        && $get('hero_mode') === 'automatic'),
                Select::make('pool_rule')
                    ->label('Candidate filter')
                    ->options([
                        'all' => 'All eligible',
                        'year' => 'Specific Year',
                    ])
                    ->required(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value
                        && $get('hero_mode') === 'automatic')
                    ->live()
                    ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value
                        && $get('hero_mode') === 'automatic'),
                TextInput::make('pool_year')
                    ->label('Year')
                    ->numeric()
                    ->minValue(1000)
                    ->maxValue(3000)
                    ->required(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value
                        && $get('hero_mode') === 'automatic'
                        && $get('pool_rule') === 'year')
                    ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value
                        && $get('hero_mode') === 'automatic'
                        && $get('pool_rule') === 'year'),
                $this->heroArtworkSelect('manual_include_ids', 'Additional includes', multiple: true)
                    ->helperText('Adds eligible artworks outside a Specific Year filter before the configured group size is applied.')
                    ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value
                        && $get('hero_mode') === 'automatic'),
                Toggle::make('show_details')
                    ->label('Show artwork information')
                    ->helperText('Shows the title, material, dimensions and other artwork label information.')
                    ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value),
                Toggle::make('show_gallery_link')
                    ->label('Show Gallery link')
                    ->helperText('Shows the Gallery context button independently from the artwork information.')
                    ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value),
                Toggle::make('public_site_gate')
                    ->label('Temporarily gate the public site')
                    ->helperText('Normal public content URLs return to Home while the Under Construction template is active. Admin and protected Preview stay available.')
                    ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::UnderConstruction->value),
                Placeholder::make('skip_target')
                    ->label('Current redirect target')
                    ->content(fn (): string => $this->skipTarget === null
                        ? 'No published top-level page exists after Home. The public root safely remains on Home.'
                        : $this->skipTarget['label'].' · '.$this->skipTarget['path'])
                    ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::SkipHome->value),
                Placeholder::make('custom_components')
                    ->label('Custom composition')
                    ->content('Components are edited in the Home workspace.')
                    ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Custom->value),
            ])
            ->modalHeading('Home settings')
            ->modalSubmitActionLabel('Save changes')
            ->action(function (array $data): void {
                $template = HomeTemplate::from((string) $data['template']);

                if ($template === HomeTemplate::Artwork) {
                    app(HomeHeroConfigurationService::class)->updateArtworkSettings(
                        $this->settings(),
                        [
                            'show_details' => $data['show_details'] ?? $this->artworkShowDetails,
                            'show_gallery_link' => $data['show_gallery_link'] ?? $this->artworkShowGalleryLink,
                            'mode' => $data['hero_mode'] ?? $this->heroMode,
                            'hero_artwork_id' => $data['fixed_artwork_id'] ?? $this->fixedArtworkId,
                            'selection' => $data['automatic_selection'] ?? $this->heroSelection,
                            'newest_by' => $data['newest_by'] ?? $this->heroNewestBy,
                            'group_size' => $data['group_size'] ?? $this->heroGroupSize,
                            'candidate_filter' => $data['pool_rule'] ?? $this->heroPoolRule,
                            'specific_year' => $data['pool_year'] ?? $this->heroPoolYear,
                            'manual_include_ids' => $data['manual_include_ids'] ?? $this->manualHeroCandidateIds,
                        ],
                    );
                } else {
                    $input = [];
                    if ($template === HomeTemplate::UnderConstruction) {
                        $input['public_site_gate'] = $data['public_site_gate'] ?? $this->publicSiteGate;
                    }

                    app(HomePresentationEditorialService::class)->updateSettings(
                        $this->settings(),
                        $template,
                        $input,
                    );
                }

                app(SiteSectionEditorialService::class)->updatePlacement(
                    $this->homeSection(),
                    'published',
                    (bool) ($data['show_in_navigation'] ?? false),
                    null,
                );
                $this->showHomeInNavigation = (bool) ($data['show_in_navigation'] ?? false);

                $this->reloadWorkspace();
                Notification::make()->title('Home settings saved')->success()->send();
            });
    }

    public function addComponentAction(): Action
    {
        return Action::make('addComponent')
            ->label('Add component')
            ->schema([
                Select::make('kind')
                    ->label('Component')
                    ->options($this->newComponentOptions)
                    ->required()
                    ->live(),
                ...$this->homeRichTextFields('kind', 'rich_text', required: true),
                MediaAssetSelect::makeId('media_asset_id', 'Image from Media Files', true)
                    ->required(fn (callable $get): bool => $get('kind') === 'image')
                    ->visible(fn (callable $get): bool => $get('kind') === 'image'),
                Toggle::make('image_decorative')
                    ->label('Decorative image')
                    ->helperText('Leave off for content images. Canonical ALT text is managed in Media Files.')
                    ->default(false)
                    ->visible(fn (callable $get): bool => $get('kind') === 'image'),
            ])
            ->modalHeading('Add Home component')
            ->modalSubmitActionLabel('Add component')
            ->action(function (array $data): void {
                $kind = (string) ($data['kind'] ?? '');
                $component = match ($kind) {
                    'rich_text' => [
                        'type' => 'text',
                        'title' => null,
                        'body' => filled($data['body'] ?? null) ? (string) $data['body'] : null,
                    ],
                    'image' => [
                        'type' => 'image',
                        'media_asset_id' => is_numeric($data['media_asset_id'] ?? null)
                            ? (int) $data['media_asset_id']
                            : null,
                        'image_decorative' => (bool) ($data['image_decorative'] ?? false),
                    ],
                    'divider' => ['type' => 'divider'],
                    default => throw ValidationException::withMessages([
                        'kind' => 'Choose a supported Home component.',
                    ]),
                };

                app(HomePresentationEditorialService::class)->addComponent(
                    $this->settings(),
                    $this->componentTemplate(),
                    $component,
                );

                $this->reloadWorkspace();
                Notification::make()->title('Home component added')->success()->send();
            });
    }

    public function editComponentAction(): Action
    {
        return Action::make('editComponent')
            ->label('Edit')
            ->fillForm(function (array $arguments): array {
                $component = $this->componentFromArguments($arguments);

                return [
                    'type' => $component['type'],
                    'editor_kind' => $this->editorKind($component),
                    'title' => $component['title'] ?? null,
                    'body' => $component['body'] ?? null,
                    'media_asset_id' => $component['media_asset_id'] ?? null,
                    'image_decorative' => (bool) ($component['image_decorative'] ?? false),
                ];
            })
            ->schema([
                Hidden::make('type'),
                Hidden::make('editor_kind'),
                TextInput::make('title')
                    ->label('Heading')
                    ->maxLength(160)
                    ->required(fn (callable $get): bool => $get('editor_kind') === 'heading')
                    ->visible(fn (callable $get): bool => $get('editor_kind') === 'heading'),
                ...$this->homeRichTextFields('editor_kind', 'rich_text', required: true),
                MediaAssetSelect::makeId('media_asset_id', 'Image from Media Files', true)
                    ->required(fn (callable $get): bool => $get('type') === 'image')
                    ->visible(fn (callable $get): bool => $get('type') === 'image'),
                Toggle::make('image_decorative')
                    ->label('Decorative image')
                    ->helperText('Leave off for content images. Canonical ALT text is managed in Media Files.')
                    ->visible(fn (callable $get): bool => $get('type') === 'image'),
            ])
            ->modalHeading('Edit Home component')
            ->modalSubmitActionLabel('Save component')
            ->action(function (array $data, array $arguments): void {
                $current = $this->componentFromArguments($arguments);
                $type = (string) $current['type'];
                $editorKind = $this->editorKind($current);
                $component = match ($type) {
                    'text' => match ($editorKind) {
                        'heading' => [
                            'type' => 'text',
                            'title' => trim((string) ($data['title'] ?? '')),
                            'body' => null,
                        ],
                        'rich_text' => [
                            'type' => 'text',
                            'title' => null,
                            'body' => filled($data['body'] ?? null) ? (string) $data['body'] : null,
                        ],
                        default => throw ValidationException::withMessages([
                            'component' => 'This text component has no supported editor mode.',
                        ]),
                    },
                    'image' => [
                        'type' => 'image',
                        'media_asset_id' => is_numeric($data['media_asset_id'] ?? null)
                            ? (int) $data['media_asset_id']
                            : null,
                        'image_decorative' => (bool) ($data['image_decorative'] ?? false),
                    ],
                    default => throw ValidationException::withMessages([
                        'component' => 'This component has no editable fields.',
                    ]),
                };

                app(HomePresentationEditorialService::class)->updateComponent(
                    $this->settings(),
                    $this->componentTemplate(),
                    (int) $arguments['index'],
                    $type,
                    $component,
                );

                $this->reloadWorkspace();
                Notification::make()->title('Home component saved')->success()->send();
            });
    }

    public function removeComponentAction(): Action
    {
        return Action::make('removeComponent')
            ->label('Delete')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete Home component?')
            ->modalDescription('The component is removed from this Home template. Other template configurations are unchanged.')
            ->action(function (array $arguments): void {
                $component = $this->componentFromArguments($arguments);
                app(HomePresentationEditorialService::class)->deleteComponent(
                    $this->settings(),
                    $this->componentTemplate(),
                    (int) $arguments['index'],
                    (string) $component['type'],
                );

                $this->reloadWorkspace();
                Notification::make()->title('Home component deleted')->success()->send();
            });
    }

    public function deleteSelectedComponentsAction(): Action
    {
        return Action::make('deleteSelectedComponents')
            ->label('Delete selected')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete selected Home components?')
            ->action(function (): void {
                $targets = $this->selectedComponentTargetData();
                if ($targets === []) {
                    return;
                }

                app(HomePresentationEditorialService::class)->deleteComponents(
                    $this->settings(),
                    $this->componentTemplate(),
                    $targets,
                );
                $count = count($targets);
                $this->reloadWorkspace();
                Notification::make()
                    ->title('Selected Home components deleted')
                    ->body($count.' component'.($count === 1 ? '' : 's').' deleted.')
                    ->success()
                    ->send();
            });
    }

    public function moveComponent(int $index, string $expectedType, string $direction): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }

        app(HomePresentationEditorialService::class)->moveComponent(
            $this->settings(),
            $this->componentTemplate(),
            $index,
            $expectedType,
            $direction,
        );

        $this->reloadWorkspace();
        Notification::make()->title('Home component order updated')->success()->send();
    }

    public function sortComponent(string $target, int $position): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }

        $targets = collect($this->componentDataset)
            ->pluck('target')
            ->filter(static fn (mixed $value): bool => is_string($value))
            ->values()
            ->all();
        $from = array_search($target, $targets, true);
        if ($from === false) {
            throw ValidationException::withMessages([
                'component' => 'The component sequence changed. Reload the workspace and try again.',
            ]);
        }

        $moved = $targets[$from];
        array_splice($targets, $from, 1);
        $position = max(0, min($position, count($targets)));
        array_splice($targets, $position, 0, [$moved]);

        app(HomePresentationEditorialService::class)->reorderComponents(
            $this->settings(),
            $this->componentTemplate(),
            array_map(fn (string $value): array => $this->parseComponentTarget($value), $targets),
        );

        $this->reloadWorkspace();
        Notification::make()->title('Home component order updated')->success()->send();
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
            $this->settings(),
            $this->componentTemplate(),
            $targets,
            $direction,
        );
        $count = count($targets);
        $this->reloadWorkspace();

        if ($changed) {
            Notification::make()
                ->title('Selected Home components moved')
                ->body($count.' component'.($count === 1 ? '' : 's').' updated.')
                ->success()
                ->send();
        }
    }

    public function toggleGalleryEligibility(int $galleryId): void
    {
        /** @var ArtworkCategory $gallery */
        $gallery = ArtworkCategory::query()->findOrFail($galleryId);

        app(GalleryEditorialService::class)->update($gallery, [
            'name' => (string) $gallery->getAttribute('name'),
            'description' => $gallery->getAttribute('description'),
            'show_on_home' => ! (bool) $gallery->getAttribute('show_on_home'),
        ]);

        $this->clearSourceSelection();
        $this->reloadWorkspace();
        Notification::make()->title('Home source updated')->success()->send();
    }

    public function toggleVisibleSourceSelection(): void
    {
        $visibleIds = $this->sourceRows()->getCollection()
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();
        if ($visibleIds === []) {
            return;
        }

        $selected = collect($this->selectedSourceIds)
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        $selectedVisibleCount = $selected->intersect($visibleIds)->count();

        if ($selectedVisibleCount === count($visibleIds)) {
            $this->selectedSourceIds = $selected
                ->reject(static fn (int $id): bool => in_array($id, $visibleIds, true))
                ->values()
                ->all();

            return;
        }

        $this->selectedSourceIds = $selected
            ->merge($visibleIds)
            ->unique()
            ->values()
            ->all();
    }

    public function setSelectedGalleryEligibility(bool $enabled): void
    {
        $ids = collect($this->selectedSourceIds)
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return;
        }

        /** @var EloquentCollection<int, ArtworkCategory> $galleries */
        $galleries = ArtworkCategory::query()->whereIn('id', $ids)->get();
        foreach ($galleries as $gallery) {
            if ((bool) $gallery->getAttribute('show_on_home') === $enabled) {
                continue;
            }

            app(GalleryEditorialService::class)->update($gallery, [
                'name' => (string) $gallery->getAttribute('name'),
                'description' => $gallery->getAttribute('description'),
                'show_on_home' => $enabled,
            ]);
        }

        $count = $galleries->count();
        $this->clearSourceSelection();
        $this->reloadWorkspace();
        Notification::make()
            ->title($enabled ? 'Home sources enabled' : 'Home sources disabled')
            ->body($count.' '.($count === 1 ? 'Gallery' : 'Galleries').' updated.')
            ->success()
            ->send();
    }

    public function sourceRows(): LengthAwarePaginator
    {
        /** @var Builder<ArtworkCategory> $query */
        $query = ArtworkCategory::query()
            ->whereHas('siteSection')
            ->with('siteSection')
            ->withCount([
                'artworks as published_artworks_count' => static fn ($query) => $query->where('state', 'published'),
            ])
            ->withMax([
                'artworks as newest_published_year' => static fn ($query) => $query
                    ->where('state', 'published')
                    ->whereNotNull('work_year'),
            ], 'work_year');

        $search = trim($this->sourceSearch);
        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->whereRaw('LOWER(name) LIKE ?', [$needle]);
        }

        if ($this->sourceStatusFilter === 'published') {
            $query->whereHas('siteSection', fn (Builder $section): Builder => $section->where('state', 'published'));
        } elseif ($this->sourceStatusFilter === 'unpublished') {
            $query->whereHas('siteSection', fn (Builder $section): Builder => $section->where('state', '<>', 'published'));
        }

        if ($this->sourceHomeFilter === 'enabled') {
            $query->where('show_on_home', true);
        } elseif ($this->sourceHomeFilter === 'disabled') {
            $query->where('show_on_home', false);
        }

        $total = (clone $query)->count();
        $perPage = in_array($this->sourcePerPage, [10, 25], true) ? $this->sourcePerPage : 10;
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($this->sourcePage, $lastPage));

        /** @var EloquentCollection<int, ArtworkCategory> $galleries */
        $galleries = $query
            ->orderBy('name')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $galleryIds = $galleries
            ->map(static fn (ArtworkCategory $gallery): int => (int) $gallery->getKey())
            ->all();

        $candidateGroup = $this->heroMode === 'automatic'
            ? app(PublicArtworkQuery::class)->configuredHomeCandidates(
                $this->heroGroupSize,
                $this->heroNewestBy,
                $this->effectivePoolRule(),
                $this->effectivePoolYear(),
                $this->effectiveManualCandidateIds(),
            )
            : new EloquentCollection;
        $candidates = $candidateGroup
            ->filter(static fn (Artwork $artwork): bool => in_array((int) $artwork->getAttribute('artwork_category_id'), $galleryIds, true))
            ->groupBy(fn (Artwork $artwork): int => (int) $artwork->getAttribute('artwork_category_id'));

        $rows = $galleries->map(function (ArtworkCategory $gallery) use ($candidates): array {
            /** @var SiteSection|null $section */
            $section = $gallery->getRelationValue('siteSection');
            $galleryCandidates = $candidates->get((int) $gallery->getKey(), collect());

            return [
                'id' => (int) $gallery->getKey(),
                'name' => (string) $gallery->getAttribute('name'),
                'eligible' => (bool) $gallery->getAttribute('show_on_home'),
                'state' => (string) ($section?->getAttribute('state') ?? 'hidden'),
                'status_label' => (string) ($section?->getAttribute('state') ?? 'hidden') === 'published'
                    ? 'Published'
                    : 'Unpublished',
                'published_artworks' => (int) $gallery->getAttribute('published_artworks_count'),
                'newest_year' => $gallery->getAttribute('newest_published_year'),
                'workspace_url' => ArtworkResource::getUrl('gallery', ['gallery' => $gallery->getKey()]),
                'candidates' => $galleryCandidates
                    ->take(5)
                    ->map(fn (Artwork $artwork): array => $this->candidateRow($artwork))
                    ->values()
                    ->all(),
            ];
        })->all();

        return new LengthAwarePaginator(
            $rows,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => 'sourcePage',
            ],
        );
    }

    private function reloadWorkspace(): void
    {
        $settings = $this->settings();
        $configuration = app(HomePresentationEditorialService::class)->configuration($settings);
        $heroConfiguration = app(HomeHeroConfigurationService::class)->configuration($settings);
        $this->settingsId = (int) $settings->getKey();
        $this->homeSectionId = (int) $settings->getAttribute('site_section_id');

        if ($settings->relationLoaded('siteSection')) {
            /** @var SiteSection|null $homeSection */
            $homeSection = $settings->getRelationValue('siteSection');
            if ($homeSection instanceof SiteSection) {
                $this->showHomeInNavigation = (bool) $homeSection->getAttribute('show_in_navigation');
            }
        }

        $template = $settings->template();
        $this->template = $template->value;
        $this->templateLabel = $template->label();

        $this->artworkShowDetails = $heroConfiguration['show_details'];
        $this->artworkShowGalleryLink = $heroConfiguration['show_gallery_link'];
        $this->heroMode = $heroConfiguration['mode'];
        $this->fixedArtworkId = $heroConfiguration['hero_artwork_id'];
        $this->heroSelection = $heroConfiguration['selection'];
        $this->heroNewestBy = $heroConfiguration['newest_by'];
        $this->heroGroupSize = $heroConfiguration['group_size'];
        $this->heroPoolRule = $heroConfiguration['candidate_filter'];
        $this->heroPoolYear = $heroConfiguration['specific_year'];
        $this->manualHeroCandidateIds = $heroConfiguration['manual_include_ids'];
        $this->publicSiteGate = (bool) $configuration[HomeTemplate::UnderConstruction->value]['public_site_gate'];

        $this->selectionIssue = null;
        $this->currentArtwork = null;
        $this->heroCandidates = [];
        $this->componentDataset = [];
        $this->components = [];
        $this->skipTarget = null;
        $this->eligibleArtworkCount = 0;
        $this->sourceGalleryCount = 0;
        $this->newestEligibleYear = null;
        $this->candidatePoolCount = 0;
        $this->componentStats = [
            'components' => 0,
            'images' => 0,
            'headings' => 0,
            'rich_text' => 0,
            'dividers' => 0,
            'media_references' => 0,
        ];
        $this->clearComponentSelection();

        if (! in_array($template, [HomeTemplate::UnderConstruction, HomeTemplate::Custom], true)) {
            $this->componentSearch = '';
            $this->componentType = 'any';
        }

        match ($template) {
            HomeTemplate::Artwork => $this->loadArtworkWorkspace(),
            HomeTemplate::UnderConstruction,
            HomeTemplate::Custom => $this->loadComponentWorkspace($template, $configuration),
            HomeTemplate::SkipHome => $this->loadSkipWorkspace(),
        };

        $this->refreshMetrics();
    }

    private function loadArtworkWorkspace(): void
    {
        $publicArtworks = app(PublicArtworkQuery::class);
        $statistics = $publicArtworks->homeCandidateStatistics();

        $this->eligibleArtworkCount = $statistics['eligible'];
        $this->newestEligibleYear = $statistics['newest_year'];
        $this->sourceGalleryCount = ArtworkCategory::query()
            ->where('show_on_home', true)
            ->whereHas('siteSection')
            ->count();

        $pool = $this->heroMode === 'automatic'
            ? $publicArtworks->configuredHomeCandidates(
                $this->heroGroupSize,
                $this->heroNewestBy,
                $this->effectivePoolRule(),
                $this->effectivePoolYear(),
                $this->effectiveManualCandidateIds(),
            )
            : new EloquentCollection;
        $this->candidatePoolCount = $pool->count();
        $this->heroCandidates = $pool
            ->map(fn (Artwork $artwork): array => $this->candidateRow($artwork))
            ->values()
            ->all();

        $current = $this->heroMode === 'manual'
            ? ($this->fixedArtworkId === null ? null : $publicArtworks->homeCandidateById($this->fixedArtworkId))
            : $pool->first();

        if ($current instanceof Artwork) {
            $this->currentArtwork = $this->artworkRow($current);
        } elseif ($this->heroMode === 'manual') {
            $this->selectionIssue = 'The Manual Hero Artwork is no longer eligible.';
        } else {
            $this->selectionIssue = 'No eligible artwork matches the Automatic candidate settings.';
        }
    }

    /** @param array<string, mixed> $configuration */
    private function loadComponentWorkspace(HomeTemplate $template, array $configuration): void
    {
        $raw = $configuration[$template->value]['components'] ?? [];
        $raw = is_array($raw) && array_is_list($raw) ? $raw : [];

        $mediaIds = collect($raw)
            ->filter(fn (mixed $component): bool => is_array($component)
                && ($component['type'] ?? null) === 'image'
                && is_numeric($component['media_asset_id'] ?? null))
            ->map(fn (array $component): int => (int) $component['media_asset_id'])
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $assets = MediaAsset::query()
            ->whereIn('id', $mediaIds)
            ->get(['id', 'original_filename', 'alt_text'])
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
                default => 'unknown',
            };
            $typeLabel = $this->componentTypeOptions[$filterType] ?? 'Component';

            if ($type === 'image' && $assetId !== null && $assetId > 0) {
                $referenceIds[] = $assetId;
            }
            if ($type === 'text' && $body !== '') {
                $referenceIds = array_merge($referenceIds, RichTextMediaReference::ids($body));
            }

            [$primary, $secondary] = match ($filterType) {
                'image' => [
                    $asset instanceof MediaAsset ? (string) $asset->getAttribute('original_filename') : 'Choose an image from Media Files',
                    $asset instanceof MediaAsset && filled($asset->getAttribute('alt_text'))
                        ? 'ALT · '.Str::limit((string) $asset->getAttribute('alt_text'), 90)
                        : 'Media Files image',
                ],
                'heading' => [$title !== '' ? $title : 'Untitled heading', ''],
                'rich_text' => [
                    $title !== '' ? $title : (Str::limit($body, 110) ?: 'Empty Rich Text'),
                    $title !== '' ? (Str::limit($body, 110) ?: 'No body text') : '',
                ],
                'divider' => ['Divider', 'Editorial divider'],
                default => ['Unsupported component', ''],
            };

            $dataset[] = [
                'index' => $index,
                'target' => $index.':'.$type,
                'type' => $type,
                'filter_type' => $filterType,
                'type_label' => $typeLabel,
                'content' => [
                    'primary' => $primary,
                    'secondary' => $secondary,
                ],
                'search_text' => Str::lower(implode(' ', [$typeLabel, $primary, $secondary, $title, $body])),
                'can_move_up' => $index > 0,
                'can_move_down' => $index < $count - 1,
                'editable' => in_array($type, ['image', 'text'], true),
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

        $this->components = collect($this->componentDataset)
            ->filter(function (array $component) use ($term, $type): bool {
                if ($type !== 'any' && ($component['filter_type'] ?? null) !== $type) {
                    return false;
                }

                return $term === '' || str_contains((string) ($component['search_text'] ?? ''), $term);
            })
            ->values()
            ->all();
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
                ['label' => 'Visits · 30d', 'value' => $this->formatMetric($this->homeVisits), 'description' => $analyticsDescription],
                ['label' => 'Views · 30d', 'value' => $this->formatMetric($this->homeViews), 'description' => $analyticsDescription],
                ['label' => 'Source Galleries', 'value' => number_format($this->sourceGalleryCount), 'description' => 'Enabled sources'],
                ['label' => 'Eligible Artworks', 'value' => number_format($this->eligibleArtworkCount), 'description' => 'Home eligible'],
                ['label' => 'Candidate Pool', 'value' => number_format($this->candidatePoolCount), 'description' => 'Configured group'],
                ['label' => 'Newest Year', 'value' => $this->newestEligibleYear === null ? '—' : (string) $this->newestEligibleYear, 'description' => 'Eligible newest'],
            ];

            return;
        }

        if (in_array($template, [HomeTemplate::UnderConstruction, HomeTemplate::Custom], true)) {
            $this->metrics = [
                ['label' => 'Components', 'value' => number_format($this->componentStats['components']), 'description' => 'This template'],
                ['label' => 'Images', 'value' => number_format($this->componentStats['images']), 'description' => 'Image blocks'],
                ['label' => 'Headings', 'value' => number_format($this->componentStats['headings']), 'description' => 'Legacy headings'],
                ['label' => 'Rich Text', 'value' => number_format($this->componentStats['rich_text']), 'description' => 'Text blocks'],
                ['label' => 'Dividers', 'value' => number_format($this->componentStats['dividers']), 'description' => 'Divider blocks'],
                ['label' => 'Media References', 'value' => number_format($this->componentStats['media_references']), 'description' => 'Referenced files'],
            ];

            return;
        }

        $this->metrics = [];
    }

    /** @return array<string, mixed> */
    private function artworkRow(Artwork $artwork): array
    {
        return [
            ...$this->candidateRow($artwork),
            'featured' => (bool) $artwork->getAttribute('featured_on_home'),
            'preview_url' => route('preview.artworks.show', ['slug' => $artwork->getAttribute('slug')]),
        ];
    }

    /** @return array<string, mixed> */
    private function candidateRow(Artwork $artwork): array
    {
        $gallery = $artwork->getRelationValue('category');
        if (! $gallery instanceof ArtworkCategory) {
            $artwork->loadMissing('category');
            $gallery = $artwork->getRelationValue('category');
        }

        $workDate = $artwork->getAttribute('work_date');

        return [
            'id' => (int) $artwork->getKey(),
            'title' => (string) $artwork->getAttribute('title'),
            'year' => $artwork->getAttribute('work_year'),
            'date' => $workDate instanceof DateTimeInterface ? $workDate->format('Y-m-d') : null,
            'gallery' => $gallery instanceof ArtworkCategory ? (string) $gallery->getAttribute('name') : null,
            'thumbnail_url' => ArtworkResource::thumbnailUrl($artwork),
            'edit_url' => ArtworkResource::getUrl('edit', ['record' => $artwork]),
            'gallery_url' => $gallery instanceof ArtworkCategory
                ? ArtworkResource::getUrl('gallery', ['gallery' => $gallery->getKey()])
                : null,
        ];
    }

    private function heroArtworkSelect(string $name, string $label, bool $multiple = false): Select
    {
        $select = Select::make($name)
            ->label($label)
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => $this->heroArtworkOptions($search))
            ->searchDebounce(300)
            ->searchPrompt('Search eligible Hero Artworks')
            ->noSearchResultsMessage('No matching eligible artworks');

        if ($multiple) {
            $select
                ->multiple()
                ->getOptionLabelsUsing(fn (array $values): array => $this->heroArtworkOptionLabels($values));
        } else {
            $select->getOptionLabelUsing(fn (mixed $value): ?string => $this->heroArtworkOptionLabel($value));
        }

        return $select;
    }

    /** @return array<int, string> */
    private function heroArtworkOptions(string $search): array
    {
        return app(PublicArtworkQuery::class)
            ->searchHomeCandidates($search, 30)
            ->mapWithKeys(fn (Artwork $artwork): array => [
                (int) $artwork->getKey() => $this->heroArtworkLabel($artwork),
            ])
            ->all();
    }

    /** @param list<mixed> $values
     *  @return array<int, string>
     */
    private function heroArtworkOptionLabels(array $values): array
    {
        $ids = collect($values)
            ->map(static fn (mixed $value): int => (int) $value)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        return app(PublicArtworkQuery::class)
            ->homeCandidatesByIds($ids)
            ->mapWithKeys(fn (Artwork $artwork): array => [
                (int) $artwork->getKey() => $this->heroArtworkLabel($artwork),
            ])
            ->all();
    }

    private function heroArtworkOptionLabel(mixed $value): ?string
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            return null;
        }

        $artwork = app(PublicArtworkQuery::class)->homeCandidateById((int) $id);

        return $artwork instanceof Artwork ? $this->heroArtworkLabel($artwork) : null;
    }

    private function heroArtworkLabel(Artwork $artwork): string
    {
        $gallery = $artwork->getRelationValue('category');
        $galleryName = $gallery instanceof ArtworkCategory ? (string) $gallery->getAttribute('name') : 'Gallery';
        $year = $artwork->getAttribute('work_year');

        return (string) $artwork->getAttribute('title')
            .' · '.$galleryName
            .($year === null ? '' : ' · '.$year);
    }

    /** @return list<mixed> */
    private function homeRichTextFields(string $stateField, string $stateValue, bool $required = false): array
    {
        $fields = AdminRichText::schema('body', 'Rich Text', 20000);

        foreach ($fields as $index => $field) {
            $field->visible(fn (callable $get): bool => $get($stateField) === $stateValue);
            if ($required && $index === 0) {
                $field->required(fn (callable $get): bool => $get($stateField) === $stateValue);
            }
        }

        return $fields;
    }

    /** @param array<string, mixed> $component */
    private function editorKind(array $component): ?string
    {
        if (($component['type'] ?? null) !== 'text') {
            return null;
        }

        $title = $component['title'] ?? null;
        $body = $component['body'] ?? null;
        $hasTitle = is_string($title) && trim($title) !== '';
        $hasBody = is_string($body) && trim($body) !== '';

        return $hasTitle && ! $hasBody ? 'heading' : 'rich_text';
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

    private function homeSection(): SiteSection
    {
        if ($this->homeSectionId <= 0) {
            throw ValidationException::withMessages(['section' => 'Home site node is missing.']);
        }

        /** @var SiteSection $section */
        $section = SiteSection::query()->whereKey($this->homeSectionId)->firstOrFail();

        return $section;
    }

    private function componentTemplate(): HomeTemplate
    {
        $template = HomeTemplate::from($this->template);
        if (! in_array($template, [HomeTemplate::UnderConstruction, HomeTemplate::Custom], true)) {
            throw ValidationException::withMessages([
                'component' => 'The active Home template does not use editable components.',
            ]);
        }

        return $template;
    }

    private function componentReorderEnabled(): bool
    {
        return trim($this->componentSearch) === '' && $this->componentType === 'any';
    }

    /** @param array<string, mixed> $arguments
     *  @return array<string, mixed>
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
            throw ValidationException::withMessages([
                'component' => 'This Home component changed. Reload the workspace and try again.',
            ]);
        }

        return $component;
    }

    /** @return list<array{index:int,type:string}> */
    private function selectedComponentTargetData(): array
    {
        $available = collect($this->componentDataset)
            ->pluck('target')
            ->filter(static fn (mixed $target): bool => is_string($target))
            ->flip();
        $targets = [];

        foreach (array_values(array_unique($this->selectedComponentTargets)) as $target) {
            if (! is_string($target) || ! $available->has($target)) {
                throw ValidationException::withMessages([
                    'component' => 'The selected Home components changed. Reload the workspace and try again.',
                ]);
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
        if (! ctype_digit($index) || ! in_array($type, ['image', 'text', 'divider'], true)) {
            throw ValidationException::withMessages(['component' => 'The Home component target is invalid.']);
        }

        return ['index' => (int) $index, 'type' => $type];
    }

    private function clearComponentSelection(): void
    {
        $this->selectedComponentTargets = [];
    }

    private function clearSourceSelection(): void
    {
        $this->selectedSourceIds = [];
    }

    private function effectivePoolRule(): string
    {
        return $this->heroMode === 'automatic' ? $this->heroPoolRule : 'all';
    }

    private function effectivePoolYear(): ?int
    {
        return $this->heroMode === 'automatic' && $this->heroPoolRule === 'year'
            ? $this->heroPoolYear
            : null;
    }

    /** @return list<int> */
    private function effectiveManualCandidateIds(): array
    {
        return $this->heroMode === 'automatic' ? $this->manualHeroCandidateIds : [];
    }

    private function analyticsMetricValue(mixed $metric): ?float
    {
        if (! is_array($metric)
            || ($metric['state'] ?? null) !== 'available'
            || ! is_numeric($metric['value'] ?? null)) {
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
