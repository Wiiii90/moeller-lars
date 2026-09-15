<?php

use App\Domain\Content\SiteSectionType;
use App\Filament\Pages\SitePages;
use App\Models\SiteSection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

it('reuses one Site page dialog section lookup within the component instance', function (): void {
    $firstSection = SiteSection::query()->create([
        'type' => SiteSectionType::CustomPage->value,
        'template' => null,
        'title' => 'First dialog page',
        'navigation_label' => 'First dialog page',
        'slug' => 'first-dialog-page',
        'state' => 'hidden',
        'position' => 100,
        'show_in_navigation' => false,
        'parent_id' => null,
        'artwork_category_id' => null,
    ]);
    $secondSection = SiteSection::query()->create([
        'type' => SiteSectionType::CustomPage->value,
        'template' => null,
        'title' => 'Second dialog page',
        'navigation_label' => 'Second dialog page',
        'slug' => 'second-dialog-page',
        'state' => 'hidden',
        'position' => 200,
        'show_in_navigation' => false,
        'parent_id' => null,
        'artwork_category_id' => null,
    ]);

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
