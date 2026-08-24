<?php

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Filament\Pages\JournalWorkspace;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use Filament\Resources\Pages\Page;

final class ListBlogPosts extends Page
{
    protected static string $resource = BlogPostResource::class;

    protected string $view = 'filament.resources.blog-posts.pages.list-blog-posts';

    public function mount(): void
    {
        $sectionId = request()->integer('section');
        abort_unless($sectionId > 0, 404);

        $this->redirect(JournalWorkspace::getUrl(['section' => $sectionId]), navigate: false);
    }
}
