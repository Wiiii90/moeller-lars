<?php

namespace App\Filament\Resources\PublicContentSettings;

use App\Domain\Content\SocialLinks;
use App\Filament\Resources\PublicContentSettings\Pages\EditPublicContentSetting;
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
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
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
            Grid::make([
                'default' => 1,
                'xl' => 2,
            ])
                ->columnSpanFull()
                ->extraAttributes(['class' => 'artist-general-grid'])
                ->schema([
                    Fieldset::make('Site identity')
                        ->contained(false)
                        ->extraAttributes(['class' => 'artist-general-section'])
                        ->schema([
                            MediaAssetSelect::make('favicon_media_asset_id', 'faviconMediaAsset', 'Favicon', imagesOnly: true)
                                ->nullable()
                                ->helperText('Choose an image from Files. The generated thumbnail variant is used as the browser icon.')
                                ->columnSpanFull(),
                        ]),
                    Fieldset::make('Public contact')
                        ->contained(false)
                        ->extraAttributes(['class' => 'artist-general-section'])
                        ->schema([
                            TextInput::make('public_email')
                                ->label('Public email')
                                ->email()
                                ->maxLength(254)
                                ->nullable(),
                            Toggle::make('show_public_email')
                                ->label('Show public email')
                                ->default(true),
                        ])
                        ->columns(2),
                    Fieldset::make('Social links')
                        ->contained(false)
                        ->extraAttributes(['class' => 'artist-general-section'])
                        ->columnSpanFull()
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
                    Fieldset::make('Contact delivery')
                        ->contained(false)
                        ->extraAttributes(['class' => 'artist-general-section'])
                        ->schema([
                            TextInput::make('contact_recipient_email')
                                ->label('Private delivery recipient')
                                ->email()
                                ->maxLength(254)
                                ->nullable()
                                ->helperText('If empty, the server-configured fallback recipient is used.'),
                        ]),
                    Fieldset::make('Legal')
                        ->contained(false)
                        ->extraAttributes(['class' => 'artist-general-section'])
                        ->schema([
                            Textarea::make('legal_disclaimer')->label('Legal disclaimer')->rows(4)->nullable(),
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
