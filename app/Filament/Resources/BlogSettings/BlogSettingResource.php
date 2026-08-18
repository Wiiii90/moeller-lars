<?php

namespace App\Filament\Resources\BlogSettings;

use App\Filament\Resources\BlogSettings\Pages\EditBlogSetting;
use App\Models\BlogSetting;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

final class BlogSettingResource extends Resource
{
    protected static ?string $model = BlogSetting::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Blog settings';

    protected static ?string $modelLabel = 'blog settings';

    protected static ?string $pluralModelLabel = 'blog settings';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Blog visibility')
                ->description('The public blog is opt-in. Posts can be prepared while the whole section stays private.')
                ->schema([
                    Toggle::make('public_enabled')->label('Publish blog section'),
                    TextInput::make('navigation_label')->label('Navigation label')->required()->maxLength(120),
                ])
                ->columns(2),
            Section::make('Listing page')
                ->description('Public heading and introductory text for the Blog index.')
                ->schema([
                    TextInput::make('listing_title')->maxLength(240)->nullable(),
                    Textarea::make('listing_intro')->maxLength(10000)->nullable()->columnSpanFull(),
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
