<?php

namespace App\Filament\Resources\JournalSettings;

use App\Filament\Resources\JournalSettings\Pages\EditJournalSetting;
use App\Models\JournalSetting;
use App\Models\SiteSection;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

final class JournalSettingResource extends Resource
{
    protected static ?string $model = JournalSetting::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Journal settings';

    protected static ?string $pluralModelLabel = 'Journal settings';

    public static function getSettingsUrl(SiteSection|int $section): string
    {
        return self::getUrl('edit', ['record' => JournalSetting::forSection($section)]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Journal presentation')
                ->description('Public visibility, navigation and site order are managed from Pages. These fields control this Journal listing only.')
                ->schema([
                    TextInput::make('listing_title')
                        ->label('Listing title')
                        ->maxLength(240)
                        ->nullable(),
                    MarkdownEditor::make('listing_intro')
                        ->label('Introduction')
                        ->toolbarButtons([
                            ['bold', 'italic', 'link'],
                            ['bulletList', 'orderedList'],
                            ['undo', 'redo'],
                        ])
                        ->helperText('Formatting is limited to the Markdown supported by the public Journal renderer.')
                        ->maxLength(10000)
                        ->nullable()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'edit' => EditJournalSetting::route('/{record}/edit'),
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
