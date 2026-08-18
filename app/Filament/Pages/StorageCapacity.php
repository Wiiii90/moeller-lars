<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\StorageCapacityOverview;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class StorageCapacity extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Library';

    protected static ?string $navigationLabel = 'Storage';

    protected static ?int $navigationSort = 13;

    protected string $view = 'filament.pages.storage-capacity';

    protected function getHeaderWidgets(): array
    {
        return [
            StorageCapacityOverview::class,
        ];
    }
}
