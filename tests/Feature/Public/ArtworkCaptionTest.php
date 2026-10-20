<?php

use App\Models\Artwork;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;

it('prints title and year above dimensions and material with its unit', function (): void {
    $artwork = new Artwork([
        'title' => 'Between Lines',
        'medium' => 'Oil on linen',
        'work_year' => 2025,
        'dimensions' => '80 × 60 cm',
        'description' => 'Narrative is shown separately.',
    ]);

    $markup = Blade::render('<x-artwork-caption :artwork="$artwork" />', ['artwork' => $artwork]);

    expect($markup)->toContain('class="artwork-caption__primary"')
        ->toContain('class="artwork-caption__secondary"')
        ->and(Str::squish(strip_tags($markup)))->toBe('Between Lines, 2025 80 × 60 cm, Oil on linen')
        ->and($markup)->not->toContain('Narrative is shown separately.')
        ->not->toContain('artwork-label__prompt');
});

it('omits absent fields and preserves custom dimension units', function (): void {
    $artwork = new Artwork(['title' => 'Unframed']);
    $markup = Blade::render('<x-artwork-caption :artwork="$artwork" />', ['artwork' => $artwork]);

    expect(Str::squish(strip_tags($markup)))->toBe('Unframed')
        ->and($markup)->not->toContain('artwork-caption__secondary')
        ->not->toContain('artwork-caption__material');

    $artwork->medium = 'Bronze';
    $artwork->dimensions = '12 × 8 × 4 mm';
    $markup = Blade::render('<x-artwork-caption :artwork="$artwork" />', ['artwork' => $artwork]);

    expect(Str::squish(strip_tags($markup)))->toBe('Unframed 12 × 8 × 4 mm, Bronze');
});

it('renders legacy two-dimensional measurements with cm and retains nonzero depth', function (): void {
    $artwork = new Artwork([
        'title' => 'Klaus Timm',
        'medium' => 'drawing',
        'work_year' => 2018,
        'dimensions' => '33 x 24 x 0',
    ]);

    $caption = Blade::render('<x-artwork-caption :artwork="$artwork" />', ['artwork' => $artwork]);
    expect(Str::squish(strip_tags($caption)))->toBe('Klaus Timm, 2018 33 × 24 cm, drawing');

    $artwork->dimensions = '33 × 24 × 1.5 mm';
    $caption = Blade::render('<x-artwork-caption :artwork="$artwork" />', ['artwork' => $artwork]);
    expect(Str::squish(strip_tags($caption)))->toBe('Klaus Timm, 2018 33 × 24 × 1.5 mm, drawing');
});

it('keeps a long complete title and does not invent a missing dimension or year', function (): void {
    $artwork = new Artwork([
        'title' => 'Göttliches Gewissen (Arachne und Athene) – ein ausführlicher Werktitel',
        'medium' => 'oil on canvas',
    ]);

    $markup = Blade::render('<x-artwork-caption :artwork="$artwork" />', ['artwork' => $artwork]);

    expect(Str::squish(strip_tags($markup)))
        ->toBe('Göttliches Gewissen (Arachne und Athene) – ein ausführlicher Werktitel oil on canvas')
        ->and($markup)->toContain('class="artwork-caption__title"')
        ->not->toContain('artwork-caption__year');
});
