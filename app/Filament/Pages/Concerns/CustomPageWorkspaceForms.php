<?php

namespace App\Filament\Pages\Concerns;

use App\Filament\Support\AdminRichText;
use App\Filament\Support\MediaAssetSelect;
use App\Models\PublicContentSetting;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;

trait CustomPageWorkspaceForms
{
    /** @return list<mixed> */
    private function componentEditorSchema(bool $includeTypeSelect): array
    {
        $typeField = $includeTypeSelect
            ? Select::make('type')
                ->label('Component')
                ->options(self::COMPONENT_LABELS)
                ->required()
                ->live()
                ->afterStateUpdated(fn (Select $component) => $component
                    ->getContainer()
                    ->getComponent('dynamicComponentFields')
                    ->getChildSchema()
                    ->fill())
            : Hidden::make('type')->required();

        return [
            Grid::make()
                ->columns(['md' => 2])
                ->schema([
                    $typeField,
                    Select::make('publication_state')
                        ->label('Status')
                        ->options(['published' => 'Published', 'unpublished' => 'Unpublished'])
                        ->default('published')
                        ->required(),
                    Grid::make()
                        ->columns(['md' => 2])
                        ->schema(fn (Get $get): array => $this->componentTypeFields((string) $get('type'), $includeTypeSelect))
                        ->key('dynamicComponentFields')
                        ->columnSpanFull(),
                ]),
        ];
    }

    /** @return list<mixed> */
    private function componentTypeFields(string $type, bool $isNew): array
    {
        return match ($type) {
            'image' => [
                MediaAssetSelect::makeId('media_asset_id', 'Image from Media Files', imagesOnly: true)->required(),
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
                MediaAssetSelect::makeId('media_asset_id', 'Optional list image', imagesOnly: true)->nullable(),
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

    /** @return list<mixed> */
    private function listEntrySchema(): array
    {
        return [
            Grid::make()
                ->columns(['md' => 2])
                ->schema([
                    Select::make('publication_state')
                        ->label('Status')
                        ->options(['published' => 'Published', 'unpublished' => 'Unpublished'])
                        ->default('published')
                        ->required(),
                    TextInput::make('date')->label('Date / year')->maxLength(120),
                    TextInput::make('title')->label('Entry')->required()->maxLength(240),
                    TextInput::make('meta')->label('Organisation / context')->maxLength(240),
                    TextInput::make('location')->maxLength(240),
                    TextInput::make('url')->label('Optional link')->url()->maxLength(2048),
                    ...AdminRichText::schema('body', 'Details', 10000),
                ]),
        ];
    }
}
