<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ArtistDashboard;
use Filament\Pages\Dashboard as BaseDashboard;

final class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Dashboard';

    protected static ?int $navigationSort = -2;

    public function getColumns(): array
    {
        return ['md' => 1];
    }

    public function getWidgets(): array
    {
        return [ArtistDashboard::class];
    }
}
