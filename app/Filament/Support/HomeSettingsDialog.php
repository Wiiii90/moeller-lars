<?php

namespace App\Filament\Support;

use App\Domain\Artwork\PublicArtworkQuery;
use App\Domain\Content\HomeHeroConfigurationService;
use App\Domain\Content\HomeHeroResolver;
use App\Domain\Content\HomePresentationEditorialService;
use App\Domain\Content\HomePresentationResolver;
use App\Domain\Content\HomeTemplate;
use App\Domain\Content\SiteSectionEditorialService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\HomePresentationSetting;
use App\Models\SiteSection;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Illuminate\Validation\ValidationException;

final class HomeSettingsDialog
{
    public function __construct(
        private readonly HomePresentationResolver $resolver,
        private readonly HomePresentationEditorialService $editorial,
        private readonly HomeHeroConfigurationService $heroConfiguration,
        private readonly HomeHeroResolver $heroResolver,
        private readonly PublicArtworkQuery $artworks,
        private readonly HomeRoutingDialog $routingDialog,
    ) {}

    /** @return array<string, mixed> */
    public function fill(): array
    {
        $settings = $this->resolver->settings();
        $hero = $this->heroConfiguration->configuration($settings);
        $configuration = $this->editorial->configuration($settings);
        $section = $this->homeSection($settings);

        return [
            'template' => $settings->template()->contentTemplate()->value,
            'show_in_navigation' => (bool) $section->getAttribute('show_in_navigation'),
            'show_details' => $hero['show_details'],
            'show_gallery_link' => $hero['show_gallery_link'],
            'group_source' => $hero['group_source'],
            'display_strategy' => $hero['display_strategy'],
            'newest_by' => $hero['newest_by'],
            'group_size' => $hero['group_size'],
            'pool_rule' => $hero['candidate_filter'],
            'pool_year' => $hero['specific_year'],
            'manual_include_ids' => $hero['manual_include_ids'],
            'rotation_interval_count' => $hero['rotation_interval']['count'],
            'rotation_interval_unit' => $hero['rotation_interval']['unit'],
            'public_site_gate' => (bool) ($configuration[HomeTemplate::UnderConstruction->value]['public_site_gate'] ?? false),
            ...$this->routingDialog->fill($settings),
        ];
    }

    /** @return list<mixed> */
    public function schema(): array
    {
        return [
            Grid::make()
                ->columns(['md' => 2])
                ->schema([
                    Select::make('template')
                        ->label('Template')
                        ->options(HomeTemplate::options())
                        ->required()
                        ->live(),
                    Toggle::make('show_in_navigation')
                        ->label('Show Home in navigation')
                        ->helperText('Only the public Home link changes. The Home page remains available at /.'),
                    ...$this->routingDialog->schema(),
                    Select::make('group_source')
                        ->label('Group source')
                        ->options(['automatic' => 'Automatic', 'manual' => 'Manual'])
                        ->required()
                        ->live()
                        ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value),
                    TextInput::make('group_size')
                        ->label('Group size')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(HomeHeroConfigurationService::MAX_GROUP_SIZE)
                        ->required()
                        ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value && $get('group_source') === 'automatic'),
                    Select::make('newest_by')
                        ->label('Newest by')
                        ->options(['artwork_date' => 'Artwork date', 'added' => 'Added'])
                        ->required()
                        ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value && $get('group_source') === 'automatic'),
                    Select::make('pool_rule')
                        ->label('Candidate filter')
                        ->options(['all' => 'All eligible', 'year' => 'Specific Year'])
                        ->required()
                        ->live()
                        ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value && $get('group_source') === 'automatic'),
                    TextInput::make('pool_year')
                        ->label('Year')
                        ->numeric()
                        ->minValue(1000)
                        ->maxValue(3000)
                        ->required(fn (callable $get): bool => $get('pool_rule') === 'year')
                        ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value && $get('group_source') === 'automatic' && $get('pool_rule') === 'year'),
                    $this->heroArtworkSelect('manual_include_ids', 'Additional includes', multiple: true)
                        ->helperText('Adds eligible artworks outside a Specific Year filter before Group size is applied.')
                        ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value && $get('group_source') === 'automatic')
                        ->columnSpanFull(),
                    Select::make('display_strategy')
                        ->label('Display strategy')
                        ->options(['ordered' => 'Ordered', 'random' => 'Random', 'sequential' => 'Sequential'])
                        ->required()
                        ->live()
                        ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value),
                    TextInput::make('rotation_interval_count')
                        ->label('Rotation interval')
                        ->numeric()
                        ->minValue(1)
                        ->required()
                        ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value && $get('display_strategy') === 'sequential'),
                    Select::make('rotation_interval_unit')
                        ->label('Interval unit')
                        ->options(['days' => 'Days', 'weeks' => 'Weeks'])
                        ->required()
                        ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value && $get('display_strategy') === 'sequential'),
                    Toggle::make('show_details')
                        ->label('Show artwork information')
                        ->helperText('Shows title, material, dimensions and other artwork label information.')
                        ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value),
                    Toggle::make('show_gallery_link')
                        ->label('Show Gallery link')
                        ->helperText('Shows the Gallery context button independently from artwork information.')
                        ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value),
                    Toggle::make('public_site_gate')
                        ->label('Temporarily gate the public site')
                        ->helperText('Normal public content URLs return to Home while Under Construction is active. Admin and protected Preview stay available.')
                        ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::UnderConstruction->value),
                    Placeholder::make('custom_components')
                        ->label('Custom composition')
                        ->content('Components are edited in the Home workspace.')
                        ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Custom->value)
                        ->columnSpanFull(),
                ]),
        ];
    }

    /** @param array<string, mixed> $data */
    public function save(array $data): void
    {
        $settings = $this->resolver->settings();
        $template = HomeTemplate::tryFrom((string) ($data['template'] ?? ''));
        if (! $template instanceof HomeTemplate || ! $template->isContentTemplate()) {
            throw ValidationException::withMessages(['template' => 'Choose a Home content template.']);
        }

        if ($template === HomeTemplate::Artwork) {
            $current = $this->heroConfiguration->configuration($settings);
            $groupSource = (string) ($data['group_source'] ?? $current['group_source']);
            $input = [
                'show_details' => $data['show_details'] ?? $current['show_details'],
                'show_gallery_link' => $data['show_gallery_link'] ?? $current['show_gallery_link'],
                'group_source' => $groupSource,
                'display_strategy' => $data['display_strategy'] ?? $current['display_strategy'],
                'newest_by' => $data['newest_by'] ?? $current['newest_by'],
                'group_size' => $data['group_size'] ?? $current['group_size'],
                'candidate_filter' => $data['pool_rule'] ?? $current['candidate_filter'],
                'specific_year' => $data['pool_year'] ?? $current['specific_year'],
                'manual_include_ids' => $data['manual_include_ids'] ?? $current['manual_include_ids'],
                'rotation_interval_count' => $data['rotation_interval_count'] ?? $current['rotation_interval']['count'],
                'rotation_interval_unit' => $data['rotation_interval_unit'] ?? $current['rotation_interval']['unit'],
            ];

            if ($groupSource === 'manual' && $current['manual_group'] === []) {
                $resolved = $this->heroResolver->resolve($settings)['current'];
                if ($resolved instanceof Artwork) {
                    $input['manual_group'] = [[
                        'artwork_id' => (int) $resolved->getKey(),
                        'weight' => HomeHeroConfigurationService::WEIGHT_TOTAL,
                    ]];
                }
            }

            $this->heroConfiguration->updateArtworkSettings($settings, $input);
        } else {
            $input = [];
            if ($template === HomeTemplate::UnderConstruction) {
                $input['public_site_gate'] = (bool) ($data['public_site_gate'] ?? false);
            }
            $this->editorial->updateSettings($settings, $template, $input);
        }

        $settings = $settings->fresh();
        if (! $settings instanceof HomePresentationSetting) {
            throw ValidationException::withMessages(['home' => 'Home settings changed while editing. Reload and try again.']);
        }

        $this->routingDialog->save($data);

        $section = $this->homeSection($settings);
        app(SiteSectionEditorialService::class)->updatePlacement(
            $section,
            'published',
            (bool) ($data['show_in_navigation'] ?? false),
            null,
        );
    }

    public function changeTemplate(HomeTemplate $template): void
    {
        if (! $template->isContentTemplate()) {
            throw ValidationException::withMessages(['template' => 'Choose a Home content template.']);
        }

        $this->editorial->updateSettings($this->resolver->settings(), $template, []);
    }

    public function templateLabel(): string
    {
        return $this->resolver->template()->label();
    }

    /** @return array{template:string,skip_home:bool,skip_target_label:?string} */
    public function tableState(): array
    {
        return [
            'template' => $this->resolver->template()->value,
            ...$this->routingDialog->tableState(),
        ];
    }

    private function homeSection(HomePresentationSetting $settings): SiteSection
    {
        $section = $settings->getRelationValue('siteSection');
        if (! $section instanceof SiteSection) {
            $section = $settings->siteSection()->firstOrFail();
        }

        return $section;
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
            $select->multiple()->getOptionLabelsUsing(fn (array $values): array => $this->heroArtworkOptionLabels($values));
        } else {
            $select->getOptionLabelUsing(fn (mixed $value): ?string => $this->heroArtworkOptionLabel($value));
        }

        return $select;
    }

    /** @return array<int, string> */
    private function heroArtworkOptions(string $search): array
    {
        return $this->artworks->searchHomeCandidates($search, 30)
            ->mapWithKeys(fn (Artwork $artwork): array => [(int) $artwork->getKey() => $this->heroArtworkLabel($artwork)])
            ->all();
    }

    /** @param list<mixed> $values
     * @return array<int, string>
     */
    private function heroArtworkOptionLabels(array $values): array
    {
        $ids = collect($values)
            ->map(static fn ($value): int => (int) $value)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $this->artworks->homeCandidatesByIds($ids)
            ->mapWithKeys(fn (Artwork $artwork): array => [(int) $artwork->getKey() => $this->heroArtworkLabel($artwork)])
            ->all();
    }

    private function heroArtworkOptionLabel(mixed $value): ?string
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            return null;
        }

        $artwork = $this->artworks->homeCandidateById((int) $id);

        return $artwork instanceof Artwork ? $this->heroArtworkLabel($artwork) : null;
    }

    private function heroArtworkLabel(Artwork $artwork): string
    {
        $artwork->loadMissing('category');
        $gallery = $artwork->getRelationValue('category');
        $galleryName = $gallery instanceof ArtworkCategory ? (string) $gallery->getAttribute('name') : 'Gallery';
        $year = $artwork->getAttribute('work_year');

        return (string) $artwork->getAttribute('title').' · '.$galleryName.($year === null ? '' : ' · '.$year);
    }
}
