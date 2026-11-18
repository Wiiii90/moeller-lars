<?php

namespace App\Http\Controllers;

use App\Domain\Content\SitePreviewContext;
use App\Models\SiteSection;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class PublicHomeController extends Controller
{
    public function __construct(
        private readonly PublicSiteSectionController $sections,
        private readonly SitePreviewContext $preview,
    ) {}

    public function show(): View|RedirectResponse
    {
        $query = SiteSection::query()->where('is_home', true);
        $this->preview->constrainSectionQuery($query);
        /** @var SiteSection|null $home */
        $home = $query->first();
        abort_unless($home instanceof SiteSection, 404);

        return $this->sections->renderSection($home);
    }
}
