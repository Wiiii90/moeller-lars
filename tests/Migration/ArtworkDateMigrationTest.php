<?php

use App\Models\ArtworkCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

function artworkDateMigration(): \Illuminate\Database\Migrations\Migration
{
    return require database_path('migrations/2026_11_03_150000_replace_artwork_date_with_components.php');
}

/**
 * Reconstruct the exact immediately preceding schema inside the disposable
 * PostgreSQL migration transaction. No application/artist database is used.
 */
function priorArtworkDateSchema(): void
{
    artworkDateMigration()->down();
    foreach (['public', 'committed'] as $schema) {
        expect(DB::table('information_schema.columns')
            ->where('table_schema', $schema)->where('table_name', 'artworks')
            ->where('column_name', 'date_precision')->exists())->toBeTrue();
    }
}

function priorArtworkDateRow(string $slug, ?int $year, ?string $day, string $precision): int
{
    return (int) DB::table('artworks')->insertGetId([
        'artwork_category_id' => null,
        'slug' => $slug,
        'title' => 'Artwork '.$slug,
        'analytics_key' => (string) Str::uuid(),
        'state' => 'draft',
        'position' => 0,
        'work_year' => $year,
        'work_date' => $day,
        'date_precision' => $precision,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('converts year month exact day and unknown Artwork dates losslessly in Working and Committed', function (): void {
    priorArtworkDateSchema();
    $artworks = [
        ['year', 2026, null, 'year', [2026, null, null]],
        ['month', 2027, '2027-06-01', 'month', [2027, 6, null]],
        ['day', 2028, '2028-02-29', 'day', [2028, 2, 29]],
        ['undated', null, null, 'unknown', [null, null, null]],
    ];
    $ids = [];
    foreach ($artworks as [$slug, $year, $date, $precision]) {
        $id = priorArtworkDateRow('historical-'.$slug, $year, $date, $precision);
        DB::table('committed.artworks')->insert((array) DB::table('artworks')->where('id', $id)->first());
        $ids[$slug] = $id;
    }

    artworkDateMigration()->up();

    expect(Schema::hasColumn('artworks', 'work_year'))->toBeFalse()
        ->and(Schema::hasColumn('artworks', 'work_date'))->toBeFalse()
        ->and(Schema::hasColumn('artworks', 'date_precision'))->toBeFalse();
    foreach (['public', 'committed'] as $schema) {
        expect(DB::table('information_schema.columns')
            ->where('table_schema', $schema)->where('table_name', 'artworks')
            ->where('column_name', 'year')->exists())->toBeTrue();
        foreach ($artworks as [$slug, $legacyYear, $date, $precision, $expected]) {
            $parts = DB::table("{$schema}.artworks")->where('id', $ids[$slug])
                ->first(['year', 'month', 'day']);
            expect([$parts->year, $parts->month, $parts->day])->toBe($expected);
        }
    }
    app(\App\Domain\Publication\PublicationSchemaGuard::class)->assertParity();
});

it('fails atomically for an inconsistent old Committed Artwork date instead of inventing precision', function (): void {
    priorArtworkDateSchema();
    $id = priorArtworkDateRow('invalid-legacy-precision', 2027, '2027-06-02', 'year');
    $record = (array) DB::table('artworks')->where('id', $id)->first();
    // Working remains valid, only Committed contains the contradictory
    // year precision plus day. Preflight must read BOTH schemas.
    DB::table('artworks')->where('id', $id)->update(['work_date' => null]);
    DB::table('committed.artworks')->insert($record);

    expect(fn () => artworkDateMigration()->up())
        ->toThrow(RuntimeException::class, 'ambiguous rows in committed.artworks');

    foreach (['public', 'committed'] as $schema) {
        expect(DB::table('information_schema.columns')
            ->where('table_schema', $schema)->where('table_name', 'artworks')
            ->where('column_name', 'date_precision')->exists())->toBeTrue()
            ->and(DB::table('information_schema.columns')
                ->where('table_schema', $schema)->where('table_name', 'artworks')
                ->where('column_name', 'year')->exists())->toBeFalse();
    }
    expect(DB::table('committed.artworks')->where('id', $id)->value('work_date'))->toBe('2027-06-02');
});
