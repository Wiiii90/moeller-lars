<?php

use App\Filament\Pages\General;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('loads the General settings record once during the initial workspace render', function (): void {
    $settingsSelects = [];
    DB::listen(function (QueryExecuted $query) use (&$settingsSelects): void {
        $sql = strtolower(ltrim($query->sql));
        if (str_starts_with($sql, 'select') && str_contains($sql, 'public_content_settings')) {
            $settingsSelects[] = $query->sql;
        }
    });

    Livewire::test(General::class)
        ->assertOk();

    expect($settingsSelects)->toHaveCount(1);
});
