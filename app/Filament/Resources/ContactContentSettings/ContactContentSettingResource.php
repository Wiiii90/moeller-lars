<?php

namespace App\Filament\Resources\ContactContentSettings;

use App\Filament\Resources\ContactContentSettings\Pages\EditContactContentSetting;
use App\Filament\Support\AdminForm;
use App\Models\PublicContentSetting;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class ContactContentSettingResource extends Resource
{
    protected static ?string $model = PublicContentSetting::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Contact content';

    protected static ?string $pluralModelLabel = 'Contact content';

    public static function getSettingsUrl(): string
    {
        return self::getUrl('edit', ['record' => PublicContentSetting::contact()]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('scope', PublicContentSetting::SCOPE_CONTACT);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            AdminForm::section('Contact presentation')
                ->schema([
                    Select::make('contact_state')
                        ->label('Form state')
                        ->options([
                            'enabled' => 'Enabled',
                            'under_construction' => 'Under construction',
                            'hidden' => 'Hidden',
                        ])
                        ->required(),
                    Textarea::make('contact_status_text')
                        ->label('Under-construction message')
                        ->maxLength(500)
                        ->nullable()
                        ->helperText('Required only while the form is under construction.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'edit' => EditContactContentSetting::route('/{record}/edit'),
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
