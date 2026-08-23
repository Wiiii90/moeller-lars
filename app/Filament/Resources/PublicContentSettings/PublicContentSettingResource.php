<?php

namespace App\Filament\Resources\PublicContentSettings;

use App\Domain\Content\SocialLinks;
use App\Filament\Resources\PublicContentSettings\Pages\EditPublicContentSetting;
use App\Filament\Support\AdminForm;
use App\Filament\Support\MediaAssetSelect;
use App\Models\PublicContentSetting;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PublicContentSettingResource extends Resource
{
    protected static ?string $model = PublicContentSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?string $navigationLabel = 'General';

    protected static ?string $modelLabel = 'general site settings';

    protected static ?string $pluralModelLabel = 'general site settings';

    protected static ?int $navigationSort = 1;

    public static function getNavigationUrl(): string
    {
        return static::getUrl('edit', ['record' => PublicContentSetting::general()]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('scope', PublicContentSetting::SCOPE_GENERAL);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(1)
                ->extraAttributes(['class' => 'admin-settings-grid'])
                ->schema([
                    AdminForm::section('Site identity')
                        ->schema([
                            MediaAssetSelect::make('favicon_media_asset_id', 'faviconMediaAsset', 'Favicon', imagesOnly: true)
                                ->nullable()
                                ->live()
                                ->afterStateUpdated(self::autosave('favicon_media_asset_id'))
                                ->hintIcon(
                                    Heroicon::OutlinedLightBulb,
                                    'Choose an available image from Media Files. Its generated thumbnail is used for browser identity.',
                                )
                                ->columnSpanFull(),
                            View::make('filament.schemas.components.favicon-preview')
                                ->columnSpanFull(),
                        ]),
                    AdminForm::section('Public contact')
                        ->schema([
                            TextInput::make('public_email')
                                ->label('Public email')
                                ->email()
                                ->maxLength(254)
                                ->nullable()
                                ->live(debounce: 700)
                                ->extraInputAttributes(['x-on:blur' => '$wire.autosaveField(\'public_email\')'])
                                ->afterStateUpdated(self::autosave('public_email'))
                                ->hintIcon(
                                    Heroicon::OutlinedLightBulb,
                                    'This address can be shown to visitors when Show publicly is enabled.',
                                ),
                            Toggle::make('show_public_email')
                                ->label('Show publicly')
                                ->default(true)
                                ->live()
                                ->afterStateUpdated(self::autosave('show_public_email')),
                        ])
                        ->columns(2),
                    AdminForm::section('Contact delivery')
                        ->schema([
                            TextInput::make('contact_recipient_email')
                                ->label('Private contact recipient')
                                ->email()
                                ->maxLength(254)
                                ->nullable()
                                ->live(debounce: 700)
                                ->extraInputAttributes(['x-on:blur' => '$wire.autosaveField(\'contact_recipient_email\')'])
                                ->afterStateUpdated(self::autosave('contact_recipient_email'))
                                ->hintIcon(
                                    Heroicon::OutlinedLightBulb,
                                    'Receives contact-form messages. If empty, the server-configured fallback recipient is used.',
                                ),
                        ]),
                    AdminForm::section('Social links')
                        ->schema([
                            Repeater::make('social_links')
                                ->label('Profiles')
                                ->schema([
                                    Select::make('platform')
                                        ->options(SocialLinks::options())
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(self::autosave('social_links')),
                                    TextInput::make('url')
                                        ->label('Profile URL')
                                        ->url()
                                        ->maxLength(2048)
                                        ->required()
                                        ->live(debounce: 700)
                                        ->extraInputAttributes(['x-on:blur' => '$wire.autosaveField(\'social_links\')'])
                                        ->afterStateUpdated(self::autosave('social_links')),
                                    Toggle::make('visible')
                                        ->label('Visible')
                                        ->default(true)
                                        ->live()
                                        ->afterStateUpdated(self::autosave('social_links')),
                                ])
                                ->table([
                                    TableColumn::make('Platform'),
                                    TableColumn::make('Profile URL'),
                                    TableColumn::make('Visible'),
                                ])
                                ->compact()
                                ->defaultItems(0)
                                ->reorderableWithButtons()
                                ->reorderableWithDragAndDrop(false)
                                ->addActionLabel('Add social link')
                                ->live()
                                ->afterStateUpdated(self::autosave('social_links'))
                                ->columnSpanFull(),
                        ]),
                    AdminForm::section('Legal / global text')
                        ->schema([
                            TextInput::make('default_media_copyright_notice')
                                ->label('Default media copyright')
                                ->maxLength(500)
                                ->nullable()
                                ->live(debounce: 700)
                                ->extraInputAttributes(['x-on:blur' => '$wire.autosaveField(\'default_media_copyright_notice\')'])
                                ->afterStateUpdated(self::autosave('default_media_copyright_notice'))
                                ->hintIcon(
                                    Heroicon::OutlinedLightBulb,
                                    'Inherited by media unless an individual file overrides the notice or explicitly uses no notice.',
                                ),
                            Textarea::make('legal_disclaimer')
                                ->label('Legal disclaimer')
                                ->rows(4)
                                ->nullable()
                                ->live(debounce: 700)
                                ->extraInputAttributes(['x-on:blur' => '$wire.autosaveField(\'legal_disclaimer\')'])
                                ->afterStateUpdated(self::autosave('legal_disclaimer')),
                        ]),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'edit' => EditPublicContentSetting::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    private static function autosave(string $field): \Closure
    {
        return static function ($livewire) use ($field): void {
            if ($livewire instanceof EditPublicContentSetting) {
                $livewire->autosaveField($field);
            }
        };
    }
}
