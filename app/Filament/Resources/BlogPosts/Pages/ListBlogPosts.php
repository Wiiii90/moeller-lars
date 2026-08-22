<?php

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Domain\Blog\BlogEditorialService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteNodeType;
use App\Domain\Content\SiteSectionEditorialService;
use App\Filament\Concerns\HasJournalSettingsAction;
use App\Filament\Pages\SitePages;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Models\BlogPost;
use App\Models\SiteSection;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;
use RuntimeException;

final class ListBlogPosts extends Page
{
    use HasJournalSettingsAction;

    protected static string $resource = BlogPostResource::class;

    protected string $view = 'filament.resources.blog-posts.pages.list-blog-posts';

    public int $sectionId;

    /** @var list<array<string, mixed>> */
    public array $posts = [];

    public function mount(): void
    {
        $this->sectionId = $this->resolveSectionId();
        $this->loadPosts();
    }

    public function movePost(int $postId, string $direction): void
    {
        /** @var BlogPost $post */
        $post = BlogPost::query()
            ->where('site_section_id', $this->sectionId)
            ->findOrFail($postId);
        if (app(BlogEditorialService::class)->move($post, $direction)) {
            Notification::make()->title('Journal order updated')->success()->send();
        }

        $this->loadPosts();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addPost')
                ->label('Add blog post')
                ->icon(Heroicon::OutlinedPlus)
                ->schema(fn (Schema $schema): Schema => BlogPostResource::form($schema))
                ->modalHeading('Add blog post')
                ->modalSubmitActionLabel('Create draft')
                ->action(function (array $data): void {
                    $data['site_section_id'] = $this->sectionId;
                    app(BlogEditorialService::class)->createDraft($data);
                    $this->loadPosts();

                    Notification::make()
                        ->title('Blog draft created')
                        ->body('The post remains private until it is explicitly published or scheduled.')
                        ->success()
                        ->send();
                }),
            $this->journalSettingsAction(),
            Action::make('deleteJournal')
                ->label('Delete Blog')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Delete this Blog?')
                ->modalDescription('An empty Blog can be removed. Blogs with entries must be emptied first.')
                ->action(function (): void {
                    /** @var SiteSection $section */
                    $section = SiteSection::query()->findOrFail($this->sectionId);

                    try {
                        app(SiteSectionEditorialService::class)->deleteSection($section);
                    } catch (RuntimeException $exception) {
                        Notification::make()
                            ->danger()
                            ->title('Blog cannot be deleted')
                            ->body($exception->getMessage())
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title('Blog deleted')
                        ->body('You can create a new Blog from Pages at any time.')
                        ->send();

                    $this->redirect(SitePages::getUrl());
                }),
            Action::make('pages')
                ->label('Back to Pages')
                ->url(SitePages::getUrl()),
        ];
    }

    protected function journalSectionId(): int
    {
        return $this->sectionId;
    }

    private function loadPosts(): void
    {
        $publicIds = BlogEditorialService::publicQuery()
            ->where('site_section_id', $this->sectionId)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        /** @var EloquentCollection<int, BlogPost> $records */
        $records = BlogPost::query()
            ->where('site_section_id', $this->sectionId)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
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
                'public_url' => in_array((int) $post->getKey(), $publicIds, true) ? BlogPostResource::publicUrl($post) : null,
                'can_move_up' => $index > 0,
                'can_move_down' => $index < $lastIndex,
            ];
        })->all();
    }

    private function resolveSectionId(): int
    {
        $sectionId = request()->integer('section');
        abort_unless($sectionId > 0, 404);

        $exists = SiteSection::query()
            ->whereKey($sectionId)
            ->where('type', SiteNodeType::Journal->value)
            ->where('template', JournalTemplate::Blog->value)
            ->exists();
        abort_unless($exists, 404);

        return $sectionId;
    }
}
