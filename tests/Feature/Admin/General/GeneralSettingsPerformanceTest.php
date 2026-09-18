<?php

use App\Domain\Admin\AdminSettingsService;
use App\Filament\Pages\General;
use App\Models\PublicContentSetting;
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

it('refreshes the request cache after General settings are saved', function (): void {
    $before = PublicContentSetting::general();

    app(AdminSettingsService::class)->updatePublicContent($before, [
        'public_email' => 'updated@example.test',
    ]);

    $after = PublicContentSetting::general();

    expect($after)
        ->not->toBe($before)
        ->and($after->getAttribute('public_email'))->toBe('updated@example.test');
});
