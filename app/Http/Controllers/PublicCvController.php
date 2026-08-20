<?php

namespace App\Http\Controllers;

use App\Domain\Content\SafeRichTextRenderer;
use App\Domain\Media\PublicMedia;
use App\Models\CvEntry;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use Illuminate\Contracts\View\View;

class PublicCvController extends Controller
{
    public function __construct(
        private readonly SafeRichTextRenderer $richText,
        private readonly PublicMedia $media,
    ) {}

    public function show(): View
    {
        abort_unless(SiteSection::isPublished(SiteSection::TYPE_VITA), 404);

        $cvEntries = CvEntry::query()
            ->where('state', 'published')
            ->with('imageMediaAsset')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return view('pages.cv', [
            'settings' => PublicContentSetting::query()->sole(),
            'cvEntries' => $cvEntries,
            'richText' => $this->richText,
            'media' => $this->media,
        ]);
    }
}
