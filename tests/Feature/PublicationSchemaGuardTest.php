<?php

use App\Domain\Publication\PublicationSchemaGuard;
use Illuminate\Support\Facades\DB;

/** @return array<string, int> */
function publicationSchemaGuardOrdinals(string $schema): array
{
    return DB::table('information_schema.columns')
        ->where('table_schema', $schema)
        ->where('table_name', 'journal_entry_media')
        ->orderBy('ordinal_position')
        ->pluck('ordinal_position', 'column_name')
        ->map(static fn (mixed $position): int => (int) $position)
        ->all();
}

it('accepts matching logical columns when PostgreSQL ordinal positions contain gaps', function (): void {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Publication schema parity requires PostgreSQL.');
    }

    $public = publicationSchemaGuardOrdinals('public');
    $committed = publicationSchemaGuardOrdinals('committed');

    expect(array_keys($public))->toBe(array_keys($committed))
        ->and($public['created_at'] ?? null)->not->toBe($committed['created_at'] ?? null)
        ->and($public['updated_at'] ?? null)->not->toBe($committed['updated_at'] ?? null);

    app(PublicationSchemaGuard::class)->assertParity();
});

it('rejects a real committed schema difference through the public guard boundary', function (): void {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Publication schema parity requires PostgreSQL.');
    }

    DB::statement('ALTER TABLE committed.journal_entry_media ADD COLUMN schema_guard_probe text');

    expect(fn () => app(PublicationSchemaGuard::class)->assertParity())
        ->toThrow(\RuntimeException::class, 'Publication snapshot schema drift detected for journal_entry_media.');
});
