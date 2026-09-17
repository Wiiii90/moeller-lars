<?php

namespace App\Http\Controllers;

use App\Domain\Content\JournalMediaRenderer;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\PublicCustomPageQuery;
use App\Domain\Content\PublicJournalQuery;
use App\Domain\Content\PublicSiteSectionQuery;
use App\Domain\Content\SafeRichTextRenderer;
use App\Domain\Content\SiteSectionPathPolicy;
use App\Domain\Content\SiteSectionType;
use App\Domain\Media\PublicMedia;
use App\Models\BlogPost;
use App\Models\SiteSection;
use App\Routing\SiteNodeRoute;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class PublicSiteSectionController extends Controller
{
    public function __construct(
        private readonly PublicArtworkController $artworks,
        private readonly PublicSiteSectionQuery $sections,
        private readonly PublicCustomPageQuery $customPages,
        private readonly PublicJournalQuery $journals,
        private readonly SafeRichTextRenderer $richText,
        private readonly PublicMedia $media,
        private readonly JournalMediaRenderer $journalMedia,
        private readonly SiteNodeRoute $siteNodeRoute,
    ) {}

    public function show(string $section): View|RedirectResponse
    {
        $siteSection = $this->sections->bySlug($section);

        if ($siteSection === null) {
            $redirect = $this->sections->redirect('/'.$section, SiteSectionPathPolicy::CUSTOM_PAGE_SLUG_REDIRECT_REASON);
            if ($redirect !== null) {
                return redirect($redirect->getAttribute('target_path'), (int) $redirect->getAttribute('status_code'));
            }

            return $this->artworks->category($section);
        }

        if ($siteSection->nodeType() === SiteSectionType::Gallery) {
            return $this->artworks->category($section);
        }

        return match ($siteSection->nodeType()) {
            SiteSectionType::CustomPage => $this->customPage($siteSection),
            SiteSectionType::Journal => $this->journal($siteSection),
            default => abort(404),
        };
    }

    public function journalEntry(string $section, string $slug): View
    {
        $journal = $this->journals->blogSectionBySlug($section);
        abort_unless($journal instanceof SiteSection, 404);
        $post = $this->journals->blogPost($journal, $slug);
        abort_unless($post instanceof BlogPost, 404);

        return view('pages.blog.show', [
            'section' => $journal,
            'post' => $post,
            'richText' => $this->richText,
            'media' => $this->media,
            'journalMedia' => $this->journalMedia,
            'siteNodeRoute' => $this->siteNodeRoute,
        ]);
    }

    private function customPage(SiteSection $section): View
    {
        $presentation = $this->customPages->presentation($section);
        abort_unless($presentation !== null, 404);

        return view('pages.custom', [
            'section' => $section,
            ...$presentation,
            'richText' => $this->richText,
            'media' => $this->media,
            'siteNodeRoute' => $this->siteNodeRoute,
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
        return view('pages.blog.index', [
            'section' => $section,
            'settings' => $this->journals->settings($section),
            'posts' => $this->journals->blogPosts($section),
            'richText' => $this->richText,
            'media' => $this->media,
            'journalMedia' => $this->journalMedia,
            'siteNodeRoute' => $this->siteNodeRoute,
        ]);
    }

    private function exhibitionsJournal(SiteSection $section): View
    {
        return view('pages.exhibitions', [
            'section' => $section,
            'settings' => $this->journals->settings($section),
            'exhibitions' => $this->journals->exhibitions($section),
            'richText' => $this->richText,
            'media' => $this->media,
            'journalMedia' => $this->journalMedia,
            'siteNodeRoute' => $this->siteNodeRoute,
        ]);
    }
}
