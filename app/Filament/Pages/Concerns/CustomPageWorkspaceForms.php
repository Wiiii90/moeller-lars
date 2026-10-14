<?php

namespace App\Filament\Pages\Concerns;

use App\Filament\Support\AdminRichText;
use App\Filament\Support\ComponentListEditor;
use App\Filament\Support\DecorativeImageChildren;
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
                DecorativeImageChildren::make($this->availableSocialPlatforms),
                ...(! $isNew ? [
                    Select::make('adopt_component_index')
                        ->label('Move existing component under image')
                        ->options(fn (): array => $this->adoptableImageComponents())
                        ->placeholder('No move')
                        ->native()
                        ->nullable()
                        ->visible(fn (Get $get): bool => (bool) $get('image_decorative'))
                        ->columnSpanFull(),
                ] : []),
            ],
            'text' => $isNew
                ? AdminRichText::schema('body', 'Rich Text', 20000)
                : [
                    TextInput::make('title')->label('Heading')->maxLength(160),
                    ...AdminRichText::schema('body', 'Rich Text', 20000),
                    $this->moveIntoImageField(),
                ],
            'list' => [
                TextInput::make('title')->label('Heading')->maxLength(160),
                MediaAssetSelect::makeId('media_asset_id', 'Optional list image', imagesOnly: true)
                    ->placeholder('No list image')
                    ->nullable(),
                ComponentListEditor::make(),
                ...(! $isNew ? [$this->moveIntoImageField()] : []),
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
                ...(! $isNew ? [$this->moveIntoImageField()] : []),
                TextInput::make('contact_status_text')
                    ->label('Status text')
                    ->maxLength(500)
                    ->required(fn (Get $get): bool => (bool) $get('contact_form_enabled') && $get('contact_form_state') === 'under_construction')
                    ->visible(fn (Get $get): bool => (bool) $get('contact_form_enabled') && $get('contact_form_state') === 'under_construction'),
            ],
            'legal_disclaimer' => [
                Placeholder::make('legal_disclaimer_note')
                    ->label('Rechtliches aus General')
                    ->content(fn (): string => \App\Domain\Content\PublicLegalContent::editorSummary(PublicContentSetting::general()))
                    ->columnSpanFull(),
            ],
            default => [],
        };
    }

    private function moveIntoImageField(): Select
    {
        return Select::make('move_into_image')
            ->label('Move this component into decorative image')
            ->options(fn (): array => collect($this->settings()->components())
                ->filter(static fn (array $block): bool => ($block['type'] ?? '') === 'image'
                    && ($block['image_decorative'] ?? false) === true)
                ->mapWithKeys(static fn (array $block, int $index): array => [
                    $index => 'Image '.($index + 1),
                ])->all())
            ->placeholder('Keep at page level')
            ->nullable()
            ->native()
            ->columnSpanFull();
    }

    /** Only eligible standalone prose can become an Image child. */
    private function adoptableImageComponents(): array
    {
        $options = [];
        foreach ($this->settings()->components() as $index => $block) {
            $type = $block['type'] ?? null;
            if (! in_array($type, \App\Models\CustomPageSetting::IMAGE_CHILD_TYPES, true)) {
                continue;
            }
            if ($type === 'list' && ($block['media_asset_id'] ?? null) !== null) {
                continue;
            }
            if ($type === 'contact') {
                $hasForm = collect($this->settings()->contactChildren($block))
                    ->contains(fn (array $item): bool => ($item['type'] ?? null) === 'contact_form'
                        && \App\Models\CustomPageSetting::contactChildPublished($item));
                if ($hasForm) {
                    continue;
                }
            }
            $title = trim((string) ($block['title'] ?? ''));
            $options[$index] = ($index + 1).' · '.(self::COMPONENT_LABELS[$type] ?? $type)
                .($title === '' ? '' : ' — '.$title);
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
