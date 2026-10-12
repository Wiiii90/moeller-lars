<?php

namespace App\Filament\Pages\Concerns;

use App\Filament\Support\AdminRichText;
use App\Filament\Support\ComponentListEditor;
use App\Filament\Support\ComponentTypeChangeControl;
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
    private function componentEditorSchema(
        ?string $originalType = null,
        ?Closure $losesContent = null,
        ?Closure $commitType = null,
    ): array {
        $applyType = null;
        if ($originalType !== null && $losesContent !== null && $commitType !== null) {
            [$typeField, $applyType] = ComponentTypeChangeControl::make(
                'type', self::COMPONENT_LABELS, $originalType, $losesContent, $commitType,
            );
        } else {
            $typeField = Select::make('type')
                ->label('Component')
                ->options(self::COMPONENT_LABELS)
                ->required()
                ->live()
                ->afterStateUpdated(fn (Select $component) => $component
                    ->getContainer()
                    ->getComponent('dynamicComponentFields')
                    ->getChildSchema()
                    ->fill());
        }

        $statusField = Select::make('publication_state')
            ->label('Status')
            ->options(['published' => 'Published', 'unpublished' => 'Unpublished'])
            ->default('published')
            ->required();

        // In Edit the type selector stages a conversion; the old fields are
        // unavailable until Apply type has atomically replaced the component.
        $dynamicFields = Grid::make()
            ->columns(['md' => 2])
            ->schema(fn (Get $get): array => $this->componentTypeFields(
                $originalType ?? (string) $get('type'),
                $originalType === null,
            ))
            ->key('dynamicComponentFields')
            ->visible(fn (Get $get): bool => $originalType === null || $get('type') === $originalType)
            ->columnSpanFull();

        return [
            Grid::make()
                ->columns(['md' => 2])
                ->schema([
                    $typeField,
                    $statusField,
                    ...($applyType === null ? [] : [$applyType]),
                    $dynamicFields,
                ]),
        ];
    }

    /** @return list<mixed> */
    private function componentTypeFields(string $type, bool $isNew): array
    {
        return match ($type) {
            'image' => [
                MediaAssetSelect::makeId('media_asset_id', 'Image from Storage', imagesOnly: true)->required(),
                Toggle::make('image_decorative')->label('Decorative image')->default(false),
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
                Placeholder::make('contact_note')
                    ->label('Contact items')
                    ->content($isNew
                        ? 'Public Email, Social Media Links and Contact Form are created with this component.'
                        : 'Contact items are managed in the child rows below.')
                    ->columnSpanFull(),
            ],
            'legal_disclaimer' => [
                Placeholder::make('legal_disclaimer_note')
                    ->label('Legal disclaimer from General')
                    ->content(function (): string {
                        $value = PublicContentSetting::general()->getAttribute('legal_disclaimer');

                        return is_string($value) && trim($value) !== ''
                            ? $value
                            : 'No legal disclaimer is configured in General.';
                    })
                    ->columnSpanFull(),
            ],
            default => [],
        };
    }

}
