<?php

namespace App\Filament\Resources\Exhibitions;

use App\Domain\Content\JournalEntryOrderService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteNodeType;
use App\Filament\Resources\Exhibitions\Pages\EditExhibition;
use App\Filament\Resources\Exhibitions\Pages\ListExhibitions;
use App\Filament\Support\AdminForm;
use App\Filament\Support\MediaAssetSelect;
use App\Models\Exhibition;
use App\Models\SiteSection;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
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
        return $schema->components([
            Hidden::make('site_section_id')
                ->default(fn (): ?int => request()->integer('section') ?: null),
            AdminForm::section('Exhibition')
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->maxLength(240)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, callable $set, callable $get): void {
                            if (blank($get('slug')) && filled($state)) {
                                $set('slug', Str::slug($state));
                            }
                        }),
                    TextInput::make('slug')
                        ->label('Entry URL slug')
                        ->required()
                        ->maxLength(180)
                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                        ->unique('exhibitions', 'slug', ignoreRecord: true),
                    TextInput::make('date_text')
                        ->label('Displayed exhibition dates')
                        ->required()
                        ->maxLength(160),
                    TextInput::make('opening_text')
                        ->label('Opening / vernissage')
                        ->maxLength(500)
                        ->nullable(),
                    Select::make('kind')->options([
                        'solo' => 'Solo',
                        'group' => 'Group',
                    ])->nullable(),
                    DatePicker::make('starts_on')->nullable(),
                    DatePicker::make('ends_on')->nullable(),
                    TextInput::make('venue')->maxLength(240)->nullable(),
                    TextInput::make('city')->maxLength(160)->nullable(),
                    TextInput::make('country')->maxLength(160)->nullable(),
                    TextInput::make('location_text')->label('Location / address')->maxLength(500)->nullable()->columnSpanFull(),
                    MarkdownEditor::make('description')
                        ->label('Description')
                        ->toolbarButtons([
                            ['bold', 'italic', 'link'],
                            ['bulletList', 'orderedList'],
                            ['undo', 'redo'],
                        ])
                        ->helperText('Formatting is limited to emphasis, links and lists so it stays compatible with the public exhibition renderer.')
                        ->maxLength(10000)
                        ->nullable()
                        ->columnSpanFull(),
                    TextInput::make('external_url')->url()->maxLength(2048)->nullable(),
                    TextInput::make('directions_url')->label('Directions URL')->url()->maxLength(2048)->nullable(),
                ])
                ->columns(2),
            AdminForm::section('Media')
                ->schema([
                    Repeater::make('mediaUsages')
                        ->relationship()
                        ->schema([
                            MediaAssetSelect::make('media_asset_id', 'mediaAsset', 'Image')
                                ->required(),
                            Select::make('role')->options([
                                'hero' => 'Hero',
                                'additional' => 'Additional',
                            ])->required()->default('additional'),
                            TextInput::make('alt_text_override')->label('ALT override')->maxLength(500)->nullable(),
                        ])
                        ->table([
                            TableColumn::make('Image'),
                            TableColumn::make('Role'),
                            TableColumn::make('ALT override'),
                        ])
                        ->compact()
                        ->orderColumn('position')
                        ->reorderableWithButtons()
                        ->reorderableWithDragAndDrop(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date_text')->label('Date')->sortable(),
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
                    ->visible(fn (Exhibition $record): bool => app(JournalEntryOrderService::class)->canMove($record, 'up'))
                    ->action(function (Exhibition $record): void {
                        app(JournalEntryOrderService::class)->move($record, 'up');
                        Notification::make()->title('Exhibition moved up')->success()->send();
                    }),
                Action::make('moveDown')
                    ->label('Move down')
                    ->icon('heroicon-o-chevron-down')
                    ->visible(fn (Exhibition $record): bool => app(JournalEntryOrderService::class)->canMove($record, 'down'))
                    ->action(function (Exhibition $record): void {
                        app(JournalEntryOrderService::class)->move($record, 'down');
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
        if (! $section instanceof SiteSection
            || $section->nodeType() !== SiteNodeType::Journal
            || $section->journalTemplate() !== JournalTemplate::Exhibitions) {
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
