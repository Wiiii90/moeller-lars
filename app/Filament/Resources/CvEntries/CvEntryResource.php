<?php

namespace App\Filament\Resources\CvEntries;

use App\Domain\Admin\EditorialRecordService;
use App\Filament\Resources\CvEntries\Pages\CreateCvEntry;
use App\Filament\Resources\CvEntries\Pages\EditCvEntry;
use App\Filament\Resources\CvEntries\Pages\ListCvEntries;
use App\Filament\Support\MediaAssetSelect;
use App\Models\CvEntry;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MarkdownEditor;
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
use UnitEnum;

class CvEntryResource extends Resource
{
    protected static ?string $model = CvEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Vita / CV';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Vita / CV entry')
                ->description('Content edits are saved independently from publication. Use the page actions to publish, unpublish or archive.')
                ->schema([
                    TextInput::make('section')->required()->maxLength(120),
                    TextInput::make('title')->required()->maxLength(240),
                    TextInput::make('year_text')->label('Displayed date/year')->required()->maxLength(80),
                    Select::make('date_precision')->options([
                        'unknown' => 'Unknown',
                        'year' => 'Year',
                        'month' => 'Month',
                        'day' => 'Day',
                    ])->required()->default('unknown'),
                    DatePicker::make('starts_on')->nullable(),
                    DatePicker::make('ends_on')->nullable(),
                    TextInput::make('organisation')->maxLength(240)->nullable(),
                    TextInput::make('location')->maxLength(240)->nullable(),
                    MarkdownEditor::make('body')
                        ->label('Details')
                        ->toolbarButtons([
                            ['bold', 'italic', 'link'],
                            ['bulletList', 'orderedList'],
                            ['undo', 'redo'],
                        ])
                        ->helperText('Formatting is limited to emphasis, links and lists so it stays compatible with the public Vita renderer.')
                        ->maxLength(10000)
                        ->nullable()
                        ->columnSpanFull(),
                    TextInput::make('external_url')->url()->maxLength(2048)->nullable()->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('Presentation')
                ->description('Ordering is managed directly from the Vita / CV list. Media previews are private admin previews.')
                ->schema([
                    MediaAssetSelect::make('image_media_asset_id', 'imageMediaAsset', 'Image')
                        ->nullable()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('section')->searchable()->sortable(),
                TextColumn::make('year_text')->label('Date')->sortable(),
                TextColumn::make('title')->searchable(),
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
                    ->visible(fn (CvEntry $record): bool => app(EditorialRecordService::class)->canMove($record, 'up'))
                    ->action(function (CvEntry $record): void {
                        app(EditorialRecordService::class)->move($record, 'up');
                        Notification::make()->title('Vita / CV entry moved up')->success()->send();
                    }),
                Action::make('moveDown')
                    ->label('Move down')
                    ->icon('heroicon-o-chevron-down')
                    ->visible(fn (CvEntry $record): bool => app(EditorialRecordService::class)->canMove($record, 'down'))
                    ->action(function (CvEntry $record): void {
                        app(EditorialRecordService::class)->move($record, 'down');
                        Notification::make()->title('Vita / CV entry moved down')->success()->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('No Vita / CV entries yet')
            ->emptyStateDescription('Add biography, education, awards or other Vita entries here. New entries start as drafts.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCvEntries::route('/'),
            'create' => CreateCvEntry::route('/create'),
            'edit' => EditCvEntry::route('/{record}/edit'),
        ];
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
