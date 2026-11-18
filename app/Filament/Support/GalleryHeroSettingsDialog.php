<?php

namespace App\Filament\Support;

use App\Domain\Artwork\GalleryPresentation;
use App\Domain\Content\GalleryHeroConfigurationService;
use App\Domain\Content\PublicImageViewer;
use App\Filament\Support\Controls\AdminControl;
use App\Models\GalleryHeroSetting;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\HtmlString;

final class GalleryHeroSettingsDialog
{
    public function __construct(private readonly GalleryHeroConfigurationService $configuration) {}

    /** @return array<string, mixed> */
    public function fill(GalleryHeroSetting $settings): array
    {
        $hero = $this->configuration->configuration($settings);

        return [
            'show_details' => $hero['show_details'],
            'show_gallery_link' => $hero['show_gallery_link'],
            'show_navigation' => $hero['show_navigation'],
            'video_sound_enabled' => $hero['video_sound_enabled'],
            'enable_viewer' => $hero['enable_viewer'],
            'show_media_credits' => $hero['show_media_credits'],
            'show_media_copyright' => $hero['show_media_copyright'],
            'caption_layout' => $hero['caption_layout'],
            'group_source' => $hero['group_source'],
            'display_strategy' => $hero['display_strategy'],
            'source_rule' => $hero['source_rule'],
            'selection_mode' => $hero['selection_mode'],
            'group_size' => $hero['group_size'],
            'rotation_interval_count' => $hero['rotation_interval']['count'],
            'rotation_interval_unit' => $hero['rotation_interval']['unit'],
        ];
    }

    /** @return list<mixed> */
    public function schema(GalleryHeroSetting $settings, ?Closure $persistHeroToggle = null): array
    {
        return [
            Grid::make()->columns(['default' => 1, 'lg' => 2])->schema([
                    Select::make('group_source')
                        ->label(self::helpLabel(
                            'Source',
                            'Choosing a Hero group',
                            'Automatic uses enabled regular Galleries, Semi-Automatic adds enabled Dummy Galleries, and Manual uses Dummy Galleries only. All modes follow Ranking, Candidates and Display. Switching Source restores its settings.',
                        ))
                        ->options(['automatic' => __('Automatic'), 'semi_automatic' => __('Semi-Automatic'), 'manual' => __('Manual')])
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set) use ($settings): void {
                            if (! in_array($state, ['automatic', 'semi_automatic', 'manual'], true)) {
                                return;
                            }
                            $profile = $this->configuration->configuration($settings, $state);
                            $set('group_size', $profile['group_size']);
                            $set('source_rule', $profile['source_rule']);
                            $set('selection_mode', $profile['selection_mode']);
                            $set('display_strategy', $profile['display_strategy']);
                            $set('rotation_interval_count', $profile['rotation_interval']['count']);
                            $set('rotation_interval_unit', $profile['rotation_interval']['unit']);
                        })
                        ,
                    Select::make('source_rule')
                        ->label(self::helpLabel(
                            'Ranking',
                            'Artwork ranking rule',
                            'Artwork rules use year, month and day. Media Added rules use the primary Media File upload time. Gallery Order variants prioritize the saved Gallery sequence before applying the chosen date or media rule within each Gallery.',
                        ))
                        ->options(GalleryHeroConfigurationService::SOURCE_RULES)
                        ->required()
                        ,
                    Select::make('selection_mode')
                        ->label(self::helpLabel(
                            'Candidates',
                            'Candidate selection scope',
                            'Total draws from all sources. Per Gallery takes the specified number from each source.',
                        ))
                        ->options(GalleryHeroConfigurationService::SELECTION_MODES)
                        ->required()
                        ->live()
                        ,
                    TextInput::make('group_size')
                        ->label(self::helpLabel(
                            'Amount',
                            'Candidate amount',
                            'Number of candidates overall in Total or per source in Per Gallery, up to 50 active candidates.',
                        ))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(GalleryHeroConfigurationService::MAX_GROUP_SIZE)
                        ->required()
                        ,
                    Select::make('display_strategy')
                        ->label(self::helpLabel(
                            'Display',
                            'Displaying the Hero group',
                            'Top Only displays the first sourced candidate. Random selects from the group using its weights. Sequential advances through the group after each rotation interval.',
                        ))
                        ->options(['ordered' => __('Top Only'), 'random' => __('Random'), 'sequential' => __('Sequential')])
                        ->required()
                        ->live()
                        ,
                    TextInput::make('rotation_interval_count')
                        ->label(self::helpLabel(
                            'Rotation interval',
                            'Rotation interval',
                            'How many units pass before the next Sequential artwork becomes active. The interval begins when the sequence or rotation settings change.',
                        ))
                        ->numeric()
                        ->minValue(1)
                        ->required()
                        ->visible(fn (callable $get): bool => $get('display_strategy') === 'sequential'),
                    Select::make('rotation_interval_unit')
                        ->label(self::helpLabel(
                            'Interval unit',
                            'Rotation interval unit',
                            'Days and weeks use fixed durations. Months follow calendar dates, so a rotation anchored on January 31 continues on February 28 and March 31.',
                        ))
                        ->options(['days' => __('Days'), 'weeks' => __('Weeks'), 'months' => __('Months')])
                        ->required()
                        ->visible(fn (callable $get): bool => $get('display_strategy') === 'sequential'),
                    $this->heroDisplayToggle('show_details', 'Show artwork information', $persistHeroToggle),
                    $this->heroDisplayToggle('show_gallery_link', 'Show Gallery link for single Hero', $persistHeroToggle),
                    $this->heroDisplayToggle('show_navigation', 'Navigate Hero group', $persistHeroToggle),
                    $this->heroDisplayToggle('show_media_credits', 'Show authored Media File credits', $persistHeroToggle),
                    $this->heroDisplayToggle('show_media_copyright', 'Show configured copyright notices', $persistHeroToggle),
                    AdminControl::select('caption_layout', 'Caption arrangement')
                        ->options(GalleryPresentation::CAPTION_LAYOUTS)
                        ->required()
                        ,
                    $this->heroDisplayToggle('video_sound_enabled', 'Allow visitors to unmute looping Hero videos', $persistHeroToggle),
                    $this->heroDisplayToggle('enable_viewer', PublicImageViewer::TOGGLE_LABEL, $persistHeroToggle),
            ]),
        ];
    }

    /** @param array<string, mixed> $data */
    public function save(GalleryHeroSetting $settings, array $data): bool
    {
        $current = $this->configuration->configuration($settings);
        $source = (string) ($data['group_source'] ?? $current['group_source']);
        $input = [];
        foreach ([
            'show_details', 'show_gallery_link', 'show_navigation', 'video_sound_enabled',
            'enable_viewer',
            'show_media_credits', 'show_media_copyright', 'caption_layout',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $input[$field] = $data[$field];
            }
        }
        $input['group_source'] = $source;
        if ($source === $current['group_source']) {
            foreach (['display_strategy', 'source_rule', 'selection_mode', 'group_size', 'rotation_interval_count', 'rotation_interval_unit'] as $field) {
                if (array_key_exists($field, $data)) {
                    $input[$field] = $data[$field];
                }
            }
        }

        return $this->configuration->updateArtworkSettings($settings, $input);
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
        $toggle = AdminControl::toggle($field, $label)->live();
        if ($persist !== null) {
            $toggle->afterStateUpdated(static function (mixed $state) use ($persist, $field): void {
                $persist($field, (bool) $state);
            });
        }

        return $toggle;
    }
}
