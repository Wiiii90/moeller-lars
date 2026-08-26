<?php

namespace App\Filament\Pages;

use App\Domain\Artwork\GalleryEditorialService;
use App\Domain\Artwork\PublicArtworkQuery;
use App\Domain\Content\HomePresentationEditorialService;
use App\Domain\Content\HomePresentationResolver;
use App\Domain\Content\HomeTemplate;
use App\Domain\Content\RichTextMediaReference;
use App\Domain\Content\SitePreviewContext;
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
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

final class HomePresentation extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Home';

    protected static ?string $slug = 'pages/home';

    protected string $view = 'filament.pages.home-presentation';

    /** @var list<array{label:string,value:string,description:string}> */
    public array $metrics = [];

    public string $template = 'artwork';

    public string $templateLabel = 'Artwork';

    public string $previewUrl = '';

    public int $settingsId = 0;

    public bool $artworkShowDetails = true;

    public bool $artworkShowGalleryLink = true;

    public bool $publicSiteGate = false;

    /** @var array<string, mixed>|null */
    public ?array $currentArtwork = null;

    /** @var list<array<string, mixed>> */
    public array $galleries = [];

    /** @var list<array<string, mixed>> */
    public array $newestEligibleArtworks = [];

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

    public ?string $selectionIssue = null;

    public ?string $readinessWarning = null;

    public int $eligibleArtworkCount = 0;

    public int $sourceGalleryCount = 0;

    public int $publicSourceGalleryCount = 0;

    public ?int $newestEligibleYear = null;

    public int $newestYearCandidateCount = 0;

    public int $explicitTieBreakerCount = 0;

    public function mount(): void
    {
        $this->previewUrl = app(SitePreviewContext::class)->previewSiteUrl();
        $this->reloadWorkspace();
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
                'show_details' => $this->artworkShowDetails,
                'show_gallery_link' => $this->artworkShowGalleryLink,
                'public_site_gate' => $this->publicSiteGate,
            ])
            ->schema([
                Select::make('template')
                    ->label('Template')
                    ->options(HomeTemplate::options())
                    ->required()
                    ->live(),
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
                app(HomePresentationEditorialService::class)->updateSettings(
                    $this->settings(),
                    $template,
                    [
                        'show_details' => $data['show_details'] ?? null,
                        'show_gallery_link' => $data['show_gallery_link'] ?? null,
                        'public_site_gate' => $data['public_site_gate'] ?? null,
                    ],
                );

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
                    ->options($this->componentTypeOptions)
                    ->required()
                    ->live(),
                TextInput::make('title')
                    ->label('Heading')
                    ->maxLength(160)
                    ->required(fn (callable $get): bool => $get('kind') === 'heading')
                    ->visible(fn (callable $get): bool => $get('kind') === 'heading'),
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
                    'heading' => [
                        'type' => 'text',
                        'title' => trim((string) ($data['title'] ?? '')),
                        'body' => null,
                    ],
                    'rich_text' => [
                        'type' => 'text',
                        'title' => null,
                        'body' => $data['body'] ?? null,
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

        $this->reloadWorkspace();
        Notification::make()->title('Homepage source updated')->success()->send();
    }

    private function reloadWorkspace(): void
    {
        $settings = $this->settings();
        $configuration = app(HomePresentationEditorialService::class)->configuration($settings);
        $this->settingsId = (int) $settings->getKey();
        $template = $settings->template();
        $this->template = $template->value;
        $this->templateLabel = $template->label();
        $this->artworkShowDetails = (bool) $configuration[HomeTemplate::Artwork->value]['show_details'];
        $this->artworkShowGalleryLink = (bool) $configuration[HomeTemplate::Artwork->value]['show_gallery_link'];
        $this->publicSiteGate = (bool) $configuration[HomeTemplate::UnderConstruction->value]['public_site_gate'];

        $this->selectionIssue = null;
        $this->readinessWarning = null;
        $this->currentArtwork = null;
        $this->galleries = [];
        $this->newestEligibleArtworks = [];
        $this->componentDataset = [];
        $this->components = [];
        $this->skipTarget = null;
        $this->eligibleArtworkCount = 0;
        $this->sourceGalleryCount = 0;
        $this->publicSourceGalleryCount = 0;
        $this->newestEligibleYear = null;
        $this->newestYearCandidateCount = 0;
        $this->explicitTieBreakerCount = 0;
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
            HomeTemplate::Custom => $this->loadComponentWorkspace($template, $settings, $configuration),
            HomeTemplate::SkipHome => $this->loadSkipWorkspace(),
        };

        $this->refreshMetrics();
    }

    private function loadArtworkWorkspace(): void
    {
        $publicArtworks = app(PublicArtworkQuery::class);

        try {
            $current = $publicArtworks->latestForHome();
            if ($current instanceof Artwork) {
                $this->currentArtwork = $this->artworkRow($current);
            }
        } catch (LogicException $exception) {
            $this->selectionIssue = $exception->getMessage();
        }

        /** @var EloquentCollection<int, ArtworkCategory> $galleries */
        $galleries = ArtworkCategory::query()
            ->whereHas('siteSection')
            ->with('siteSection')
            ->withCount([
                'artworks as published_artworks_count' => static fn ($query) => $query->where('state', 'published'),
            ])
            ->withMax([
                'artworks as newest_published_year' => static fn ($query) => $query
                    ->where('state', 'published')
                    ->whereNotNull('work_year'),
            ], 'work_year')
            ->orderBy('name')
            ->get();

        $this->galleries = $galleries
            ->map(function (ArtworkCategory $gallery): array {
                /** @var SiteSection|null $section */
                $section = $gallery->getRelationValue('siteSection');

                return [
                    'id' => (int) $gallery->getKey(),
                    'name' => (string) $gallery->getAttribute('name'),
                    'eligible' => (bool) $gallery->getAttribute('show_on_home'),
                    'state' => (string) ($section?->getAttribute('state') ?? 'hidden'),
                    'published_artworks' => (int) $gallery->getAttribute('published_artworks_count'),
                    'newest_year' => $gallery->getAttribute('newest_published_year'),
                    'workspace_url' => ArtworkResource::getUrl('gallery', ['gallery' => $gallery->getKey()]),
                ];
            })
            ->values()
            ->all();

        $this->sourceGalleryCount = collect($this->galleries)->where('eligible', true)->count();
        $this->publicSourceGalleryCount = collect($this->galleries)
            ->where('eligible', true)
            ->where('state', 'published')
            ->count();

        $statistics = $publicArtworks->homeCandidateStatistics();
        $this->eligibleArtworkCount = $statistics['eligible'];
        $this->newestEligibleYear = $statistics['newest_year'];
        $this->newestYearCandidateCount = $statistics['newest_year_candidates'];
        $this->explicitTieBreakerCount = $statistics['explicit_tie_breakers'];

        $this->newestEligibleArtworks = $publicArtworks->newestHomeCandidates()
            ->map(fn (Artwork $artwork): array => $this->artworkRow($artwork))
            ->values()
            ->all();

        if ($this->selectionIssue !== null) {
            $this->readinessWarning = 'The current hero needs an explicit tie-breaker.';
        } elseif ($this->currentArtwork === null) {
            $this->readinessWarning = 'No public Home artwork is currently eligible.';
        }
    }

    /** @param array<string, mixed> $configuration */
    private function loadComponentWorkspace(
        HomeTemplate $template,
        HomePresentationSetting $settings,
        array $configuration,
    ): void {
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

        if ($template === HomeTemplate::UnderConstruction) {
            $hasImage = collect($raw)->contains(fn (mixed $component): bool => is_array($component)
                && ($component['type'] ?? null) === 'image'
                && is_numeric($component['media_asset_id'] ?? null));
            $hasText = collect($raw)->contains(fn (mixed $component): bool => is_array($component)
                && ($component['type'] ?? null) === 'text'
                && (filled($component['title'] ?? null) || filled($component['body'] ?? null)));

            if (! $hasImage || ! $hasText) {
                $this->readinessWarning = 'Under Construction should contain both an image and text before public use.';
            }
        } elseif ($raw === []) {
            $this->readinessWarning = 'Custom Home has no components yet.';
        }
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
            $this->readinessWarning = 'No published top-level page exists after Home. The public root will stay on Home instead of redirecting.';

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
            $this->metrics = [
                ['label' => 'Source Galleries', 'value' => number_format($this->sourceGalleryCount), 'description' => 'Galleries enabled as Home sources'],
                ['label' => 'Public Sources', 'value' => number_format($this->publicSourceGalleryCount), 'description' => 'Enabled source Galleries currently public'],
                ['label' => 'Eligible Artworks', 'value' => number_format($this->eligibleArtworkCount), 'description' => 'Published Home candidates'],
                ['label' => 'Newest Year', 'value' => $this->newestEligibleYear === null ? '—' : (string) $this->newestEligibleYear, 'description' => 'Newest eligible artwork year'],
                ['label' => 'Newest-year Candidates', 'value' => number_format($this->newestYearCandidateCount), 'description' => 'Candidates in the newest eligible year'],
                ['label' => 'Explicit Tie-breakers', 'value' => number_format($this->explicitTieBreakerCount), 'description' => 'Eligible artworks marked for Home tie-breaking'],
            ];

            return;
        }

        if (in_array($template, [HomeTemplate::UnderConstruction, HomeTemplate::Custom], true)) {
            $this->metrics = [
                ['label' => 'Components', 'value' => number_format($this->componentStats['components']), 'description' => 'Components in this Home template'],
                ['label' => 'Images', 'value' => number_format($this->componentStats['images']), 'description' => 'Image components'],
                ['label' => 'Headings', 'value' => number_format($this->componentStats['headings']), 'description' => 'Heading components'],
                ['label' => 'Rich Text', 'value' => number_format($this->componentStats['rich_text']), 'description' => 'Rich Text components'],
                ['label' => 'Dividers', 'value' => number_format($this->componentStats['dividers']), 'description' => 'Divider components'],
                ['label' => 'Media References', 'value' => number_format($this->componentStats['media_references']), 'description' => 'Distinct Media Files referenced by this template'],
            ];

            return;
        }

        $this->metrics = [];
    }

    /** @return array<string, mixed> */
    private function artworkRow(Artwork $artwork): array
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
            'featured' => (bool) $artwork->getAttribute('featured_on_home'),
            'gallery' => $gallery instanceof ArtworkCategory ? (string) $gallery->getAttribute('name') : null,
            'thumbnail_url' => ArtworkResource::thumbnailUrl($artwork),
            'edit_url' => ArtworkResource::getUrl('edit', ['record' => $artwork]),
            'preview_url' => route('preview.artworks.show', ['slug' => $artwork->getAttribute('slug')]),
            'gallery_url' => $gallery instanceof ArtworkCategory
                ? ArtworkResource::getUrl('gallery', ['gallery' => $gallery->getKey()])
                : null,
        ];
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
}
