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
        abort_unless(
            SiteSection::query()->where('type', SiteSection::TYPE_VITA)->where('state', 'published')->exists(),
            404,
        );

        $settings = PublicContentSetting::query()->findOrFail(1);
        $cvEntries = CvEntry::query()
            ->where('state', 'published')
            ->where('section', 'Biography')
            ->with('imageMediaAsset')
            ->orderBy('position')
            ->get();

        return view('pages.cv', [
            'settings' => $settings,
            'cvEntries' => $cvEntries,
            'richText' => $this->richText,
            'media' => $this->media,
        ]);
    }
}
