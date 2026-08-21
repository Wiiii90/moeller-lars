<?php

use App\Domain\Admin\AdminAuditService;
use App\Domain\Content\SiteSectionOrderService;
use App\Models\SiteSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('reuses one sibling lookup across repeated movement checks in a request', function (): void {
    $sections = SiteSection::query()
        ->whereNull('parent_id')
        ->where('type', '<>', SiteSection::TYPE_HOME)
        ->orderBy('position')
        ->orderBy('id')
        ->take(3)
        ->get();

    expect($sections)->toHaveCount(3);

    $request = Request::create('/admin/pages', 'GET');
    $service = new SiteSectionOrderService(app(AdminAuditService::class), $request);

    DB::flushQueryLog();
    DB::enableQueryLog();

    foreach ($sections as $section) {
        $service->canMove($section, 'up');
        $service->canMove($section, 'down');
    }

    $siteSectionReads = collect(DB::getQueryLog())
        ->filter(static fn (array $query): bool => str_starts_with(strtolower(ltrim((string) $query['query'])), 'select')
            && str_contains(strtolower((string) $query['query']), 'site_sections'))
        ->count();

    DB::disableQueryLog();

    expect($siteSectionReads)->toBe(1);
});
