<?php

use App\Domain\Analytics\ArtworkAttentionReport;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('assigns a stable public analytics identity that survives editorial renames', function (): void {
    $category = ArtworkCategory::create([
        'name' => 'Analytics Gallery',
        'slug' => 'analytics-gallery',
        'state' => 'published',
        'position' => 0,
        'show_in_navigation' => true,
        'show_on_home' => false,
    ]);

    $artwork = Artwork::create([
        'artwork_category_id' => $category->getKey(),
        'slug' => 'first-title',
        'title' => 'First title',
        'state' => 'published',
        'position' => 0,
        'date_precision' => 'unknown',
    ]);

    $key = (string) $artwork->getAttribute('analytics_key');
    expect(Str::isUuid($key))->toBeTrue();

    $artwork->update(['title' => 'Renamed work', 'slug' => 'renamed-work']);

    expect((string) $artwork->fresh()->getAttribute('analytics_key'))->toBe($key);

    $other = Artwork::create([
        'artwork_category_id' => $category->getKey(),
        'slug' => 'second-work',
        'title' => 'Second work',
        'state' => 'draft',
        'position' => 1,
        'date_precision' => 'unknown',
    ]);

    expect((string) $other->getAttribute('analytics_key'))->not->toBe($key);
});

it('resolves Matomo artwork events back to current editorial context', function (): void {
    $category = ArtworkCategory::create([
        'name' => 'Attention Gallery',
        'slug' => 'attention-gallery',
        'state' => 'published',
        'position' => 0,
        'show_in_navigation' => true,
        'show_on_home' => false,
    ]);

    $artwork = Artwork::create([
        'artwork_category_id' => $category->getKey(),
        'slug' => 'attention-work',
        'title' => 'Attention Work',
        'state' => 'published',
        'position' => 0,
        'date_precision' => 'unknown',
    ]);
    $key = (string) $artwork->getAttribute('analytics_key');

    $events = [
        ['action' => 'artwork_detail_view', 'analytics_key' => $key, 'nb_events' => 4],
        ['action' => 'artwork_open', 'analytics_key' => $key, 'nb_events' => 3],
        ['action' => 'artwork_zoom_used', 'analytics_key' => $key, 'nb_events' => 1],
        ['action' => 'artwork_next', 'analytics_key' => $key, 'nb_events' => 2],
        ['action' => 'artwork_previous', 'analytics_key' => $key, 'nb_events' => 1],
        [
            'action' => 'artwork_attention',
            'analytics_key' => $key,
            'nb_events' => 2,
            'nb_events_with_value' => 2,
            'sum_event_value' => 18,
        ],
    ];
    $series = [
        ['date' => '2026-08-18', 'action' => 'artwork_attention', 'analytics_key' => $key, 'nb_events' => 1, 'sum_event_value' => 7],
        ['date' => '2026-08-19', 'action' => 'artwork_attention', 'analytics_key' => $key, 'nb_events' => 1, 'sum_event_value' => 11],
    ];

    $rows = app(ArtworkAttentionReport::class)->build($events, $series);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['analytics_key'])->toBe($key)
        ->and($rows[0]['title'])->toBe('Attention Work')
        ->and($rows[0]['category'])->toBe('Attention Gallery')
        ->and($rows[0]['detail_views'])->toBe(4)
        ->and($rows[0]['viewer_opens'])->toBe(3)
        ->and($rows[0]['zooms'])->toBe(1)
        ->and($rows[0]['navigation'])->toBe(3)
        ->and($rows[0]['attention_events'])->toBe(2)
        ->and($rows[0]['attention_seconds'])->toBe(18.0)
        ->and($rows[0]['average_attention_seconds'])->toBe(9.0)
        ->and($rows[0]['trend'])->toHaveCount(2)
        ->and($rows[0]['trend'][1]['attention_seconds'])->toBe(11.0)
        ->and($rows[0]['public_url'])->toBe(route('artworks.show', ['slug' => 'attention-work']));
});
