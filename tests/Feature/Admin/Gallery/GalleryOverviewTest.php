<?php

use App\Domain\Analytics\ArtistReportingService;
use App\Filament\Pages\GalleryWorkspace;
use App\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    \Filament\Facades\Filament::setCurrentPanel('admin');
    \Filament\Facades\Filament::bootCurrentPanel();
});

require_once __DIR__.'/GalleryFixtures.php';

it('shows real Gallery totals and per-artwork analytics in the workspace', function (): void {
    $gallery = galleryFixtureCategory();
    $artwork = galleryFixtureArtwork($gallery, [
        'state' => 'published',
        'position' => 0,
    ]);
    $image = galleryFixtureAsset();
    galleryFixturePrimary($artwork, $image);
    galleryFixtureThumbnail($image);

    $reporting = new class((string) $artwork->getAttribute('analytics_key'))
    {
        public function __construct(private readonly string $analyticsKey) {}

        /** @param list<string> $artworkAnalyticsKeys */
        public function gallery(string $publicPath, array $artworkAnalyticsKeys, string $range = '30d'): array
        {
            return [
                'status' => 'available',
                'page' => [
                    'visits' => ['state' => 'available', 'value' => 12.0],
                    'views' => ['state' => 'available', 'value' => 18.0],
                ],
                'artworks' => [
                    'state' => 'available',
                    'rows' => [[
                        'analytics_key' => $this->analyticsKey,
                        'detail_views' => 4,
                        'viewer_opens' => 3,
                        'zooms' => 2,
                        'navigation' => 1,
                        'attention_seconds' => 195.0,
                        'attention_label' => '3m 15s',
                    ]],
                ],
            ];
        }
    };
    app()->instance(ArtistReportingService::class, $reporting);

    $response = $this->get(GalleryWorkspace::getUrl(['gallery' => $gallery->getKey()]));

    $response->assertOk()
        ->assertSeeText('Artworks')
        ->assertSeeText('Published')
        ->assertSeeText('Visits')
        ->assertSeeText('Views')
        ->assertSeeText('Artwork opens')
        ->assertSeeText('Attention')
        ->assertSeeText($artwork->title)
        ->assertSeeText('30d · 4 views · 3 opens · 2 zooms · 3m 15s attention');
});


it('renders previews with incomplete drafts and invalid legacy primary rows without a 500', function (): void {
    $gallery = galleryFixtureCategory();
    $broken = galleryFixtureArtwork($gallery, ['title' => 'No primary media', 'position' => 0]);
    $missingAlt = galleryFixtureArtwork($gallery, ['title' => 'No image ALT text', 'position' => 1]);
    $asset = galleryFixtureAsset('image/jpeg', '');
    galleryFixtureThumbnail($asset);
    galleryFixturePrimary($missingAlt, $asset);
    $valid = galleryFixtureArtwork($gallery, ['title' => 'Valid visible artwork', 'position' => 2]);
    $video = galleryFixtureAsset('video/mp4', 'Accessible moving-image description');
    galleryFixturePrimary($valid, $video);

    // Simulate a legacy published row that no longer has a primary usage.
    $broken->forceFill(['state' => 'published'])->save();

    $this->get(route('preview.site.section', ['section' => $gallery->slug]))
        ->assertOk()
        ->assertSee('Valid visible artwork')
        ->assertDontSee('No primary media')
        ->assertDontSee('No image ALT text');
});
