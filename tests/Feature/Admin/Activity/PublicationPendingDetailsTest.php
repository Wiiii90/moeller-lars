<?php

use App\Domain\Publication\PublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('reports concrete changed records and fields for staged publication review', function (): void {
    $id = (int) DB::table('public_content_settings')->where('scope', 'general')->value('id');

    DB::table('public_content_settings')->where('id', $id)->update([
        'legal_disclaimer' => 'Staged publication review value',
    ]);

    $details = app(PublicationService::class)->pendingDetails();
    $row = collect($details['rows'])->firstWhere('table', 'public_content_settings');

    expect($row)->not->toBeNull()
        ->and($row['row_key'])->toBe((string) $id)
        ->and($row['change'])->toBe('changed')
        ->and($row['fields'])->toContain('legal_disclaimer')
        ->and($details['truncated'])->toBeFalse();
});
