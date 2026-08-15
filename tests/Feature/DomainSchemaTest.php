<?php

use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\AuditEvent;
use App\Models\BlogPost;
use App\Models\BlogSetting;
use App\Models\CvEntry;
use App\Models\DailyMetric;
use App\Models\Exhibition;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

function makeCategory(array $attributes = []): ArtworkCategory
{
    return ArtworkCategory::create(array_merge([
        'slug' => 'paintings-'.uniqid(),
        'name' => 'Paintings',
        'state' => 'published',
        'position' => 0,
    ], $attributes));
}

function makeAsset(array $attributes = []): MediaAsset
{
    return MediaAsset::create(array_merge([
        'storage_key' => 'originals/'.uniqid().'.jpg',
        'original_filename' => 'work.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 1024,
        'sha256' => str_repeat('a', 64),
        'state' => 'available',
        'alt_text' => 'Artwork image',
    ], $attributes));
}

it('applies the PostgreSQL domain schema with critical columns', function () {
    $tables = [
        'artwork_categories', 'artworks', 'media_assets', 'media_variants',
        'artwork_media', 'exhibitions', 'cv_entries', 'blog_posts',
        'blog_settings', 'redirects', 'audit_events', 'daily_metrics',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    expect(Schema::hasColumns('artworks', [
        'artwork_category_id', 'slug', 'title', 'work_date', 'date_precision',
        'position', 'legacy_id', 'legacy_source',
    ]))->toBeTrue();
    expect(Schema::hasColumns('media_assets', [
        'storage_key', 'sha256', 'alt_text', 'legacy_path',
    ]))->toBeTrue();
    expect(Schema::hasColumns('audit_events', [
        'admin_user_id', 'action', 'entity_type', 'occurred_at',
    ]))->toBeTrue();
});

it('connects artwork, original media, derivatives, and category relationships', function () {
    $category = makeCategory(['slug' => 'paintings']);
    $artwork = Artwork::create([
        'artwork_category_id' => $category->id,
        'slug' => 'work-one',
        'title' => 'Work One',
        'state' => 'draft',
        'position' => 1,
        'date_precision' => 'unknown',
    ]);
    $asset = makeAsset();
    $variant = MediaVariant::create([
        'media_asset_id' => $asset->id,
        'variant_kind' => 'thumbnail',
        'storage_key' => 'derivatives/'.uniqid().'.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 512,
        'sha256' => str_repeat('b', 64),
        'transform_profile' => 'thumbnail-v1',
        'state' => 'available',
    ]);
    $usage = ArtworkMedia::create([
        'artwork_id' => $artwork->id,
        'media_asset_id' => $asset->id,
        'role' => 'primary',
        'position' => 0,
    ]);

    expect($artwork->category->is($category))->toBeTrue()
        ->and($artwork->artworkMedia->first()->is($usage))->toBeTrue()
        ->and($artwork->mediaAssets->first()->is($asset))->toBeTrue()
        ->and($asset->variants->first()->is($variant))->toBeTrue()
        ->and($variant->mediaAsset->is($asset))->toBeTrue();
});

it('defaults the singleton blog setting to publicly disabled', function () {
    $setting = BlogSetting::query()->first();

    expect($setting)->not->toBeNull()
        ->and($setting->id)->toBe(1)
        ->and($setting->public_enabled)->toBeFalse();
});

it('enforces stable category slugs', function () {
    makeCategory(['slug' => 'paintings']);

    expect(fn () => makeCategory(['slug' => 'paintings']))
        ->toThrow(QueryException::class);
});

it('enforces partial legacy provenance uniqueness', function () {
    makeCategory([
        'slug' => 'prints',
        'legacy_id' => 42,
        'legacy_source' => 'legacy:prints',
    ]);

    expect(fn () => makeCategory([
        'slug' => 'drawings',
        'legacy_id' => 42,
        'legacy_source' => 'legacy:prints',
    ]))->toThrow(QueryException::class);
});

it('does not impose global uniqueness on original media SHA-256 values', function () {
    $first = makeAsset(['sha256' => str_repeat('c', 64)]);
    $second = makeAsset(['sha256' => str_repeat('c', 64)]);

    expect($second->exists)->toBeTrue()
        ->and($first->sha256)->toBe($second->sha256);
});

it('allows incomplete legacy provenance to remain non-unique', function () {
    makeCategory(['slug' => 'cyanotype', 'legacy_source' => 'legacy:prints']);
    makeCategory(['slug' => 'bichromate', 'legacy_source' => 'legacy:prints']);

    expect(ArtworkCategory::count())->toBe(2);
});

it('rejects a negative category position', function () {
    expect(fn () => makeCategory(['position' => -1]))
        ->toThrow(QueryException::class);
});

it('rejects invalid category states', function () {
    expect(fn () => makeCategory(['state' => 'draft']))
        ->toThrow(QueryException::class);
});

it('rejects a negative artwork position', function () {
    $category = makeCategory(['slug' => 'drawings']);
    expect(fn () => Artwork::create([
        'artwork_category_id' => $category->id,
        'slug' => 'negative-position',
        'title' => 'Invalid',
        'state' => 'draft',
        'position' => -1,
    ]))->toThrow(QueryException::class);
});

it('rejects invalid blog post states', function () {
    expect(fn () => BlogPost::create([
        'slug' => 'invalid-state',
        'title' => 'Invalid',
        'state' => 'unknown',
        'position' => 0,
    ]))->toThrow(QueryException::class);
});

it('keeps CV entries and exhibitions as independent entities', function () {
    $cvEntry = CvEntry::create([
        'section' => 'education',
        'title' => 'Academy',
        'state' => 'published',
        'position' => 0,
        'date_precision' => 'year',
        'year_text' => '2001',
    ]);
    $exhibition = Exhibition::create([
        'slug' => 'solo-show',
        'title' => 'Solo Show',
        'state' => 'published',
        'position' => 0,
    ]);

    expect($cvEntry->getTable())->toBe('cv_entries')
        ->and($exhibition->getTable())->toBe('exhibitions')
        ->and(Schema::hasTable('cv_entries'))->toBeTrue()
        ->and(Schema::hasTable('exhibitions'))->toBeTrue();
});

it('nulls the audit actor when the canonical user is deleted', function () {
    $user = User::factory()->admin()->create();
    $event = AuditEvent::create([
        'admin_user_id' => $user->id,
        'action' => 'artwork.publish',
        'entity_type' => 'artwork',
        'entity_id' => 1,
        'occurred_at' => now(),
        'metadata' => ['source' => 'test'],
    ]);

    expect($event->adminUser->is($user))->toBeTrue();
    $user->delete();
    expect($event->fresh()->admin_user_id)->toBeNull();
});

it('stores only allowlisted operational metric names', function () {
    $metric = DailyMetric::create([
        'metric_date' => '2026-08-15',
        'metric_name' => 'bot_requests',
        'source' => 'local_log',
        'value' => 12,
        'unit' => 'count',
        'calculated_at' => now(),
    ]);

    expect($metric->exists)->toBeTrue();
    expect(fn () => DailyMetric::create([
        'metric_date' => '2026-08-15',
        'metric_name' => 'human_visitors',
        'source' => 'application',
        'value' => 1,
        'unit' => 'count',
        'calculated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rejects negative daily metric values', function () {
    expect(fn () => DailyMetric::create([
        'metric_date' => '2026-08-15',
        'metric_name' => 'error_count',
        'source' => 'application',
        'value' => -1,
        'unit' => 'count',
        'calculated_at' => now(),
    ]))->toThrow(QueryException::class);
});
