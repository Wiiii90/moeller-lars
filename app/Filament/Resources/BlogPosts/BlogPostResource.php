<?php

namespace App\Filament\Resources\BlogPosts;

use App\Domain\Blog\BlogEditorialService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteNodeType;
use App\Filament\Resources\BlogPosts\Pages\EditBlogPost;
use App\Filament\Resources\BlogPosts\Pages\ListBlogPosts;
use App\Filament\Support\JournalEntryEditorSchema;
use App\Models\BlogPost;
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

final class BlogPostResource extends Resource
{
    protected static ?string $model = BlogPost::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;
    protected static string|UnitEnum|null $navigationGroup = 'Website';
    protected static ?string $navigationLabel = 'Journal';
    protected static ?string $modelLabel = 'blog post';
    protected static ?string $pluralModelLabel = 'Blog Journal';

    public static function form(Schema $schema): Schema
    {
        return JournalEntryEditorSchema::blog($schema);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->searchable(),
            TextColumn::make('state')->badge()->sortable(),
            TextColumn::make('scheduled_at')->dateTime()->sortable(),
            TextColumn::make('published_at')->dateTime()->sortable(),
            TextColumn::make('position')->label('Listing order')->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->defaultSort('position')->filters([
            SelectFilter::make('state')->options([
                'draft' => 'Draft', 'scheduled' => 'Scheduled', 'published' => 'Published',
                'unpublished' => 'Unpublished', 'archived' => 'Archived',
            ]),
        ])->recordActions([
            Action::make('moveUp')->label('Move up')->icon('heroicon-o-chevron-up')
                ->visible(fn (BlogPost $record): bool => app(BlogEditorialService::class)->canMove($record, 'up'))
                ->action(function (BlogPost $record): void {
                    app(BlogEditorialService::class)->move($record, 'up');
                    Notification::make()->title('Post moved up')->success()->send();
                }),
            Action::make('moveDown')->label('Move down')->icon('heroicon-o-chevron-down')
                ->visible(fn (BlogPost $record): bool => app(BlogEditorialService::class)->canMove($record, 'down'))
                ->action(function (BlogPost $record): void {
                    app(BlogEditorialService::class)->move($record, 'down');
                    Notification::make()->title('Post moved down')->success()->send();
                }),
            Action::make('viewPublic')->label('View on site')->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->url(fn (BlogPost $record): string => self::publicUrl($record))->openUrlInNewTab()
                ->visible(fn (BlogPost $record): bool => BlogEditorialService::publicQuery()->whereKey($record->getKey())
                    ->whereHas('siteSection', fn ($section) => $section->where('state', 'published'))->exists()),
            EditAction::make(),
        ])->toolbarActions([])
            ->emptyStateHeading('No posts yet')
            ->emptyStateDescription('Create a draft first. Journal publication and navigation are managed from Pages.');
    }

    public static function publicUrl(BlogPost $post): string
    {
        /** @var SiteSection|null $section */
        $section = $post->siteSection()->first();
        if (! $section instanceof SiteSection || $section->nodeType() !== SiteNodeType::Journal || $section->journalTemplate() !== JournalTemplate::Blog) {
            throw new LogicException('Blog posts must belong to a Blog Journal.');
        }
        return route('journal.show', ['section' => $section->getAttribute('slug'), 'slug' => $post->getAttribute('slug')]);
    }

    public static function getPages(): array
    {
        return ['index' => ListBlogPosts::route('/'), 'edit' => EditBlogPost::route('/{record}/edit')];
    }

    public static function canDelete(Model $record): bool { return false; }
}
