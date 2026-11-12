<?php

namespace App\Filament\Support;

use App\Domain\Media\MediaTypePolicy;
use App\Filament\Pages\HomePresentation;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Illuminate\Validation\ValidationException;

final class HomeHeroGroupDialog
{
    /** The established Edit dialog hosts one Storage picker and one real Admin table. */
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
                ->afterStateUpdated(function (mixed $state, Set $set, $livewire): void {
                    if ($state === null || $state === '') {
                        return;
                    }

                    $id = filter_var($state, FILTER_VALIDATE_INT);
                    if ($id === false || $id <= 0 || ! $livewire instanceof HomePresentation) {
                        throw ValidationException::withMessages([
                            'pending_media_asset_id' => 'Choose an eligible image or video from Storage.',
                        ]);
                    }

                    $livewire->addHeroCandidateFromStorage((int) $id);
                    $set('pending_media_asset_id', null);
                })
                ->columnSpanFull(),
            View::make('filament.pages.partials.home-hero-group-table')
                ->viewData(fn ($livewire): array => [
                    'rows' => $livewire instanceof HomePresentation ? $livewire->manualHeroEditorRows() : [],
                ])
                ->columnSpanFull(),
        ];
    }
}
