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
        return static::getUrl('edit', ['record' => PublicContentSetting::query()->sole()]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Site identity')
                ->description('Shared browser identity for the public website.')
                ->schema([
                    MediaAssetSelect::make('favicon_media_asset_id', 'faviconMediaAsset', 'Favicon', imagesOnly: true)
                        ->nullable()
                        ->helperText('Choose an image from Media. The thumbnail preview helps identify the selected asset; the public generated variant is used as the browser icon.')
                        ->columnSpanFull(),
                ]),
            Section::make('Public contact')
                ->description('Public contact data can be shown independently from the private address that receives form submissions. Vita and Exhibitions publication/navigation are managed from Pages.')
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
                ->description('Add the artist profiles that should be linked publicly. Each supported platform has one URL; links can be hidden without deleting them.')
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
            Section::make('Contact form delivery')
                ->description('The recipient can be changed here without a deployment. SMTP credentials and mail-server configuration remain runtime/platform secrets and are not exposed in the admin.')
                ->schema([
                    TextInput::make('contact_recipient_email')
                        ->label('Private delivery recipient')
                        ->email()
                        ->maxLength(254)
                        ->nullable()
                        ->helperText('Form messages are delivered here. If empty, the server-configured fallback recipient is used.'),
                ]),
            Section::make('Contact form presentation')
                ->description('Controls the currently shared Contact form presentation. When Contact becomes a canonical Pages/SiteSection type, these presentation fields can move there without duplicating delivery settings.')
                ->schema([
                    Select::make('contact_state')->label('Form state')->options([
                        'enabled' => 'Enabled',
                        'under_construction' => 'Under construction',
                        'hidden' => 'Hidden',
                    ])->required(),
                    Textarea::make('contact_status_text')
                        ->label('Under-construction message')
                        ->maxLength(500)
                        ->nullable()
                        ->helperText('Used only while the form state is “Under construction”.')
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('Additional Vita / CV text')
                ->description('Optional site-wide profile text currently composed on Vita. Reorder the blocks here instead of adding one-off public templates.')
                ->schema([
                    Repeater::make('profile_text_blocks')
                        ->label('Text blocks')
                        ->schema([
                            TextInput::make('title')->required()->maxLength(120),
                            Textarea::make('body')->required()->rows(4)->maxLength(5000),
                        ])
                        ->defaultItems(0)
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                        ->columnSpanFull(),
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
