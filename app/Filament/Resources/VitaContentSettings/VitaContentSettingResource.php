<?php

namespace App\Filament\Resources\VitaContentSettings;

use App\Filament\Resources\VitaContentSettings\Pages\EditVitaContentSetting;
use App\Models\PublicContentSetting;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class VitaContentSettingResource extends Resource
{
    protected static ?string $model = PublicContentSetting::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Vita content';

    protected static ?string $pluralModelLabel = 'Vita content';

    public static function getSettingsUrl(): string
    {
        return self::getUrl('edit', ['record' => PublicContentSetting::vita()]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('scope', PublicContentSetting::SCOPE_VITA);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Additional Vita text')
                ->description('Optional text blocks composed on the public Vita page. Entries themselves remain managed in Vita content.')
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
                        ->itemLabel(fn (array $state): ?string => isset($state['title']) && is_string($state['title']) ? $state['title'] : null)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'edit' => EditVitaContentSetting::route('/{record}/edit'),
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
