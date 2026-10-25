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
    static $sequence = 0;
    $sequence++;

    return MediaAsset::query()->create([
        'storage_key' => 'originals/copyright-fixture-'.$sequence.'.jpg',
        'original_filename' => 'copyright.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'copyright-fixture-'.$sequence),
        'state' => 'available',
        'alt_text' => 'Copyright test',
        'copyright_notice_mode' => $mode,
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
        ->and($asset->effectiveCopyrightNotice())->toBe('© General');
});

it('preserves an explicit asset override when the General default changes', function (): void {
    PublicContentSetting::general()->update(['default_media_copyright_notice' => '© General']);
    $asset = copyrightAsset('© Asset override', MediaAsset::COPYRIGHT_OVERRIDE);

    PublicContentSetting::general()->update(['default_media_copyright_notice' => '© Changed']);

    expect($asset->fresh()->copyright_notice)->toBe('© Asset override')
        ->and($asset->fresh()->copyright_notice_mode)->toBe(MediaAsset::COPYRIGHT_OVERRIDE)
        ->and($asset->fresh()->effectiveCopyrightNotice())->toBe('© Asset override');
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
        ->and($asset->effectiveCopyrightNotice())->toBeNull();
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


it('projects optional photographer and license data without claiming rights from the artwork', function (): void {
    PublicContentSetting::general()->update(['default_media_copyright_notice' => '© General']);
    $asset = copyrightAsset();

    app(MediaAssetEditorialService::class)->updateMetadata($asset, [
        'file_creator' => ' Photographer ',
        'file_rights_holder' => 'Photo Agency',
        'rights_usage_terms' => 'Permission is required',
        'license_inquiry_url' => 'https://example.org/rights',
    ]);

    expect(app(\App\Domain\Media\MediaRightsProjection::class)->forAsset($asset->fresh()))
        ->toMatchArray([
            'creator' => 'Photographer',
            'rights_holder' => 'Photo Agency',
            'credit' => null,
            'copyright_notice' => '© General',
            'usage_terms' => 'Permission is required',
            'license_inquiry_url' => 'https://example.org/rights',
        ]);

    app(MediaAssetEditorialService::class)->updateMetadata($asset, [
        'copyright_notice_mode' => MediaAsset::COPYRIGHT_NONE,
    ]);
    expect(app(\App\Domain\Media\MediaRightsProjection::class)->forAsset($asset->fresh())['copyright_notice'])->toBeNull()
        ->and($asset->fresh()->file_rights_holder)->toBe('Photo Agency');
});

it('rejects non-HTTPS media license inquiry URLs', function (): void {
    $asset = copyrightAsset();
    expect(fn () => app(MediaAssetEditorialService::class)->updateMetadata($asset, [
        'license_inquiry_url' => 'http://example.org/rights',
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect($asset->fresh()->license_inquiry_url)->toBeNull();
});
