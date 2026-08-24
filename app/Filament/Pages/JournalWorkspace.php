<?php

namespace App\Filament\Pages;

use App\Domain\Blog\BlogEditorialService;
use App\Domain\Content\JournalEntryOrderService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteNodeType;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Models\BlogPost;
use App\Models\Exhibition;
use App\Models\SiteSection;
use DateTimeInterface;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;

final class JournalWorkspace extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'pages/journal/{section}';

    protected static ?string $title = 'Journal';

    protected string $view = 'filament.resources.blog-posts.pages.list-blog-posts';

    public int $sectionId;

    public string $template;

    /** @var list<array<string, mixed>> */
    public array $posts = [];

    /** @var list<array<string, mixed>> */
    public array $exhibitions = [];

    public function mount(int|string $section): void
    {
        /** @var SiteSection $siteSection */
        $siteSection = SiteSection::query()
            ->whereKey((int) $section)
            ->where('type', SiteNodeType::Journal->value)
            ->firstOrFail();
        $template = $siteSection->journalTemplate();
        abort_unless($template instanceof JournalTemplate, 404);

        $this->sectionId = (int) $siteSection->getKey();
        $this->template = $template->value;

        if ($template === JournalTemplate::Blog) {
            $this->view = 'filament.resources.blog-posts.pages.list-blog-posts';
            $this->loadPosts();

            return;
        }

        $this->view = 'filament.resources.exhibitions.pages.list-exhibitions';
        $this->loadExhibitions();
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

    public function moveExhibition(int $exhibitionId, string $direction): void
    {
        /** @var Exhibition $exhibition */
        $exhibition = Exhibition::query()
            ->where('site_section_id', $this->sectionId)
            ->findOrFail($exhibitionId);
        if (app(JournalEntryOrderService::class)->move($exhibition, $direction)) {
            Notification::make()->title('Exhibition order updated')->success()->send();
        }

        $this->loadExhibitions();
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

    private function loadExhibitions(): void
    {
        /** @var EloquentCollection<int, Exhibition> $records */
        $records = Exhibition::query()
            ->where('site_section_id', $this->sectionId)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
        $lastIndex = $records->count() - 1;

        $this->exhibitions = $records->values()->map(static function (Exhibition $exhibition, int $index) use ($lastIndex): array {
            $meta = array_values(array_filter([
                $exhibition->getAttribute('venue'),
                $exhibition->getAttribute('city'),
                $exhibition->getAttribute('country'),
            ], static fn ($value): bool => is_string($value) && trim($value) !== ''));
            $kind = $exhibition->getAttribute('kind');
            $opening = $exhibition->getAttribute('opening_text');

            return [
                'id' => (int) $exhibition->getKey(),
                'type' => is_string($kind) && $kind !== '' ? ucfirst($kind).' exhibition' : 'Exhibition',
                'title' => (string) $exhibition->getAttribute('title'),
                'date' => (string) $exhibition->getAttribute('date_text'),
                'meta' => $meta === [] ? 'No venue details' : implode(' · ', $meta),
                'opening' => is_string($opening) && trim($opening) !== '' ? trim($opening) : null,
                'state' => (string) $exhibition->getAttribute('state'),
                'edit_url' => ExhibitionResource::getUrl('edit', ['record' => $exhibition]),
                'public_url' => $exhibition->getAttribute('state') === 'published' ? ExhibitionResource::publicUrl($exhibition) : null,
                'can_move_up' => $index > 0,
                'can_move_down' => $index < $lastIndex,
            ];
        })->all();
    }
}
