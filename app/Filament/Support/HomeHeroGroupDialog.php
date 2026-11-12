<?php

namespace App\Filament\Support;

use App\Domain\Artwork\ArtworkDate;
use App\Domain\Content\HomeHeroCandidate;
use App\Domain\Content\HomeHeroConfigurationService;
use App\Domain\Media\MediaPreviewVariant;
use App\Domain\Media\MediaTypePolicy;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Support\Controls\AdminControl;
use App\Models\Artwork;
use App\Models\MediaAsset;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

final class HomeHeroGroupDialog
{
    /** The entire form is a draft; no field performs an editorial mutation. */
    public function schema(): array
    {
        return [
            MediaAssetSelect::makeId(
                'pending_media_asset_id',
                'Insert from Storage',
                allowedMimeTypes: [...MediaTypePolicy::IMAGE_MIME_TYPES, ...MediaTypePolicy::VIDEO_MIME_TYPES],
            )
                ->live()
                ->dehydrated(false)
                ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                    if ($state === null || $state === '') {
                        return;
                    }

                    $id = filter_var($state, FILTER_VALIDATE_INT);
                    if ($id === false || $id <= 0) {
                        throw ValidationException::withMessages([
                            'pending_media_asset_id' => 'Choose an eligible image or video from Storage.',
                        ]);
                    }

                    $draft = $get('manual_group') ?? [];
                    $next = app(HomeHeroConfigurationService::class)->appendDraftCandidate(
                        is_array($draft) ? array_values($draft) : [],
                        (int) $id,
                    );
                    $set('manual_group', $next);
                    $set('pending_media_asset_id', null);
                }),
            Repeater::make('manual_group')
                ->label('Manual Sourced Artworks')
                ->table([
                    TableColumn::make('Position')->width('4rem'),
                    TableColumn::make('Candidate'),
                    TableColumn::make('Enabled')->width('6rem'),
                ])
                ->schema([
                    Hidden::make('artwork_id'),
                    Hidden::make('media_asset_id'),
                    Hidden::make('weight'),
                    Placeholder::make('position')
                        ->hiddenLabel()
                        ->content(fn (int $parentRepeaterItemIndex): HtmlString => new HtmlString(
                            '<span class="admin-position">'.($parentRepeaterItemIndex + 1).'</span>',
                        )),
                    Placeholder::make('candidate')
                        ->hiddenLabel()
                        ->content(fn (Get $get): HtmlString => $this->candidatePreview(
                            $get('artwork_id'), $get('media_asset_id'),
                        )),
                    AdminControl::toggle('enabled', 'Enabled')->default(true),
                ])
                ->addable(false)
                ->deletable()
                ->deleteAction(fn (Action $action): Action => $action
                    ->icon(AdminIcon::Delete->value)
                    ->label('Remove'))
                ->reorderable()
                ->reorderableWithButtons()
                ->reorderableWithDragAndDrop()
                ->maxItems(HomeHeroConfigurationService::MAX_GROUP_SIZE)
                ->compact()
                ->columnSpanFull(),
        ];
    }

    /** Existing canonical Artwork and Storage previews, with one shared compact Media variant. */
    private function candidatePreview(mixed $artworkId, mixed $mediaId): HtmlString
    {
        $artwork = filter_var($artworkId, FILTER_VALIDATE_INT);
        $media = filter_var($mediaId, FILTER_VALIDATE_INT);
        $artworkModel = $artwork !== false && $artwork > 0
            ? Artwork::query()->with('category')->find((int) $artwork)
            : null;
        $mediaModel = $media !== false && $media > 0
            ? MediaAsset::query()->with('variants')->find((int) $media)
            : null;

        $title = 'Unavailable candidate';
        $detail = '';
        $thumbnailUrl = null;
        if ($artworkModel instanceof Artwork) {
            $candidate = HomeHeroCandidate::fromArtwork($artworkModel);
            $title = $candidate->title();
            $detail = implode(', ', array_filter([
                $candidate->gallery()?->getAttribute('name'), ArtworkDate::display($artworkModel),
            ]));
            $thumbnailUrl = ArtworkResource::thumbnailUrl($artworkModel);
        } elseif ($mediaModel instanceof MediaAsset) {
            $title = (string) $mediaModel->getAttribute('original_filename');
            $detail = 'Storage';
            $variant = MediaPreviewVariant::preview($mediaModel);
            $thumbnailUrl = $variant ? route('admin.media.variant', $variant) : null;
        }

        $preview = is_string($thumbnailUrl) && $thumbnailUrl !== ''
            ? '<img src="'.e($thumbnailUrl).'" alt="" loading="lazy" decoding="async">'
            : '<span class="admin-media-choice__unavailable">—</span>';

        return new HtmlString(
            '<span class="admin-media-choice admin-media-choice--inline">'
            .'<span class="admin-media-choice__preview">'.$preview.'</span>'
            .'<span class="admin-media-choice__meta"><strong>'.e($title).'</strong><small>'.e($detail).'</small></span>'
            .'</span>',
        );
    }
}
