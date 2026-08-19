<?php

namespace App\Filament\Pages;

use App\Domain\Admin\AdminActionCatalog;
use App\Domain\Admin\AdminActivityFeed;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class Activity extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Insights';

    protected static ?string $navigationLabel = 'Activity';

    protected static ?int $navigationSort = 45;

    protected string $view = 'filament.pages.activity';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $area = request()->query('area');
        $family = request()->query('family');
        $area = is_string($area) && array_key_exists($area, AdminActionCatalog::areaOptions()) ? $area : null;
        $family = is_string($family) && array_key_exists($family, AdminActionCatalog::familyOptions()) ? $family : null;
        $feed = app(AdminActivityFeed::class)->page($area, $family);

        return [
            ...$feed,
            'area' => $area,
            'family' => $family,
            'areaOptions' => AdminActionCatalog::areaOptions(),
            'familyOptions' => AdminActionCatalog::familyOptions(),
        ];
    }
}
