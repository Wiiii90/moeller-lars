<?php

namespace App\Filament\Resources\PublicContentSettings;

use App\Domain\Content\SocialLinks;
use App\Filament\Resources\PublicContentSettings\Pages\EditPublicContentSetting;
use App\Filament\Support\MediaAssetSelect;
use App\Models\PublicContentSetting;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PublicContentSettingResource extends Resource
{
    protected static ?string $model = PublicContentSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'General';

    protected static ?string $modelLabel = 'general site settings';

    protected static ?string $pluralModelLabel = 'general site settings';

    protected static ?int $navigationSort = 30;

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
            Section::make('Site identity')
                ->description('Shared browser identity for the public website.')
                ->schema([
                    MediaAssetSelect::make('favicon_media_asset_id', 'faviconMediaAsset', 'Favicon', imagesOnly: true)
                        ->nullable()
                        ->helperText('Choose an image from Media. The generated thumbnail variant is used as the browser icon.')
                        ->columnSpanFull(),
                ]),
            Section::make('Public contact')
                ->description('Public contact data is separate from the private address that receives form submissions.')
                ->schema([
                    TextInput::make('public_email')
                        ->label('Public email')
                        ->email()
                        ->maxLength(254)
                        ->nullable()
                        ->helperText('The address visitors may see on the public website.'),
                    Toggle::make('show_public_email')
                        ->label('Show public email')
                        ->default(true),
                ])
                ->columns(2),
            Section::make('Social links')
                ->description('Public artist profiles. Each supported platform can be configured once and hidden without deletion.')
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
                        ->columns(3)
                        ->defaultItems(0)
                        ->reorderable()
                        ->collapsible()
                        ->addActionLabel('Add social link')
                        ->itemLabel(fn (array $state): ?string => isset($state['platform']) && is_string($state['platform']) ? SocialLinks::label($state['platform']) : null)
                        ->columnSpanFull(),
                ]),
            Section::make('Contact delivery')
                ->description('Only the private recipient lives here. SMTP credentials, sender identity, DKIM and TLS remain runtime/platform configuration.')
                ->schema([
                    TextInput::make('contact_recipient_email')
                        ->label('Private delivery recipient')
                        ->email()
                        ->maxLength(254)
                        ->nullable()
                        ->helperText('If empty, the server-configured fallback recipient is used.'),
                ]),
            Section::make('Legal')
                ->description('Site-wide public disclaimer text displayed where the public design includes it.')
                ->schema([
                    Textarea::make('legal_disclaimer')->label('Legal disclaimer')->rows(4)->nullable(),
                ])
                ->collapsible(),
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
