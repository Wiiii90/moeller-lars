<?php

namespace App\Filament\Resources\Exhibitions;

use App\Domain\Admin\EditorialRecordService;
use App\Filament\Resources\Exhibitions\Pages\CreateExhibition;
use App\Filament\Resources\Exhibitions\Pages\EditExhibition;
use App\Filament\Resources\Exhibitions\Pages\ListExhibitions;
use App\Filament\Support\MediaAssetSelect;
use App\Models\Exhibition;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

class ExhibitionResource extends Resource
{
    protected static ?string $model = Exhibition::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Exhibitions';

    protected static ?int $navigationSort = 21;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Exhibition')
                ->description('Content edits are saved independently from publication. Use the page actions to publish, unpublish or archive.')
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
                        ->label('Public URL slug')
                        ->required()
                        ->maxLength(180)
                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                        ->unique('exhibitions', 'slug', ignoreRecord: true),
                    TextInput::make('date_text')->label('Displayed date')->required()->maxLength(160),
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
            Section::make('Media')
                ->description('Choose media visually and drag items into their public order. A published exhibition may have at most one hero image.')
                ->schema([
                    Repeater::make('mediaUsages')
                        ->relationship()
                        ->schema([
                            MediaAssetSelect::make('media_asset_id', 'mediaAsset', 'Image')
                                ->required()
                                ->columnSpanFull(),
                            Select::make('role')->options([
                                'hero' => 'Hero',
                                'additional' => 'Additional',
                            ])->required()->default('additional'),
                            TextInput::make('alt_text_override')->label('ALT text override')->maxLength(500)->nullable(),
                        ])
                        ->columns(2)
                        ->orderColumn('position'),
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
                TextColumn::make('position')
                    ->label('Display order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('position')
            ->filters([
                SelectFilter::make('state')->options([
                    'draft' => 'Draft',
                    'published' => 'Published',
                    'hidden' => 'Hidden (legacy)',
                    'archived' => 'Archived',
                ]),
            ])
            ->recordActions([
                Action::make('moveUp')
                    ->label('Move up')
                    ->icon('heroicon-o-chevron-up')
                    ->visible(fn (Exhibition $record): bool => app(EditorialRecordService::class)->canMove($record, 'up'))
                    ->action(function (Exhibition $record): void {
                        app(EditorialRecordService::class)->move($record, 'up');
                        Notification::make()->title('Exhibition moved up')->success()->send();
                    }),
                Action::make('moveDown')
                    ->label('Move down')
                    ->icon('heroicon-o-chevron-down')
                    ->visible(fn (Exhibition $record): bool => app(EditorialRecordService::class)->canMove($record, 'down'))
                    ->action(function (Exhibition $record): void {
                        app(EditorialRecordService::class)->move($record, 'down');
                        Notification::make()->title('Exhibition moved down')->success()->send();
                    }),
                Action::make('viewPublic')
                    ->label('View on site')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Exhibition $record): string => route('exhibitions.index'))
                    ->openUrlInNewTab()
                    ->visible(fn (Exhibition $record): bool => $record->getAttribute('state') === 'published'),
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('No exhibitions yet')
            ->emptyStateDescription('Add the first exhibition. New exhibitions start as drafts and are published explicitly.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExhibitions::route('/'),
            'create' => CreateExhibition::route('/create'),
            'edit' => EditExhibition::route('/{record}/edit'),
        ];
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
