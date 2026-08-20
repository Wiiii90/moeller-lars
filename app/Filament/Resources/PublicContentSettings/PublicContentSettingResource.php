<?php

namespace App\Filament\Resources\PublicContentSettings;

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
        return static::getUrl('edit', ['record' => 1]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Public identity and contact')
                ->description('Central public profile values. Vita / CV uses these values directly; visibility can be controlled independently from the contact form.')
                ->schema([
                    TextInput::make('public_email')->label('Public email')->email()->maxLength(254)->nullable(),
                    Toggle::make('show_public_email')->label('Show email on Vita / CV')->default(true),
                    TextInput::make('instagram_handle')->label('Instagram handle')->maxLength(30)->regex('/^[A-Za-z0-9._]{1,30}$/')->nullable(),
                    Toggle::make('show_instagram')->label('Show Instagram on Vita / CV')->default(true),
                ])
                ->columns(2),
            Section::make('Contact form')
                ->description('Controls the message form inside Vita / CV. The delivery address stays private and mail transport credentials remain server configuration.')
                ->schema([
                    Select::make('contact_state')->label('Form state')->options([
                        'enabled' => 'Enabled',
                        'under_construction' => 'Under construction',
                        'hidden' => 'Hidden',
                    ])->required(),
                    TextInput::make('contact_recipient_email')
                        ->label('Delivery recipient')
                        ->email()
                        ->maxLength(254)
                        ->nullable()
                        ->helperText('Messages are delivered here. If empty, the server-configured fallback address is used.'),
                    Textarea::make('contact_status_text')
                        ->label('Under-construction message')
                        ->maxLength(500)
                        ->nullable()
                        ->helperText('Used only while the form state is “Under construction”.')
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('Site identity')
                ->description('Shared browser identity for the public site.')
                ->schema([
                    MediaAssetSelect::make('favicon_media_asset_id', 'faviconMediaAsset', 'Favicon')
                        ->nullable()
                        ->helperText('Use a small square source image. The public generated variant is served as the browser icon.')
                        ->columnSpanFull(),
                ]),
            Section::make('Additional Vita / CV text')
                ->description('Optional editorial text blocks shown between Contact and the legal disclaimer. Reorder them here instead of adding one-off public templates.')
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
                ->description('Public disclaimer text displayed at the bottom of Vita / CV where configured.')
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
