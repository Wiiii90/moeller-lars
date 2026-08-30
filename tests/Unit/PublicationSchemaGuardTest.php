<?php

use App\Domain\Publication\PublicationSchemaGuard;

/**
 * @return object{
 *     column_name: string,
 *     ordinal_position: int,
 *     data_type: string,
 *     udt_name: string,
 *     is_nullable: string,
 *     character_maximum_length: ?int,
 *     numeric_precision: ?int,
 *     numeric_scale: ?int,
 *     datetime_precision: ?int,
 *     is_identity: string,
 *     identity_generation: ?string,
 *     is_generated: string
 * }
 */
function publicationSchemaGuardColumn(
    string $name,
    int $ordinalPosition,
    string $dataType = 'text',
    string $udtName = 'text',
): object {
    return (object) [
        'column_name' => $name,
        'ordinal_position' => $ordinalPosition,
        'data_type' => $dataType,
        'udt_name' => $udtName,
        'is_nullable' => 'YES',
        'character_maximum_length' => null,
        'numeric_precision' => null,
        'numeric_scale' => null,
        'datetime_precision' => null,
        'is_identity' => 'NO',
        'identity_generation' => null,
        'is_generated' => 'NEVER',
    ];
}

/** @param list<object> $rows */
function publicationSchemaGuardSignature(array $rows): array
{
    $method = new ReflectionMethod(PublicationSchemaGuard::class, 'signature');

    return $method->invoke(new PublicationSchemaGuard, $rows);
}

it('ignores ordinal gaps while preserving the logical column order contract', function (): void {
    $public = [
        publicationSchemaGuardColumn('id', 1, 'bigint', 'int8'),
        publicationSchemaGuardColumn('alt_text_override', 7),
        publicationSchemaGuardColumn('created_at', 9, 'timestamp without time zone', 'timestamp'),
        publicationSchemaGuardColumn('updated_at', 10, 'timestamp without time zone', 'timestamp'),
    ];
    $committed = [
        publicationSchemaGuardColumn('id', 1, 'bigint', 'int8'),
        publicationSchemaGuardColumn('alt_text_override', 7),
        publicationSchemaGuardColumn('created_at', 8, 'timestamp without time zone', 'timestamp'),
        publicationSchemaGuardColumn('updated_at', 9, 'timestamp without time zone', 'timestamp'),
    ];

    expect(publicationSchemaGuardSignature($public))
        ->toBe(publicationSchemaGuardSignature($committed))
        ->and(publicationSchemaGuardSignature(array_reverse($committed)))
        ->not->toBe(publicationSchemaGuardSignature($public));
});

it('still detects real schema differences', function (): void {
    $public = [
        publicationSchemaGuardColumn('id', 1, 'bigint', 'int8'),
        publicationSchemaGuardColumn('alt_text_override', 7),
    ];
    $committed = [
        publicationSchemaGuardColumn('id', 1, 'bigint', 'int8'),
        publicationSchemaGuardColumn('alt_text_override', 7, 'character varying', 'varchar'),
    ];

    expect(publicationSchemaGuardSignature($public))
        ->not->toBe(publicationSchemaGuardSignature($committed));
});
