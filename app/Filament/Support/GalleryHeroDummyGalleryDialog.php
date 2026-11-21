<?php

namespace App\Filament\Support;

use App\Domain\Artwork\PublicArtworkQuery;
use App\Domain\Content\GalleryHeroCandidate;
use App\Domain\Content\RegionalFormats;
use App\Domain\Media\MediaIngestService;
use App\Domain\Media\MediaTypePolicy;
use App\Filament\Pages\GalleryHeroWorkspace;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Support\Controls\AdminControl;
use App\Models\Artwork;
use App\Models\MediaAsset;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class GalleryHeroDummyGalleryDialog
{
    /** One Create/Edit form and one existing Artwork/Storage selection control. */
    public function schema(int $galleryId, bool $creating): array
    {
        return [
            AdminControl::text('name', 'Name')
                ->maxLength(100)
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, $livewire) use ($galleryId, $creating): void {
                    if (! $creating && $livewire instanceof GalleryHeroWorkspace) {
                        $livewire->renameDummyGallery($galleryId, (string) $state);
                    }
                })
                ->columnSpanFull(),
            // Exhibition and Dummy Gallery share the same visual picker renderer.
            // Only the candidate source and typed Artwork/Storage IDs differ.
            MediaAssetPicker::make('pending_candidate')
                ->label('Insert existing Artwork')
                ->placeholder('Choose Artwork or Storage')
                ->searchable()
                ->native(false)
                ->searchDebounce(AdminControl::SEARCH_DEBOUNCE_MS)
                ->searchPrompt('Search Artwork or Storage')
                ->searchingMessage('Loading Artwork or Storage…')
                ->noSearchResultsMessage('No matching Artwork or Storage')
                ->extraAlpineAttributes(['class' => 'admin-media-picker--visual'], merge: true)
                ->mediaPages(fn (int $offset, ?string $search): array => self::candidatePageOptions($offset, $search ?? ''))
                ->allowHtml()
                ->getOptionLabelUsing(self::selectedCandidateLabel(...))
                ->live()
                ->dehydrated(false)
                ->afterStateUpdated(function (mixed $state, Set $set, $livewire) use ($galleryId, $creating): void {
                    if ($state === null || $state === '') {
                        return;
                    }
                    if (! is_string($state)
                        || preg_match('/^(artwork|media):([1-9][0-9]*)$/D', $state, $matches) !== 1
                        || ! $livewire instanceof GalleryHeroWorkspace) {
                        throw ValidationException::withMessages(['pending_candidate' => 'Choose an eligible Artwork or Storage file.']);
                    }

                    $id = (int) $matches[2];
                    if ($matches[1] === 'artwork') {
                        if ($creating) {
                            $livewire->addDraftDummyArtwork($id);
                        } else {
                            $livewire->addDummyArtwork($galleryId, $id);
                        }
                    } elseif ($creating) {
                        $livewire->addDraftDummyMedia($id);
                    } else {
                        $livewire->addDummyMediaFromStorage($galleryId, $id);
                    }

                    $set('pending_candidate', null);
                })
                ->columnSpanFull(),
            View::make('filament.pages.partials.gallery-hero-dummy-gallery-members')
                ->viewData(fn ($livewire): array => [
                    'members' => $livewire instanceof GalleryHeroWorkspace
                        ? $livewire->dummyEditorMembers($galleryId, $creating) : [],
                    'galleryId' => $galleryId,
                    'creating' => $creating,
                    'adminRegion' => RegionalFormats::adminRegion(),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * Page across both eligible catalogs without truncating either source.
     * Artwork results precede Storage; both queries keep their native sort
     * order and each page fetches at most one bounded slice per source.
     *
     * @return array{options:list<array{value:string,label:string}>,hasMore:bool}
     */
    private static function candidatePageOptions(int $offset, string $search): array
    {
        $offset = max(0, $offset);
        $size = MediaAssetSelect::PAGE_SIZE;
        $artworks = app(PublicArtworkQuery::class);
        $artworkTotal = $artworks->countEditorialHeroCandidates($search);
        $artworkTake = min($size, max(0, $artworkTotal - $offset));
        $options = [];

        if ($artworkTake > 0) {
            foreach ($artworks->pageEditorialHeroCandidates($search, $offset, $artworkTake) as $artwork) {
                $image = ArtworkResource::thumbnailUrl($artwork);
                $preview = $image === null
                    ? '<span class="admin-media-choice__unavailable">—</span>'
                    : '<img src="'.e($image).'" alt="" width="160" height="120" loading="lazy" decoding="async">';
                $options[] = [
                    'value' => 'artwork:'.$artwork->getKey(),
                    'label' => '<span class="admin-media-choice">'
                        .'<span class="admin-media-choice__preview">'.$preview.'</span>'
                        .'<span class="admin-media-choice__meta"><strong>'.e((string) $artwork->getAttribute('title')).'</strong>'
                        .'<small>Artwork</small></span></span>',
                ];
            }
        }

        // If more Artwork results exist, Storage starts on a later page.
        if ($artworkTotal > $offset + $size) {
            return ['options' => $options, 'hasMore' => true];
        }

        $remaining = $size - count($options);
        $storageOffset = max(0, $offset - $artworkTotal);
        $storage = MediaAssetSelect::pageOptions(
            $storageOffset,
            $search,
            allowedMimeTypes: [...MediaTypePolicy::IMAGE_MIME_TYPES, ...MediaTypePolicy::VIDEO_MIME_TYPES],
            modifyQueryUsing: static function (Builder $query): void {
                $query->whereRaw("NULLIF(BTRIM(alt_text), '') IS NOT NULL")
                    ->whereRaw('CHAR_LENGTH(alt_text) <= 500')
                    ->where(static fn (Builder $eligible): Builder => $eligible
                        ->whereIn('mime_type', MediaTypePolicy::VIDEO_MIME_TYPES)
                        ->orWhere(static fn (Builder $images): Builder => $images
                            ->whereIn('mime_type', MediaTypePolicy::IMAGE_MIME_TYPES)
                            ->whereHas('variants', static fn (Builder $variants): Builder => $variants
                                ->where('variant_kind', MediaIngestService::THUMBNAIL_KIND)
                                ->where('mime_type', 'image/webp')
                                ->where('state', 'available'))));
            },
            visual: true,
        );
        foreach (array_slice($storage['options'], 0, $remaining) as $asset) {
            $options[] = [
                'value' => 'media:'.$asset['value'],
                'label' => $asset['label'],
            ];
        }

        return [
            'options' => $options,
            'hasMore' => count($storage['options']) > $remaining || $storage['hasMore'],
        ];
    }

    private static function selectedCandidateLabel(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^(artwork|media):([1-9][0-9]*)$/D', $value, $matches) !== 1) {
            return null;
        }
        if ($matches[1] === 'artwork') {
            $artwork = app(PublicArtworkQuery::class)->editorialHeroCandidatesByIds([(int) $matches[2]])->first();
            return $artwork instanceof Artwork ? e((string) $artwork->getAttribute('title')) : null;
        }

        $asset = MediaAsset::query()->with('variants')->find((int) $matches[2]);
        return $asset instanceof MediaAsset && GalleryHeroCandidate::mediaIsEligible($asset)
            ? e((string) $asset->getAttribute('original_filename')) : null;
    }
}
