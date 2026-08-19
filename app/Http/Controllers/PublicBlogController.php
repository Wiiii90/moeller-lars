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
        abort_unless(
            SiteSection::query()->where('type', SiteSection::TYPE_BLOG)->where('state', 'published')->exists(),
            404,
        );

        $settings = BlogSetting::query()->findOrFail(1);
        $posts = BlogEditorialService::publicQuery()
            ->with('coverMedia.variants')
            ->orderBy('position')
            ->get();

        return view('pages.blog.index', [
            'settings' => $settings,
            'posts' => $posts,
            'richText' => $this->richText,
            'media' => $this->media,
        ]);
    }

    public function show(string $slug): View
    {
        abort_unless(
            SiteSection::query()->where('type', SiteSection::TYPE_BLOG)->where('state', 'published')->exists(),
            404,
        );

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
}
