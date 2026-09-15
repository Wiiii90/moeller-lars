<?php

use App\Domain\Content\SiteSectionEditorialService;
use App\Filament\Pages\SitePages;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

it('reuses one Site page dialog section lookup within the component instance', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $editorial = app(SiteSectionEditorialService::class);
    $firstSection = $editorial->createCustomPage('First dialog page', 'first-dialog-page');
    $secondSection = $editorial->createCustomPage('Second dialog page', 'second-dialog-page');

    $sectionSelects = [];
    DB::listen(function (QueryExecuted $query) use (&$sectionSelects): void {
        $sql = strtolower(ltrim($query->sql));
        if (str_starts_with($sql, 'select') && str_contains($sql, 'site_sections')) {
            $sectionSelects[] = $query->sql;
        }
    });

    $page = new SitePages;
    $dialogSection = new ReflectionMethod($page, 'dialogSection');
    $firstArguments = ['section' => (int) $firstSection->getKey()];
    $secondArguments = ['section' => (int) $secondSection->getKey()];

    $first = $dialogSection->invoke($page, $firstArguments);
    $repeat = $dialogSection->invoke($page, $firstArguments);
    $second = $dialogSection->invoke($page, $secondArguments);

    expect($first)->toBe($repeat)
        ->and($first->is($firstSection))->toBeTrue()
        ->and($second->is($secondSection))->toBeTrue()
        ->and($sectionSelects)->toHaveCount(2);
});
