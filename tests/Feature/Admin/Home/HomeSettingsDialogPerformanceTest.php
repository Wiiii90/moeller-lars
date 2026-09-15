<?php

use App\Filament\Support\HomeSettingsDialog;
use App\Models\HomePresentationSetting;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

it('reuses the loaded Home settings while filling routing state', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $settingsTable = (new HomePresentationSetting)->getTable();
    $settingsSelects = [];

    DB::listen(function (QueryExecuted $query) use (&$settingsSelects, $settingsTable): void {
        $sql = strtolower(ltrim($query->sql));
        if (str_starts_with($sql, 'select') && str_contains($sql, $settingsTable)) {
            $settingsSelects[] = $query->sql;
        }
    });

    $state = app(HomeSettingsDialog::class)->fill();

    expect($state)->toHaveKeys([
        'template',
        'show_in_navigation',
        'skip_home',
        'skip_target_section_id',
    ])->and($settingsSelects)->toHaveCount(1);
});
