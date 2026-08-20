<?php

namespace App\Http\Controllers;

use App\Domain\Content\SafeRichTextRenderer;
use App\Domain\Media\PublicMedia;
use App\Models\Exhibition;
use App\Models\SiteSection;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;

final class PublicExhibitionController extends Controller
{
    public function __construct(
        private readonly SafeRichTextRenderer $richText,
        private readonly PublicMedia $media,
    ) {}

    public function index(): View
    {
        abort_unless(SiteSection::isPublished(SiteSection::TYPE_EXHIBITIONS), 404);

        /** @var Collection<int, Exhibition> $exhibitions */
        $exhibitions = Exhibition::query()
            ->where('state', 'published')
            ->with(['mediaUsages.mediaAsset.variants'])
            ->orderBy('position')
            ->get();

        return view('pages.exhibitions', [
            'exhibitions' => $exhibitions,
            'richText' => $this->richText,
            'media' => $this->media,
        ]);
    }
}
