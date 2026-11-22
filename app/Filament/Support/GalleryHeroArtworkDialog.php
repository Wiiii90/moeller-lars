<?php

namespace App\Filament\Support;

use App\Domain\Artwork\ArtworkDate;
use App\Domain\Artwork\ArtworkDimensions;
use App\Filament\Resources\Artworks\Support\ArtworkMaterialSelect;
use App\Filament\Support\Controls\AdminControl;
use App\Filament\Support\Controls\AdminPanelControl;
use App\Models\Artwork;
use Filament\Schemas\Components\Grid;

/**
 * One optional Hero-only metadata override. A source Artwork is inherited
 * unchanged unless this panel is active. Media and display visibility remain
 * owned by the source Gallery and the existing Hero presentation settings.
 */
final class GalleryHeroArtworkDialog
{
    /** @param array<string,mixed> $overrides
     *  @return array<string,mixed>
     */
    public function fill(Artwork $artwork, array $overrides): array
    {
        $dimensions = ArtworkDimensions::split(
            array_key_exists('dimensions', $overrides)
                ? $overrides['dimensions']
                : $artwork->getAttribute('dimensions'),
        );

        return [
            'hero_override_enabled' => $overrides !== []
                && ($overrides['enabled'] ?? true) === true,
            'title' => $overrides['title'] ?? $artwork->getAttribute('title'),
            'medium' => array_key_exists('medium', $overrides)
                ? $overrides['medium'] : $artwork->getAttribute('medium'),
            'artwork_date' => array_key_exists('date', $overrides)
                ? $overrides['date'] : ArtworkDate::fromArtwork($artwork),
            'dimension_height' => $dimensions['height'],
            'dimension_width' => $dimensions['width'],
            'dimension_depth' => $dimensions['depth'],
            'dimension_unit' => $dimensions['unit'],
        ];
    }

    /** @return list<mixed> */
    public function schema(): array
    {
        return [
            AdminPanelControl::make(
                'Activate Override',
                'hero_override_enabled',
                [
                    Grid::make(['md' => 2])->schema([
                        AdminControl::text('title', 'Title')
                            ->required()
                            ->maxLength(240)
                            ->columnSpanFull(),
                        AdminPartialDateControl::make('artwork_date', 'Date')
                            ->columnSpanFull(),
                        ArtworkMaterialSelect::make()->columnSpanFull(),
                        AdminDimensionsControl::make(),
                    ]),
                ],
                collapsible: true,
                bodyColumns: ['default' => 1],
                hideWhenDisabled: true,
            ),
        ];
    }
}
