<?php

namespace App\Http\Controllers;

use App\Domain\Content\BlogEditorialService;
use App\Domain\Content\JournalMediaRenderer;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SafeRichTextRenderer;
use App\Domain\Content\SitePreviewContext;
use App\Domain\Content\SiteSectionPathPolicy;
use App\Domain\Content\SiteSectionType;
use App\Domain\Media\PublicMedia;
use App\Models\BlogPost;
use App\Models\CustomPageSetting;
use App\Models\Exhibition;
use App\Models\JournalSetting;
use App\Models\MediaAsset;
use App\Models\PublicContentSetting;
use App\Models\Redirect;
use App\Models\SiteSection;
use App\Routing\SiteNodeRoute;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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

    public function show(string $section): View|RedirectResponse|JsonResponse
    {
        $query = SiteSection::query()->where('slug', $section);
        $this->preview->constrainSectionQuery($query);
        $siteSection = $query->first();

        if ($siteSection === null) {
            if (! $this->preview->active()) {
                $redirect = Redirect::query()
                    ->where('source_path', '/'.$section)
                    ->where('enabled', true)
                    ->where('reason', SiteSectionPathPolicy::CUSTOM_PAGE_SLUG_REDIRECT_REASON)
                    ->first();

                if ($redirect !== null) {
                    return redirect($redirect->getAttribute('target_path'), (int) $redirect->getAttribute('status_code'));
                }
            }

            return $this->artworks->category($section);
        }

        return $this->renderSection($siteSection);
    }

    public function renderSection(SiteSection $section): View|RedirectResponse|JsonResponse
    {
        return match ($section->nodeType()) {
            SiteSectionType::Gallery => $section->galleryTemplate() === \App\Domain\Content\GalleryTemplate::HeroArtworks
                ? view('pages.gallery.hero', app(\App\Domain\Content\GalleryHeroPresentationResolver::class)->presentation($section))
                : $this->artworks->categoryForSection($section),
            SiteSectionType::CustomPage => $this->customPage($section),
            SiteSectionType::Journal => $this->journal($section),
            default => abort(404),
        };
    }

    public function homeJournalEntry(string $slug): View|RedirectResponse
    {
        $query = SiteSection::query()
            ->where('is_home', true)
            ->where('type', SiteSectionType::Journal->value)
            ->where('template', JournalTemplate::Blog->value);
        $this->preview->constrainSectionQuery($query);
        $journal = $query->first();
        abort_unless($journal instanceof SiteSection, 404);
        $post = $this->findJournalPost($journal, $slug);
        if ($slug !== (string) $post->getKey()) {
            return redirect()->route($this->preview->active() ? 'preview.home.journal.show' : 'home.journal.show', [
                'slug' => $post->getKey(),
            ], 301);
        }

        return view('pages.blog.show', [
            'section' => $journal, 'post' => $post,
            'richText' => $this->richText, 'media' => $this->media,
            'journalMedia' => $this->journalMedia, 'siteNodeRoute' => $this->siteNodeRoute,
        ]);
    }

    public function journalEntry(string $section, string $slug): View|RedirectResponse
    {
        $sectionQuery = SiteSection::query()->where('type', SiteSectionType::Journal->value)->where('template', JournalTemplate::Blog->value)->where('slug', $section);
        $this->preview->constrainSectionQuery($sectionQuery);
        $journal = $sectionQuery->first();
        abort_unless($journal instanceof SiteSection, 404);
        $post = $this->findJournalPost($journal, $slug);
        if ($slug !== (string) $post->getKey()) {
            return redirect()->route($this->preview->active() ? 'preview.journal.show' : 'journal.show', [
                'section' => $section, 'slug' => $post->getKey(),
            ], 301);
        }

        return view('pages.blog.show', [
            'section' => $journal, 'post' => $post, 'richText' => $this->richText, 'media' => $this->media,
            'journalMedia' => $this->journalMedia, 'siteNodeRoute' => $this->siteNodeRoute,
        ]);
    }

    /** Numeric entry IDs are canonical; retained legacy Blog slugs redirect. */
    private function findJournalPost(SiteSection $journal, string $identifier): BlogPost
    {
        $post = ctype_digit($identifier)
            ? $this->blogPostsQuery($journal)->whereKey((int) $identifier)
                ->with('mediaUsages.mediaAsset.variants')->first()
            : null;

        $post ??= $this->blogPostsQuery($journal)->where('slug', $identifier)
            ->with('mediaUsages.mediaAsset.variants')->first();
        abort_unless($post instanceof BlogPost, 404);

        return $post;
    }

    private function customPage(SiteSection $section): View
    {
        $section->load('customPageSetting');
        $settings = $section->getRelation('customPageSetting');
        abort_unless($settings instanceof CustomPageSetting, 404);
        $blocks = $settings->components();
        $mediaIds = collect($blocks)
            ->filter(fn (array $block): bool => in_array($block['type'] ?? null, [...CustomPageSetting::MEDIA_COMPONENT_TYPES, 'list'], true))
            ->pluck('media_asset_id')
            ->filter(fn ($id): bool => is_numeric($id))->map(fn ($id): int => (int) $id)->unique()->values();
        /** @var Collection<int, MediaAsset> $assets */
        $assets = MediaAsset::query()->whereKey($mediaIds)->with('variants')->get()->keyBy(fn (MediaAsset $asset): int => (int) $asset->getKey());

        return view('pages.custom', [
            'section' => $section, 'settings' => $settings, 'blocks' => $blocks, 'assets' => $assets,
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
