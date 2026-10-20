<?php

use App\Models\Artwork;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;

it('renders complete authored Artwork captions without inventing missing facts', function (): void {
    foreach ([
        [
            ['title' => 'Between Lines', 'medium' => 'Oil on linen', 'work_year' => 2025,
                'dimensions' => '80 × 60 cm', 'description' => 'Narrative is shown separately.'],
            'Between Lines, 2025 80 × 60 cm, Oil on linen',
        ],
        [['title' => 'Unframed'], 'Unframed'],
        [
            ['title' => 'Unframed', 'medium' => 'Bronze', 'dimensions' => '12 × 8 × 4 mm'],
            'Unframed 12 × 8 × 4 mm, Bronze',
        ],
        [
            ['title' => 'Klaus Timm', 'medium' => 'drawing', 'work_year' => 2018,
                'dimensions' => '33 x 24 x 0'],
            'Klaus Timm, 2018 33 × 24 cm, drawing',
        ],
        [
            ['title' => 'Klaus Timm', 'medium' => 'drawing', 'work_year' => 2018,
                'dimensions' => '33 × 24 × 1.5 mm'],
            'Klaus Timm, 2018 33 × 24 × 1.5 mm, drawing',
        ],
        [
            ['title' => 'Göttliches Gewissen (Arachne und Athene) – ein ausführlicher Werktitel',
                'medium' => 'oil on canvas'],
            'Göttliches Gewissen (Arachne und Athene) – ein ausführlicher Werktitel oil on canvas',
        ],
    ] as [$attributes, $expectedText]) {
        $html = Blade::render('<x-artwork-caption :artwork="$artwork" />', [
            'artwork' => new Artwork($attributes),
        ]);
        expect(Str::squish(strip_tags($html)))->toBe($expectedText)
            ->and($html)->not->toContain('Narrative is shown separately.');
    }
});
