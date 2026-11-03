<?php

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Publication\PublicationSchemaGuard;
use App\Domain\Publication\PublicationService;
use App\Domain\Publication\PublicationVersionService;
use App\Models\Exhibition;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Model the immediately preceding schema, in the already-isolated PostgreSQL
 * test transaction. No production schema is ever restored or mutated here.
 */
function previousExhibitionDateSchema(): void
{
    foreach (['public', 'committed'] as $schema) {
        DB::statement("ALTER TABLE {$schema}.exhibitions DROP CONSTRAINT IF EXISTS exhibitions_partial_dates_check");
        DB::statement("ALTER TABLE {$schema}.exhibitions DROP CONSTRAINT IF EXISTS exhibitions_published_content_check");
        DB::statement("ALTER TABLE {$schema}.exhibitions ALTER COLUMN starts_on TYPE DATE USING starts_on::date");
        DB::statement("ALTER TABLE {$schema}.exhibitions ALTER COLUMN ends_on TYPE DATE USING ends_on::date");
        DB::statement("ALTER TABLE {$schema}.exhibitions ADD COLUMN date_text VARCHAR(160) NULL");
    }
    app(PublicationSchemaGuard::class)->assertParity(['exhibitions']);
}

function exhibitionDateCutover(): \Illuminate\Database\Migrations\Migration
{
    return require database_path('migrations/2026_11_03_180000_canonicalize_exhibition_partial_dates.php');
}

function legacyExhibitionDraft(string $slug, ?string $authored, ?string $start = null): Exhibition
{
    $section = app(SiteSectionEditorialService::class)->createJournal(
        'Historical '.ucfirst($slug), 'historic-'.$slug, JournalTemplate::Exhibitions->value,
    );
    $entry = Exhibition::query()->create([
        'site_section_id' => $section->getKey(),
        'slug' => $slug,
        'title' => 'Historical '.$slug,
        'state' => 'draft',
        'position' => 1,
        'starts_on' => $start,
    ]);
    DB::table('exhibitions')->where('id', $entry->getKey())->update(['date_text' => $authored]);

    return $entry;
}

it('converts both publication schemas without rewriting a real immutable historical checkpoint', function (): void {
    previousExhibitionDateSchema();
    $entry = legacyExhibitionDraft('month-event', 'June 2014');
    $actor = User::factory()->admin()->create();
    $checkpoint = app(PublicationService::class)->commit($actor, 'Old authored Exhibition');
    expect($checkpoint)->not->toBeNull();
    $originalHash = $checkpoint->snapshot_hash;
    $historical = DB::table('publication_version_rows')
        ->where('publication_checkpoint_id', $checkpoint->getKey())
        ->where('table_name', 'exhibitions')->where('row_key', (string) $entry->id)
        ->value('payload');
    $historical = is_string($historical) ? json_decode($historical, true, 512, JSON_THROW_ON_ERROR) : (array) $historical;
    expect($historical['date_text'])->toBe('June 2014');

    exhibitionDateCutover()->up();

    expect(Schema::hasColumn('exhibitions', 'date_text'))->toBeFalse()
        ->and(DB::table('information_schema.columns')
            ->where('table_schema', 'committed')->where('table_name', 'exhibitions')
            ->where('column_name', 'date_text')->exists())->toBeFalse()
        ->and($entry->fresh()->starts_on)->toBe('2014-06')
        ->and($entry->fresh()->ends_on)->toBeNull()
        ->and(DB::table('committed.exhibitions')->where('id', $entry->id)->value('starts_on'))->toBe('2014-06')
        ->and($checkpoint->fresh()->snapshot_hash)->toBe($originalHash);
    $afterHistory = DB::table('publication_version_rows')
        ->where('publication_checkpoint_id', $checkpoint->getKey())
        ->where('table_name', 'exhibitions')->where('row_key', (string) $entry->id)
        ->value('payload');
    expect($afterHistory)->toBe($historical);
    app(PublicationSchemaGuard::class)->assertParity();

    $versions = app(PublicationVersionService::class);
    expect($versions->isRestorable($checkpoint->fresh()))->toBeTrue();
    DB::table('exhibitions')->where('id', $entry->id)->update(['starts_on' => '2028']);
    $before = DB::table('exhibitions')->where('id', $entry->id)->value('starts_on');
    $review = $versions->verifyCheckpointReadOnly($checkpoint->fresh());
    expect($review['historical_date_projection'])->toBeTrue()
        ->and($review['differences_from_working'])->toBeGreaterThan(0)
        ->and(DB::table('exhibitions')->where('id', $entry->id)->value('starts_on'))->toBe($before)
        ->and($checkpoint->fresh()->snapshot_hash)->toBe($originalHash);

    $versions->stageVersion($checkpoint->fresh(), $actor);
    expect($entry->fresh()->starts_on)->toBe('2014-06')
        ->and($entry->fresh()->ends_on)->toBeNull()
        ->and(DB::table('committed.exhibitions')->where('id', $entry->id)->value('starts_on'))->toBe('2014-06')
        ->and($checkpoint->fresh()->snapshot_hash)->toBe($originalHash);
});

it('aborts an invalid Committed conversion without changing either active publication schema', function (
    ?string $start,
    ?string $end,
    ?string $authored,
    string $state,
    string $message,
): void {
    previousExhibitionDateSchema();
    $entry = legacyExhibitionDraft('source-conflict', null, '2027-06-01');
    $actor = User::factory()->admin()->create();
    expect(app(PublicationService::class)->commit($actor, 'Old valid draft'))->not->toBeNull();
    DB::table('committed.exhibitions')->where('id', $entry->id)->update([
        'starts_on' => $start,
        'ends_on' => $end,
        'date_text' => $authored,
        'state' => $state,
    ]);

    expect(fn () => exhibitionDateCutover()->up())->toThrow(\RuntimeException::class, $message);
    expect(Schema::hasColumn('exhibitions', 'date_text'))->toBeTrue()
        ->and(DB::table('information_schema.columns')
            ->where('table_schema', 'committed')->where('table_name', 'exhibitions')
            ->where('column_name', 'date_text')->exists())->toBeTrue()
        ->and(DB::table('exhibitions')->where('id', $entry->id)->value('starts_on'))->toBe('2027-06-01')
        ->and(DB::table('committed.exhibitions')->where('id', $entry->id)->value('date_text'))->toBe($authored);
    app(PublicationSchemaGuard::class)->assertParity(['exhibitions']);
})->with([
    'unknown authored expression' => [null, null, 'Summer 2027', 'draft', 'unconvertible artist date'],
    'contradictory authored date' => ['2027-06-01', null, 'July 2027', 'draft', 'unconvertible artist date'],
    'published without date' => [null, null, null, 'published', 'published but has no recoverable date'],
    'end before beginning' => ['2027-06-15', '2027-06-01', null, 'draft', 'unconvertible artist date'],
]);

it('rejects an unconvertible immutable checkpoint even when Working and Committed are valid', function (): void {
    previousExhibitionDateSchema();
    $entry = legacyExhibitionDraft('bad-history', 'Unknown 2027');
    $actor = User::factory()->admin()->create();
    $checkpoint = app(PublicationService::class)->commit($actor, 'Source-era historical conflict');
    expect($checkpoint)->not->toBeNull();
    $hash = $checkpoint->snapshot_hash;

    foreach (['public', 'committed'] as $schema) {
        DB::table("{$schema}.exhibitions")->where('id', $entry->id)
            ->update(['date_text' => 'June 2027']);
    }

    expect(fn () => exhibitionDateCutover()->up())
        ->toThrow(\RuntimeException::class, 'contains an unconvertible date');

    expect(Schema::hasColumn('exhibitions', 'date_text'))->toBeTrue()
        ->and($checkpoint->fresh()->snapshot_hash)->toBe($hash)
        ->and(DB::table('committed.exhibitions')->where('id', $entry->id)->value('date_text'))->toBe('June 2027');
});
