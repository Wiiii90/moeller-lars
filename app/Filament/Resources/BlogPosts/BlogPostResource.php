<?php

namespace App\Filament\Resources\BlogPosts;

use App\Filament\Resources\BlogPosts\Pages\CreateBlogPost;
use App\Filament\Resources\BlogPosts\Pages\EditBlogPost;
use App\Filament\Resources\BlogPosts\Pages\ListBlogPosts;
use App\Models\BlogPost;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class BlogPostResource extends Resource
{
    protected static ?string $model = BlogPost::class;

    protected static ?string $navigationLabel = 'Blog';

    protected static ?int $navigationSort = 22;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(240)->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, callable $set, callable $get): void {
                    if (blank($get('slug')) && filled($state)) {
                        $set('slug', Str::slug($state));
                    }
                }),
            TextInput::make('slug')->required()->maxLength(220)
                ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                ->unique('blog_posts', 'slug', ignoreRecord: true)
                ->disabled(fn (?Model $record): bool => $record?->getAttribute('published_at') !== null || $record?->getAttribute('scheduled_at') !== null)
                ->dehydrated(),
            Textarea::make('excerpt')->maxLength(1000)->nullable(),
            Textarea::make('body')->rows(18)->nullable(),
            Select::make('cover_media_asset_id')->relationship('coverMedia', 'original_filename')->searchable()->preload()->nullable(),
            TextInput::make('position')->integer()->required()->minValue(0)->default(0),
            Select::make('state')->options([
                'draft' => 'Draft',
                'scheduled' => 'Scheduled',
                'published' => 'Published',
                'unpublished' => 'Unpublished',
                'archived' => 'Archived',
            ])->required()->default('draft'),
            DateTimePicker::make('scheduled_at')->nullable(),
            DateTimePicker::make('published_at')->nullable()->disabled()->dehydrated(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->searchable(),
            TextColumn::make('state')->badge()->sortable(),
            TextColumn::make('position')->sortable(),
            TextColumn::make('scheduled_at')->dateTime()->sortable(),
            TextColumn::make('published_at')->dateTime()->sortable(),
        ])->defaultSort('position')->filters([
            SelectFilter::make('state')->options([
                'draft' => 'Draft',
                'scheduled' => 'Scheduled',
                'published' => 'Published',
                'unpublished' => 'Unpublished',
                'archived' => 'Archived',
            ]),
        ])->recordActions([EditAction::make()])->toolbarActions([]);
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
