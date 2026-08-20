<?php

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Domain\Blog\BlogEditorialService;
use App\Filament\Pages\SitePages;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\BlogSettings\BlogSettingResource;
use App\Models\BlogPost;
use App\Models\BlogSetting;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;

final class ListBlogPosts extends Page
{
    protected static string $resource = BlogPostResource::class;

    protected string $view = 'filament.resources.blog-posts.pages.list-blog-posts';

    /** @var list<array<string, mixed>> */
    public array $posts = [];

    public function mount(): void
    {
        $this->loadPosts();
    }

    public function movePost(int $postId, string $direction): void
    {
        /** @var BlogPost $post */
        $post = BlogPost::query()->findOrFail($postId);
        if (app(BlogEditorialService::class)->move($post, $direction)) {
            Notification::make()->title('Blog order updated')->success()->send();
        }

        $this->loadPosts();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addPost')
                ->label('Add blog post')
                ->icon(Heroicon::OutlinedPlus)
                ->url(BlogPostResource::getUrl('create')),
            Action::make('blogSettings')
                ->label('Blog page settings')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->url(fn (): string => BlogSettingResource::getUrl('edit', ['record' => BlogSetting::query()->sole()])),
            Action::make('pages')
                ->label('Back to Pages')
                ->url(SitePages::getUrl()),
        ];
    }

    private function loadPosts(): void
    {
        $publicIds = BlogEditorialService::publicQuery()
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        /** @var EloquentCollection<int, BlogPost> $records */
        $records = BlogPost::query()->orderBy('position')->orderBy('id')->get();
        $lastIndex = $records->count() - 1;

        $this->posts = $records->values()->map(static function (BlogPost $post, int $index) use ($lastIndex, $publicIds): array {
            $state = (string) $post->getAttribute('state');
            $publishedAt = $post->getAttribute('published_at');
            $scheduledAt = $post->getAttribute('scheduled_at');
            $date = match (true) {
                $state === 'scheduled' && $scheduledAt instanceof DateTimeInterface => 'Scheduled '.$scheduledAt->format('M j, Y'),
                $publishedAt instanceof DateTimeInterface => $publishedAt->format('M j, Y'),
                default => 'Not published',
            };
            $excerpt = $post->getAttribute('excerpt');

            return [
                'id' => (int) $post->getKey(),
                'title' => (string) $post->getAttribute('title'),
                'meta' => is_string($excerpt) && trim($excerpt) !== '' ? Str::limit(trim($excerpt), 120) : 'No excerpt',
                'date' => $date,
                'state' => $state,
                'edit_url' => BlogPostResource::getUrl('edit', ['record' => $post]),
                'public_url' => in_array((int) $post->getKey(), $publicIds, true) ? route('blog.show', ['slug' => $post->getAttribute('slug')]) : null,
                'can_move_up' => $index > 0,
                'can_move_down' => $index < $lastIndex,
            ];
        })->all();
    }
}
