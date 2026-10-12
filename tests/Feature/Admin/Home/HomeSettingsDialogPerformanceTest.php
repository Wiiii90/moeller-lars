<?php

use App\Domain\Content\HomePresentationResolver;
use App\Filament\Support\HomeRoutingDialog;
use App\Models\HomePresentationSetting;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

it('reuses supplied Home settings while filling routing state', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $settings = app(HomePresentationResolver::class)->settings();
    $settingsTable = (new HomePresentationSetting)->getTable();
    $settingsSelects = [];

    DB::listen(function (QueryExecuted $query) use (&$settingsSelects, $settingsTable): void {
        $sql = strtolower(ltrim($query->sql));

        if (str_starts_with($sql, 'select') && str_contains($sql, $settingsTable)) {
            $settingsSelects[] = $query->sql;
        }
    });

    $state = app(HomeRoutingDialog::class)->fill($settings);

    expect($state)->toHaveKeys([
        'skip_home',
        'skip_target_section_id',
    ])->and($settingsSelects)->toBe([]);
});

it('edits Hero navigation and viewer controls through the existing Home settings form', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $dialog = app(\App\Filament\Support\HomeSettingsDialog::class);
    $data = $dialog->fill();

    expect($data)->toHaveKeys([
        'show_details',
        'show_gallery_link',
        'show_navigation',
        'enable_viewer',
    ]);

    $data['template'] = 'artwork';
    $data['show_navigation'] = true;
    $data['enable_viewer'] = false;
    expect($dialog->save($data))->toBeTrue();

    $saved = $dialog->fill();
    expect($saved['show_navigation'])->toBeTrue()
        ->and($saved['enable_viewer'])->toBeFalse();
});
