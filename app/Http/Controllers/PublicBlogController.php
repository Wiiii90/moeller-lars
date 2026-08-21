<?php

namespace App\Http\Controllers;

use App\Domain\Blog\BlogEditorialService;
use App\Domain\Content\SafeRichTextRenderer;
use App\Domain\Content\SitePreviewContext;
use App\Domain\Media\PublicMedia;
use App\Models\BlogPost;
use App\Models\BlogSetting;
use App\Models\SiteSection;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

final class PublicBlogController extends Controller
{
    public function __construct(
        private readonly SafeRichTextRenderer $richText,
        private readonly PublicMedia $media,
        private readonly SitePreviewContext $preview,
    ) {}

    public function index(): View
    {
        $this->requireAvailableBlog();

        $posts = $this->postsQuery()
            ->with('coverMedia.variants')
            ->orderBy('position')
            ->get();

        return view('pages.blog.index', [
            'settings' => BlogSetting::query()->sole(),
            'posts' => $posts,
            'richText' => $this->richText,
            'media' => $this->media,
        ]);
    }

    public function show(string $slug): View
    {
        $this->requireAvailableBlog();

        /** @var BlogPost|null $post */
        $post = $this->postsQuery()
            ->where('slug', $slug)
            ->with('coverMedia.variants')
            ->first();
        abort_unless($post !== null, 404);

        return view('pages.blog.show', [
            'post' => $post,
            'richText' => $this->richText,
            'media' => $this->media,
        ]);
    }

    /** @return Builder<BlogPost> */
    private function postsQuery(): Builder
    {
        if ($this->preview->active()) {
            return BlogPost::query()->where('state', '<>', 'archived');
        }

        return BlogEditorialService::publicQuery();
    }

    private function requireAvailableBlog(): void
    {
        abort_unless($this->preview->sectionIsAvailable(SiteSection::TYPE_BLOG), 404);
    }
}
