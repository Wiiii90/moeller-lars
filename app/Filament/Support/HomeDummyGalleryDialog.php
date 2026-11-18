<?php

namespace App\Filament\Support;

use App\Domain\Media\MediaTypePolicy;
use App\Filament\Pages\HomePresentation;
use App\Models\Artwork;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Illuminate\Validation\ValidationException;

final class HomeDummyGalleryDialog
{
    /** One form grammar for a staged Create and an autosaving Edit. */
    public function schema(int $galleryId, bool $creating): array
    {
        return [
            TextInput::make('name')
                ->label('Name')
                ->required()
                ->maxLength(100)
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, $livewire) use ($galleryId, $creating): void {
                    if (! $creating && $livewire instanceof HomePresentation) {
                        $livewire->renameDummyGallery($galleryId, (string) $state);
                    }
                })
                ->columnSpanFull(),
            Select::make('pending_artwork_id')
                ->label('Insert existing Artwork')
                ->searchable()
                ->getSearchResultsUsing(static fn (string $search): array => Artwork::query()
                    ->where('state', 'published')
                    ->whereRaw('LOWER(title) LIKE ?', ['%'.mb_strtolower(trim($search)).'%'])
                    ->orderBy('title')
                    ->limit(30)
                    ->get(['id', 'title'])
                    ->mapWithKeys(static fn (Artwork $artwork): array =>
                        [(int) $artwork->getKey() => (string) $artwork->getAttribute('title')])->all())
                ->getOptionLabelUsing(static fn ($value): ?string =>
                    Artwork::query()->whereKey($value)->value('title'))
                ->live()
                ->dehydrated(false)
                ->afterStateUpdated(function (mixed $state, Set $set, $livewire) use ($galleryId, $creating): void {
                    if ($state === null || $state === '') {
                        return;
                    }
                    $id = filter_var($state, FILTER_VALIDATE_INT);
                    if ($id === false || $id < 1 || ! $livewire instanceof HomePresentation) {
                        throw ValidationException::withMessages(['pending_artwork_id' => 'Choose an eligible Artwork.']);
                    }
                    if ($creating) {
                        $livewire->addDraftDummyArtwork($id);
                    } else {
                        $livewire->addDummyArtwork($galleryId, $id);
                    }
                    $set('pending_artwork_id', null);
                })
                ->columnSpanFull(),
            MediaAssetSelect::makeId(
                'pending_media_asset_id',
                'Insert from Storage',
                allowedMimeTypes: [...MediaTypePolicy::IMAGE_MIME_TYPES, ...MediaTypePolicy::VIDEO_MIME_TYPES],
            )
                ->live()
                ->dehydrated(false)
                ->afterStateUpdated(function (mixed $state, Set $set, $livewire) use ($galleryId, $creating): void {
                    if ($state === null || $state === '') {
                        return;
                    }
                    $id = filter_var($state, FILTER_VALIDATE_INT);
                    if ($id === false || $id < 1 || ! $livewire instanceof HomePresentation) {
                        throw ValidationException::withMessages(['pending_media_asset_id' => 'Choose an eligible Storage file.']);
                    }
                    if ($creating) {
                        $livewire->addDraftDummyMedia($id);
                    } else {
                        $livewire->addDummyMediaFromStorage($galleryId, $id);
                    }
                    $set('pending_media_asset_id', null);
                })
                ->columnSpanFull(),
            View::make('filament.pages.partials.home-dummy-gallery-members')
                ->viewData(fn ($livewire): array => [
                    'members' => $livewire instanceof HomePresentation
                        ? $livewire->dummyEditorMembers($galleryId, $creating) : [],
                    'galleryId' => $galleryId,
                    'creating' => $creating,
                ])
                ->columnSpanFull(),
        ];
    }
}
