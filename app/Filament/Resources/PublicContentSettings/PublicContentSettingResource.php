<?php

namespace App\Filament\Resources\PublicContentSettings;

use App\Filament\Resources\PublicContentSettings\Pages\EditPublicContentSetting;
use App\Models\PublicContentSetting;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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

    protected static ?string $navigationLabel = 'Website settings';

    protected static ?string $modelLabel = 'website settings';

    protected static ?string $pluralModelLabel = 'website settings';

    protected static ?int $navigationSort = 30;

    public static function getNavigationUrl(): string
    {
        return static::getUrl('edit', ['record' => 1]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Public contact details')
                ->description('These details are shown in the Contact area of the public Vita / CV page. Vita and Exhibitions publication/navigation are managed from Pages.')
                ->schema([
                    TextInput::make('public_email')->label('Public email')->email()->maxLength(254)->nullable(),
                    TextInput::make('instagram_handle')->label('Instagram handle')->maxLength(30)->regex('/^[A-Za-z0-9._]{1,30}$/')->nullable(),
                ])
                ->columns(2),
            Section::make('Contact form')
                ->description('Controls the message form embedded in Vita / CV. The delivery address is private and is never rendered on the public site. Mail transport credentials remain server configuration.')
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
            Section::make('Legal')
                ->description('Public legal/disclaimer text displayed at the bottom of the Vita / CV surface where configured.')
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
