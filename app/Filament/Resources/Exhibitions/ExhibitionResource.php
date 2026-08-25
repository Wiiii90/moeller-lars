<?php

namespace App\Filament\Resources\Exhibitions;

use App\Domain\Content\ExhibitionEditorialService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteNodeType;
use App\Filament\Resources\Exhibitions\Pages\EditExhibition;
use App\Filament\Resources\Exhibitions\Pages\ListExhibitions;
use App\Filament\Support\JournalEntryEditorSchema;
use App\Models\Exhibition;
use App\Models\SiteSection;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('starts_on')->label('Starts')->date()->sortable(),
                TextColumn::make('title')->searchable(),
                TextColumn::make('venue')->searchable(),
                TextColumn::make('state')->badge()->sortable(),
                TextColumn::make('position')->label('Display order')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('position')
            ->filters([
                SelectFilter::make('state')->options([
                    'draft' => 'Draft',
                    'published' => 'Published',
                    'archived' => 'Archived',
                ]),
            ])
            ->recordActions([
                Action::make('moveUp')
                    ->label('Move up')
                    ->icon('heroicon-o-chevron-up')
                    ->visible(fn (Exhibition $record): bool => app(ExhibitionEditorialService::class)->canMove($record, 'up'))
                    ->action(function (Exhibition $record): void {
                        app(ExhibitionEditorialService::class)->move($record, 'up');
                        Notification::make()->title('Exhibition moved up')->success()->send();
                    }),
                Action::make('moveDown')
                    ->label('Move down')
                    ->icon('heroicon-o-chevron-down')
                    ->visible(fn (Exhibition $record): bool => app(ExhibitionEditorialService::class)->canMove($record, 'down'))
                    ->action(function (Exhibition $record): void {
                        app(ExhibitionEditorialService::class)->move($record, 'down');
                        Notification::make()->title('Exhibition moved down')->success()->send();
                    }),
                Action::make('viewPublic')
                    ->label('View on site')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Exhibition $record): string => self::publicUrl($record))
                    ->openUrlInNewTab()
                    ->visible(fn (Exhibition $record): bool => $record->getAttribute('state') === 'published'
                        && $record->siteSection()->where('state', 'published')->exists()),
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('No exhibitions yet')
            ->emptyStateDescription('Add the first exhibition. New exhibitions start as drafts and are published explicitly.');
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
