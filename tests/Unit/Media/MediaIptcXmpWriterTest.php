<?php

use App\Domain\Media\MediaIptcXmpWriter;
use App\Domain\Media\MediaRightsProjection;
use App\Models\MediaAsset;

function metadataTestAsset(array $values): MediaAsset
{
    $asset = new MediaAsset;
    $asset->setRawAttributes($values, true);

    return $asset;
}

it('maps verified photo data into standard IPTC/XMP tags without inventing optional fields', function (): void {
    $asset = metadataTestAsset([
        'file_creator' => 'Test Photographer',
        'file_rights_holder' => 'Photo Agency',
        'credit' => 'Agency / Photographer',
        'rights_usage_terms' => 'Reproduction by permission',
        'license_inquiry_url' => 'https://example.org/licensing',
        'copyright_notice_mode' => MediaAsset::COPYRIGHT_OVERRIDE,
        'copyright_notice' => '© Photo Agency',
    ]);

    $writer = new MediaIptcXmpWriter(new MediaRightsProjection);
    expect($writer->tagsForAsset($asset))->toBe([
        'XMP-dc:Creator' => 'Test Photographer',
        'XMP-plus:CopyrightOwnerName' => 'Photo Agency',
        'XMP-photoshop:Credit' => 'Agency / Photographer',
        'XMP-dc:Rights' => '© Photo Agency',
        'XMP-xmpRights:UsageTerms' => 'Reproduction by permission',
        'XMP-plus:LicensorURL' => 'https://example.org/licensing',
    ]);
});

it('omits default artwork copyright from externally attributed photos and does not reserve data mining without authorization', function (): void {
    $asset = metadataTestAsset([
        'file_rights_holder' => 'Photo Agency',
        'copyright_notice_mode' => MediaAsset::COPYRIGHT_NONE,
    ]);
    $writer = new MediaIptcXmpWriter(new MediaRightsProjection);
    expect($writer->tagsForAsset($asset))->toBe([
        'XMP-plus:CopyrightOwnerName' => 'Photo Agency',
    ]);
    expect($writer->tagsForAsset($asset, true))->toBe([
        'XMP-plus:CopyrightOwnerName' => 'Photo Agency',
        'XMP-plus:DataMining' => 'Prohibited except for search engine indexing',
    ]);
});

it('rejects ExifTool releases without the IPTC PLUS data mining vocabulary', function (): void {
    expect(fn () => MediaIptcXmpWriter::assertExifToolVersion('12.57'))
        ->toThrow(RuntimeException::class);
    expect(fn () => MediaIptcXmpWriter::assertExifToolVersion('unknown'))
        ->toThrow(RuntimeException::class);
    MediaIptcXmpWriter::assertExifToolVersion('12.67');
    MediaIptcXmpWriter::assertExifToolVersion('13.25');
    expect(true)->toBeTrue();
});
