<?php

namespace App\Filament\Support;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Content\HomeHeroConfigurationService;
use App\Domain\Content\HomeHeroResolver;
use App\Domain\Content\HomePresentationEditorialService;
use App\Domain\Content\HomePresentationResolver;
use App\Domain\Content\HomeTemplate;
use App\Domain\Content\PublicImageViewer;
use App\Domain\Content\SitePageHeading;
use App\Domain\Content\SiteSectionEditorialService;
use App\Filament\Support\Controls\AdminControl;
use App\Models\Artwork;
use App\Models\HomePresentationSetting;
use App\Models\SiteSection;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

final class HomeSettingsDialog
{
    public function __construct(
        private readonly HomePresentationResolver $resolver,
        private readonly HomePresentationEditorialService $editorial,
        private readonly HomeHeroConfigurationService $heroConfiguration,
        private readonly HomeHeroResolver $heroResolver,
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
            'show_details' => $hero['show_details'],
            'show_gallery_link' => $hero['show_gallery_link'],
            'show_navigation' => $hero['show_navigation'],
            'video_sound_enabled' => $hero['video_sound_enabled'],
            'enable_viewer' => $hero['enable_viewer'],
            'custom_image_viewer_enabled' => (bool) $configuration[HomeTemplate::Custom->value]['image_viewer_enabled'],
            'construction_image_viewer_enabled' => (bool) $configuration[HomeTemplate::UnderConstruction->value]['image_viewer_enabled'],
            'group_source' => $hero['group_source'],
            'display_strategy' => $hero['display_strategy'],
            'newest_by' => $hero['newest_by'],
            'group_size' => $hero['group_size'],
            'rotation_interval_count' => $hero['rotation_interval']['count'],
            'rotation_interval_unit' => $hero['rotation_interval']['unit'],
            'public_site_gate' => (bool) ($configuration[HomeTemplate::UnderConstruction->value]['public_site_gate'] ?? false),
            ...$this->routingDialog->fill($settings),
            'show_in_navigation' => (bool) $section->getAttribute('show_in_navigation'),
            ...SitePageHeading::formState($section),
        ];
    }

    /** @return list<mixed> */
    public function schema(?Closure $persistHeroToggle = null): array
    {
        $persistHeading = function (string $field, mixed $value): bool {
            $section = $this->homeSection($this->resolver->settings());
            $updated = app(SiteSectionEditorialService::class)->updatePageHeading($section, [$field => $value]);

            return $updated->wasChanged($field);
        };

        return [
            Grid::make()
                ->columns(['md' => 2])
                ->schema([
                    Select::make('template')
                        ->label(self::helpLabel(
                            'Template',
                            'Home templates',
                            'Hero Artwork displays selected artworks. Under Construction is a prepared content template, while Custom is fully editable. Each template retains its own settings and content when you switch between them.',
                        ))
                        ->options(array_map('__', HomeTemplate::options()))
                        ->required()
                        ->live()
                        ->columnSpan(fn (callable $get): int => $get('template') === HomeTemplate::UnderConstruction->value ? 1 : 2),
                    AdminControl::toggle('public_site_gate', 'Temporarily gate the public site')
                        ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::UnderConstruction->value),
                    ...$this->routingDialog->schema(),
                    AdminControl::toggle('show_in_navigation', 'Show in public navigation')
                        ->live()
                        ->visible(fn (callable $get): bool => ! (bool) $get('skip_home'))
                        ->afterStateUpdated(function (mixed $state): void {
                            if ($this->saveNavigationVisibility((bool) $state)) {
                                app(AdminNotifier::class)->notification(
                                    title: 'Home navigation updated',
                                    status: 'success',
                                );
                            }
                        }),
                    Select::make('group_source')
                        ->label(self::helpLabel(
                            'Group source',
                            'Choosing a Hero group',
                            'Automatic selects eligible artworks from enabled, published Galleries. Manual uses the artworks you explicitly add and order.',
                        ))
                        ->options(['automatic' => __('Automatic'), 'manual' => __('Manual')])
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
                        ->options(['artwork_date' => __('Artwork date'), 'added' => __('Added')])
                        ->required()
                        ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value && $get('group_source') === 'automatic'),
                    Select::make('display_strategy')
                        ->label(self::helpLabel(
                            'Display strategy',
                            'Displaying the Hero group',
                            'Ordered keeps the first artwork visible. Random selects a weighted artwork. Sequential advances through the group after each rotation interval.',
                        ))
                        ->options(['ordered' => __('Ordered'), 'random' => __('Random'), 'sequential' => __('Sequential')])
                        ->required()
                        ->live()
                        ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value),
                    TextInput::make('rotation_interval_count')
                        ->label(self::helpLabel(
                            'Rotation interval',
                            'Rotation interval',
                            'How many units pass before the next Sequential artwork becomes active. The interval begins when the sequence or rotation settings change.',
                        ))
                        ->numeric()
                        ->minValue(1)
                        ->required()
                        ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value && $get('display_strategy') === 'sequential'),
                    Select::make('rotation_interval_unit')
                        ->label(self::helpLabel(
                            'Interval unit',
                            'Rotation interval unit',
                            'Choose whether the Sequential rotation interval is measured in days or weeks.',
                        ))
                        ->options(['days' => __('Days'), 'weeks' => __('Weeks')])
                        ->required()
                        ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value && $get('display_strategy') === 'sequential'),
                    $this->heroDisplayToggle('show_details', 'Show artwork information', $persistHeroToggle),
                    $this->heroDisplayToggle('show_gallery_link', 'Show Gallery link for single Hero', $persistHeroToggle),
                    $this->heroDisplayToggle('show_navigation', 'Navigate Hero group', $persistHeroToggle),
                    $this->heroDisplayToggle('video_sound_enabled', 'Allow visitors to unmute looping Home videos', $persistHeroToggle),
                    PageHeadingFields::headingToggle($persistHeading, 'Home settings saved'),
                    $this->heroDisplayToggle('enable_viewer', PublicImageViewer::TOGGLE_LABEL, $persistHeroToggle),
                    AdminControl::toggle('custom_image_viewer_enabled', PublicImageViewer::TOGGLE_LABEL)
                        ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Custom->value),
                    AdminControl::toggle('construction_image_viewer_enabled', PublicImageViewer::TOGGLE_LABEL)
                        ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::UnderConstruction->value),
                    ...PageHeadingFields::detailFields($persistHeading, 'Home settings saved'),
                ]),
        ];
    }

    /** @param array<string, mixed> $data */
    public function save(array $data): bool
    {
        return DB::transaction(fn (): bool => $this->saveSettings($data));
    }

    /** @param array<string, mixed> $data */
    private function saveSettings(array $data): bool
    {
        $settings = $this->resolver->settings();
        $changed = false;
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
                'show_navigation' => $data['show_navigation'] ?? $current['show_navigation'],
                'video_sound_enabled' => $data['video_sound_enabled'] ?? $current['video_sound_enabled'],
                'enable_viewer' => $data['enable_viewer'] ?? $current['enable_viewer'],
                'group_source' => $groupSource,
                'display_strategy' => $data['display_strategy'] ?? $current['display_strategy'],
                'newest_by' => $data['newest_by'] ?? $current['newest_by'],
                'group_size' => $data['group_size'] ?? $current['group_size'],
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

            $changed = $this->heroConfiguration->updateArtworkSettings($settings, $input);
        } else {
            $current = $this->editorial->configuration($settings)[$template->value];
            $field = $template === HomeTemplate::Custom ? 'custom_image_viewer_enabled' : 'construction_image_viewer_enabled';
            $input = ['image_viewer_enabled' => (bool) ($data[$field] ?? $current['image_viewer_enabled'])];
            if ($template === HomeTemplate::UnderConstruction) {
                $input['public_site_gate'] = (bool) ($data['public_site_gate'] ?? false);
            }
            $changed = $this->editorial->updateSettings($settings, $template, $input);
        }

        $settings = $settings->fresh();
        if (! $settings instanceof HomePresentationSetting) {
            throw ValidationException::withMessages(['home' => 'Home settings changed while editing. Reload and try again.']);
        }

        $changed = $this->routingDialog->save($data) || $changed;
        if (array_key_exists('show_in_navigation', $data)) {
            $changed = $this->saveNavigationVisibility((bool) $data['show_in_navigation']) || $changed;
        }

        $section = $this->homeSection($settings);
        $section = app(SiteSectionEditorialService::class)->updatePlacement($section, 'published', null);

        return $section->wasChanged(['state', 'parent_id', 'position']) || $changed;
    }

    public function changeTemplate(HomeTemplate $template): bool
    {
        if (! $template->isContentTemplate()) {
            throw ValidationException::withMessages(['template' => 'Choose a Home content template.']);
        }

        return $this->editorial->updateSettings($this->resolver->settings(), $template, []);
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

    private static function helpLabel(string $label, string $title, string $body): HtmlString
    {
        return new HtmlString(
            '<span class="admin-form-label-with-help">'.e($label).
            AdminHelp::make($title, $body)->toHtml().
            '</span>',
        );
    }

    private function heroDisplayToggle(string $field, string $label, ?Closure $persist): Toggle
    {
        // Filament's modal wire:change does not reliably submit live toggle
        // state. Use the same canonical state-update mechanism as Pages Settings.
        $toggle = AdminControl::toggle($field, $label)
            ->live()
            ->visible(fn (callable $get): bool => $get('template') === HomeTemplate::Artwork->value);

        if ($persist !== null) {
            $toggle->afterStateUpdated(static function (mixed $state) use ($persist, $field): void {
                $persist($field, (bool) $state);
            });
        }

        return $toggle;
    }

    private function saveNavigationVisibility(bool $visible): bool
    {
        return app(SiteSectionEditorialService::class)->updateHomeNavigationVisibility(
            $this->homeSection($this->resolver->settings()),
            $visible,
        );
    }

    private function homeSection(HomePresentationSetting $settings): SiteSection
    {
        $section = $settings->getRelationValue('siteSection');
        if (! $section instanceof SiteSection) {
            $section = $settings->siteSection()->firstOrFail();
        }

        return $section;
    }

}
