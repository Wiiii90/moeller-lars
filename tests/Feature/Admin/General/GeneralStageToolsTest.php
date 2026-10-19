<?php

use App\Filament\Pages\General;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('renders both stage tool rows without preview preset text', function (): void {
    Livewire::test(General::class)
        ->assertSeeHtml('admin-stage-tabs--with-tools')
        ->assertSeeHtml('admin-control-separator admin-stage-tabs__separator')
        ->assertSeeHtml('aria-label="Live preview controls"')
        ->assertSeeHtml('aria-label="Preview device"')
        ->assertSeeHtml('aria-label="Preview zoom"')
        ->assertSeeHtml('aria-label="Preview actions"')
        ->assertSeeHtml('general-live-preview__zoom-value')
        ->assertDontSee('Desktop · 1280 × 720')
        ->assertDontSee('iPhone · 390 × 844');
});
