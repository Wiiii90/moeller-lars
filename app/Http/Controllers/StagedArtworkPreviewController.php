<?php

namespace App\Http\Controllers;

use App\Domain\Artwork\ArtworkDate;
use App\Domain\Artwork\ArtworkDimensions;
use App\Domain\Content\SafeRichTextRenderer;
use App\Domain\Media\MediaTypePolicy;
use App\Domain\Media\PublicMedia;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final class StagedArtworkPreviewController extends Controller
{
    public function __construct(
        private readonly PublicMedia $media,
        private readonly SafeRichTextRenderer $richText,
    ) {}

    /**
     * Render the canonical Public Artwork page with an in-memory draft.
     * The private Livewire editor supplies this browser-session snapshot;
     * no Artwork or media relationship is persisted until Create Artwork.
     */
    public function show(int $gallery, string $token): View
    {
        $snapshot = session()->get('gallery.stage.preview.'.$token);
        abort_unless(is_array($snapshot) && (int) ($snapshot['gallery_id'] ?? 0) === $gallery, 404);

        $category = ArtworkCategory::query()->findOrFail($gallery);
        $asset = MediaAsset::query()->with('variants')
            ->whereKey((int) ($snapshot['media_asset_id'] ?? 0))
            ->where('state', 'available')->firstOrFail();
        $mime = (string) $asset->getAttribute('mime_type');
        abort_unless(MediaTypePolicy::isImage($mime) || MediaTypePolicy::isVideo($mime), 404);
        $data = is_array($snapshot['form'] ?? null) ? $snapshot['form'] : [];

        try {
            $date = ArtworkDate::normalize($data['artwork_date'] ?? null);
        } catch (ValidationException) {
            $date = ['year' => null, 'month' => null, 'day' => null];
        }
        try {
            $dimensions = ArtworkDimensions::compose(
                $data['dimension_height'] ?? null,
                $data['dimension_width'] ?? null,
                $data['dimension_depth'] ?? null,
                $data['dimension_unit'] ?? 'cm',
                null,
            );
        } catch (ValidationException) {
            $dimensions = null;
        }

        $title = trim((string) ($data['title'] ?? ''));
        $title = $title !== '' ? $title : (string) $asset->getAttribute('original_filename');
        $alt = trim((string) ($data['primary_alt_text'] ?? $asset->getAttribute('alt_text') ?? ''));
        $alt = mb_substr($alt !== '' ? $alt : $title, 0, 500);

        $artwork = new Artwork;
        $artwork->forceFill([
            'id' => -((int) $asset->getKey()),
            'artwork_category_id' => $gallery,
            'slug' => 'staged-'.(int) $asset->getKey(),
            'title' => $title,
            // Stage has no Description editor. Rich Text form data may be
            // structured before validation and must not enter this string model.
            'description' => null,
            'medium' => filled($data['medium'] ?? null) ? trim((string) $data['medium']) : null,
            'dimensions' => $dimensions,
            'state' => 'draft',
            'analytics_key' => 'staged-'.(int) $asset->getKey(),
            ...$date,
        ]);
        $artwork->setRelation('category', $category);

        $usage = new ArtworkMedia;
        $usage->forceFill([
            'media_asset_id' => $asset->getKey(),
            'role' => 'primary',
            'position' => 1,
            'alt_text_override' => $alt,
        ]);
        $usage->setRelation('mediaAsset', $asset);
        $artwork->setRelation('artworkMedia', new Collection([$usage]));

        return view('pages.artworks.show', [
            'artwork' => $artwork,
            'media' => $this->media,
            'richText' => $this->richText,
            'viewerArtworks' => new Collection([$artwork]),
            'stagedPreview' => true,
        ]);
    }
};
