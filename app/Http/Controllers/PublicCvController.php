<?php

namespace App\Http\Controllers;

use App\Domain\Content\SafeRichTextRenderer;
use App\Domain\Media\PublicMedia;
use App\Models\CvEntry;
use App\Models\Exhibition;
use App\Models\ExhibitionMedia;
use App\Models\PublicContentSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;

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
            /** @var Collection<int, Exhibition> $exhibitions */
            $exhibitions = Exhibition::query()
                ->where('state', 'published')
                ->with(['mediaUsages.mediaAsset.variants'])
                ->orderBy('position')
                ->get();

            foreach ($exhibitions as $exhibition) {
                /** @var Collection<int, ExhibitionMedia> $mediaUsages */
                $mediaUsages = $exhibition->getRelationValue('mediaUsages');
                $exhibition->setRelation(
                    'mediaUsages',
                    $mediaUsages->sortBy(static fn (ExhibitionMedia $usage): int => (int) $usage->getAttribute('position'))->values(),
                );
            }
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
