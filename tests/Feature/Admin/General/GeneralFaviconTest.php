<?php

use App\Filament\Support\MediaAssetSelect;
use App\Models\MediaAsset;
use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

require_once __DIR__.'/GeneralFixtures.php';

it('searches favicon choices by filename and keeps the picker image-only and available-only', function (): void {
    $image = MediaAsset::query()->create([
        'storage_key' => 'originals/picker-match.png',
        'original_filename' => 'picker-match.png',
        'mime_type' => 'image/png',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'picker-match.png'),
        'state' => 'available',
        'alt_text' => 'Picker image',
    ]);
    MediaAsset::query()->create([
        'storage_key' => 'originals/picker-match.mp4',
        'original_filename' => 'picker-match.mp4',
        'mime_type' => 'video/mp4',
        'byte_size' => 5,
        'sha256' => hash('sha256', 'picker-match.mp4'),
        'state' => 'available',
    ]);

    MediaAsset::query()->create([
        'storage_key' => 'originals/picker-match-unavailable.png',
        'original_filename' => 'picker-match-unavailable.png',
        'mime_type' => 'image/png',
        'byte_size' => 6,
        'sha256' => hash('sha256', 'picker-match-unavailable.png'),
        'state' => 'quarantined',
    ]);

    $results = MediaAssetSelect::searchOptions('picker-match', imagesOnly: true);

    expect(array_keys($results))->toBe([(int) $image->id]);
});
