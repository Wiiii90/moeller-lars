<?php

namespace App\Http\Controllers;

use App\Domain\Content\SafeRichTextRenderer;
use App\Domain\Media\PublicMedia;
use App\Models\CvEntry;
use App\Models\Exhibition;
use App\Models\PublicContentSetting;
use Illuminate\Contracts\View\View;

class PublicCvController extends Controller
{
    public function __construct(
        private readonly SafeRichTextRenderer $richText,
        private readonly PublicMedia $media,
    ) {}

    public function show(): View
    {
        $settings = PublicContentSetting::query()->findOrFail(1);
        abort_unless($settings->cvSurfaceEnabled(), 404);

        $cvEntries = collect();
        if ((bool) $settings->getAttribute('cv_enabled')) {
            $cvEntries = CvEntry::query()
                ->where('state', 'published')
                ->with('imageMediaAsset')
                ->orderBy('position')
                ->get();
        }

        $exhibitions = collect();
        if ((bool) $settings->getAttribute('exhibitions_enabled')) {
            $exhibitions = Exhibition::query()
                ->where('state', 'published')
                ->with(['mediaUsages.mediaAsset.variants'])
                ->orderBy('position')
                ->get();
        }

        return view('pages.cv', [
            'settings' => $settings,
            'cvEntries' => $cvEntries,
            'exhibitions' => $exhibitions,
            'richText' => $this->richText,
            'media' => $this->media,
        ]);
    }
}
