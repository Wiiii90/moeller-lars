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
use App\Models\Redirect;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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
    expect(Schema::hasColumns('cv_entries', [
        'external_url', 'image_media_asset_id',
    ]))->toBeTrue();
    expect(Schema::hasColumns('audit_events', [
        'admin_user_id', 'action', 'entity_type', 'occurred_at',
    ]))->toBeTrue();
});

it('connects artwork, original media, derivatives, and category relationships', function () {
    $category = makeCategory(['slug' => 'schema-paintings']);
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

it('manages artwork media through normal Eloquent identity', function () {
    $category = makeCategory(['slug' => 'schema-prints']);
    $artwork = Artwork::create([
        'artwork_category_id' => $category->id,
        'slug' => 'identity-test',
        'title' => 'Identity Test',
        'state' => 'draft',
        'position' => 0,
    ]);
    $asset = makeAsset();
    $usage = ArtworkMedia::create([
        'artwork_id' => $artwork->id,
        'media_asset_id' => $asset->id,
        'role' => 'primary',
        'position' => 0,
    ]);

    $retrieved = ArtworkMedia::findOrFail($usage->id);
    $retrieved->update(['alt_text_override' => 'Updated artwork text']);

    expect($retrieved->id)->toBe($usage->id)
        ->and($retrieved->fresh()->alt_text_override)->toBe('Updated artwork text');

    $retrieved->delete();
    expect(ArtworkMedia::find($usage->id))->toBeNull();
});

it('rejects duplicate artwork media pairs', function () {
    $category = makeCategory(['slug' => 'schema-drawings']);
    $artwork = Artwork::create([
        'artwork_category_id' => $category->id,
        'slug' => 'duplicate-pair',
        'title' => 'Duplicate Pair',
        'state' => 'draft',
        'position' => 0,
    ]);
    $asset = makeAsset();
    ArtworkMedia::create(['artwork_id' => $artwork->id, 'media_asset_id' => $asset->id, 'position' => 0]);

    expect(fn () => ArtworkMedia::create([
        'artwork_id' => $artwork->id,
        'media_asset_id' => $asset->id,
        'position' => 1,
    ]))->toThrow(QueryException::class);
});

it('rejects a second primary artwork media row', function () {
    $category = makeCategory(['slug' => 'schema-cyanotype']);
    $artwork = Artwork::create([
        'artwork_category_id' => $category->id,
        'slug' => 'second-primary',
        'title' => 'Second Primary',
        'state' => 'draft',
        'position' => 0,
    ]);
    $first = makeAsset();
    $second = makeAsset();
    ArtworkMedia::create(['artwork_id' => $artwork->id, 'media_asset_id' => $first->id, 'role' => 'primary', 'position' => 0]);

    expect(fn () => ArtworkMedia::create([
        'artwork_id' => $artwork->id,
        'media_asset_id' => $second->id,
        'role' => 'primary',
        'position' => 1,
    ]))->toThrow(QueryException::class);
});

it('defaults the singleton blog setting to publicly disabled', function () {
    $setting = BlogSetting::query()->first();

    expect($setting)->not->toBeNull()
        ->and($setting->id)->toBe(1)
        ->and($setting->public_enabled)->toBeFalse();
});

it('uses safe defaults for categories and original media', function () {
    $category = ArtworkCategory::create([
        'slug' => 'default-hidden',
        'name' => 'Default Hidden',
        'position' => 0,
    ]);
    $asset = MediaAsset::create([
        'storage_key' => 'originals/'.uniqid().'.jpg',
        'original_filename' => 'default.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 1,
        'sha256' => str_repeat('e', 64),
        'alt_text' => 'Default asset',
    ]);

    expect($category->fresh()->state)->toBe('hidden')
        ->and($asset->fresh()->state)->toBe('quarantined');
});

it('enforces the blog setting singleton and permits updates', function () {
    expect(fn () => DB::table('blog_settings')->insert([
        'id' => 2,
        'public_enabled' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('updates but does not delete the blog setting singleton', function () {
    $setting = BlogSetting::query()->firstOrFail();
    $setting->update(['public_enabled' => true]);

    expect($setting->fresh()->public_enabled)->toBeTrue();
    expect(fn () => $setting->delete())->toThrow(LogicException::class);
});

it('enforces stable category slugs', function () {
    makeCategory(['slug' => 'schema-duplicate']);

    expect(fn () => makeCategory(['slug' => 'schema-duplicate']))
        ->toThrow(QueryException::class);
});

it('enforces partial legacy provenance uniqueness', function () {
    makeCategory([
        'slug' => 'schema-provenance-one',
        'legacy_id' => 42,
        'legacy_source' => 'legacy:prints',
    ]);

    expect(fn () => makeCategory([
        'slug' => 'schema-provenance-two',
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
    makeCategory(['slug' => 'schema-incomplete-one', 'legacy_source' => 'legacy:prints']);
    makeCategory(['slug' => 'schema-incomplete-two', 'legacy_source' => 'legacy:prints']);

    expect(ArtworkCategory::query()->where('legacy_source', 'legacy:prints')->count())->toBe(2);
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
    $category = makeCategory(['slug' => 'schema-negative-artwork']);
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

it('enforces published blog post requirements', function (array $attributes) {
    expect(fn () => BlogPost::create(array_merge([
        'slug' => 'invalid-published-'.uniqid(),
        'title' => 'Published',
        'state' => 'published',
        'position' => 0,
    ], $attributes)))->toThrow(QueryException::class);
})->with([
    'null body' => [['body' => null, 'published_at' => now()]],
    'blank body' => [['body' => '   ', 'published_at' => now()]],
    'missing published timestamp' => [['body' => 'Content']],
]);

it('rejects a published blog post with a blank title', function () {
    expect(fn () => BlogPost::create([
        'slug' => 'blank-published-title',
        'title' => '   ',
        'body' => 'Content',
        'state' => 'published',
        'position' => 0,
        'published_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('accepts a valid published blog post', function () {
    $post = BlogPost::create([
        'slug' => 'valid-published',
        'title' => 'Published',
        'body' => 'Content',
        'state' => 'published',
        'position' => 0,
        'published_at' => now(),
    ]);

    expect($post->exists)->toBeTrue();
});

it('enforces scheduled blog post timestamps', function () {
    expect(fn () => BlogPost::create([
        'slug' => 'invalid-scheduled',
        'title' => 'Scheduled',
        'state' => 'scheduled',
        'position' => 0,
    ]))->toThrow(QueryException::class);
});

it('accepts a scheduled blog post with a scheduled timestamp', function () {
    $post = BlogPost::create([
        'slug' => 'valid-scheduled',
        'title' => 'Scheduled',
        'state' => 'scheduled',
        'position' => 0,
        'scheduled_at' => now()->addDay(),
    ]);

    expect($post->exists)->toBeTrue();
});

it('enforces positive media dimensions', function (string $table, array $attributes) {
    expect(fn () => $table === 'media_assets'
        ? MediaAsset::create(array_merge([
            'storage_key' => 'originals/'.uniqid().'.jpg',
            'original_filename' => 'invalid.jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => 100,
            'sha256' => str_repeat('e', 64),
            'state' => 'quarantined',
        ], $attributes))
        : MediaVariant::create(array_merge([
            'media_asset_id' => makeAsset()->id,
            'variant_kind' => 'medium',
            'storage_key' => 'derivatives/'.uniqid().'.jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => 10,
            'sha256' => str_repeat('d', 64),
            'transform_profile' => 'medium-v1',
            'state' => 'available',
        ], $attributes)))->toThrow(QueryException::class);
})->with([
    'asset width' => ['media_assets', ['width' => 0]],
    'asset height' => ['media_assets', ['height' => 0]],
    'variant width' => ['media_variants', ['width' => 0]],
    'variant height' => ['media_variants', ['height' => 0]],
]);

it('accepts redirects to internal and HTTPS targets', function () {
    $internal = Redirect::create(['source_path' => '/old-work', 'target_path' => '/artworks/new-work']);
    $external = Redirect::create(['source_path' => '/external-work', 'target_path' => 'https://example.com/work']);

    expect($internal->exists)->toBeTrue()->and($external->exists)->toBeTrue();
});

it('rejects unsafe redirect paths and targets', function (string $source, string $target) {
    expect(fn () => Redirect::create(['source_path' => $source, 'target_path' => $target]))
        ->toThrow(QueryException::class);
})->with([
    'source without slash' => ['old-work', '/artworks/new-work'],
    'protocol-relative source' => ['//old-work', '/artworks/new-work'],
    'source fragment' => ['/old-work#part', '/artworks/new-work'],
    'source query' => ['/old-work?x=1', '/artworks/new-work'],
    'same paths' => ['/old-work', '/old-work'],
    'internal target fragment' => ['/old-work', '/target#fragment'],
    'internal target query' => ['/old-work', '/target?x=1'],
    'external target fragment' => ['/old-work', 'https://example.com/work#fragment'],
    'javascript target' => ['/old-work', 'javascript:alert(1)'],
    'data target' => ['/old-work', 'data:text/plain,unsafe'],
    'http target' => ['/old-work', 'http://example.com/work'],
]);

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

it('connects CV entries to image media through a normal relation', function () {
    $asset = makeAsset(['storage_key' => 'originals/cv-'.uniqid().'.jpg']);
    $entry = CvEntry::create([
        'section' => 'awards',
        'title' => 'Award',
        'state' => 'published',
        'position' => 0,
        'image_media_asset_id' => $asset->id,
    ]);

    expect($entry->imageMediaAsset->is($asset))->toBeTrue()
        ->and($asset->cvEntries->first()->is($entry))->toBeTrue();

    expect(fn () => $asset->delete())->toThrow(QueryException::class);
});

it('enforces HTTPS CV external URLs', function () {
    expect(fn () => CvEntry::create([
        'section' => 'publications',
        'title' => 'Publication',
        'state' => 'draft',
        'position' => 0,
        'external_url' => 'http://example.com',
    ]))->toThrow(QueryException::class);
});

it('enforces exhibition kinds', function () {
    expect(fn () => Exhibition::create([
        'slug' => 'invalid-kind',
        'title' => 'Invalid Kind',
        'state' => 'draft',
        'position' => 0,
        'kind' => 'festival',
    ]))->toThrow(QueryException::class);
});

it('derives deterministic exhibition temporal state', function () {
    $exhibition = new Exhibition(['starts_on' => '2026-08-20', 'ends_on' => '2026-08-25']);

    expect($exhibition->temporalState(CarbonImmutable::parse('2026-08-19')))->toBe('upcoming')
        ->and($exhibition->temporalState(CarbonImmutable::parse('2026-08-22')))->toBe('current')
        ->and($exhibition->temporalState(CarbonImmutable::parse('2026-08-26')))->toBe('past')
        ->and((new Exhibition)->temporalState(CarbonImmutable::parse('2026-08-22')))->toBe('unknown');

    $singleDay = new Exhibition(['starts_on' => '2026-08-20']);

    expect($singleDay->temporalState(CarbonImmutable::parse('2026-08-20')))->toBe('current')
        ->and($singleDay->temporalState(CarbonImmutable::parse('2026-08-21')))->toBe('past');
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

it('rejects audit event updates and deletes', function () {
    $event = AuditEvent::create([
        'action' => 'artwork.create',
        'entity_type' => 'artwork',
        'entity_id' => 1,
        'occurred_at' => now(),
    ]);

    expect(fn () => $event->update(['action' => 'artwork.delete']))
        ->toThrow(LogicException::class);
    expect(fn () => $event->delete())
        ->toThrow(LogicException::class);
});

it('retains both public artwork listing indexes', function () {
    $definitions = collect(DB::select("SELECT indexdef FROM pg_indexes WHERE tablename = 'artworks'"))
        ->pluck('indexdef')
        ->implode("\n");

    expect($definitions)->toContain('(artwork_category_id, state, work_date, "position")')
        ->and($definitions)->toContain('(state, work_date, "position")');
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
