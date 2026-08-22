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
                                ->helperText('Choose an image from Files. The existing generated thumbnail is used for browser identity.')
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
                                ->helperText('Address visitors may see when public display is enabled.'),
                            Toggle::make('show_public_email')
                                ->label('Show publicly')
                                ->default(true),
                        ])
                        ->columns(2),
                    AdminForm::section('Contact delivery')
                        ->schema([
                            TextInput::make('contact_recipient_email')
                                ->label('Private contact recipient')
                                ->email()
                                ->maxLength(254)
                                ->nullable()
                                ->helperText('Receives contact-form messages. If empty, the server-configured fallback recipient is used.'),
                        ]),
                    AdminForm::section('Social links')
                        ->schema([
                            Repeater::make('social_links')
                                ->label('Profiles')
                                ->schema([
                                    Select::make('platform')
                                        ->options(SocialLinks::options())
                                        ->required(),
                                    TextInput::make('url')
                                        ->label('Profile URL')
                                        ->url()
                                        ->maxLength(2048)
                                        ->required(),
                                    Toggle::make('visible')
                                        ->label('Visible')
                                        ->default(true),
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
                                ->columnSpanFull(),
                        ]),
                    AdminForm::section('Legal')
                        ->schema([
                            Textarea::make('legal_disclaimer')
                                ->label('Legal disclaimer')
                                ->rows(4)
                                ->nullable(),
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
}
