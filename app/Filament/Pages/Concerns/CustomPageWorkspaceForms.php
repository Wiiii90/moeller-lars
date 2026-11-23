<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Content\PublicLegalContent;
use App\Domain\Content\SitePageHeading;
use App\Filament\Support\Controls\AdminControl;
use App\Filament\Support\Controls\AdminPanelControl;
use App\Domain\Media\MediaTypePolicy;
use App\Filament\Support\AdminRichText;
use App\Filament\Support\MediaAssetSelect;
use App\Models\CustomPageSetting;
use App\Models\PublicContentSetting;
use Closure;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

trait CustomPageWorkspaceForms
{
    /** Same Gallery Stage contract: canonical controls with immediate audited saves. */
    /** Layout-specific controls remain in the visual Stage, not Page Settings. */
    public function customStageLayoutForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    AdminControl::select('layout_mode', 'Arrangement')
                        ->options(['rows' => 'Aligned rows', 'columns' => 'Independent columns'])
                        ->live()
                        ->afterStateUpdated(fn (mixed $state) => $this->setStageLayoutProperty('layout_mode', $state)),
                    AdminControl::select('columns', 'Columns')
                        ->options([1 => '1', 2 => '2', 3 => '3', 4 => '4'])
                        ->live()
                        ->afterStateUpdated(fn (mixed $state) => $this->setStageLayoutProperty('columns', $state)),
                    AdminControl::select('rows', 'Rows')
                        ->options(array_combine(range(1, 12), array_map('strval', range(1, 12))))
                        ->visible(fn (Get $get): bool => $get('layout_mode') !== 'columns')
                        ->live()
                        ->afterStateUpdated(fn (mixed $state) => $this->setStageLayoutProperty('rows', $state)),
                    AdminControl::select('column_layout', 'Column widths')
                        ->options(['equal' => 'Equal', 'left-wide' => '65 / 35', 'right-wide' => '35 / 65'])
                        ->visible(fn (Get $get): bool => (int) $get('columns') === 2)
                        ->live()
                        ->afterStateUpdated(fn (mixed $state) => $this->setStageLayoutProperty('column_layout', $state)),
                    AdminControl::select('gap', 'Spacing')
                        ->options(['none' => 'None', 'small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'])
                        ->live()
                        ->afterStateUpdated(fn (mixed $state) => $this->setStageLayoutProperty('gap', $state)),
                ]),
            ])
            ->statePath('customStageLayoutData');
    }

    private function refreshCustomStageLayoutForm(): void
    {
        $selected = $this->stageSelectedLayoutId !== null
            ? $this->stageLayoutById($this->stageSelectedLayoutId) : null;
        if ($selected === null) {
            $this->stageSelectedLayoutId = null;
            $this->customStageLayoutForm->fill([]);

            return;
        }

        $layout = $selected['block'];
        $this->customStageLayoutForm->fill([
            'layout_mode' => $layout['layout_mode'] ?? 'rows',
            'columns' => $layout['columns'] ?? 2,
            'rows' => $layout['rows'] ?? 1,
            'column_layout' => $layout['column_layout'] ?? 'equal',
            'gap' => $layout['gap'] ?? 'medium',
        ]);
    }

    public function customStageSettingsForm(Schema $schema): Schema
    {
        $heading = AdminPanelControl::make(
            'Activate Page Heading',
            'content_heading_enabled',
            [
                AdminControl::text('content_heading_override', 'Heading text')
                    ->maxLength(160)
                    ->placeholder('Use page title')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (mixed $state) => $this->updateCustomStageHeading('content_heading_override', $state))
                    ->columnSpanFull(),
                AdminControl::select('content_heading_alignment', 'Alignment')
                    ->options(SitePageHeading::ALIGNMENTS)
                    ->default('left')
                    ->live()
                    ->afterStateUpdated(fn (mixed $state) => $this->updateCustomStageHeading('content_heading_alignment', $state)),
                AdminControl::select('content_heading_divider', 'Divider')
                    ->options(SitePageHeading::DIVIDERS)
                    ->default('none')
                    ->live()
                    ->afterStateUpdated(fn (mixed $state) => $this->updateCustomStageHeading('content_heading_divider', $state)),
            ],
            fn (mixed $state) => $this->updateCustomStageHeading('content_heading_enabled', (bool) $state),
            collapsible: true,
            bodyColumns: ['default' => 1, 'sm' => 2],
            dense: true,
        );

        return $schema
            ->components([
                Grid::make(['default' => 1])->schema([
                    $heading,
                    AdminControl::textarea('meta_description', 'Meta description')
                        ->rows(3)
                        ->maxLength(10000)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (mixed $state) => $this->updateCustomStageMetaDescription($state)),
                ]),
            ])
            ->statePath('customStageSettingsData');
    }

    private function refreshCustomStageSettings(): void
    {
        $section = $this->section();
        $this->customStageSettingsForm->fill([
            ...SitePageHeading::formState($section),
            'meta_description' => (string) ($section->getAttribute('meta_description') ?? ''),
        ]);
    }

    public function customStageNewForm(Schema $schema): Schema
    {
        return $schema
            ->components($this->componentEditorSchema(stage: true))
            ->statePath('customStageNewData');
    }

    /** @return array<string,mixed> */
    private function defaultCustomStageNew(): array
    {
        return [
            'type' => 'text',
            'publication_state' => 'unpublished',
            'title' => '',
            'body' => '',
            'layout_area_id' => null,
            'layout_divider_before' => 'none',
            'layout_mode' => 'rows',
            'columns' => 2,
            'rows' => 1,
            'column_layout' => 'equal',
            'gap' => 'medium',
            'media_asset_id' => null,
            'image_scale_percent' => CustomPageSetting::DEFAULT_IMAGE_SCALE_PERCENT,
            'image_alignment' => 'left',
            'video_controls_enabled' => true,
            'source_page_id' => null,
            'gallery_presentation' => 'sequence',
        ];
    }

    /** @return list<mixed> */
    private function componentEditorSchema(?Closure $onTypeChange = null, ?Closure $onImageChange = null, ?Closure $onContactChange = null, bool $stage = false): array
    {
        $typeField = Select::make('type')
            ->label('Component')
            ->options(array_map('__', self::COMPONENT_LABELS))
            ->required()
            ->live()
            ->afterStateUpdated(
                $onTypeChange ?? fn (Select $component) => $component
                    ->getContainer()
                    ->getComponent('dynamicComponentFields')
                    ->getChildSchema()
                    ->fill()
            );

        $statusField = Select::make('publication_state')
            ->label('Status')
            ->options(['published' => __('Published'), 'unpublished' => __('Unpublished')])
            ->default('published')
            ->required();

        // Type and Status share the first row in both Create and Edit.
        // Edit type changes use the canonical conversion Action, never a
        // second implicit mutation in the field state callback.
        $dynamicFields = Grid::make()
            ->columns($stage ? ['default' => 1] : ['md' => 2])
            ->schema(fn (Get $get): array => [
                ...$this->componentTypeFields((string) $get('type'), $onImageChange, $onContactChange),
            ])
            ->key('dynamicComponentFields')
            ->columnSpanFull();

        return [
            Grid::make()
                ->columns($stage ? ['default' => 1] : ['md' => 2])
                ->schema([
                    $typeField,
                    $statusField,
                        Select::make('layout_area_id')
                            ->label('Layout area')
                            ->placeholder('Full width')
                            ->options(fn (): array => $this->layoutAreaOptions())
                            ->nullable(),
                        Select::make('layout_divider_before')
                            ->label('Separator above')
                            ->options(['none' => 'None', ...self::DIVIDER_LABELS])
                            ->default('none')
                            ->required(),

                    $dynamicFields,
                ]),
        ];
    }

    /** @return list<mixed> */
    private function componentTypeFields(string $type, ?Closure $onImageChange = null, ?Closure $onContactChange = null): array
    {
        return match ($type) {
            'image', 'video' => [
                $this->mediaSelectField($onImageChange, $type),
                TextInput::make('image_scale_percent')
                    ->label('Media size')
                    ->numeric()
                    ->rules(['integer'])
                    ->minValue(CustomPageSetting::MIN_IMAGE_SCALE_PERCENT)
                    ->maxValue(CustomPageSetting::MAX_IMAGE_SCALE_PERCENT)
                    ->step(1)
                    ->suffix('%')
                    ->default(CustomPageSetting::DEFAULT_IMAGE_SCALE_PERCENT)
                    ->required(),
                Select::make('image_alignment')
                    ->label('Image alignment')
                    ->options(['left' => __('Left'), 'center' => __('Center'), 'right' => __('Right')])
                    ->default('left')
                    ->required(),
                ...($type === 'video' ? [
                    Toggle::make('video_controls_enabled')->label('Show video playback controls')->default(true),
                ] : []),
            ],
            'layout' => [
                Select::make('layout_mode')->label('Arrangement')
                    ->options(['rows' => 'Aligned rows', 'columns' => 'Independent columns'])
                    ->default('rows')->live()->required(),
                Select::make('columns')->label('Columns')->options([
                    1 => '1', 2 => '2', 3 => '3', 4 => '4',
                ])->default(2)->required(),
                Select::make('rows')->label('Rows')->options(array_combine(
                    range(1, 12), array_map('strval', range(1, 12)),
                ))->default(1)->required()
                    ->visible(fn (Get $get): bool => $get('layout_mode') !== 'columns'),
                Select::make('column_layout')->label('Column widths')
                    ->options([
                        'equal' => 'Equal',
                        'left-wide' => 'Left 65%, right 35%',
                        'right-wide' => 'Left 35%, right 65%',
                    ])
                    ->default('equal')
                    ->visible(fn (Get $get): bool => (int) $get('columns') === 2)
                    ->required(),
                Select::make('gap')->label('Gap')->options([
                    'none' => 'None', 'small' => 'Small', 'medium' => 'Medium', 'large' => 'Large',
                ])->default('medium')->required(),
            ],
            'gallery', 'hero_artworks', 'exhibitions', 'blog' => [
                Select::make('source_page_id')->label('Source page')
                    ->options(fn (): array => app(\App\Domain\Content\CustomPageEmbeddedContent::class)->sourceOptions($type))
                    ->searchable()->nullable(),
                ...($type === 'gallery' ? [
                    Select::make('gallery_presentation')->label('Presentation')
                        ->options(['sequence' => 'Sequence', 'two_columns' => 'Two columns'])
                        ->default('sequence')->required(),
                ] : []),
            ],
            'text' => [
                TextInput::make('title')->label('Heading')->maxLength(160),
                ...AdminRichText::schema('body', 'Text Editor', 20000),
            ],
            'contact' => [
                $this->contactEditorField(
                    TextInput::make('title')->label('Heading')->placeholder(__('Contact'))->maxLength(160)->columnSpanFull(),
                    'title', $onContactChange, blur: true,
                ),
                $this->contactEditorField(
                    Toggle::make('contact_email_enabled')->label('Public Email')->default(true)->live(),
                    'contact_email_enabled', $onContactChange,
                ),
                $this->contactEditorField(
                    Toggle::make('contact_social_enabled')->label('Social Media Links')->default(true)->live(),
                    'contact_social_enabled', $onContactChange,
                ),
                // Every profile reuses the same Toggle control as the rest
                // of Contact. The old native multi-select rendered chips and
                // obscured which configured platforms were actually enabled.
                Grid::make()
                    ->columns(['md' => 2])
                    ->schema(collect($this->availableSocialPlatforms)
                        ->map(fn (string $label, string $platform): Toggle => $this->contactEditorField(
                            Toggle::make('contact_social_selected.'.$platform)
                                ->label($label)->default(true),
                            'contact_social_selected.'.$platform, $onContactChange,
                        )
                        )->values()->all())
                    ->visible(fn (Get $get): bool => (bool) $get('contact_social_enabled'))
                    ->columnSpanFull(),
                $this->contactEditorField(
                    Toggle::make('contact_form_enabled')->label('Contact Form')
                        ->default(true)->live(),
                    'contact_form_enabled', $onContactChange,
                ),
            ],
            'legal_disclaimer' => [
                Placeholder::make('legal_disclaimer_note')
                    ->label('Legal Disclaimer from General')
                    ->content(fn (): string => PublicLegalContent::editorSummary(PublicContentSetting::general()))
                    ->columnSpanFull(),
            ],
            default => [],
        };
    }

    /**
     * Contact controls are Filament/Livewire state, not reliable native DOM
     * change events on the modal window. Persist their changed value through
     * one canonical per-field callback; create dialogs keep draft form state.
     */
    private function contactEditorField(mixed $field, string $key, ?Closure $save, bool $blur = false): mixed
    {
        if ($save === null) {
            return $field;
        }
        if ($blur) {
            $field->live(onBlur: true);
        } else {
            $field->live();
        }

        return $field->afterStateUpdated(static fn (mixed $state) => $save($key, $state));
    }

    /** @return array<string, string> */
    public function layoutAreaOptions(): array
    {
        $options = [];
        $layoutNumber = 0;
        foreach ($this->settings()->components() as $block) {
            if (($block['type'] ?? null) !== 'layout') {
                continue;
            }
            $layoutNumber++;
            $columns = (int) ($block['columns'] ?? 2);
            foreach ($block['areas'] ?? [] as $index => $area) {
                if (! is_array($area) || ! is_string($area['id'] ?? null)) {
                    continue;
                }
                $row = intdiv($index, $columns) + 1;
                $column = ($index % $columns) + 1;
                $options[$area['id']] = 'Layout '.$layoutNumber.', row '.$row.', column '.$column;
            }
        }

        return $options;
    }

    private function mediaSelectField(?Closure $onImageChange, string $type): Select
    {
        $allowed = $type === 'video' ? MediaTypePolicy::VIDEO_MIME_TYPES : MediaTypePolicy::IMAGE_MIME_TYPES;
        $label = $type === 'video' ? 'Video from Storage' : 'Image from Storage';
        $field = MediaAssetSelect::makeId('media_asset_id', $label, allowedMimeTypes: $allowed)
            ->required();

        // Searchable Filament selects update their Livewire state without a
        // reliable native change event on the surrounding Edit dialog.
        // Persist only on an actual field update; the existing action remains
        // the owner of the other component fields.
        if ($onImageChange !== null) {
            $field->live()->afterStateUpdated($onImageChange);
        }

        return $field;
    }
}
