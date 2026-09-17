<?php

namespace App\Domain\Content;

use App\Models\BlogPost;
use App\Models\Exhibition;
use App\Models\JournalSetting;
use App\Models\SiteSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class PublicJournalQuery
{
    public function __construct(private readonly SitePreviewContext $preview) {}

    public function blogSectionBySlug(string $slug): ?SiteSection
    {
        $query = SiteSection::query()
            ->where('type', SiteSectionType::Journal->value)
            ->where('template', JournalTemplate::Blog->value)
            ->where('slug', $slug);
        $this->preview->constrainSectionQuery($query);

        return $query->first();
    }

    public function settings(SiteSection $section): JournalSetting
    {
        return JournalSetting::forSection($section);
    }

    public function blogPost(SiteSection $section, string $slug): ?BlogPost
    {
        return $this->blogPostsQuery($section)
            ->where('slug', $slug)
            ->with('mediaUsages.mediaAsset.variants')
            ->first();
    }

    /** @return EloquentCollection<int, BlogPost> */
    public function blogPosts(SiteSection $section): EloquentCollection
    {
        return $this->blogPostsQuery($section)
            ->with('mediaUsages.mediaAsset.variants')
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /** @return EloquentCollection<int, Exhibition> */
    public function exhibitions(SiteSection $section): EloquentCollection
    {
        return Exhibition::query()
            ->where('site_section_id', $section->getKey())
            ->when(
                $this->preview->active(),
                fn (Builder $query) => $query->where('state', '<>', 'archived'),
                fn (Builder $query) => $query->where('state', 'published'),
            )
            ->with('mediaUsages.mediaAsset.variants')
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /** @return Builder<BlogPost> */
    private function blogPostsQuery(SiteSection $section): Builder
    {
        $query = $this->preview->active()
            ? BlogPost::query()->where('state', '<>', 'archived')
            : BlogPost::query()->publiclyVisible();

        return $query->where('site_section_id', $section->getKey());
    }
}
