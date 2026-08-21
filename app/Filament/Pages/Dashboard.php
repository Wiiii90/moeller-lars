<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ArtistDashboard;
use App\Filament\Widgets\ContactHealth;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

final class Dashboard extends BaseDashboard
{
    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $title = 'Dashboard';

    protected static ?int $navigationSort = -2;

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getColumns(): array
    {
        return ['md' => 1];
    }

    public function getWidgets(): array
    {
        return [
            ArtistDashboard::class,
            ContactHealth::class,
        ];
    }
}
