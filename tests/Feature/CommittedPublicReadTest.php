<?php

use App\Domain\Admin\AdminSettingsService;
use App\Domain\Publication\CommittedRead;
use App\Domain\Publication\PublicationService;
use App\Domain\Publication\PublicationSnapshot;
use App\Filament\Pages\General;
use App\Models\PublicContentSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actor = User::factory()->admin()->create();
    $this->actingAs($this->actor, 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

function committedPublicReadSetAppearance(string $end = '#C9C3C3'): void
{
    $appearance = [
        'background_mode' => 'gradient',
        'background_gradient_start' => '#555555',
        'background_gradient_end' => $end,
        'background_gradient_angle' => 2,
    ];

    foreach (['public', 'committed'] as $schema) {
        DB::table($schema.'.public_content_settings')
            ->where('scope', PublicContentSetting::SCOPE_GENERAL)
            ->update($appearance);

        DB::table($schema.'.home_presentation_settings')->update([
            'template' => 'custom',
        ]);
    }
}

it('keeps the real public request committed while preview and Livewire stay on Working', function (): void {
    committedPublicReadSetAppearance();

    $committed = '#C9C3C3';
    $working = '#0F19FA';
    $committedCss = 'linear-gradient(2deg, #555555, #C9C3C3) fixed';
    $workingCss = 'linear-gradient(2deg, #555555, #0F19FA) fixed';

    expect(app(PublicationService::class)->hasPendingChanges())->toBeFalse();

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'background_gradient_end' => $working,
    ]);

    expect((string) DB::table('public.public_content_settings')
        ->where('scope', PublicContentSetting::SCOPE_GENERAL)
        ->value('background_gradient_end'))->toBe($working)
        ->and((string) DB::table('committed.public_content_settings')
            ->where('scope', PublicContentSetting::SCOPE_GENERAL)
            ->value('background_gradient_end'))->toBe($committed)
        ->and((string) PublicContentSetting::general()->getAttribute('background_gradient_end'))->toBe($working)
        ->and(app(PublicationService::class)->hasPendingChanges())->toBeTrue();

    $queries = [];
    $probe = [
        'connection' => null,
        'search_path' => null,
        'current_schema' => null,
    ];
    $probing = false;

    DB::listen(function (QueryExecuted $query) use (&$queries, &$probe, &$probing): void {
        if ($probing) {
            return;
        }

        $queries[] = $query->sql;

        if ($probe['search_path'] !== null
            || ! str_contains($query->sql, '"committed"."public_content_settings"')) {
            return;
        }

        $probing = true;

        try {
            $searchPath = $query->connection->selectOne('SHOW search_path');
            $schema = $query->connection->selectOne('SELECT current_schema() AS schema');

            $probe['connection'] = $query->connectionName;
            $probe['search_path'] = (string) ($searchPath->search_path ?? '');
            $probe['current_schema'] = (string) ($schema->schema ?? '');
        } finally {
            $probing = false;
        }
    });

    $public = $this->get('/')->assertOk();
    $publicQueries = $queries;
    $queries = [];

    $public
        ->assertSee('--public-page: '.$committedCss.';', false)
        ->assertDontSee($working, false);

    expect($probe['connection'])->toBe('pgsql')
        ->and($probe['search_path'])->toContain('public')
        ->and($probe['search_path'])->not->toContain('committed')
        ->and($probe['current_schema'])->toBe('public')
        ->and(collect($publicQueries)->contains(
            fn (string $sql): bool => str_contains($sql, '"committed"."public_content_settings"'),
        ))->toBeTrue()
        ->and(collect($publicQueries)->contains(
            fn (string $sql): bool => str_contains($sql, '"committed"."home_presentation_settings"'),
        ))->toBeTrue()
        ->and(collect($publicQueries)->contains(
            fn (string $sql): bool => str_contains($sql, '"committed"."site_sections"'),
        ))->toBeTrue();

    $preview = $this->get('/preview')->assertOk();
    $previewQueries = $queries;
    $queries = [];

    $preview
        ->assertSee('--public-page: '.$workingCss.';', false)
        ->assertDontSee($committed, false)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

    expect(collect($previewQueries)->contains(
        fn (string $sql): bool => str_contains($sql, '"committed"."public_content_settings"'),
    ))->toBeFalse()
        ->and(collect($previewQueries)->contains(
            fn (string $sql): bool => str_contains($sql, 'from "public_content_settings"'),
        ))->toBeTrue();

    $nextWorking = '#123ABC';
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'background_gradient_end' => $nextWorking,
    ]);

    Livewire::test(General::class)
        ->assertSet('data.background_secondary_color', $nextWorking);

    $this->get('/')
        ->assertOk()
        ->assertSee($committed, false)
        ->assertDontSee($nextWorking, false);

    $checkpoint = app(PublicationService::class)->commit($this->actor);

    expect($checkpoint)->not->toBeNull()
        ->and(app(PublicationService::class)->hasPendingChanges())->toBeFalse()
        ->and((string) DB::table('committed.public_content_settings')
            ->where('scope', PublicContentSetting::SCOPE_GENERAL)
            ->value('background_gradient_end'))->toBe($nextWorking);

    $this->get('/')
        ->assertOk()
        ->assertSee($nextWorking, false)
        ->assertDontSee($committed, false);
});

it('schema-qualifies every PublicationSnapshot table inside CommittedRead without changing the connection search path', function (): void {
    committedPublicReadSetAppearance();

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'background_gradient_end' => '#0F19FA',
    ]);

    $workingSql = collect(PublicationSnapshot::TABLES)
        ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->toSql()])
        ->all();

    $committedRead = app(CommittedRead::class)->run(function (): array {
        $searchPath = DB::selectOne('SHOW search_path');
        $schema = DB::selectOne('SELECT current_schema() AS schema');

        return [
            'connection' => DB::connection()->getName(),
            'search_path' => (string) ($searchPath->search_path ?? ''),
            'current_schema' => (string) ($schema->schema ?? ''),
            'appearance' => (string) PublicContentSetting::general()->getAttribute('background_gradient_end'),
            'sql' => collect(PublicationSnapshot::TABLES)
                ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->toSql()])
                ->all(),
        ];
    });

    expect($committedRead['connection'])->toBe('pgsql')
        ->and($committedRead['search_path'])->toContain('public')
        ->and($committedRead['search_path'])->not->toContain('committed')
        ->and($committedRead['current_schema'])->toBe('public')
        ->and($committedRead['appearance'])->toBe('#C9C3C3')
        ->and((string) PublicContentSetting::general()->getAttribute('background_gradient_end'))->toBe('#0F19FA');

    foreach (PublicationSnapshot::TABLES as $table) {
        expect($committedRead['sql'][$table])
            ->toContain('"committed"."'.$table.'"')
            ->and($workingSql[$table])
            ->not->toContain('"committed"."'.$table.'"');
    }

    expect($committedRead['sql']['custom_page_settings'])
        ->toContain('"committed"."custom_page_settings"');
});
