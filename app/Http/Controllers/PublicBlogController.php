<?php

namespace App\Http\Controllers;

use App\Domain\Blog\BlogEditorialService;
use App\Domain\Content\SafeRichTextRenderer;
use App\Domain\Media\PublicMedia;
use App\Models\BlogPost;
use App\Models\BlogSetting;
use App\Models\SiteSection;
use Illuminate\Contracts\View\View;

final class PublicBlogController extends Controller
{
    public function __construct(
        private readonly SafeRichTextRenderer $richText,
        private readonly PublicMedia $media,
    ) {}

    public function index(): View
    {
        $this->requirePublishedBlog();

        $posts = BlogEditorialService::publicQuery()
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
        $this->requirePublishedBlog();

        /** @var BlogPost|null $post */
        $post = BlogEditorialService::publicQuery()
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

    private function requirePublishedBlog(): void
    {
        abort_unless(SiteSection::isPublished(SiteSection::TYPE_BLOG), 404);
    }
}
