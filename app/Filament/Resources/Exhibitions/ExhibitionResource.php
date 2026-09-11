<?php

namespace App\Filament\Resources\Exhibitions;

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteNodeType;
use App\Filament\Resources\Exhibitions\Pages\EditExhibition;
use App\Filament\Resources\Exhibitions\Pages\ListExhibitions;
use App\Filament\Support\JournalEntryEditorSchema;
use App\Models\Exhibition;
use App\Models\SiteSection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use UnitEnum;

class ExhibitionResource extends Resource
{
    protected static ?string $model = Exhibition::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;
    protected static string|UnitEnum|null $navigationGroup = 'Website';
    protected static ?string $navigationLabel = 'Journal';

    public static function form(Schema $schema): Schema
    {
        return JournalEntryEditorSchema::exhibition($schema);
    }

    public static function publicUrl(Exhibition $exhibition): string
    {
        /** @var SiteSection|null $section */
        $section = $exhibition->siteSection()->first();
        if (
            ! $section instanceof SiteSection
            || $section->nodeType() !== SiteNodeType::Journal
            || $section->journalTemplate() !== JournalTemplate::Exhibitions
        ) {
            throw new LogicException('Exhibitions must belong to an Exhibitions Journal.');
        }

        return route('site.section', ['section' => $section->getAttribute('slug')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExhibitions::route('/'),
            'edit' => EditExhibition::route('/{record}/edit'),
        ];
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
