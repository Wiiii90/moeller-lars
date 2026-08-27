<?php

namespace App\Http\Controllers;

use App\Domain\Blog\BlogEditorialService;
use App\Domain\Content\JournalMediaRenderer;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SafeRichTextRenderer;
use App\Domain\Content\SiteNodeType;
use App\Domain\Content\SitePreviewContext;
use App\Domain\Media\PublicMedia;
use App\Models\BlogPost;
use App\Models\CustomPageSetting;
use App\Models\CvEntry;
use App\Models\Exhibition;
use App\Models\JournalSetting;
use App\Models\MediaAsset;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use App\Routing\SiteNodeRoute;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;

final class PublicSiteSectionController extends Controller
{
    public function __construct(
        private readonly PublicArtworkController $artworks,
        private readonly SafeRichTextRenderer $richText,
        private readonly PublicMedia $media,
        private readonly JournalMediaRenderer $journalMedia,
        private readonly SitePreviewContext $preview,
        private readonly SiteNodeRoute $siteNodeRoute,
    ) {}

    public function show(string $section): View|RedirectResponse
    {
        $query = SiteSection::query()->where('slug', $section);
        $this->preview->constrainSectionQuery($query);
        $siteSection = $query->first();
        if ($siteSection === null || $siteSection->nodeType() === SiteNodeType::Gallery) { return $this->artworks->category($section); }
        return match ($siteSection->nodeType()) {
            SiteNodeType::CustomPage => $this->customPage($siteSection),
            SiteNodeType::Journal => $this->journal($siteSection),
            default => abort(404),
        };
    }

    public function journalEntry(string $section, string $slug): View
    {
        $sectionQuery = SiteSection::query()->where('type', SiteNodeType::Journal->value)->where('template', JournalTemplate::Blog->value)->where('slug', $section);
        $this->preview->constrainSectionQuery($sectionQuery);
        $journal = $sectionQuery->first();
        abort_unless($journal instanceof SiteSection, 404);
        $post = $this->blogPostsQuery($journal)->where('slug', $slug)->with('mediaUsages.mediaAsset.variants')->first();
        abort_unless($post instanceof BlogPost, 404);
        return view('pages.blog.show', [
            'section' => $journal, 'post' => $post, 'richText' => $this->richText, 'media' => $this->media,
            'journalMedia' => $this->journalMedia, 'siteNodeRoute' => $this->siteNodeRoute,
        ]);
    }

    private function customPage(SiteSection $section): View
    {
        $section->load('customPageSetting');
        $settings = $section->getRelation('customPageSetting'); abort_unless($settings instanceof CustomPageSetting, 404);
        $blocks = $settings->components();
        $mediaIds = collect($blocks)
            ->filter(fn (array $block): bool => in_array($block['type'] ?? null, ['image', 'cv_list'], true))
            ->pluck('media_asset_id')
            ->filter(fn ($id): bool => is_numeric($id))->map(fn ($id): int => (int) $id)->unique()->values();
        /** @var Collection<int, MediaAsset> $assets */
        $assets = MediaAsset::query()->whereKey($mediaIds)->with('variants')->get()->keyBy(fn (MediaAsset $asset): int => (int) $asset->getKey());

        $cvListBlocks = collect($blocks)->filter(fn (array $block): bool => ($block['type'] ?? null) === 'cv_list');
        $hasRenderableCvList = $this->preview->active()
            ? $cvListBlocks->isNotEmpty()
            : $cvListBlocks->contains(fn (array $block): bool => CustomPageSetting::componentPublished($block));

        $cvEntries = collect();
        if ($hasRenderableCvList) {
            $cvEntries = CvEntry::query()
                ->when($this->preview->active(), fn (Builder $query) => $query->where('state', '<>', 'archived'), fn (Builder $query) => $query->where('state', 'published'))
                ->orderBy('position')->orderBy('id')->get();
        }
        return view('pages.custom', [
            'section' => $section, 'settings' => $settings, 'blocks' => $blocks, 'assets' => $assets, 'cvEntries' => $cvEntries,
            'generalSettings' => PublicContentSetting::general(), 'richText' => $this->richText, 'media' => $this->media, 'siteNodeRoute' => $this->siteNodeRoute,
        ]);
    }

    private function journal(SiteSection $section): View
    {
        return match ($section->journalTemplate()) {
            JournalTemplate::Blog => $this->blogJournal($section),
            JournalTemplate::Exhibitions => $this->exhibitionsJournal($section),
            null => abort(404),
        };
    }

    private function blogJournal(SiteSection $section): View
    {
        $posts = $this->blogPostsQuery($section)->with('mediaUsages.mediaAsset.variants')->orderBy('position')->orderBy('id')->get();
        return view('pages.blog.index', [
            'section' => $section, 'settings' => JournalSetting::forSection($section), 'posts' => $posts,
            'richText' => $this->richText, 'media' => $this->media, 'journalMedia' => $this->journalMedia, 'siteNodeRoute' => $this->siteNodeRoute,
        ]);
    }

    private function exhibitionsJournal(SiteSection $section): View
    {
        $exhibitions = Exhibition::query()->where('site_section_id', $section->getKey())
            ->when($this->preview->active(), fn (Builder $query) => $query->where('state', '<>', 'archived'), fn (Builder $query) => $query->where('state', 'published'))
            ->with('mediaUsages.mediaAsset.variants')->orderBy('position')->orderBy('id')->get();
        return view('pages.exhibitions', [
            'section' => $section, 'settings' => JournalSetting::forSection($section), 'exhibitions' => $exhibitions,
            'richText' => $this->richText, 'media' => $this->media, 'journalMedia' => $this->journalMedia, 'siteNodeRoute' => $this->siteNodeRoute,
        ]);
    }

    /** @return Builder<BlogPost> */
    private function blogPostsQuery(SiteSection $section): Builder
    {
        $query = $this->preview->active() ? BlogPost::query()->where('state', '<>', 'archived') : BlogEditorialService::publicQuery();
        return $query->where('site_section_id', $section->getKey());
    }
}
