<?php

namespace App\Filament\Resources\BlogPosts;

use App\Domain\Blog\BlogEditorialService;
use App\Filament\Resources\BlogPosts\Pages\CreateBlogPost;
use App\Filament\Resources\BlogPosts\Pages\EditBlogPost;
use App\Filament\Resources\BlogPosts\Pages\ListBlogPosts;
use App\Models\BlogPost;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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

final class BlogPostResource extends Resource
{
    protected static ?string $model = BlogPost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Blog';

    protected static ?string $modelLabel = 'blog post';

    protected static ?string $pluralModelLabel = 'Blog';

    protected static ?int $navigationSort = 22;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Post')
                ->description('Write and save the post here. Publication is controlled with the page actions rather than a raw state field.')
                ->schema([
                    TextInput::make('title')->required()->maxLength(240)->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, callable $set, callable $get): void {
                            if (blank($get('slug')) && filled($state)) {
                                $set('slug', Str::slug($state));
                            }
                        }),
                    TextInput::make('slug')
                        ->label('Public URL slug')
                        ->required()
                        ->maxLength(220)
                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                        ->unique('blog_posts', 'slug', ignoreRecord: true)
                        ->disabled(fn (?Model $record): bool => $record?->getAttribute('published_at') !== null || $record?->getAttribute('scheduled_at') !== null)
                        ->dehydrated(),
                    Textarea::make('excerpt')->maxLength(1000)->nullable()->columnSpanFull(),
                    Textarea::make('body')->rows(18)->nullable()->columnSpanFull(),
                    Select::make('cover_media_asset_id')
                        ->label('Cover image')
                        ->relationship('coverMedia', 'original_filename')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('Publication status')
                ->description('Use Publish now, Schedule, Unpublish, Archive or Restore to draft in the page header. Listing order is managed directly from the Blog list.')
                ->schema([
                    Select::make('state')->options([
                        'draft' => 'Draft',
                        'scheduled' => 'Scheduled',
                        'published' => 'Published',
                        'unpublished' => 'Unpublished',
                        'archived' => 'Archived',
                    ])->default('draft')->disabled()->dehydrated(false),
                    DateTimePicker::make('scheduled_at')
                        ->label('Scheduled for')
                        ->disabled()
                        ->dehydrated(false),
                    DateTimePicker::make('published_at')
                        ->label('First published')
                        ->disabled()
                        ->dehydrated(false),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->searchable(),
            TextColumn::make('state')->badge()->sortable(),
            TextColumn::make('scheduled_at')->dateTime()->sortable(),
            TextColumn::make('published_at')->dateTime()->sortable(),
            TextColumn::make('position')
                ->label('Listing order')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ])->defaultSort('position')->filters([
            SelectFilter::make('state')->options([
                'draft' => 'Draft',
                'scheduled' => 'Scheduled',
                'published' => 'Published',
                'unpublished' => 'Unpublished',
                'archived' => 'Archived',
            ]),
        ])->recordActions([
            Action::make('moveUp')
                ->label('Move up')
                ->icon('heroicon-o-chevron-up')
                ->visible(fn (BlogPost $record): bool => app(BlogEditorialService::class)->canMove($record, 'up'))
                ->action(function (BlogPost $record): void {
                    app(BlogEditorialService::class)->move($record, 'up');
                    Notification::make()->title('Blog post moved up')->success()->send();
                }),
            Action::make('moveDown')
                ->label('Move down')
                ->icon('heroicon-o-chevron-down')
                ->visible(fn (BlogPost $record): bool => app(BlogEditorialService::class)->canMove($record, 'down'))
                ->action(function (BlogPost $record): void {
                    app(BlogEditorialService::class)->move($record, 'down');
                    Notification::make()->title('Blog post moved down')->success()->send();
                }),
            Action::make('viewPublic')
                ->label('View on site')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->url(fn (BlogPost $record): string => route('blog.show', ['slug' => $record->getAttribute('slug')]))
                ->openUrlInNewTab()
                ->visible(fn (BlogPost $record): bool => BlogPost::query()->publiclyVisible()->whereKey($record->getKey())->exists()),
            EditAction::make(),
        ])->toolbarActions([])
            ->emptyStateHeading('No blog posts yet')
            ->emptyStateDescription('Create a draft first. The Blog remains private until it is explicitly enabled in Blog settings.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlogPosts::route('/'),
            'create' => CreateBlogPost::route('/create'),
            'edit' => EditBlogPost::route('/{record}/edit'),
        ];
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
