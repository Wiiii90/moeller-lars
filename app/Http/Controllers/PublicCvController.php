<?php

namespace App\Http\Controllers;

use App\Domain\Content\SafeRichTextRenderer;
use App\Domain\Content\SitePreviewContext;
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
        private readonly SitePreviewContext $preview,
    ) {}

    public function show(): View
    {
        abort_unless($this->preview->sectionIsAvailable(SiteSection::TYPE_VITA), 404);

        $cvEntries = CvEntry::query()
            ->when(
                $this->preview->active(),
                fn ($query) => $query->where('state', '<>', 'archived'),
                fn ($query) => $query->where('state', 'published'),
            )
            ->with('imageMediaAsset')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return view('pages.cv', [
            'generalSettings' => PublicContentSetting::general(),
            'contactSettings' => PublicContentSetting::contact(),
            'vitaSettings' => PublicContentSetting::vita(),
            'cvEntries' => $cvEntries,
            'richText' => $this->richText,
            'media' => $this->media,
        ]);
    }
}
