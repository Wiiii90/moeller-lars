<?php

namespace App\Filament\Support;

use App\Domain\Artwork\ArtworkDimensions;
use App\Domain\Artwork\ArtworkInformation;
use App\Domain\Content\GalleryHeroCandidate;
use App\Domain\Media\MediaTypePolicy;
use App\Filament\Resources\Artworks\Support\ArtworkMaterialSelect;
use App\Filament\Support\Controls\AdminControl;
use App\Models\Artwork;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\HtmlString;

/**
 * The source Artwork is read-only input. No field in this dialog ever calls
 * ArtworkDraftService, Gallery editorial services or primary-media mutations.
 * The mounted Hero Action persists only the Hero configuration overlay.
 */
final class GalleryHeroArtworkDialog
{
    /** @param array<string,mixed> $overrides
     *  @return array<string,mixed>
     */
    public function fill(Artwork $artwork, string $sourceName, array $overrides): array
    {
        $base = app(ArtworkEditDialog::class)->fill($artwork);
        $dimensions = array_key_exists('dimensions', $overrides)
            ? ArtworkDimensions::split($overrides['dimensions'])
            : ArtworkDimensions::split($artwork->getAttribute('dimensions'));
        $baseInfo = ArtworkInformation::effective($artwork->getRelation('category'), $artwork);
        $effectiveInfo = $baseInfo;
        $individual = $overrides['information'] ?? null;
        if (is_array($individual)) {
            if (array_key_exists('enabled', $individual)) {
                $effectiveInfo['enabled'] = (bool) $individual['enabled'];
            }
            foreach ($individual['visible'] ?? [] as $field => $visible) {
                if (array_key_exists($field, ArtworkInformation::FIELDS)) {
                    $effectiveInfo['visible'][$field] = (bool) $visible;
                }
            }
        }

        $originalMediaId = $base['primary_media_asset_id'];
        $heroMediaId = $overrides['media_asset_id'] ?? $originalMediaId;

        return [
            ...$base,
            ...ArtworkInformation::formValues($effectiveInfo),
            'info_individual' => is_array($individual),
            'source_name' => $sourceName,
            'title' => $overrides['title'] ?? $base['title'],
            'medium' => array_key_exists('medium', $overrides) ? $overrides['medium'] : $base['medium'],
            'artwork_date' => array_key_exists('date', $overrides) ? $overrides['date'] : $base['artwork_date'],
            'dimension_height' => $dimensions['height'],
            'dimension_width' => $dimensions['width'],
            'dimension_depth' => $dimensions['depth'],
            'dimension_unit' => $dimensions['unit'],
            'dimension_custom' => $dimensions['custom'],
            'primary_media_asset_id' => $heroMediaId,
            'primary_alt_text' => array_key_exists('alt_text', $overrides) ? $overrides['alt_text']
                : ($heroMediaId === $originalMediaId ? $base['primary_alt_text'] : null),
        ];
    }

    /** @return list<mixed> */
    public function schema(): array
    {
        return [
            Grid::make(['md' => 2])->schema([
                AdminControl::text('source_name')
                    ->label(new HtmlString(
                        '<span class="admin-form-label-with-help">Original source'.
                        AdminHelp::make(
                            'Hero Artwork version help',
                            'Changes in this editor belong only to this Hero source. The original Artwork and its Gallery remain unchanged.',
                        )->toHtml().'</span>',
                    ))
                    ->readOnly()
                    ->dehydrated(false)
                    ->columnSpanFull(),
                AdminControl::text('title', 'Title')
                    ->required()
                    ->maxLength(240)
                    ->columnSpanFull(),
                AdminPartialDateControl::make('artwork_date', 'Date')
                    ->columnSpanFull(),
                ArtworkMaterialSelect::make()->columnSpanFull(),
                AdminDimensionsControl::make(),
                AdminControl::text('dimension_custom', 'Custom dimensions')
                    ->maxLength(240)
                    ->columnSpanFull(),
                MediaAssetSelect::makeId(
                    'primary_media_asset_id',
                    'Hero Media File',
                    allowedMimeTypes: [...MediaTypePolicy::IMAGE_MIME_TYPES, ...MediaTypePolicy::VIDEO_MIME_TYPES],
                )
                    ->nullable()
                    ->live()
                    ->afterStateUpdated(static function (mixed $state, callable $set): void {
                        $set('primary_alt_text', null);
                    })
                    ->columnSpanFull(),
                AdminControl::text('primary_alt_text', 'Hero ALT text')
                    ->nullable()
                    ->maxLength(500)
                    ->columnSpanFull(),
                app(ArtworkEditDialog::class)
                    ->individualInformationPanel('Hero Artwork information')
                    ->columnSpanFull(),
            ]),
        ];
    }
}
