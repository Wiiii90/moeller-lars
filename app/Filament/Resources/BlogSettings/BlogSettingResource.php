<?php

namespace App\Filament\Resources\BlogSettings;

use App\Filament\Resources\BlogSettings\Pages\EditBlogSetting;
use App\Models\BlogSetting;
use App\Models\SiteSection;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

final class BlogSettingResource extends Resource
{
    protected static ?string $model = BlogSetting::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Journal settings';

    protected static ?string $modelLabel = 'Journal settings';

    protected static ?string $pluralModelLabel = 'Journal settings';

    public static function getSettingsUrl(SiteSection|int|null $section = null): string
    {
        $settings = $section === null
            ? BlogSetting::forBlogSection()
            : BlogSetting::forSection($section);

        return self::getUrl('edit', ['record' => $settings]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Blog Journal page')
                ->description('Public visibility, navigation and site order are managed from Pages. These fields control this Journal listing only.')
                ->schema([
                    TextInput::make('listing_title')->maxLength(240)->nullable(),
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
            'edit' => EditBlogSetting::route('/{record}/edit'),
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
