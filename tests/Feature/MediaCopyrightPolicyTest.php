<?php

use App\Domain\Admin\AdminSettingsService;
use App\Domain\Media\MediaAssetEditorialService;
use App\Models\MediaAsset;
use App\Models\PublicContentSetting;
use App\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

function copyrightAsset(?string $notice = null, string $mode = MediaAsset::COPYRIGHT_INHERIT): MediaAsset
{
    return MediaAsset::query()->create([
        'storage_key' => 'originals/'.uniqid('', true).'.jpg',
        'original_filename' => 'copyright.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', uniqid('', true)),
        'state' => 'available',
        'alt_text' => 'Copyright test',
        'copyright_notice_mode' => $mode,
        'copyright_notice' => $notice,
    ]);
}

function legacyCopyrightAsset(string $notice): MediaAsset
{
    return MediaAsset::query()->create([
        'storage_key' => 'originals/'.uniqid('', true).'.jpg',
        'original_filename' => 'legacy-copyright.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', uniqid('', true)),
        'state' => 'available',
        'alt_text' => 'Legacy copyright test',
        'copyright_notice' => $notice,
    ]);
}

it('saves and reloads the General default media copyright through the canonical settings service', function (): void {
    $setting = PublicContentSetting::general();

    app(AdminSettingsService::class)->updatePublicContent($setting, [
        'default_media_copyright_notice' => '© Test Artist',
    ]);

    expect(PublicContentSetting::general()->default_media_copyright_notice)->toBe('© Test Artist');
});

it('inherits the General default without copying it onto the asset', function (): void {
    PublicContentSetting::general()->update(['default_media_copyright_notice' => '© General']);
    $asset = copyrightAsset();

    expect($asset->copyright_notice)->toBeNull()
        ->and($asset->copyright_notice_mode)->toBe(MediaAsset::COPYRIGHT_INHERIT)
        ->and($asset->effectiveCopyrightNotice())->toBe('© General')
        ->and($asset->copyrightNoticeSourceLabel())->toBe('Inherited from General');
});

it('supports an explicit asset override and preserves it when the General default changes', function (): void {
    PublicContentSetting::general()->update(['default_media_copyright_notice' => '© General']);
    $asset = legacyCopyrightAsset('© Legacy explicit');

    PublicContentSetting::general()->update(['default_media_copyright_notice' => '© Changed']);

    expect($asset->fresh()->copyright_notice)->toBe('© Legacy explicit')
        ->and($asset->fresh()->copyright_notice_mode)->toBe(MediaAsset::COPYRIGHT_OVERRIDE)
        ->and($asset->fresh()->effectiveCopyrightNotice())->toBe('© Legacy explicit');
});

it('supports explicit no-notice independently of the General default', function (): void {
    PublicContentSetting::general()->update(['default_media_copyright_notice' => '© General']);
    $asset = copyrightAsset();

    app(MediaAssetEditorialService::class)->updateMetadata($asset, [
        'copyright_notice_mode' => MediaAsset::COPYRIGHT_NONE,
        'copyright_notice' => 'ignored',
    ]);

    $asset->refresh();
    expect($asset->copyright_notice)->toBeNull()
        ->and($asset->copyright_notice_mode)->toBe(MediaAsset::COPYRIGHT_NONE)
        ->and($asset->effectiveCopyrightNotice())->toBeNull()
        ->and($asset->copyrightNoticeSourceLabel())->toBe('No notice');
});

it('saves an explicit override through the canonical media editorial service', function (): void {
    $asset = copyrightAsset();

    app(MediaAssetEditorialService::class)->updateMetadata($asset, [
        'copyright_notice_mode' => MediaAsset::COPYRIGHT_OVERRIDE,
        'copyright_notice' => '© Asset override',
    ]);

    $asset->refresh();
    expect($asset->copyright_notice_mode)->toBe(MediaAsset::COPYRIGHT_OVERRIDE)
        ->and($asset->copyright_notice)->toBe('© Asset override')
        ->and($asset->effectiveCopyrightNotice())->toBe('© Asset override');
});
