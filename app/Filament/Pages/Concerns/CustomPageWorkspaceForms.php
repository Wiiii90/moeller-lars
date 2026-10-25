<?php

namespace App\Filament\Pages\Concerns;

use App\Filament\Support\AdminRichText;
use App\Filament\Support\ComponentListEditor;
use App\Filament\Support\HierarchyParentField;
use App\Filament\Support\MediaAssetSelect;
use App\Domain\Media\MediaTypePolicy;
use App\Models\PublicContentSetting;
use Closure;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;

trait CustomPageWorkspaceForms
{
    /** @return list<mixed> */
    private function componentEditorSchema(?Closure $onTypeChange = null, ?Closure $onImageChange = null, ?string $editingType = null, ?Closure $onContactChange = null): array
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
            ->columns(['md' => 2])
            ->schema(fn (Get $get): array => [
                ...$this->componentTypeFields((string) $get('type'), $onTypeChange === null, $onImageChange, $onContactChange),
            ])
            ->key('dynamicComponentFields')
            ->columnSpanFull();

        return [
            Grid::make()
                ->columns(['md' => 2])
                ->schema([
                    $typeField,
                    $statusField,
                    ...($onTypeChange !== null && in_array($editingType, \App\Models\CustomPageSetting::IMAGE_CHILD_TYPES, true)
                        && $editingType !== 'text'
                        ? [$this->componentParentField($editingType)]
                        : []),
                    $dynamicFields,
                ]),
        ];
    }

    /** @return list<mixed> */
    private function componentTypeFields(string $type, bool $isNew, ?Closure $onImageChange = null, ?Closure $onContactChange = null): array
    {
        return match ($type) {
            'image' => [
                $this->imageSelectField($onImageChange),
                TextInput::make('image_scale_percent')
                    ->label('Media size')
                    ->numeric()
                    ->rules(['integer'])
                    ->minValue(\App\Models\CustomPageSetting::MIN_IMAGE_SCALE_PERCENT)
                    ->maxValue(\App\Models\CustomPageSetting::MAX_IMAGE_SCALE_PERCENT)
                    ->step(1)
                    ->suffix('%')
                    ->default(\App\Models\CustomPageSetting::DEFAULT_IMAGE_SCALE_PERCENT)
                    ->required(),
                Select::make('image_alignment')
                    ->label('Image alignment')
                    ->options(['left' => __('Left'), 'center' => __('Center'), 'right' => __('Right')])
                    ->default('left')
                    ->required(),
                Toggle::make('image_decorative')->label('Decorative image')->default(false)->live(),
                Toggle::make('video_controls_enabled')->label('Show video playback controls')->default(true),
            ],
            'text' => $isNew
                ? AdminRichText::schema('body', 'Text Editor', 20000)
                : [
                    $this->componentParentField('text')->columnSpan(1),
                    TextInput::make('title')->label('Heading')->maxLength(160),
                    ...AdminRichText::schema('body', 'Text Editor', 20000),
                ],
            'list' => [
                TextInput::make('title')->label('Heading')->maxLength(160),
                MediaAssetSelect::makeId('media_asset_id', 'Optional list image', imagesOnly: true)
                    ->placeholder(__('No list image'))
                    ->nullable(),
                ComponentListEditor::make(),
            ],
            'divider' => [
                Select::make('variant')->label('Divider')->options(array_map('__', self::DIVIDER_LABELS))->default('thin')->required(),
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
                        ->map(fn (string $label, string $platform): Toggle =>
                            $this->contactEditorField(
                                Toggle::make('contact_social_selected.'.$platform)
                                    ->label($label)->default(true),
                                'contact_social_selected.'.$platform, $onContactChange,
                            )
                        )->values()->all())
                    ->visible(fn (Get $get): bool => (bool) $get('contact_social_enabled'))
                    ->columnSpanFull(),
                $this->contactEditorField(
                    Toggle::make('contact_form_enabled')->label('Contact Form')->default(true)->live(),
                    'contact_form_enabled', $onContactChange,
                ),
                $this->contactEditorField(
                    Select::make('contact_form_state')
                        ->label('Form presentation')
                        ->options(['enabled' => __('Enabled'), 'under_construction' => __('Under construction')])
                        ->default('enabled')->live()
                        ->visible(fn (Get $get): bool => (bool) $get('contact_form_enabled')),
                    'contact_form_state', $onContactChange,
                ),
                $this->contactEditorField(
                    TextInput::make('contact_status_text')
                        ->label('Status text')->maxLength(500)
                        ->required(fn (Get $get): bool => (bool) $get('contact_form_enabled') && $get('contact_form_state') === 'under_construction')
                        ->visible(fn (Get $get): bool => (bool) $get('contact_form_enabled') && $get('contact_form_state') === 'under_construction'),
                    'contact_status_text', $onContactChange, blur: true,
                ),
            ],
            'legal_disclaimer' => [
                Placeholder::make('legal_disclaimer_note')
                    ->label('Legal Disclaimer from General')
                    ->content(fn (): string => \App\Domain\Content\PublicLegalContent::editorSummary(PublicContentSetting::general()))
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
        if ($save === null) return $field;
        if ($blur) {
            $field->live(onBlur: true);
        } else {
            $field->live();
        }

        return $field->afterStateUpdated(static fn (mixed $state) => $save($key, $state));
    }

    /** Visible at the top of every eligible component edit, even for List
     * dialogs with long repeater content. Null means Page level. */
    private function componentParentField(string $editingType): Select
    {
        // Root and child Edit Actions already know the persisted type.
        // Do not hide Divider's Parent based on a reactive Grid path.
        return HierarchyParentField::make(
            'parent_image_index',
            fn (): array => $this->decorativeImageParentOptions(),
            'Parent', 'Page level',
        )
            ->disabled(fn (Get $get): bool =>
                ($editingType === 'list' && filled($get('media_asset_id')))
                || ($editingType === 'contact' && (bool) $get('contact_form_enabled'))
            )
            ->columnSpanFull();
    }

    /** @return array<int,string> */
    private function decorativeImageParentOptions(): array
    {
        $options = [];
        foreach ($this->settings()->components() as $index => $block) {
            if (($block['type'] ?? null) === 'image' && ($block['image_decorative'] ?? false)) {
                $options[$index] = 'Decorative Image '.($index + 1);
            }
        }
        return $options;
    }

    private function imageSelectField(?Closure $onImageChange): Select
    {
        $field = MediaAssetSelect::makeId('media_asset_id', 'Image or video from Storage', allowedMimeTypes: [...MediaTypePolicy::IMAGE_MIME_TYPES, ...MediaTypePolicy::VIDEO_MIME_TYPES])
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
