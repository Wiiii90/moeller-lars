<?php

namespace App\Filament\Pages\Concerns;

use App\Filament\Support\AdminRichText;
use App\Filament\Support\ComponentListEditor;
use App\Filament\Support\HierarchyParentField;
use App\Filament\Support\MediaAssetSelect;
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
    private function componentEditorSchema(?Closure $onTypeChange = null, ?Closure $onImageChange = null): array
    {
        $typeField = Select::make('type')
            ->label('Component')
            ->options(self::COMPONENT_LABELS)
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
            ->options(['published' => 'Published', 'unpublished' => 'Unpublished'])
            ->default('published')
            ->required();

        // Type and Status share the first row in both Create and Edit.
        // Edit type changes use the canonical conversion Action, never a
        // second implicit mutation in the field state callback.
        $dynamicFields = Grid::make()
            ->columns(['md' => 2])
            ->schema(fn (Get $get): array => [
                ...$this->componentTypeFields((string) $get('type'), $onTypeChange === null, $onImageChange),
            ])
            ->key('dynamicComponentFields')
            ->columnSpanFull();

        return [
            Grid::make()
                ->columns(['md' => 2])
                ->schema([
                    $typeField,
                    $statusField,
                    ...($onTypeChange === null ? [] : [$this->componentParentField()]),
                    $dynamicFields,
                ]),
        ];
    }

    /** @return list<mixed> */
    private function componentTypeFields(string $type, bool $isNew, ?Closure $onImageChange = null): array
    {
        return match ($type) {
            'image' => [
                $this->imageSelectField($onImageChange),
                TextInput::make('image_scale_percent')
                    ->label('Image size')
                    ->numeric()
                    ->rules(['integer'])
                    ->minValue(\App\Models\CustomPageSetting::MIN_IMAGE_SCALE_PERCENT)
                    ->maxValue(\App\Models\CustomPageSetting::MAX_IMAGE_SCALE_PERCENT)
                    ->step(1)
                    ->suffix('%')
                    ->default(\App\Models\CustomPageSetting::DEFAULT_IMAGE_SCALE_PERCENT)
                    ->required(),
                Toggle::make('image_decorative')->label('Decorative image')->default(false)->live(),

            ],
            'text' => $isNew
                ? AdminRichText::schema('body', 'Rich Text', 20000)
                : [
                    TextInput::make('title')->label('Heading')->maxLength(160),
                    ...AdminRichText::schema('body', 'Rich Text', 20000),
                ],
            'list' => [
                TextInput::make('title')->label('Heading')->maxLength(160),
                MediaAssetSelect::makeId('media_asset_id', 'Optional list image', imagesOnly: true)
                    ->placeholder('No list image')
                    ->nullable(),
                ComponentListEditor::make(),
            ],
            'divider' => [
                Select::make('variant')->label('Divider')->options(self::DIVIDER_LABELS)->default('thin')->required(),
            ],
            'contact' => [
                TextInput::make('title')->label('Heading')->placeholder('Contact')->maxLength(160)->columnSpanFull(),
                Toggle::make('contact_email_enabled')->label('Public Email')->default(true)->live(),
                Toggle::make('contact_social_enabled')->label('Social Media Links')->default(true)->live(),
                Select::make('contact_social_platforms')
                    ->label('Social links from General')
                    ->options($this->availableSocialPlatforms)
                    ->multiple()
                    ->default(array_keys($this->availableSocialPlatforms))
                    ->visible(fn (Get $get): bool => (bool) $get('contact_social_enabled'))
                    ->columnSpanFull(),
                Toggle::make('contact_form_enabled')->label('Contact Form')->default(true)->live(),
                Select::make('contact_form_state')
                    ->label('Form presentation')
                    ->options(['enabled' => 'Enabled', 'under_construction' => 'Under construction'])
                    ->default('enabled')
                    ->live()
                    ->visible(fn (Get $get): bool => (bool) $get('contact_form_enabled')),
                TextInput::make('contact_status_text')
                    ->label('Status text')
                    ->maxLength(500)
                    ->required(fn (Get $get): bool => (bool) $get('contact_form_enabled') && $get('contact_form_state') === 'under_construction')
                    ->visible(fn (Get $get): bool => (bool) $get('contact_form_enabled') && $get('contact_form_state') === 'under_construction'),
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

    /** Visible at the top of every eligible component edit, even for List
     * dialogs with long repeater content. Null means Page level. */
    private function componentParentField(): Select
    {
        return HierarchyParentField::make(
            'parent_image_index',
            fn (): array => $this->decorativeImageParentOptions(),
            'Parent', 'Page level',
        )
            ->visible(fn (Get $get): bool =>
                in_array((string) $get('type'), \App\Models\CustomPageSetting::IMAGE_CHILD_TYPES, true)
                && ($get('type') !== 'list' || blank($get('media_asset_id')))
                && ($get('type') !== 'contact' || ! $get('contact_form_enabled'))
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
        $field = MediaAssetSelect::makeId('media_asset_id', 'Image from Storage', imagesOnly: true)
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
