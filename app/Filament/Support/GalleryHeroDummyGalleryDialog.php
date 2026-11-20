<?php

namespace App\Filament\Support;

use App\Domain\Artwork\PublicArtworkQuery;
use App\Domain\Content\GalleryHeroCandidate;
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
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * Both candidate types use their existing eligibility and visual choice
     * components. Typed keys keep Artwork and Storage IDs collision-free.
     *
     * @return array<string,string>
     */
    private static function candidateOptions(string $search): array
    {
        $options = [];
        foreach (app(PublicArtworkQuery::class)->searchEditorialHeroCandidates($search, 30) as $artwork) {
            $key = 'artwork:'.$artwork->getKey();
            $image = ArtworkResource::thumbnailUrl($artwork);
            $preview = $image === null
                ? '<span class="admin-media-choice__unavailable">—</span>'
                : '<img src="'.e($image).'" alt="" width="160" height="120" loading="lazy" decoding="async">';
            $options[$key] = '<span class="admin-media-choice">'
                .'<span class="admin-media-choice__preview">'.$preview.'</span>'
                .'<span class="admin-media-choice__meta"><strong>'.e((string) $artwork->getAttribute('title')).'</strong>'
                .'<small>Artwork</small></span></span>';
        }

        $mediaOptions = MediaAssetSelect::searchOptions(
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
            maxResults: 30,
        );
        foreach ($mediaOptions as $id => $label) {
            $options['media:'.$id] = $label;
        }

        return $options;
    }

    /**
     * Use the shared paginated visual picker without changing eligible-source
     * semantics. The Artwork/Storage search remains the canonical bounded
     * candidate query; pages expose its existing 30+30 result window.
     *
     * @return array{options:list<array{value:string,label:string}>,hasMore:bool}
     */
    private static function candidatePageOptions(int $offset, string $search): array
    {
        $choices = self::candidateOptions($search);
        $start = max(0, $offset);
        $page = array_slice($choices, $start, MediaAssetSelect::PAGE_SIZE, true);
        $options = [];
        foreach ($page as $value => $label) {
            $options[] = ['value' => (string) $value, 'label' => $label];
        }

        return [
            'options' => $options,
            'hasMore' => $start + count($page) < count($choices),
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
