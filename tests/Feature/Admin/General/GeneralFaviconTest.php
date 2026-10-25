<?php

use App\Filament\Support\MediaAssetSelect;
use App\Models\MediaAsset;
use App\Models\User;
use App\Filament\Pages\General;
use App\Models\PublicContentSetting;
use App\Models\AdminNotification;
use App\Domain\Admin\AdminSettingsService;
use App\Domain\Admin\AdminActivityNotificationContext;
use Livewire\Livewire;
use Filament\Facades\Filament;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('persists and removes the site icon once per real change', function (): void {
    $image = generalStatusFavicon();
    $before = generalSettingsAuditCount();
    $component = Livewire::test(General::class)->set('data.favicon_media_asset_id', $image->id)
        ->assertDispatched('general-appearance-updated')->assertDispatched('admin-header-notification');
    expect((int) PublicContentSetting::general()->favicon_media_asset_id)->toBe($image->id)
        ->and(generalSettingsAuditCount())->toBe($before + 1)
        ->and(AdminNotification::query()->count())->toBe(1);
    $component->call('removeFavicon')->assertDispatched('general-appearance-updated');
    expect(PublicContentSetting::general()->favicon_media_asset_id)->toBeNull()
        ->and($image->fresh())->not->toBeNull()->and(generalSettingsAuditCount())->toBe($before + 2)
        ->and(AdminNotification::query()->count())->toBe(2);
    $component->call('removeFavicon')->assertNotDispatched('admin-header-notification');
    expect(AdminNotification::query()->count())->toBe(2);
});

it('preserves the selected icon when a replacement is unavailable or not an image', function (string $kind): void {
    $image = generalStatusFavicon();
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), ['favicon_media_asset_id' => $image->id]);
    app(AdminActivityNotificationContext::class)->discard();
    $id = $kind === 'missing' ? 999999 : MediaAsset::query()->create([
        'storage_key' => 'originals/rejected-icon', 'original_filename' => 'rejected-icon',
        'mime_type' => $kind === 'video' ? 'video/mp4' : 'image/png', 'byte_size' => 4,
        'sha256' => hash('sha256', 'rejected-icon'), 'state' => $kind === 'unavailable' ? 'quarantined' : 'available',
    ])->id;
    $before = generalSettingsAuditCount();
    Livewire::test(General::class)->set('data.favicon_media_asset_id', $id)->assertHasErrors(['data.favicon_media_asset_id']);
    expect((int) PublicContentSetting::query()->findOrFail(1)->favicon_media_asset_id)->toBe($image->id)
        ->and(generalSettingsAuditCount())->toBe($before)
        ->and(AdminNotification::query()->where('status', 'success')->count())->toBe(0);
})->with(['missing', 'video', 'unavailable']);

require_once __DIR__.'/GeneralFixtures.php';

it('searches favicon choices by filename and keeps the picker image-only and available-only', function (): void {
    $image = MediaAsset::query()->create([
        'storage_key' => 'originals/picker-match.png',
        'original_filename' => 'picker-match.png',
        'mime_type' => 'image/png',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'picker-match.png'),
        'state' => 'available',
        'alt_text' => 'Picker image',
    ]);
    MediaAsset::query()->create([
        'storage_key' => 'originals/picker-match.mp4',
        'original_filename' => 'picker-match.mp4',
        'mime_type' => 'video/mp4',
        'byte_size' => 5,
        'sha256' => hash('sha256', 'picker-match.mp4'),
        'state' => 'available',
    ]);

    MediaAsset::query()->create([
        'storage_key' => 'originals/picker-match-unavailable.png',
        'original_filename' => 'picker-match-unavailable.png',
        'mime_type' => 'image/png',
        'byte_size' => 6,
        'sha256' => hash('sha256', 'picker-match-unavailable.png'),
        'state' => 'quarantined',
    ]);

    $results = MediaAssetSelect::searchOptions('picker-match', imagesOnly: true);

    expect(array_keys($results))->toBe([(int) $image->id]);
});
