<?php

namespace App\Filament\Pages;

use App\Domain\Artwork\GalleryEditorialService;
use App\Domain\Artwork\PublicArtworkQuery;
use App\Domain\Content\HomePresentationEditorialService;
use App\Domain\Content\HomePresentationResolver;
use App\Domain\Content\HomeTemplate;
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

    /** @var array<string, mixed>|null */
    public ?array $currentArtwork = null;

    /** @var list<array<string, mixed>> */
    public array $galleries = [];

    /** @var list<array<string, mixed>> */
    public array $newestEligibleArtworks = [];

    /** @var list<array<string, mixed>> */
    public array $components = [];

    /** @var array<string, mixed>|null */
    public ?array $skipTarget = null;

    public ?string $selectionIssue = null;

    public ?string $readinessWarning = null;

    public int $eligibleArtworkCount = 0;

    public int $sourceGalleryCount = 0;

    public int $publicSourceGalleryCount = 0;

    public function mount(): void
    {
        $this->previewUrl = app(SitePreviewContext::class)->previewSiteUrl();
        $this->loadWorkspace();
    }

    public function settingsAction(): Action
    {
        return Action::make('settings')
            ->label('Settings')
            ->fillForm(function (): array {
                $settings = $this->settings();
                $configuration = app(HomePresentationEditorialService::class)->configuration($settings);

                return [
                    'template' => $settings->template()->value,
                    'show_details' => (bool) $configuration[HomeTemplate::Artwork->value]['show_details'],
                    'show_gallery_link' => (bool) $configuration[HomeTemplate::Artwork->value]['show_gallery_link'],
                    'public_site_gate' => (bool) $configuration[HomeTemplate::UnderConstruction->value]['public_site_gate'],
                ];
            })
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
                        ? 'No published top-level page exists after Home. The public root will safely remain on Home.'
                        : $this->skipTarget['label'].' · '.$this->skipTarget['path'])
                    ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::SkipHome->value),
                Placeholder::make('custom_components')
                    ->label('Custom composition')
                    ->content('Components are edited in the Home workspace below the settings row.')
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

                $this->loadWorkspace();
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
                    ->options([
                        'heading' => 'Heading',
                        'rich_text' => 'Rich Text',
                        'image' => 'Image',
                        'divider' => 'Divider',
                    ])
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

                $this->loadWorkspace();
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
                    'title' => $component['title'] ?? null,
                    'body' => $component['body'] ?? null,
                    'media_asset_id' => $component['media_asset_id'] ?? null,
                    'image_decorative' => (bool) ($component['image_decorative'] ?? false),
                ];
            })
            ->schema([
                Hidden::make('type'),
                TextInput::make('title')
                    ->label('Heading')
                    ->maxLength(160)
                    ->visible(fn (callable $get): bool => $get('type') === 'text'),
                ...$this->homeRichTextFields('type', 'text'),
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
                $component = match ($type) {
                    'text' => [
                        'type' => 'text',
                        'title' => filled($data['title'] ?? null) ? trim((string) $data['title']) : null,
                        'body' => filled($data['body'] ?? null) ? (string) $data['body'] : null,
                    ],
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

                $this->loadWorkspace();
                Notification::make()->title('Home component saved')->success()->send();
            });
    }

    public function removeComponentAction(): Action
    {
        return Action::make('removeComponent')
            ->label('Remove')
            ->requiresConfirmation()
            ->modalHeading('Remove Home component?')
            ->modalDescription('The component is removed from this Home template. Other template configurations are unchanged.')
            ->action(function (array $arguments): void {
                $component = $this->componentFromArguments($arguments);
                app(HomePresentationEditorialService::class)->deleteComponent(
                    $this->settings(),
                    $this->componentTemplate(),
                    (int) $arguments['index'],
                    (string) $component['type'],
                );

                $this->loadWorkspace();
                Notification::make()->title('Home component removed')->success()->send();
            });
    }

    public function moveComponent(int $index, string $expectedType, string $direction): void
    {
        app(HomePresentationEditorialService::class)->moveComponent(
            $this->settings(),
            $this->componentTemplate(),
            $index,
            $expectedType,
            $direction,
        );

        $this->loadWorkspace();
        Notification::make()->title('Home component order updated')->success()->send();
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

        $this->loadWorkspace();
        Notification::make()->title('Homepage source updated')->success()->send();
    }

    private function loadWorkspace(): void
    {
        $this->selectionIssue = null;
        $this->readinessWarning = null;
        $this->currentArtwork = null;
        $this->galleries = [];
        $this->newestEligibleArtworks = [];
        $this->components = [];
        $this->skipTarget = null;
        $this->eligibleArtworkCount = 0;
        $this->sourceGalleryCount = 0;
        $this->publicSourceGalleryCount = 0;

        $settings = $this->settings();
        $template = $settings->template();
        $this->template = $template->value;
        $this->templateLabel = $template->label();

        match ($template) {
            HomeTemplate::Artwork => $this->loadArtworkWorkspace(),
            HomeTemplate::UnderConstruction,
            HomeTemplate::Custom => $this->loadComponentWorkspace($template),
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
        $this->eligibleArtworkCount = $publicArtworks->homeCandidateCount();

        $eligible = $publicArtworks->homeCandidates();
        $newestYear = $eligible->max('work_year');
        $this->newestEligibleArtworks = $eligible
            ->filter(static fn (Artwork $artwork): bool => (int) $artwork->getAttribute('work_year') === (int) $newestYear)
            ->map(fn (Artwork $artwork): array => $this->artworkRow($artwork))
            ->values()
            ->all();

        if ($this->selectionIssue !== null) {
            $this->readinessWarning = 'The current hero needs an explicit tie-breaker.';
        } elseif ($this->currentArtwork === null) {
            $this->readinessWarning = 'No public Home artwork is currently eligible.';
        }
    }

    private function loadComponentWorkspace(HomeTemplate $template): void
    {
        $settings = $this->settings();
        $configuration = app(HomePresentationEditorialService::class)->configuration($settings);
        $raw = $configuration[$template->value]['components'] ?? [];
        $raw = is_array($raw) && array_is_list($raw) ? $raw : [];

        $mediaIds = collect($raw)
            ->filter(fn (mixed $component): bool => is_array($component)
                && ($component['type'] ?? null) === 'image'
                && is_numeric($component['media_asset_id'] ?? null))
            ->map(fn (array $component): int => (int) $component['media_asset_id'])
            ->unique()
            ->values()
            ->all();

        $assets = MediaAsset::query()
            ->whereIn('id', $mediaIds)
            ->get(['id', 'original_filename', 'alt_text'])
            ->keyBy(fn (MediaAsset $asset): int => (int) $asset->getKey());

        $count = count($raw);
        $this->components = collect($raw)
            ->map(function (array $component, int $index) use ($assets, $count): array {
                $type = is_string($component['type'] ?? null) ? $component['type'] : 'unknown';
                $assetId = is_numeric($component['media_asset_id'] ?? null) ? (int) $component['media_asset_id'] : null;
                $asset = $assetId === null ? null : $assets->get($assetId);
                $summary = match ($type) {
                    'image' => $asset instanceof MediaAsset
                        ? (string) $asset->getAttribute('original_filename')
                        : 'Choose an image from Media Files',
                    'text' => filled($component['title'] ?? null)
                        ? (string) $component['title']
                        : Str::limit(trim((string) ($component['body'] ?? '')), 110) ?: 'Empty Rich Text',
                    'divider' => 'Editorial divider',
                    default => 'Unsupported component',
                };

                return [
                    'index' => $index,
                    'type' => $type,
                    'type_label' => match ($type) {
                        'image' => 'Image',
                        'text' => filled($component['title'] ?? null) && blank($component['body'] ?? null)
                            ? 'Heading'
                            : 'Rich Text',
                        'divider' => 'Divider',
                        default => 'Component',
                    },
                    'summary' => $summary,
                    'preview_url' => $asset instanceof MediaAsset
                        ? route('admin.media.original', ['mediaAsset' => $asset])
                        : null,
                    'can_move_up' => $index > 0,
                    'can_move_down' => $index < $count - 1,
                    'editable' => in_array($type, ['image', 'text'], true),
                ];
            })
            ->values()
            ->all();

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
        $settings = $this->settings();
        $template = $settings->template();
        $configuration = app(HomePresentationEditorialService::class)->configuration($settings);

        $publicBehavior = match ($template) {
            HomeTemplate::Artwork => ['Artwork home', 'Shows the selected newest eligible artwork.'],
            HomeTemplate::UnderConstruction => (bool) $configuration[HomeTemplate::UnderConstruction->value]['public_site_gate']
                ? ['Site gated', 'Public content URLs return to the construction Home.']
                : ['Landing only', 'Home shows construction content; other public pages remain reachable.'],
            HomeTemplate::SkipHome => $this->skipTarget === null
                ? ['Safe fallback', 'No redirect occurs until a valid next public page exists.']
                : ['Redirect', '/ → '.$this->skipTarget['path']],
            HomeTemplate::Custom => ['Custom home', 'Home renders its ordered component composition.'],
        };

        $sourceMetric = $template === HomeTemplate::Artwork
            ? [$this->sourceGalleryCount.' enabled', $this->publicSourceGalleryCount.' enabled Galleries are currently public.']
            : ['Not used', 'Gallery source eligibility is retained for Artwork mode.'];
        $eligibleMetric = $template === HomeTemplate::Artwork
            ? [(string) $this->eligibleArtworkCount, 'Published artworks currently eligible for Home selection.']
            : ['Not used', 'Artwork candidates are retained for Artwork mode.'];

        $primaryMetric = match ($template) {
            HomeTemplate::Artwork => $this->currentArtwork === null
                ? ['None', 'No unambiguous public hero is selected.']
                : ['Selected', (string) $this->currentArtwork['title']],
            HomeTemplate::UnderConstruction => [count($this->components).' components', 'Ordered construction presentation.'],
            HomeTemplate::SkipHome => $this->skipTarget === null
                ? ['No target', 'Waiting for a later published top-level page.']
                : [$this->skipTarget['label'], 'Current canonical redirect target.'],
            HomeTemplate::Custom => [count($this->components).' components', 'Ordered custom Home composition.'],
        };

        $statusMetric = $this->readinessWarning === null
            ? ['Ready', 'No Home presentation issue is currently detected.']
            : ['Needs attention', $this->readinessWarning];

        $this->metrics = [
            ['label' => 'Template', 'value' => $this->templateLabel, 'description' => 'Active public Home presentation.'],
            ['label' => 'Public behavior', 'value' => $publicBehavior[0], 'description' => $publicBehavior[1]],
            ['label' => 'Source Galleries', 'value' => $sourceMetric[0], 'description' => $sourceMetric[1]],
            ['label' => 'Eligible Artworks', 'value' => $eligibleMetric[0], 'description' => $eligibleMetric[1]],
            ['label' => 'Primary content', 'value' => $primaryMetric[0], 'description' => $primaryMetric[1]],
            ['label' => 'Template status', 'value' => $statusMetric[0], 'description' => $statusMetric[1]],
        ];
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

    private function settings(): HomePresentationSetting
    {
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
}
