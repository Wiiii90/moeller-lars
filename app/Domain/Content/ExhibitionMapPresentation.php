<?php

namespace App\Domain\Content;

use App\Models\Exhibition;

final class ExhibitionMapPresentation
{
    /** @return array{embed_url:string, public_url:string, shape:string}|null */
    public function for(Exhibition $exhibition): ?array
    {
        if (! $exhibition->hasCoordinates()) {
            return null;
        }

        $latitude = (float) $exhibition->getAttribute('latitude');
        $longitude = (float) $exhibition->getAttribute('longitude');
        $shape = $this->shape((string) ($exhibition->getAttribute('map_shape') ?? 'wide'));
        $delta = 0.008;
        $bbox = implode(',', [
            $longitude - $delta,
            $latitude - $delta,
            $longitude + $delta,
            $latitude + $delta,
        ]);

        return [
            'embed_url' => 'https://www.openstreetmap.org/export/embed.html?'.http_build_query([
                'bbox' => $bbox,
                'layer' => 'mapnik',
                'marker' => $latitude.','.$longitude,
            ]),
            'public_url' => 'https://www.openstreetmap.org/?'.http_build_query([
                'mlat' => $latitude,
                'mlon' => $longitude,
            ]).'#map=16/'.$latitude.'/'.$longitude,
            'shape' => $shape,
        ];
    }

    public function shape(string $shape): string
    {
        return in_array($shape, ['wide', 'square'], true) ? $shape : 'wide';
    }

    public function aspectRatio(string $shape): string
    {
        return $this->shape($shape) === 'square' ? '1 / 1' : '16 / 9';
    }
}
